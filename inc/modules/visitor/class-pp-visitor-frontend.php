<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Invite a Guest" — the member-facing half of the Visitor Pass module.
 * Shown on the My Pass page (not its own shortcode, same surface). Only
 * creates the account + a pending-invitation flag; staff finalize (pick a
 * plan) from PassPress → Visitors when the guest arrives.
 */
class PP_Visitor_Frontend {

	public function __construct() {
		add_action( 'wp_ajax_pp_invite_guest', array( $this, 'ajax_invite_guest' ) );
	}

	public function ajax_invite_guest() {
		check_ajax_referer( 'pp_invite_guest', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to invite a guest.', 'passpress' ) ) );
		}

		$guest_name  = isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : '';
		$guest_email = isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '';

		if ( ! $guest_name ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your guest\'s name.', 'passpress' ) ) );
		}

		$result = PP_Visitor::invite_guest( $guest_name, $guest_email, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Invitation sent! Your guest can pick up their pass at the front desk.', 'passpress' ) ) );
	}
}
