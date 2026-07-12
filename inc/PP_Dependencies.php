<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Soft dependency checks. PassPress never hard-requires WooCommerce — see
 * CLAUDE.md ("WooCommerce is optional, not required"). These helpers let
 * later modules (Shop, Billing's WC Subscriptions gateway) detect it without
 * blocking activation or forcing an install prompt.
 */

if ( ! function_exists( 'pp_is_woocommerce_active' ) ) {
	function pp_is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}
}

if ( ! function_exists( 'pp_is_woocommerce_subscriptions_active' ) ) {
	function pp_is_woocommerce_subscriptions_active() {
		return class_exists( 'WC_Subscriptions' );
	}
}

if ( ! function_exists( 'pp_is_elementor_active' ) ) {
	function pp_is_elementor_active() {
		return did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );
	}
}
