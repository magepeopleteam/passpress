<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small procedural helpers shared across admin, frontend, and module code.
 */

function pp_get_settings() {
	$defaults = array(
		'currency_symbol'  => '$',
		'currency_code'    => 'usd',
		'date_format'      => 'F j, Y',
		'qr_size'          => 200,
		'show_pin_on_pass' => 1,
	);
	return wp_parse_args( get_option( 'passpress_settings', array() ), $defaults );
}

function pp_get_setting( $key, $default = null ) {
	$settings = pp_get_settings();
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

function pp_format_date( $mysql_date ) {
	if ( ! $mysql_date ) {
		return '';
	}
	return date_i18n( pp_get_setting( 'date_format', 'F j, Y' ), strtotime( $mysql_date ) );
}

function pp_format_datetime( $mysql_datetime ) {
	if ( ! $mysql_datetime ) {
		return '';
	}
	return date_i18n( get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' ), strtotime( $mysql_datetime ) );
}

function pp_status_label( $status ) {
	$labels = array(
		'active'    => __( 'Active', 'passpress' ),
		'frozen'    => __( 'Frozen', 'passpress' ),
		'suspended' => __( 'Suspended', 'passpress' ),
		'expired'   => __( 'Expired', 'passpress' ),
		'cancelled' => __( 'Cancelled', 'passpress' ),
	);
	return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
}

/**
 * Generates a unique, human-readable membership number, e.g. PP-A3F9K2.
 */
function pp_generate_membership_number() {
	global $wpdb;
	$table = $wpdb->prefix . 'pp_memberships';
	do {
		$number = 'PP-' . strtoupper( wp_generate_password( 6, false, false ) );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE membership_number = %s", $number ) );
	} while ( $exists );
	return $number;
}

/**
 * Generates the unique token encoded in a member's QR pass.
 */
function pp_generate_pass_token() {
	global $wpdb;
	$table = $wpdb->prefix . 'pp_memberships';
	do {
		$token  = wp_generate_password( 32, false, false );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE pass_token = %s", $token ) );
	} while ( $exists );
	return $token;
}

function pp_generate_pin() {
	return str_pad( (string) wp_rand( 0, 9999 ), 4, '0', STR_PAD_LEFT );
}

/**
 * Correlates a checkout attempt with its gateway return/webhook — passed to
 * Stripe as client_reference_id and to PayPal as a purchase_unit custom_id.
 */
function pp_generate_checkout_token() {
	global $wpdb;
	$table = $wpdb->prefix . 'pp_billing_history';
	do {
		$token  = wp_generate_password( 40, false, false );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE checkout_token = %s", $token ) );
	} while ( $exists );
	return $token;
}

function pp_format_price( $amount ) {
	$settings = pp_get_settings();
	return $settings['currency_symbol'] . number_format_i18n( (float) $amount, 2 );
}

/**
 * Best-effort lookup of a published page containing the given shortcode
 * (e.g. the "My Pass" page the Gym template creates), cached briefly since
 * it's a LIKE query. Returns false if none is found — callers should treat
 * the link as optional, not assume one exists.
 */
function pp_find_shortcode_page_url( $shortcode ) {
	$cache_key = 'pp_shortcode_page_' . md5( $shortcode );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached ? $cached : false;
	}

	global $wpdb;
	$post_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s LIMIT 1",
			'%[' . $wpdb->esc_like( $shortcode ) . '%'
		)
	);

	$url = $post_id ? get_permalink( $post_id ) : false;
	set_transient( $cache_key, $url ? $url : '', HOUR_IN_SECONDS );

	return $url;
}
