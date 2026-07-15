<?php
/**
 * Plugin Name: PassPress – Membership, Subscription & Pass Management
 * Plugin URI: https://example.com/passpress
 * Description: Modular membership, subscription and pass management for gyms, parks, clubs and sports facilities. Issue membership passes, scan QR/PIN entries at the door, enforce access rules, take online payments, and manage facility/class bookings, visitor passes, and attendance.
 * Version: 0.5.26
 * Author: PassPress
 * Text Domain: passpress
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PASSPRESS_PLUGIN_FILE', __FILE__ );
define( 'PASSPRESS_PLUGIN_DIR', __DIR__ );
define( 'PASSPRESS_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'PASSPRESS_PLUGIN_VERSION', '0.5.26' );
define( 'PASSPRESS_DB_VERSION', '1.2.0' );

require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Roles.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Install.php';

register_activation_hook( PASSPRESS_PLUGIN_FILE, array( 'PP_Install', 'activate' ) );
register_deactivation_hook( PASSPRESS_PLUGIN_FILE, array( 'PP_Install', 'deactivate' ) );

require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Functions.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Activity_Logger.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Query.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Dependencies.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Shortcodes.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Frontend.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Cron.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Hooks.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/membership/class-pp-membership.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/membership/class-pp-membership-cpt.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/membership/class-pp-membership-renewal.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/membership/class-pp-membership-status.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/facility/class-pp-facility.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/facility/class-pp-facility-cpt.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/access-control/class-pp-entry-restrictions.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/access-control/class-pp-access-control.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/access-control/class-pp-qr-scanner.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/access-control/class-pp-pin-entry.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/business-templates/class-pp-business-templates.php';

// Billing: required unconditionally (not just is_admin()) — the checkout
// page renders on the front end via template_redirect, and gateway
// webhooks arrive through admin-ajax.php from external servers.
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/billing/interface-pp-gateway.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/billing/class-pp-billing-history.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/billing/class-pp-billing.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/billing/gateways/class-pp-gateway-offline.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/billing/gateways/class-pp-gateway-stripe.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/billing/gateways/class-pp-gateway-paypal.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/booking/class-pp-booking.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/booking/class-pp-booking-slots.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/booking/class-pp-booking-calendar.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/booking/class-pp-booking-waitlist.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/booking/class-pp-booking-frontend.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/visitor/class-pp-visitor.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/visitor/class-pp-visitor-frontend.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/attendance/class-pp-attendance.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/class-session/class-pp-class-session-cpt.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/class-session/class-pp-class-session.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/class-session/class-pp-class-frontend.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/reports/class-pp-reports.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/marketing/class-pp-coupon-cpt.php';
require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/marketing/class-pp-coupon.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/notifications/class-pp-notifications.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/modules/shop/class-pp-shop-woocommerce.php';
require_once PASSPRESS_PLUGIN_DIR . '/support/elementor/elementor-support.php';

require_once PASSPRESS_PLUGIN_DIR . '/inc/PP_Blocks.php';

if ( is_admin() ) {
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Admin.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Dashboard.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Plans_List.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Coupons_List.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Facilities_List.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Class_Sessions_List.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Memberships_List.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Visitors_List.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Bookings_List.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Scan_Gate.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Billing_History_Page.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Attendance_Reports_Page.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Reports_Page.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Activity_Log_Page.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Setup_Wizard.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/PP_Welcome.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/settings/PP_Settings_Page.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/settings/PP_Settings.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/settings/PP_Billing_Settings.php';
	require_once PASSPRESS_PLUGIN_DIR . '/admin/settings/PP_Notification_Settings.php';
}

add_action( 'plugins_loaded', 'passpress_init' );

/**
 * Boots all modules once every class file above has been loaded.
 */
function passpress_init() {
	load_plugin_textdomain( 'passpress', false, dirname( plugin_basename( PASSPRESS_PLUGIN_FILE ) ) . '/languages' );
	PP_Hooks::init();
}
