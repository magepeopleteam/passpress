<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level admin menu. The parent menu and its first submenu (Dashboard)
 * use the broad CAP_SCAN capability so Gate Operators can see the menu at
 * all; the React Dashboard screen (admin-app/src/pages/Dashboard.jsx) reads
 * `can_manage` from GET /passpress/v1/dashboard and internally branches to a
 * simplified view for anyone without CAP_MANAGE, so they never hit a "you
 * don't have permission" wall on the very link the menu points at.
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
			array( 'PP_App_Page', 'render' ),
			'dashicons-id-alt',
			26
		);

		// Every submenu here renders the same React app shell (admin-app/ +
		// inc/rest/) — PP_App_Page::render() outputs one root div, and the
		// client-side router (admin-app/src/router.jsx) reads ?page= to decide
		// which screen to show.
		add_submenu_page( 'passpress', __( 'Dashboard', 'passpress' ), __( 'Dashboard', 'passpress' ), PP_Roles::CAP_SCAN, 'passpress', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Membership Plans', 'passpress' ), __( 'Membership Plans', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-plans', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Coupons', 'passpress' ), __( 'Coupons', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-coupons', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Facilities', 'passpress' ), __( 'Facilities', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-facilities', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Class Sessions', 'passpress' ), __( 'Class Sessions', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-class-sessions', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Memberships', 'passpress' ), __( 'Memberships', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-memberships', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Visitors', 'passpress' ), __( 'Visitors', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-visitors', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Bookings', 'passpress' ), __( 'Bookings', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-bookings', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Scan Gate', 'passpress' ), __( 'Scan Gate', 'passpress' ), PP_Roles::CAP_SCAN, 'passpress-scan-gate', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Billing History', 'passpress' ), __( 'Billing History', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-billing-history', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Attendance', 'passpress' ), __( 'Attendance', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-attendance', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Reports', 'passpress' ), __( 'Reports', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-reports', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Activity Log', 'passpress' ), __( 'Activity Log', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-activity-log', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Setup Wizard', 'passpress' ), __( 'Setup Wizard', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-setup', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( 'passpress', __( 'Settings', 'passpress' ), __( 'Settings', 'passpress' ), PP_Roles::CAP_MANAGE, 'passpress-settings', array( 'PP_App_Page', 'render' ) );

		// Hidden legacy slugs — PP_App_Page::maybe_redirect_legacy_settings_slugs()
		// (admin_init) sends these into the unified Settings page's matching tab.
		add_submenu_page( null, '', '', PP_Roles::CAP_MANAGE, 'passpress-billing-settings', array( 'PP_App_Page', 'render' ) );
		add_submenu_page( null, '', '', PP_Roles::CAP_MANAGE, 'passpress-notification-settings', array( 'PP_App_Page', 'render' ) );
	}

	/**
	 * Every passpress-* admin screen is now the React app — enqueues the
	 * Vite-built admin-app/ bundle (admin-app/src/main.jsx) plus the design
	 * source it reuses class names from (KPI tiles, card-grid, modal, status
	 * pills, etc.), and gives the bundle a REST root+nonce pair via a
	 * `window.passpressApp` global.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'passpress' ) ) {
			return;
		}

		wp_enqueue_style( 'passpress-admin', PASSPRESS_PLUGIN_URL . '/assets/admin/passpress-admin.css', array(), PASSPRESS_PLUGIN_VERSION );

		$asset_file = PASSPRESS_PLUGIN_DIR . '/assets/admin/build/admin-app.css';
		if ( file_exists( $asset_file ) ) {
			wp_enqueue_style( 'passpress-admin-app', PASSPRESS_PLUGIN_URL . '/assets/admin/build/admin-app.css', array( 'passpress-admin' ), PASSPRESS_PLUGIN_VERSION );
		}

		wp_enqueue_script( 'passpress-admin-app', PASSPRESS_PLUGIN_URL . '/assets/admin/build/admin-app.js', array(), PASSPRESS_PLUGIN_VERSION, true );

		wp_add_inline_script(
			'passpress-admin-app',
			'window.passpressApp = ' . wp_json_encode(
				array(
					'root'  => esc_url_raw( rest_url() ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				)
			) . ';',
			'before'
		);
	}
}
