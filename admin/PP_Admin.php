<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level admin menu. The parent menu and its first submenu (Dashboard)
 * use the broad CAP_SCAN capability so Gate Operators can see the menu at
 * all; PP_Dashboard::render() internally branches to a simplified view for
 * anyone without CAP_MANAGE so they never hit a "you don't have permission"
 * wall on the very link the menu points at.
 */
class PP_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'PassPress', 'passpress' ),
			__( 'PassPress', 'passpress' ),
			PP_Roles::CAP_SCAN,
			'passpress',
			array( 'PP_Dashboard', 'render' ),
			'dashicons-id-alt',
			26
		);

		add_submenu_page( 'passpress', __( 'Dashboard', 'passpress' ), __( 'Dashboard', 'passpress' ), PP_Roles::CAP_SCAN, 'passpress', array( 'PP_Dashboard', 'render' ) );
		add_submenu_page( 'passpress', __( 'Membership Plans', 'passpress' ), __( 'Membership Plans', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-plans', array( 'PP_Plans_List', 'render' ) );
		add_submenu_page( 'passpress', __( 'Coupons', 'passpress' ), __( 'Coupons', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-coupons', array( 'PP_Coupons_List', 'render' ) );
		add_submenu_page( 'passpress', __( 'Facilities', 'passpress' ), __( 'Facilities', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-facilities', array( 'PP_Facilities_List', 'render' ) );
		add_submenu_page( 'passpress', __( 'Class Sessions', 'passpress' ), __( 'Class Sessions', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-class-sessions', array( 'PP_Class_Sessions_List', 'render' ) );
		add_submenu_page( 'passpress', __( 'Memberships', 'passpress' ), __( 'Memberships', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-memberships', array( 'PP_Memberships_List', 'render' ) );
		add_submenu_page( 'passpress', __( 'Visitors', 'passpress' ), __( 'Visitors', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-visitors', array( 'PP_Visitors_List', 'render' ) );
		add_submenu_page( 'passpress', __( 'Bookings', 'passpress' ), __( 'Bookings', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-bookings', array( 'PP_Bookings_List', 'render' ) );
		add_submenu_page( 'passpress', __( 'Scan Gate', 'passpress' ), __( 'Scan Gate', 'passpress' ), PP_Roles::CAP_SCAN, 'passpress-scan-gate', array( 'PP_Scan_Gate', 'render' ) );
		add_submenu_page( 'passpress', __( 'Billing History', 'passpress' ), __( 'Billing History', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-billing-history', array( 'PP_Billing_History_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Attendance', 'passpress' ), __( 'Attendance', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-attendance', array( 'PP_Attendance_Reports_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Reports', 'passpress' ), __( 'Reports', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-reports', array( 'PP_Reports_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Activity Log', 'passpress' ), __( 'Activity Log', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-activity-log', array( 'PP_Activity_Log_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Setup Wizard', 'passpress' ), __( 'Setup Wizard', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-setup', array( 'PP_Setup_Wizard', 'render' ) );
		add_submenu_page( 'passpress', __( 'Settings', 'passpress' ), __( 'Settings', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-settings', array( 'PP_Settings_Page', 'render' ) );

		// Hidden legacy slugs — redirect into the unified Settings page tabs.
		add_submenu_page( null, '', '', PP_Roles::CAP_MANAGE, 'passpress-billing-settings', array( 'PP_Settings_Page', 'render_legacy_billing' ) );
		add_submenu_page( null, '', '', PP_Roles::CAP_MANAGE, 'passpress-notification-settings', array( 'PP_Settings_Page', 'render_legacy_notifications' ) );
	}

	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'passpress' ) ) {
			return;
		}

		wp_enqueue_style( 'passpress-admin', PASSPRESS_PLUGIN_URL . '/assets/admin/passpress-admin.css', array(), PASSPRESS_PLUGIN_VERSION );
		wp_enqueue_script( 'passpress-admin', PASSPRESS_PLUGIN_URL . '/assets/admin/passpress-admin.js', array( 'jquery' ), PASSPRESS_PLUGIN_VERSION, true );

		wp_localize_script(
			'passpress-admin',
			'PassPressScan',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'pp_scan_gate' ),
				'deniedLabel' => __( 'Access denied', 'passpress' ),
			)
		);
	}
}
