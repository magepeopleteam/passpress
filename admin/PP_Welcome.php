<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirects to the Setup Wizard once, right after activation.
 * The setup screen shows a welcome banner when pp_welcome=1.
 */
class PP_Welcome {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ), 1 );
	}

	public static function maybe_redirect() {
		if ( ! get_transient( 'passpress_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'passpress_activation_redirect' );

		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) || ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			return;
		}

		$target = admin_url( 'admin.php?page=passpress-setup&pp_welcome=1' );

		if ( headers_sent() ) {
			echo '<script>window.location.href=' . wp_json_encode( $target ) . ';</script>';
			echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr( $target ) . '"></noscript>';
			exit;
		}

		wp_safe_redirect( $target );
		exit;
	}
}
