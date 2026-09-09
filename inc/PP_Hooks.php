<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central loader: instantiates every module class once all files are
 * required. Runs on 'plugins_loaded' (see passpress.php) — each class
 * attaches its own hooks (init, save_post_*, wp_ajax_*, ...) in its
 * constructor and does nothing eagerly here.
 */
class PP_Hooks {

	public static function init() {
		new PP_Membership_Plan_CPT();
		new PP_Facility_CPT();
		new PP_Class_Session_CPT();
		new PP_Booking_Frontend();
		new PP_Visitor_Frontend();
		new PP_Class_Frontend();
		new PP_Coupon_CPT();

		PP_Shortcodes::init();
		PP_Frontend::init();
		PP_Billing::init();
		PP_Cron::init();
		PP_Blocks::init();
		PP_Shop_WooCommerce::init();
		PP_Elementor_Support::init();

		add_action( 'wp_ajax_nopriv_passpress_stripe_webhook', array( 'PP_Gateway_Stripe', 'handle_webhook' ) );
		add_action( 'wp_ajax_nopriv_passpress_paypal_webhook', array( 'PP_Gateway_Paypal', 'handle_webhook' ) );

		if ( is_admin() ) {
			new PP_Admin();
			add_action( 'admin_init', array( 'PP_App_Page', 'maybe_redirect_legacy_cpt_screens' ) );
			add_action( 'admin_init', array( 'PP_App_Page', 'maybe_redirect_legacy_settings_slugs' ) );
			PP_Welcome::init();
		}
	}
}
