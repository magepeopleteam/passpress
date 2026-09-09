<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps the passpress/v1 REST namespace that backs the new React admin
 * (admin-app/). Required unconditionally from passpress.php, same rationale
 * as the Billing module: REST requests don't set is_admin() to true, so this
 * can't sit inside the is_admin() block that guards the legacy PHP-rendered
 * admin screens.
 */
class PP_REST {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$controllers = array(
			new PP_REST_Dashboard(),
			new PP_REST_Plans(),
			new PP_REST_Facilities(),
			new PP_REST_Class_Sessions(),
			new PP_REST_Coupons(),
			new PP_REST_Memberships(),
			new PP_REST_Bookings(),
			new PP_REST_Visitors(),
			new PP_REST_Billing_History(),
			new PP_REST_Attendance(),
			new PP_REST_Reports(),
			new PP_REST_Activity_Log(),
			new PP_REST_Scan(),
			new PP_REST_Setup(),
			new PP_REST_Settings(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}
}
