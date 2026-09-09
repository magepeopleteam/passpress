<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /passpress/v1/dashboard — direct REST port of admin/PP_Dashboard.php's
 * data assembly. Field-for-field the same source calls (PP_Query,
 * PP_Activity_Logger, PP_Business_Templates) so the React Dashboard screen
 * renders identically to the PHP one it replaces.
 */
class PP_REST_Dashboard extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( __CLASS__, 'permission_scan' ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request
	 */
	public function get_dashboard( $request ) {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return rest_ensure_response( array( 'can_manage' => false ) );
		}

		$stats        = PP_Query::dashboard_stats();
		$activity_raw = PP_Activity_Logger::get_recent( 8 );
		$roadmap      = class_exists( 'PP_Business_Templates' ) ? PP_Business_Templates::get_roadmap() : array();
		$active_type  = get_option( 'passpress_active_business_type', '' );
		$active_label = ( $active_type && isset( $roadmap[ $active_type ] ) ) ? $roadmap[ $active_type ] : '';
		$notice       = get_transient( 'passpress_setup_notice' );
		if ( $notice ) {
			delete_transient( 'passpress_setup_notice' );
		}

		$activity = array();
		foreach ( $activity_raw as $row ) {
			$activity[] = array(
				'message'  => self::format_activity_message( $row ),
				'meta'     => self::format_activity_meta( $row ),
				'category' => self::activity_category( $row ),
			);
		}

		return rest_ensure_response(
			array(
				'can_manage'          => true,
				'stats'               => array(
					'active_memberships' => (int) $stats['active_memberships'],
					'expiring_soon'      => (int) $stats['expiring_soon'],
					'todays_checkins'    => (int) $stats['todays_checkins'],
					'frozen_suspended'   => (int) $stats['frozen_suspended'],
				),
				'activity'            => $activity,
				'active_business_type' => $active_type,
				'active_label'        => $active_label,
				'plans_count'         => self::count_plans(),
				'needs_setup'         => empty( $active_type ),
				'notice'              => $notice ? $notice : null,
			)
		);
	}

	private static function count_plans() {
		$plans = get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'fields'         => 'ids',
			)
		);
		return is_array( $plans ) ? count( $plans ) : 0;
	}

	/**
	 * @param object $row Activity log row.
	 */
	private static function format_activity_message( $row ) {
		if ( ! empty( $row->message ) ) {
			return $row->message;
		}
		$event = isset( $row->event ) ? $row->event : '';
		return $event ? ucwords( str_replace( '_', ' ', $event ) ) : __( 'Activity', 'passpress' );
	}

	/**
	 * @param object $row Activity log row.
	 */
	private static function format_activity_meta( $row ) {
		$parts = array();
		if ( ! empty( $row->event ) ) {
			$parts[] = ucwords( str_replace( '_', ' ', $row->event ) );
		}
		if ( ! empty( $row->created_at ) ) {
			$parts[] = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->created_at );
		}
		return implode( ' · ', $parts );
	}
}
