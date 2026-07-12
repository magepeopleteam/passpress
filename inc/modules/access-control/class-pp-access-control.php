<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single validate-and-log entrypoint used by both the QR scanner and PIN
 * entry, so scan gate hardware/PIN pad share one source of truth for what
 * counts as a valid entry/exit.
 */
class PP_Access_Control {

	/**
	 * @param object|null $membership Row from pp_memberships, or null if the
	 *                                code/PIN didn't resolve to one.
	 * @return array{allowed: bool, reason?: string, membership?: object, plan_name?: string, member_name?: string}
	 */
	public static function validate_and_log( $membership, $method, $facility_id = 0, $direction = 'entry' ) {
		$facility_id = absint( $facility_id );
		$direction   = 'exit' === $direction ? 'exit' : 'entry';
		$operator_id = get_current_user_id();

		if ( ! $membership ) {
			$reason = __( 'No membership found for this code.', 'passpress' );
			self::log_result( 0, $facility_id, $direction, $method, 'denied', $reason, $operator_id );
			return array(
				'allowed' => false,
				'reason'  => $reason,
			);
		}

		$membership = PP_Membership_Status::maybe_expire( $membership );

		if ( PP_Membership::STATUS_ACTIVE !== $membership->status ) {
			$labels = array(
				PP_Membership::STATUS_FROZEN    => __( 'This membership is currently frozen.', 'passpress' ),
				PP_Membership::STATUS_SUSPENDED => __( 'This membership is currently suspended.', 'passpress' ),
				PP_Membership::STATUS_EXPIRED   => __( 'This membership has expired.', 'passpress' ),
				PP_Membership::STATUS_CANCELLED => __( 'This membership has been cancelled.', 'passpress' ),
			);
			$reason = isset( $labels[ $membership->status ] ) ? $labels[ $membership->status ] : __( 'This membership is not active.', 'passpress' );

			self::log_result( $membership->id, $facility_id, $direction, $method, 'denied', $reason, $operator_id );
			return array(
				'allowed'    => false,
				'reason'     => $reason,
				'membership' => $membership,
			);
		}

		if ( 'entry' === $direction ) {
			$restriction_check = PP_Entry_Restrictions::check( $membership, $membership->plan_id );
			if ( empty( $restriction_check['allowed'] ) ) {
				self::log_result( $membership->id, $facility_id, $direction, $method, 'denied', $restriction_check['reason'], $operator_id );
				return array(
					'allowed'    => false,
					'reason'     => $restriction_check['reason'],
					'membership' => $membership,
				);
			}
		}

		self::log_result( $membership->id, $facility_id, $direction, $method, 'allowed', '', $operator_id );

		$plan = get_post( $membership->plan_id );

		return array(
			'allowed'     => true,
			'membership'  => $membership,
			'plan_name'   => $plan ? $plan->post_title : '',
			'member_name' => self::member_display_name( $membership->user_id ),
			'reason'      => 'exit' === $direction ? __( 'Exit recorded.', 'passpress' ) : __( 'Access granted.', 'passpress' ),
		);
	}

	private static function member_display_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown member', 'passpress' );
	}

	private static function log_result( $membership_id, $facility_id, $direction, $method, $result, $reason, $operator_id ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'pp_access_logs',
			array(
				'membership_id' => absint( $membership_id ),
				'facility_id'   => absint( $facility_id ),
				'direction'     => sanitize_key( $direction ),
				'method'        => sanitize_key( $method ),
				'result'        => sanitize_key( $result ),
				'reason'        => sanitize_text_field( $reason ),
				'operator_id'   => absint( $operator_id ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		PP_Activity_Logger::log(
			'denied' === $result ? 'access_denied' : 'access_allowed',
			'membership',
			$membership_id,
			sprintf( '%s via %s at facility #%d: %s', ucfirst( $direction ), strtoupper( $method ), $facility_id, $reason ? $reason : 'OK' ),
			$operator_id
		);
	}
}
