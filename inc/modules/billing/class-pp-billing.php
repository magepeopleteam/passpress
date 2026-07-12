<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscription Billing orchestrator. Owns the zero-config checkout page
 * (`/?passpress_checkout=1&plan_id=X`, no WP page needs to be created — same
 * pattern as wpbookingly's native checkout) and the shared, idempotent
 * "payment confirmed" path that every gateway funnels into.
 *
 * Deliberately NOT true zero-touch recurring billing: there is no stored
 * card / subscription object and no automatic re-charge. "Renew" means the
 * member (or a reminder email) is sent back through this same one-time-charge
 * checkout for the same plan, which calls PP_Membership_Renewal::renew()
 * instead of PP_Membership::issue(). See CLAUDE.md's Phase 2 notes for why.
 */
class PP_Billing {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_checkout' ) );
	}

	public static function default_settings() {
		return array(
			'payment_method_type'   => 'native', // native|wc_subscriptions
			'offline_enabled'       => 1,
			'offline_auto_confirm'  => 1,
			'stripe_enabled'        => 0,
			'stripe_mode'           => 'test',
			'stripe_publishable_key' => '',
			'stripe_secret_key'     => '',
			'stripe_webhook_secret' => '',
			'paypal_enabled'        => 0,
			'paypal_mode'           => 'sandbox',
			'paypal_client_id'      => '',
			'paypal_client_secret'  => '',
			'renewal_reminder_days' => 7,
		);
	}

	public static function get_settings() {
		return wp_parse_args( get_option( 'passpress_billing_settings', array() ), self::default_settings() );
	}

	/**
	 * @return PP_Gateway_Interface[] id => instance, regardless of enabled state.
	 */
	public static function get_gateway_instances() {
		static $gateways = null;
		if ( null !== $gateways ) {
			return $gateways;
		}

		$gateways = array(
			'offline' => new PP_Gateway_Offline(),
			'stripe'  => new PP_Gateway_Stripe(),
			'paypal'  => new PP_Gateway_Paypal(),
		);

		return $gateways;
	}

	/**
	 * @return PP_Gateway_Interface[] id => instance, enabled in settings AND configured.
	 */
	public static function get_enabled_gateways() {
		$settings = self::get_settings();
		$enabled  = array();

		foreach ( self::get_gateway_instances() as $id => $gateway ) {
			if ( ! empty( $settings[ $id . '_enabled' ] ) && $gateway->is_configured() ) {
				$enabled[ $id ] = $gateway;
			}
		}

		return $enabled;
	}

	public static function is_billing_available() {
		return (bool) self::get_enabled_gateways();
	}

	public static function checkout_url( $plan_id, $renew_membership_id = 0 ) {
		$args = array(
			'passpress_checkout' => 1,
			'plan_id'            => absint( $plan_id ),
		);
		if ( $renew_membership_id ) {
			$args['renew'] = absint( $renew_membership_id );
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	public static function maybe_render_checkout() {
		if ( ! isset( $_GET['passpress_checkout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route detection
			return;
		}
		self::render_checkout();
		exit;
	}

	private static function render_checkout() {
		status_header( 200 );
		global $wp_query;
		if ( $wp_query ) {
			$wp_query->is_404 = false;
		}

		wp_enqueue_style( 'passpress-frontend' );

		$plan_id  = isset( $_GET['plan_id'] ) ? absint( $_GET['plan_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$renew_id = isset( $_GET['renew'] ) ? absint( $_GET['renew'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plan     = $plan_id ? get_post( $plan_id ) : null;

		$state      = 'form';
		$message    = '';
		$membership = null;

		if ( ! is_user_logged_in() ) {
			$state = 'login_required';
		} elseif ( ! $plan || 'pp_membership_plan' !== get_post_type( $plan ) || 'publish' !== $plan->post_status ) {
			$state   = 'error';
			$message = __( 'This membership plan is not available.', 'passpress' );
		} else {
			if ( $renew_id ) {
				$candidate = PP_Membership::get( $renew_id );
				if ( $candidate && (int) $candidate->user_id === get_current_user_id() && (int) $candidate->plan_id === $plan_id ) {
					$membership = $candidate;
				} else {
					$renew_id = 0;
				}
			}

			if ( isset( $_GET['passpress_return'], $_GET['gateway'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- gateway return, verified via gateway-specific signature/lookup instead
				$result  = self::handle_gateway_return( sanitize_key( wp_unslash( $_GET['gateway'] ) ) );
				$state   = $result['state'];
				$message = $result['message'];
			} elseif ( isset( $_GET['passpress_cancelled'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$state   = 'cancelled';
				$message = __( 'Payment was cancelled. You can try again below.', 'passpress' );
			} elseif ( isset( $_POST['passpress_pay'] ) && check_admin_referer( 'passpress_checkout_' . $plan_id ) ) {
				$result  = self::process_checkout_submit( $plan, $membership );
				$state   = $result['state'];
				$message = $result['message'];
			}
		}

		$gateways    = self::get_enabled_gateways();
		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sticky display value only, re-validated on actual submit

		include PASSPRESS_PLUGIN_DIR . '/templates/checkout/checkout.php';
	}

	private static function process_checkout_submit( $plan, $membership ) {
		$gateway_id  = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$gateways    = self::get_enabled_gateways();

		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			return array(
				'state'   => 'error',
				'message' => __( 'Please choose a valid payment method.', 'passpress' ),
			);
		}

		$user     = wp_get_current_user();
		$settings = pp_get_settings();
		$price    = (float) get_post_meta( $plan->ID, '_pp_price', true );
		$type     = $membership ? 'renewal' : 'initial';

		$amount          = $price;
		$discount_amount = 0;
		$applied_code    = '';

		if ( $coupon_code ) {
			$coupon_result = PP_Coupon::validate( $coupon_code, $plan->ID, $user->ID, $price );
			if ( empty( $coupon_result['valid'] ) ) {
				return array(
					'state'   => 'error',
					'message' => $coupon_result['error'],
				);
			}
			$amount          = $coupon_result['final_amount'];
			$discount_amount = $coupon_result['discount_amount'];
			$applied_code    = $coupon_result['code'];
		}

		$history_id  = PP_Billing_History::create( $user->ID, $plan->ID, $type, $gateway_id, $amount, $settings['currency_code'], $membership ? $membership->id : 0, $applied_code, $discount_amount );
		$billing_row = PP_Billing_History::get( $history_id );

		$gateway = $gateways[ $gateway_id ];
		$result  = $gateway->initiate( $billing_row, $plan, $user );

		if ( ! empty( $result['redirect'] ) ) {
			wp_redirect( $result['redirect'] ); // phpcs:ignore WordPress.Security.SafeRedirect -- gateway-provided URL (Stripe/PayPal), not user input
			exit;
		}
		if ( ! empty( $result['completed'] ) ) {
			return array(
				'state'   => 'success',
				'message' => '',
			);
		}
		if ( ! empty( $result['pending'] ) ) {
			return array(
				'state'   => 'pending',
				'message' => __( 'Your payment is awaiting confirmation. You will be notified once it is approved.', 'passpress' ),
			);
		}

		$error_message = ! empty( $result['error'] ) ? $result['error'] : __( 'Payment could not be started. Please try again.', 'passpress' );
		PP_Billing_History::mark_failed( $history_id, $error_message );
		PP_Notifications::payment_failed( $billing_row, $error_message );

		return array(
			'state'   => 'error',
			'message' => $error_message,
		);
	}

	private static function handle_gateway_return( $gateway_id ) {
		$gateways = self::get_gateway_instances();

		if ( ! isset( $gateways[ $gateway_id ] ) || ! method_exists( $gateways[ $gateway_id ], 'handle_return' ) ) {
			return array(
				'state'   => 'error',
				'message' => __( 'Unable to confirm payment.', 'passpress' ),
			);
		}

		return $gateways[ $gateway_id ]->handle_return();
	}

	/**
	 * The single point every gateway (return-URL AND webhook) funnels through
	 * to confirm a payment. Idempotent: the atomic claim in
	 * PP_Billing_History::mark_paid() happens BEFORE issuing/renewing the
	 * membership, so a webhook and a browser return racing each other can
	 * never both create a membership for the same payment — only whichever
	 * request wins the UPDATE proceeds past that point.
	 *
	 * @return bool True if this payment is (now, or already was) confirmed paid.
	 */
	public static function complete_payment( $checkout_token, $gateway_id, $gateway_ref = '', $raw_response = '' ) {
		$billing_row = PP_Billing_History::get_by_token( $checkout_token );
		if ( ! $billing_row ) {
			return false;
		}

		$claimed = PP_Billing_History::mark_paid( $billing_row->id, 0, $gateway_ref, $raw_response );
		if ( ! $claimed ) {
			return true; // Already claimed by a concurrent request — idempotent no-op.
		}

		if ( 'renewal' === $billing_row->type && $billing_row->membership_id ) {
			$membership = PP_Membership_Renewal::renew( $billing_row->membership_id );
		} else {
			$membership = PP_Membership::issue( $billing_row->user_id, $billing_row->plan_id );
		}

		if ( is_wp_error( $membership ) ) {
			PP_Activity_Logger::log( 'billing_paid_but_membership_failed', 'billing', $billing_row->id, 'Payment confirmed but membership issuance failed: ' . $membership->get_error_message() );
			return false;
		}

		PP_Billing_History::set_membership_id( $billing_row->id, $membership->id );
		PP_Notifications::receipt( $membership, $billing_row );

		PP_Activity_Logger::log(
			'billing_paid',
			'billing',
			$billing_row->id,
			sprintf( 'Payment of %s %s via %s confirmed for membership %s.', $billing_row->amount, strtoupper( $billing_row->currency ), $gateway_id, $membership->membership_number )
		);

		return true;
	}

	public static function fail_payment( $checkout_token, $reason = '' ) {
		$billing_row = PP_Billing_History::get_by_token( $checkout_token );
		if ( ! $billing_row ) {
			return false;
		}
		return PP_Billing_History::mark_failed( $billing_row->id, $reason );
	}

}
