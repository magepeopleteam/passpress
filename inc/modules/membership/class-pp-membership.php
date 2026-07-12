<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for the actual member <-> plan record, stored in the custom
 * pp_memberships table (not postmeta — this is per-member transactional
 * data, not content).
 */
class PP_Membership {

	const STATUS_ACTIVE    = 'active';
	const STATUS_FROZEN    = 'frozen';
	const STATUS_SUSPENDED = 'suspended';
	const STATUS_EXPIRED   = 'expired';
	const STATUS_CANCELLED = 'cancelled';

	const TYPE_MEMBER  = 'member';
	const TYPE_VISITOR = 'visitor';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_memberships';
	}

	/**
	 * Issues a new membership. Phase 1 has no payment gateway, so this is the
	 * admin/front-desk "sign this member up" action — see CLAUDE.md phase notes.
	 *
	 * @return object|WP_Error
	 */
	public static function issue( $user_id, $plan_id, $start_date = '', $member_type = self::TYPE_MEMBER ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$plan_id = absint( $plan_id );

		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return new WP_Error( 'pp_invalid_user', __( 'Please choose a valid member.', 'passpress' ) );
		}
		if ( ! $plan_id || 'pp_membership_plan' !== get_post_type( $plan_id ) ) {
			return new WP_Error( 'pp_invalid_plan', __( 'Please choose a valid membership plan.', 'passpress' ) );
		}

		$start  = $start_date ? $start_date : current_time( 'Y-m-d' );
		$expiry = self::calculate_expiry( $plan_id, $start );

		$data = array(
			'user_id'           => $user_id,
			'plan_id'           => $plan_id,
			'membership_number' => pp_generate_membership_number(),
			'pass_token'        => pp_generate_pass_token(),
			'pin_code'          => pp_generate_pin(),
			'status'            => self::STATUS_ACTIVE,
			'member_type'       => self::TYPE_VISITOR === $member_type ? self::TYPE_VISITOR : self::TYPE_MEMBER,
			'start_date'        => $start,
			'expiry_date'       => $expiry,
			'auto_renew'        => 0,
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		);

		$inserted = $wpdb->insert(
			self::table(),
			$data,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'pp_db_error', __( 'Could not create the membership record.', 'passpress' ) );
		}

		$id = (int) $wpdb->insert_id;

		PP_Activity_Logger::log(
			'membership_issued',
			'membership',
			$id,
			sprintf( 'Membership %s issued to user #%d on plan #%d.', $data['membership_number'], $user_id, $plan_id )
		);

		$membership = self::get( $id );
		PP_Notifications::welcome( $membership );

		return $membership;
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ) );
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		if ( ! $token ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE pass_token = %s', $token ) );
	}

	public static function get_by_number( $number ) {
		global $wpdb;
		if ( ! $number ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE membership_number = %s', $number ) );
	}

	public static function get_active_for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id DESC', absint( $user_id ) ) );
	}

	/**
	 * Computes an expiry date from a plan's duration meta. Plans with no
	 * duration (or an explicit "lifetime" unit) never expire in practice.
	 */
	public static function calculate_expiry( $plan_id, $start_date ) {
		$duration_value = (int) get_post_meta( $plan_id, '_pp_duration_value', true );
		$duration_unit  = get_post_meta( $plan_id, '_pp_duration_unit', true );

		if ( 'lifetime' === $duration_unit || ! $duration_value ) {
			return '2099-12-31';
		}

		$unit_map = array(
			'day'   => 'days',
			'week'  => 'weeks',
			'month' => 'months',
			'year'  => 'years',
		);
		$unit = isset( $unit_map[ $duration_unit ] ) ? $unit_map[ $duration_unit ] : 'months';

		return gmdate( 'Y-m-d', strtotime( "{$start_date} +{$duration_value} {$unit}" ) );
	}

	public static function update_status( $id, $status, $reason = '' ) {
		global $wpdb;

		$updated = $wpdb->update(
			self::table(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			PP_Activity_Logger::log( 'membership_status_changed', 'membership', $id, sprintf( 'Status changed to %s. %s', $status, $reason ) );
		}

		return false !== $updated;
	}
}
