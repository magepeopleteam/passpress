<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for the pp_billing_history table. `mark_paid()` is written as a
 * single atomic UPDATE ... WHERE status != 'paid' so a Stripe/PayPal webhook
 * arriving at the same moment as the browser's return-URL request can't
 * double-issue a membership for the same payment (idempotency by row state,
 * not by application-level check-then-act).
 */
class PP_Billing_History {

	const STATUS_PENDING   = 'pending';
	const STATUS_PAID      = 'paid';
	const STATUS_FAILED    = 'failed';
	const STATUS_REFUNDED  = 'refunded';
	const STATUS_CANCELLED = 'cancelled';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_billing_history';
	}

	/**
	 * @return int Insert ID.
	 */
	public static function create( $user_id, $plan_id, $type, $gateway, $amount, $currency, $membership_id = 0, $coupon_code = '', $discount_amount = 0 ) {
		global $wpdb;

		$checkout_token = pp_generate_checkout_token();

		$wpdb->insert(
			self::table(),
			array(
				'membership_id'   => absint( $membership_id ),
				'user_id'         => absint( $user_id ),
				'plan_id'         => absint( $plan_id ),
				'type'            => 'renewal' === $type ? 'renewal' : 'initial',
				'gateway'         => sanitize_key( $gateway ),
				'gateway_ref'     => '',
				'amount'          => (float) $amount,
				'currency'        => sanitize_key( $currency ),
				'status'          => self::STATUS_PENDING,
				'checkout_token'  => $checkout_token,
				'coupon_code'     => sanitize_text_field( $coupon_code ),
				'discount_amount' => (float) $discount_amount,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%f', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ) );
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		if ( ! $token ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE checkout_token = %s', $token ) );
	}

	public static function set_gateway_ref( $id, $gateway_ref ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'gateway_ref' => sanitize_text_field( $gateway_ref ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Atomically flips a row from any non-paid status to paid. Returns false
	 * if it was already paid (i.e. this call was a redundant confirmation —
	 * the return-URL and the webhook racing each other, or a doubled webhook).
	 */
	public static function mark_paid( $id, $membership_id, $gateway_ref = '', $raw_response = '' ) {
		global $wpdb;

		$table = self::table();
		$sql   = $wpdb->prepare(
			"UPDATE {$table} SET status = %s, membership_id = %d, gateway_ref = %s, raw_response = %s, updated_at = %s WHERE id = %d AND status != %s",
			self::STATUS_PAID,
			absint( $membership_id ),
			sanitize_text_field( $gateway_ref ),
			wp_kses_post( $raw_response ),
			current_time( 'mysql' ),
			absint( $id ),
			self::STATUS_PAID
		);

		$wpdb->query( $sql );

		return $wpdb->rows_affected > 0;
	}

	public static function set_membership_id( $id, $membership_id ) {
		global $wpdb;
		$wpdb->update(
			self::table(),
			array(
				'membership_id' => absint( $membership_id ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	public static function mark_failed( $id, $reason = '' ) {
		global $wpdb;

		$table = self::table();
		$sql   = $wpdb->prepare(
			"UPDATE {$table} SET status = %s, raw_response = %s, updated_at = %s WHERE id = %d AND status = %s",
			self::STATUS_FAILED,
			sanitize_text_field( $reason ),
			current_time( 'mysql' ),
			absint( $id ),
			self::STATUS_PENDING
		);

		$wpdb->query( $sql );

		return $wpdb->rows_affected > 0;
	}

	public static function get_recent( $limit = 50 ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) );
	}

	public static function get_for_membership( $membership_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE membership_id = %d ORDER BY id DESC", absint( $membership_id ) ) );
	}
}
