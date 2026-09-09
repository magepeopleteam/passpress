<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/scan — direct REST port of the QR/PIN AJAX handlers
 * (class-pp-qr-scanner.php's pp_scan_validate, class-pp-pin-entry.php's
 * pp_pin_validate). Both still funnel into the same
 * PP_Access_Control::validate_and_log() entrypoint.
 *
 * Unlike the legacy AJAX responses (which dumped the whole pp_memberships row
 * back to the browser, pass_token/pin_code included), this only returns the
 * fields the Scan Gate screen actually renders — a small, deliberate
 * tightening made while porting this endpoint, not a behavior change the
 * screen depends on.
 */
class PP_REST_Scan extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/scan/facilities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_facilities' ),
				'permission_callback' => array( __CLASS__, 'permission_scan' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/scan/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_qr' ),
				'permission_callback' => array( __CLASS__, 'permission_scan' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/scan/pin',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_pin' ),
				'permission_callback' => array( __CLASS__, 'permission_scan' ),
			)
		);
	}

	public function list_facilities( $request ) {
		$facilities = array_map(
			function ( $f ) { return array( 'id' => (int) $f->ID, 'name' => $f->post_title ); },
			PP_Facility::get_all()
		);
		return rest_ensure_response( array( 'facilities' => $facilities ) );
	}

	public function validate_qr( $request ) {
		$token       = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$facility_id = absint( $request->get_param( 'facility_id' ) );
		$direction   = 'exit' === $request->get_param( 'direction' ) ? 'exit' : 'entry';

		if ( ! $token ) {
			return rest_ensure_response( array( 'allowed' => false, 'reason' => __( 'No code scanned.', 'passpress' ) ) );
		}

		$membership = PP_Membership::get_by_token( $token );
		$result     = PP_Access_Control::validate_and_log( $membership, 'qr', $facility_id, $direction );

		return rest_ensure_response( self::public_result( $result ) );
	}

	public function validate_pin( $request ) {
		$number      = sanitize_text_field( (string) $request->get_param( 'membership_number' ) );
		$pin         = sanitize_text_field( (string) $request->get_param( 'pin' ) );
		$facility_id = absint( $request->get_param( 'facility_id' ) );
		$direction   = 'exit' === $request->get_param( 'direction' ) ? 'exit' : 'entry';

		if ( ! $number || ! $pin ) {
			return rest_ensure_response( array( 'allowed' => false, 'reason' => __( 'Enter both membership number and PIN.', 'passpress' ) ) );
		}

		$membership = PP_Membership::get_by_number( $number );
		$pin_ok     = $membership && hash_equals( (string) $membership->pin_code, $pin );

		// A wrong PIN is logged/validated as "no membership" so the response
		// doesn't leak whether the number or the PIN was the wrong part.
		$result = PP_Access_Control::validate_and_log( $pin_ok ? $membership : null, 'pin', $facility_id, $direction );

		if ( ! $pin_ok ) {
			return rest_ensure_response( array( 'allowed' => false, 'reason' => __( 'Incorrect membership number or PIN.', 'passpress' ) ) );
		}

		return rest_ensure_response( self::public_result( $result ) );
	}

	/**
	 * @param array $result PP_Access_Control::validate_and_log()'s return value.
	 */
	private static function public_result( $result ) {
		return array(
			'allowed'     => ! empty( $result['allowed'] ),
			'reason'      => isset( $result['reason'] ) ? $result['reason'] : '',
			'plan_name'   => isset( $result['plan_name'] ) ? $result['plan_name'] : '',
			'member_name' => isset( $result['member_name'] ) ? $result['member_name'] : '',
		);
	}
}
