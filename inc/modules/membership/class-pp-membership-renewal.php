<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manual renewal: extends expiry_date by the plan's duration, from whichever
 * is later (today, or the current expiry if it hasn't lapsed yet). Auto-renew
 * via a payment gateway is a Billing-module (Phase 2) concern.
 */
class PP_Membership_Renewal {

	/**
	 * @return object|WP_Error
	 */
	public static function renew( $id ) {
		$membership = PP_Membership::get( $id );
		if ( ! $membership ) {
			return new WP_Error( 'pp_not_found', __( 'Membership not found.', 'passpress' ) );
		}

		$base_date  = strtotime( $membership->expiry_date ) > strtotime( current_time( 'Y-m-d' ) ) ? $membership->expiry_date : current_time( 'Y-m-d' );
		$new_expiry = PP_Membership::calculate_expiry( $membership->plan_id, $base_date );

		global $wpdb;
		$updated = $wpdb->update(
			PP_Membership::table(),
			array(
				'expiry_date' => $new_expiry,
				'status'      => PP_Membership::STATUS_ACTIVE,
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'pp_db_error', __( 'Could not renew membership.', 'passpress' ) );
		}

		PP_Activity_Logger::log( 'membership_renewed', 'membership', $id, sprintf( 'Renewed manually. New expiry: %s.', $new_expiry ) );

		return PP_Membership::get( $id );
	}
}
