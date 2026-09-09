<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirects once, right after activation, to the Dashboard with pp_welcome=1 —
 * which makes the React Dashboard screen render the three-step import modal
 * over it (admin-app/src/components/SetupWizardModal.jsx). Landing on the Dashboard rather than on the
 * Setup screen means a fresh install shows a single yes/no question first, and
 * an admin who declines is already where they need to be.
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

		$target = admin_url( 'admin.php?page=passpress&pp_welcome=1' );

		if ( headers_sent() ) {
			echo '<script>window.location.href=' . wp_json_encode( $target ) . ';</script>';
			echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr( $target ) . '"></noscript>';
			exit;
		}

		wp_safe_redirect( $target );
		exit;
	}
}
