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
		add_action( 'wp_ajax_passpress_modal_checkout', array( __CLASS__, 'ajax_modal_checkout' ) );
		add_action( 'wp_ajax_nopriv_passpress_modal_checkout', array( __CLASS__, 'ajax_modal_checkout' ) );
		add_action( 'wp_ajax_passpress_apply_coupon', array( __CLASS__, 'ajax_apply_coupon' ) );
		add_action( 'wp_ajax_nopriv_passpress_apply_coupon', array( __CLASS__, 'ajax_apply_coupon' ) );
	}

	public static function default_settings() {
		return array(
			'payment_method_type'     => 'native',
			'offline_enabled'         => 1,
			'offline_auto_confirm'    => 1,
			'offline_instructions'    => '',
			'stripe_enabled'          => 0,
			'stripe_mode'             => 'test',
			'stripe_publishable_key'  => '',
			'stripe_secret_key'       => '',
			'stripe_webhook_secret'   => '',
			'paypal_enabled'          => 0,
			'paypal_mode'             => 'sandbox',
			'paypal_client_id'        => '',
			'paypal_client_secret'    => '',
			'paypal_webhook_id'       => '',
			'renewal_reminder_days'   => 7,
			'wc_add_to_cart_redirect' => 'checkout',
			'wc_require_login'        => 0,
		);
	}

	public static function get_settings() {
		$settings = wp_parse_args( get_option( 'passpress_billing_settings', array() ), self::default_settings() );

		// Migrate legacy WooCommerce Subscriptions mode — PassPress owns renewals.
		if ( 'wc_subscriptions' === $settings['payment_method_type'] || 'custom' === $settings['payment_method_type'] ) {
			$settings['payment_method_type'] = 'native';
		}

		if ( ! in_array( $settings['payment_method_type'], array( 'native', 'woocommerce', 'none' ), true ) ) {
			$settings['payment_method_type'] = 'native';
		}

		return $settings;
	}

	public static function get_payment_method_type() {
		return self::get_settings()['payment_method_type'];
	}

	public static function is_native_mode() {
		return 'native' === self::get_payment_method_type();
	}

	public static function is_woocommerce_mode() {
		return 'woocommerce' === self::get_payment_method_type() && pp_is_woocommerce_active();
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
	 * Gateways turned on in Billing settings (shown in checkout UI).
	 * Configuration (API keys) is validated at payment time so an enabled
	 * but not-yet-keyed gateway still appears in the modal.
	 *
	 * @return PP_Gateway_Interface[] id => instance
	 */
	public static function get_enabled_gateways() {
		if ( ! self::is_native_mode() ) {
			return array();
		}

		$settings = self::get_settings();
		$enabled  = array();

		foreach ( self::get_gateway_instances() as $id => $gateway ) {
			if ( ! empty( $settings[ $id . '_enabled' ] ) ) {
				$enabled[ $id ] = $gateway;
			}
		}

		return $enabled;
	}

	/**
	 * Prefer a stable display order: Offline, Stripe, PayPal.
	 *
	 * @return PP_Gateway_Interface[]
	 */
	public static function get_checkout_gateways() {
		$order   = array( 'offline', 'stripe', 'paypal' );
		$enabled = self::get_enabled_gateways();
		$sorted  = array();

		foreach ( $order as $id ) {
			if ( isset( $enabled[ $id ] ) ) {
				$sorted[ $id ] = $enabled[ $id ];
			}
		}

		foreach ( $enabled as $id => $gateway ) {
			if ( ! isset( $sorted[ $id ] ) ) {
				$sorted[ $id ] = $gateway;
			}
		}

		return $sorted;
	}

	public static function is_billing_available() {
		if ( self::is_woocommerce_mode() ) {
			return true;
		}
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

		// WooCommerce mode: send buyers to the shop product instead of native checkout.
		if ( self::is_woocommerce_mode() ) {
			$plan_id = isset( $_GET['plan_id'] ) ? absint( $_GET['plan_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$shop_url = ( $plan_id && class_exists( 'PP_Shop_WooCommerce' ) ) ? PP_Shop_WooCommerce::buy_url( $plan_id ) : '';
			if ( $shop_url ) {
				wp_safe_redirect( $shop_url );
				exit;
			}
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

		$gateways    = self::get_checkout_gateways();
		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sticky display value only, re-validated on actual submit

		include PASSPRESS_PLUGIN_DIR . '/templates/checkout/checkout.php';
	}

	private static function process_checkout_submit( $plan, $membership ) {
		$gateway_id  = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$gateways    = self::get_checkout_gateways();

		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			return array(
				'state'   => 'error',
				'message' => __( 'Please choose a valid payment method.', 'passpress' ),
			);
		}

		if ( ! $gateways[ $gateway_id ]->is_configured() ) {
			return array(
				'state'   => 'error',
				'message' => sprintf(
					/* translators: %s: gateway label */
					__( '%s is enabled but not configured yet. Add your API keys in PassPress → Settings → Payment Method.', 'passpress' ),
					$gateways[ $gateway_id ]->label()
				),
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

	/**
	 * Apply a coupon from the checkout modal (preview totals only).
	 */
	public static function ajax_apply_coupon() {
		$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
		$code    = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $plan_id || ! wp_verify_nonce( $nonce, 'passpress_checkout_' . $plan_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'passpress' ) ) );
		}

		$plan = get_post( $plan_id );
		if ( ! $plan || 'pp_membership_plan' !== $plan->post_type || 'publish' !== $plan->post_status ) {
			wp_send_json_error( array( 'message' => __( 'This membership plan is not available.', 'passpress' ) ) );
		}

		$settings = pp_get_settings();
		$price    = (float) get_post_meta( $plan_id, '_pp_price', true );
		$user_id  = get_current_user_id();

		if ( ! $code ) {
			wp_send_json_success(
				array(
					'final_amount'    => $price,
					'discount_amount' => 0,
					'price_label'     => $settings['currency_symbol'] . number_format_i18n( $price, 2 ),
					'total_label'     => $settings['currency_symbol'] . number_format_i18n( $price, 2 ),
					'discount_label'  => '',
					'message'         => '',
				)
			);
		}

		$result = PP_Coupon::validate( $code, $plan_id, $user_id, $price );
		if ( empty( $result['valid'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		wp_send_json_success(
			array(
				'final_amount'    => $result['final_amount'],
				'discount_amount' => $result['discount_amount'],
				'code'            => $result['code'],
				'price_label'     => $settings['currency_symbol'] . number_format_i18n( $price, 2 ),
				'total_label'     => $settings['currency_symbol'] . number_format_i18n( $result['final_amount'], 2 ),
				'discount_label'  => '−' . $settings['currency_symbol'] . number_format_i18n( $result['discount_amount'], 2 ),
				'message'         => __( 'Coupon applied.', 'passpress' ),
			)
		);
	}

	/**
	 * Process checkout from the plan-list modal.
	 */
	public static function ajax_modal_checkout() {
		if ( ! self::is_native_mode() ) {
			wp_send_json_error( array( 'message' => __( 'Native checkout is not enabled.', 'passpress' ) ) );
		}

		$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
		$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $plan_id || ! wp_verify_nonce( $nonce, 'passpress_checkout_' . $plan_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'passpress' ) ) );
		}

		$full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! $full_name || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your full name and a valid email.', 'passpress' ) ) );
		}

		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( self::checkout_url( $plan_id ) );
			wp_send_json_error(
				array(
					'message'  => __( 'Please log in to complete your purchase.', 'passpress' ),
					'loginUrl' => $login_url,
				)
			);
		}

		$user = wp_get_current_user();
		if ( $full_name && $full_name !== $user->display_name ) {
			wp_update_user(
				array(
					'ID'           => $user->ID,
					'display_name' => $full_name,
				)
			);
		}

		$plan = get_post( $plan_id );
		if ( ! $plan || 'pp_membership_plan' !== $plan->post_type || 'publish' !== $plan->post_status ) {
			wp_send_json_error( array( 'message' => __( 'This membership plan is not available.', 'passpress' ) ) );
		}

		$renew_id   = isset( $_POST['renew'] ) ? absint( $_POST['renew'] ) : 0;
		$membership = null;
		if ( $renew_id ) {
			$candidate = PP_Membership::get( $renew_id );
			if ( $candidate && (int) $candidate->user_id === get_current_user_id() && (int) $candidate->plan_id === $plan_id ) {
				$membership = $candidate;
			}
		}

		$gateway_id  = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
		$is_gift     = ! empty( $_POST['is_gift'] );
		$gateways    = self::get_checkout_gateways();

		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid payment method.', 'passpress' ) ) );
		}

		if ( ! $gateways[ $gateway_id ]->is_configured() ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: gateway label */
						__( '%s is enabled but not configured yet. Add your API keys in PassPress → Settings → Payment Method.', 'passpress' ),
						$gateways[ $gateway_id ]->label()
					),
				)
			);
		}

		$settings        = pp_get_settings();
		$price           = (float) get_post_meta( $plan->ID, '_pp_price', true );
		$type            = $membership ? 'renewal' : 'initial';
		$amount          = $price;
		$discount_amount = 0;
		$applied_code    = '';

		if ( $coupon_code ) {
			$coupon_result = PP_Coupon::validate( $coupon_code, $plan->ID, $user->ID, $price );
			if ( empty( $coupon_result['valid'] ) ) {
				wp_send_json_error( array( 'message' => $coupon_result['error'] ) );
			}
			$amount          = $coupon_result['final_amount'];
			$discount_amount = $coupon_result['discount_amount'];
			$applied_code    = $coupon_result['code'];
		}

		$history_id  = PP_Billing_History::create( $user->ID, $plan->ID, $type, $gateway_id, $amount, $settings['currency_code'], $membership ? $membership->id : 0, $applied_code, $discount_amount );
		$billing_row = PP_Billing_History::get( $history_id );

		if ( $is_gift ) {
			PP_Activity_Logger::log( 'checkout_gift', 'billing', $history_id, sprintf( 'Gift checkout for plan #%d by user #%d (%s).', $plan->ID, $user->ID, $email ) );
		}

		$gateway = $gateways[ $gateway_id ];
		$result  = $gateway->initiate( $billing_row, $plan, $user );

		if ( ! empty( $result['redirect'] ) ) {
			wp_send_json_success(
				array(
					'state'    => 'redirect',
					'redirect' => $result['redirect'],
				)
			);
		}

		if ( ! empty( $result['completed'] ) ) {
			$my_pass = pp_find_shortcode_page_url( 'passpress_my_pass' );
			wp_send_json_success(
				array(
					'state'   => 'success',
					'message' => __( 'Payment received! Your membership is now active.', 'passpress' ),
					'passUrl' => $my_pass ? $my_pass : home_url( '/' ),
				)
			);
		}

		if ( ! empty( $result['pending'] ) ) {
			wp_send_json_success(
				array(
					'state'   => 'pending',
					'message' => __( 'Your payment is awaiting confirmation. You will be notified once it is approved.', 'passpress' ),
				)
			);
		}

		$error_message = ! empty( $result['error'] ) ? $result['error'] : __( 'Payment could not be started. Please try again.', 'passpress' );
		PP_Billing_History::mark_failed( $history_id, $error_message );
		PP_Notifications::payment_failed( $billing_row, $error_message );

		wp_send_json_error( array( 'message' => $error_message ) );
	}

}
