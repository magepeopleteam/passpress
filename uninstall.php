<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/inc/PP_Roles.php';
PP_Roles::remove_roles();

wp_clear_scheduled_hook( 'passpress_daily_renewal_check' );

global $wpdb;

$tables = array(
	$wpdb->prefix . 'pp_memberships',
	$wpdb->prefix . 'pp_access_logs',
	$wpdb->prefix . 'pp_activity_log',
	$wpdb->prefix . 'pp_billing_history',
	$wpdb->prefix . 'pp_bookings',
	$wpdb->prefix . 'pp_booking_waitlist',
);
foreach ( $tables as $table ) {
	// Table names are hardcoded above, never derived from user input.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

foreach ( array( 'pp_membership_plan', 'pp_facility' ) as $post_type ) {
	$posts = get_posts(
		array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		)
	);
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

delete_option( 'passpress_settings' );
delete_option( 'passpress_billing_settings' );
delete_option( 'passpress_db_version' );
delete_option( 'passpress_active_business_type' );
delete_option( 'passpress_template_imported_gym' );
