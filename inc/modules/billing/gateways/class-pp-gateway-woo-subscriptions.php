<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Subscriptions bridge — deliberately a thin detection stub, not
 * a real integration, for one concrete reason: this site has no WooCommerce
 * installed at all, so there is no way to verify a real bridge here even at
 * a basic "loads without a fatal error" level, let alone confirm hook names
 * (`woocommerce_subscription_status_active`, `_renewal_payment_complete`,
 * etc.) against the actual installed version. Writing 150+ lines of
 * speculative hook-wiring against an unverifiable API would look done
 * without being done — see CLAUDE.md's Phase 2 notes.
 *
 * What this class actually does: detects whether WC Subscriptions is active
 * and the admin has switched Billing to `wc_subscriptions` mode, and shows a
 * plain admin notice saying the bridge isn't built yet. Real implementation
 * (hidden WC_Product_Subscription per plan, subscription status ->
 * membership status sync, renewal-payment -> PP_Membership_Renewal::renew())
 * should happen in an environment where WooCommerce + WC Subscriptions can
 * actually be installed and tested against.
 */
class PP_Gateway_Woo_Subscriptions {

	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_not_implemented_notice' ) );
	}

	public static function is_available() {
		return pp_is_woocommerce_subscriptions_active();
	}

	public static function maybe_show_not_implemented_notice() {
		$settings = PP_Billing::get_settings();
		if ( 'wc_subscriptions' !== $settings['payment_method_type'] ) {
			return;
		}
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'PassPress: the WooCommerce Subscriptions billing bridge is not implemented yet. Switch Payment Method back to Offline/Stripe/PayPal in Billing Settings.', 'passpress' ); ?></p>
		</div>
		<?php
	}
}
