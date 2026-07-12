<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Status transitions: freeze / suspend / reactivate / cancel, plus the
 * lazy auto-expire check run whenever a membership is read for scanning
 * or listing (no WP-Cron dependency for Phase 1).
 */
class PP_Membership_Status {

	public static function freeze( $id, $reason = '' ) {
		return PP_Membership::update_status( $id, PP_Membership::STATUS_FROZEN, $reason ? $reason : 'Frozen by staff.' );
	}

	public static function suspend( $id, $reason = '' ) {
		return PP_Membership::update_status( $id, PP_Membership::STATUS_SUSPENDED, $reason ? $reason : 'Suspended by staff.' );
	}

	public static function reactivate( $id ) {
		$membership = PP_Membership::get( $id );
		if ( ! $membership ) {
			return false;
		}

		if ( strtotime( $membership->expiry_date ) < strtotime( current_time( 'Y-m-d' ) ) ) {
			return PP_Membership::update_status( $id, PP_Membership::STATUS_EXPIRED, 'Reactivation blocked: membership already expired, renew first.' );
		}

		return PP_Membership::update_status( $id, PP_Membership::STATUS_ACTIVE, 'Reactivated by staff.' );
	}

	public static function cancel( $id, $reason = '' ) {
		return PP_Membership::update_status( $id, PP_Membership::STATUS_CANCELLED, $reason ? $reason : 'Cancelled by staff.' );
	}

	/**
	 * Flips an active-but-past-expiry membership to "expired" the moment it's
	 * looked up, so status is always accurate without needing a cron job.
	 */
	public static function maybe_expire( $membership ) {
		if ( ! $membership ) {
			return $membership;
		}

		if ( PP_Membership::STATUS_ACTIVE === $membership->status
			&& strtotime( $membership->expiry_date ) < strtotime( current_time( 'Y-m-d' ) ) ) {
			PP_Membership::update_status( $membership->id, PP_Membership::STATUS_EXPIRED, 'Auto-expired: expiry date passed.' );
			$membership->status = PP_Membership::STATUS_EXPIRED;
		}

		return $membership;
	}
}
