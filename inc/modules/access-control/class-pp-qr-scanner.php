<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QR scan entrypoint. Generation is client-side (assets/frontend/passpress-my-pass.js
 * renders the QR from the pass_token via the bundled qrcodejs library) — this
 * class only builds the payload and handles the AJAX validation.
 *
 * Reading happens on the Scan Gate admin screen via a plain text input: any
 * USB/Bluetooth QR scanner behaves as a keyboard (HID) and types the decoded
 * token followed by Enter, so no camera/JS-decoder is needed for Phase 1.
 */
class PP_QR_Scanner {

	public function __construct() {
		add_action( 'wp_ajax_pp_scan_validate', array( $this, 'handle_scan' ) );
	}

	public function handle_scan() {
		check_ajax_referer( 'pp_scan_gate', 'nonce' );

		if ( ! current_user_can( PP_Roles::CAP_SCAN ) ) {
			wp_send_json_error( array( 'reason' => __( 'You do not have permission to scan passes.', 'passpress' ) ) );
		}

		$token       = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$facility_id = isset( $_POST['facility_id'] ) ? absint( $_POST['facility_id'] ) : 0;
		$direction   = isset( $_POST['direction'] ) && 'exit' === $_POST['direction'] ? 'exit' : 'entry';

		if ( ! $token ) {
			wp_send_json_error( array( 'reason' => __( 'No code scanned.', 'passpress' ) ) );
		}

		$membership = PP_Membership::get_by_token( $token );
		$result     = PP_Access_Control::validate_and_log( $membership, 'qr', $facility_id, $direction );

		if ( empty( $result['allowed'] ) ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * The exact string encoded into a member's QR image.
	 */
	public static function build_payload( $membership ) {
		return $membership->pass_token;
	}
}
