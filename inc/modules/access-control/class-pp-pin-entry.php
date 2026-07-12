<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PIN-based check-in, for members without a scannable device: staff type the
 * membership number + PIN on the Scan Gate screen.
 */
class PP_Pin_Entry {

	public function __construct() {
		add_action( 'wp_ajax_pp_pin_validate', array( $this, 'handle_scan' ) );
	}

	public function handle_scan() {
		check_ajax_referer( 'pp_scan_gate', 'nonce' );

		if ( ! current_user_can( PP_Roles::CAP_SCAN ) ) {
			wp_send_json_error( array( 'reason' => __( 'You do not have permission to check in members.', 'passpress' ) ) );
		}

		$number      = isset( $_POST['membership_number'] ) ? sanitize_text_field( wp_unslash( $_POST['membership_number'] ) ) : '';
		$pin         = isset( $_POST['pin'] ) ? sanitize_text_field( wp_unslash( $_POST['pin'] ) ) : '';
		$facility_id = isset( $_POST['facility_id'] ) ? absint( $_POST['facility_id'] ) : 0;
		$direction   = isset( $_POST['direction'] ) && 'exit' === $_POST['direction'] ? 'exit' : 'entry';

		if ( ! $number || ! $pin ) {
			wp_send_json_error( array( 'reason' => __( 'Enter both membership number and PIN.', 'passpress' ) ) );
		}

		$membership = PP_Membership::get_by_number( $number );
		$pin_ok     = $membership && hash_equals( (string) $membership->pin_code, $pin );

		// A wrong PIN is logged/validated as "no membership" so the response
		// doesn't leak whether the number or the PIN was the wrong part.
		$result = PP_Access_Control::validate_and_log( $pin_ok ? $membership : null, 'pin', $facility_id, $direction );

		if ( ! $pin_ok ) {
			wp_send_json_error( array( 'reason' => __( 'Incorrect membership number or PIN.', 'passpress' ) ) );
		}

		if ( empty( $result['allowed'] ) ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success( $result );
	}
}
