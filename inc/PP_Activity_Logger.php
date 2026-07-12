<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes to the pp_activity_log table. Used by every module so admins have
 * one place to see what happened, without needing a settings/email UI yet.
 */
class PP_Activity_Logger {

	public static function log( $event, $object_type = '', $object_id = 0, $message = '', $user_id = 0 ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'pp_activity_log',
			array(
				'event'       => sanitize_key( $event ),
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => absint( $object_id ),
				'message'     => wp_strip_all_tags( $message ),
				'user_id'     => $user_id ? absint( $user_id ) : get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s' )
		);
	}

	public static function get_recent( $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pp_activity_log';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ) );
	}
}
