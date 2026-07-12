<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Waitlist for fully-booked slots. Promotion emails the next person rather
 * than auto-booking them — they still need to actually complete a booking,
 * so availability/membership validity gets re-checked at that point instead
 * of trusting stale state from when they joined the waitlist.
 */
class PP_Booking_Waitlist {

	const STATUS_WAITING  = 'waiting';
	const STATUS_NOTIFIED = 'notified';
	const STATUS_EXPIRED  = 'expired';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_booking_waitlist';
	}

	public static function join( $facility_id, $user_id, $date, $start, $end ) {
		global $wpdb;

		$wpdb->insert(
			self::table(),
			array(
				'facility_id'  => absint( $facility_id ),
				'user_id'      => absint( $user_id ),
				'booking_date' => $date,
				'start_time'   => $start . ':00',
				'end_time'     => $end . ':00',
				'status'       => self::STATUS_WAITING,
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		$id = (int) $wpdb->insert_id;

		PP_Activity_Logger::log( 'waitlist_joined', 'waitlist', $id, sprintf( 'User #%d joined the waitlist for facility #%d on %s %s-%s.', $user_id, $facility_id, $date, $start, $end ) );

		return $id;
	}

	public static function join_class( $class_session_id, $user_id, $date ) {
		global $wpdb;

		$wpdb->insert(
			self::table(),
			array(
				'facility_id'      => (int) get_post_meta( $class_session_id, '_pp_facility_id', true ),
				'user_id'          => absint( $user_id ),
				'class_session_id' => absint( $class_session_id ),
				'booking_date'     => $date,
				'start_time'       => ( get_post_meta( $class_session_id, '_pp_start_time', true ) ?: '00:00' ) . ':00',
				'end_time'         => ( get_post_meta( $class_session_id, '_pp_end_time', true ) ?: '00:00' ) . ':00',
				'status'           => self::STATUS_WAITING,
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		$id = (int) $wpdb->insert_id;

		PP_Activity_Logger::log( 'waitlist_joined', 'waitlist', $id, sprintf( 'User #%d joined the waitlist for class #%d on %s.', $user_id, $class_session_id, $date ) );

		return $id;
	}

	public static function maybe_promote_class( $class_session_id, $date ) {
		global $wpdb;
		$table = self::table();

		$next = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE class_session_id = %d AND booking_date = %s AND status = %s ORDER BY created_at ASC LIMIT 1",
				absint( $class_session_id ),
				$date,
				self::STATUS_WAITING
			)
		);

		if ( ! $next ) {
			return;
		}

		$user = get_userdata( $next->user_id );
		if ( $user ) {
			PP_Notifications::waitlist_spot_opened( $user, get_the_title( $class_session_id ), pp_format_date( $date ) );
		}

		$wpdb->update( $table, array( 'status' => self::STATUS_NOTIFIED ), array( 'id' => $next->id ), array( '%s' ), array( '%d' ) );

		PP_Activity_Logger::log( 'waitlist_notified', 'waitlist', $next->id, 'Notified next person on class waitlist that a spot opened up.' );
	}

	public static function get_for_facility_date( $facility_id, $date ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE facility_id = %d AND booking_date = %s ORDER BY created_at ASC", absint( $facility_id ), $date )
		);
	}

	public static function maybe_promote( $facility_id, $date, $start, $end ) {
		global $wpdb;
		$table = self::table();

		$next = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE facility_id = %d AND booking_date = %s AND start_time = %s AND end_time = %s AND status = %s ORDER BY created_at ASC LIMIT 1",
				absint( $facility_id ),
				$date,
				$start . ':00',
				$end . ':00',
				self::STATUS_WAITING
			)
		);

		if ( ! $next ) {
			return;
		}

		$user = get_userdata( $next->user_id );
		if ( $user ) {
			PP_Notifications::waitlist_spot_opened( $user, get_the_title( $facility_id ), pp_format_date( $date ) . ', ' . $start . '-' . $end );
		}

		$wpdb->update( $table, array( 'status' => self::STATUS_NOTIFIED ), array( 'id' => $next->id ), array( '%s' ), array( '%d' ) );

		PP_Activity_Logger::log( 'waitlist_notified', 'waitlist', $next->id, 'Notified next person on waitlist that a spot opened up.' );
	}
}
