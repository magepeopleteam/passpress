<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Soft dependency checks. PassPress never hard-requires WooCommerce —
 * memberships and renewals are managed by PassPress itself. These helpers
 * let optional bridges (Shop, Elementor) detect plugins without blocking
 * activation or forcing an install prompt.
 */

if ( ! function_exists( 'pp_is_woocommerce_active' ) ) {
	function pp_is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}

/**
 * 0 = not installed, 1 = active, 2 = installed but inactive.
 *
 * @return int
 */
if ( ! function_exists( 'pp_woocommerce_status' ) ) {
	function pp_woocommerce_status() {
		if ( class_exists( 'WooCommerce' ) ) {
			return 1;
		}
		if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
			return 2;
		}
		return 0;
	}
}

if ( ! function_exists( 'pp_is_elementor_active' ) ) {
	function pp_is_elementor_active() {
		return did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
	}
}
