<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates a coupon code against a plan/user and computes the discount.
 * Usage counting queries pp_billing_history directly (coupon_code +
 * status='paid') rather than a separate redemptions table — the billing
 * ledger already is the record of what was actually charged.
 */
class PP_Coupon {

	public static function get_by_code( $code ) {
		global $wpdb;
		$code = strtoupper( trim( $code ) );
		if ( ! $code ) {
			return null;
		}

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'pp_coupon' AND post_status = 'publish' AND UPPER(post_title) = %s LIMIT 1",
				$code
			)
		);

		return $post_id ? get_post( $post_id ) : null;
	}

	private static function usage_count( $coupon_code, $user_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pp_billing_history';

		if ( $user_id ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE coupon_code = %s AND user_id = %d AND status = 'paid'",
					$coupon_code,
					$user_id
				)
			);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE coupon_code = %s AND status = 'paid'", $coupon_code )
		);
	}

	/**
	 * @return array{valid: bool, error?: string, coupon_id?: int, discount_amount?: float, final_amount?: float}
	 */
	public static function validate( $code, $plan_id, $user_id, $amount ) {
		$coupon = self::get_by_code( $code );

		if ( ! $coupon ) {
			return array(
				'valid' => false,
				'error' => __( 'Invalid coupon code.', 'passpress' ),
			);
		}

		if ( ! get_post_meta( $coupon->ID, '_pp_active', true ) ) {
			return array(
				'valid' => false,
				'error' => __( 'This coupon is no longer active.', 'passpress' ),
			);
		}

		$expiry_date = get_post_meta( $coupon->ID, '_pp_expiry_date', true );
		if ( $expiry_date && strtotime( $expiry_date ) < strtotime( current_time( 'Y-m-d' ) ) ) {
			return array(
				'valid' => false,
				'error' => __( 'This coupon has expired.', 'passpress' ),
			);
		}

		$applicable_plans = get_post_meta( $coupon->ID, '_pp_applicable_plans', true );
		$applicable_plans = is_array( $applicable_plans ) ? array_map( 'intval', $applicable_plans ) : array();
		if ( $applicable_plans && ! in_array( (int) $plan_id, $applicable_plans, true ) ) {
			return array(
				'valid' => false,
				'error' => __( "This coupon isn't valid for the selected plan.", 'passpress' ),
			);
		}

		$usage_limit_total = (int) get_post_meta( $coupon->ID, '_pp_usage_limit_total', true );
		if ( $usage_limit_total > 0 && self::usage_count( $coupon->post_title ) >= $usage_limit_total ) {
			return array(
				'valid' => false,
				'error' => __( 'This coupon has reached its usage limit.', 'passpress' ),
			);
		}

		$usage_limit_per_user = get_post_meta( $coupon->ID, '_pp_usage_limit_per_user', true );
		$usage_limit_per_user = '' === $usage_limit_per_user ? 1 : (int) $usage_limit_per_user;
		if ( $usage_limit_per_user > 0 && self::usage_count( $coupon->post_title, $user_id ) >= $usage_limit_per_user ) {
			return array(
				'valid' => false,
				'error' => __( "You've already used this coupon.", 'passpress' ),
			);
		}

		$discount_type   = get_post_meta( $coupon->ID, '_pp_discount_type', true ) ?: 'percent';
		$discount_amount = (float) get_post_meta( $coupon->ID, '_pp_discount_amount', true );
		$amount          = (float) $amount;

		$discount = 'fixed' === $discount_type ? $discount_amount : ( $amount * ( $discount_amount / 100 ) );
		$discount = max( 0, min( $discount, $amount ) );

		return array(
			'valid'           => true,
			'coupon_id'       => $coupon->ID,
			'code'            => $coupon->post_title,
			'discount_amount' => round( $discount, 2 ),
			'final_amount'    => round( $amount - $discount, 2 ),
		);
	}
}
