<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirects to the Setup Wizard once, right after activation.
 */
class PP_Welcome {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
	}

	public static function maybe_redirect() {
		if ( ! get_transient( 'passpress_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'passpress_activation_redirect' );

		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) || ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=passpress-setup' ) );
		exit;
	}
}
