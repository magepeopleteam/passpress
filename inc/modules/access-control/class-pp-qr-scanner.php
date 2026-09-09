<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QR scan entrypoint. Generation is client-side (assets/frontend/passpress-my-pass.js
 * renders the QR from the pass_token via the bundled qrcodejs library) — this
 * class builds the payload; validation is now handled by
 * inc/rest/class-pp-rest-scan.php (POST /passpress/v1/scan/validate), which
 * calls PP_Access_Control::validate_and_log() directly — the wp_ajax_pp_scan_validate
 * handler that used to live here is retired along with the Scan Gate's
 * jQuery/admin-ajax code.
 *
 * Reading happens on the Scan Gate admin screen via a plain text input: any
 * USB/Bluetooth QR scanner behaves as a keyboard (HID) and types the decoded
 * token followed by Enter, so no camera/JS-decoder is needed.
 */
class PP_QR_Scanner {

	/**
	 * The exact string encoded into a member's QR image.
	 */
	public static function build_payload( $membership ) {
		return $membership->pass_token;
	}
}
