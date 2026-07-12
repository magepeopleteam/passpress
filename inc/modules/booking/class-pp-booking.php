<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for the pp_bookings table. A custom table, not a CPT — consistent
 * with the Phase 1 decision to keep high-volume/relational transactional
 * data (memberships, access logs) out of postmeta; see CLAUDE.md.
 */
class PP_Booking {

	const STATUS_CONFIRMED = 'confirmed';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_COMPLETED = 'completed';
	const STATUS_NO_SHOW    = 'no_show';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_bookings';
	}

	/**
	 * @return array 'H:i-H:i' => confirmed booking count, for one facility/date.
	 */
	public static function get_booked_counts( $facility_id, $date ) {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_time, end_time, COUNT(*) as cnt FROM {$table} WHERE facility_id = %d AND booking_date = %s AND status = %s GROUP BY start_time, end_time",
				absint( $facility_id ),
				$date,
				self::STATUS_CONFIRMED
			)
		);

		$counts = array();
		foreach ( $rows as $row ) {
			$key            = substr( $row->start_time, 0, 5 ) . '-' . substr( $row->end_time, 0, 5 );
			$counts[ $key ] = (int) $row->cnt;
		}

		return $counts;
	}

	/**
	 * Class-session capacity is keyed by class_session_id + date (the time
	 * is fixed/implied by the class itself), NOT by facility_id + time like
	 * PP_Booking::get_booked_counts() — two different classes can share a
	 * facility and time slot without incorrectly sharing capacity.
	 */
	public static function get_class_booked_count( $class_session_id, $date ) {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE class_session_id = %d AND booking_date = %s AND status = %s",
				absint( $class_session_id ),
				$date,
				self::STATUS_CONFIRMED
			)
		);
	}

	/**
	 * @return object|WP_Error
	 */
	public static function create_for_class( $class_session_id, $user_id, $date, $membership_id = 0 ) {
		global $wpdb;

		$class_session_id = absint( $class_session_id );
		if ( 'pp_class_session' !== get_post_type( $class_session_id ) ) {
			return new WP_Error( 'pp_invalid_class', __( 'Class session not found.', 'passpress' ) );
		}

		$capacity = (int) get_post_meta( $class_session_id, '_pp_capacity', true );
		$capacity = $capacity > 0 ? $capacity : 1;

		if ( self::get_class_booked_count( $class_session_id, $date ) >= $capacity ) {
			return new WP_Error( 'pp_slot_full', __( 'This class is fully booked.', 'passpress' ) );
		}

		$facility_id = (int) get_post_meta( $class_session_id, '_pp_facility_id', true );
		$start       = get_post_meta( $class_session_id, '_pp_start_time', true ) ?: '00:00';
		$end         = get_post_meta( $class_session_id, '_pp_end_time', true ) ?: '00:00';

		$wpdb->insert(
			self::table(),
			array(
				'facility_id'       => $facility_id,
				'user_id'           => absint( $user_id ),
				'membership_id'     => absint( $membership_id ),
				'class_session_id'  => $class_session_id,
				'booking_date'      => $date,
				'start_time'        => $start . ':00',
				'end_time'          => $end . ':00',
				'status'            => self::STATUS_CONFIRMED,
				'created_at'        => current_time( 'mysql' ),
				'updated_at'        => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$id = (int) $wpdb->insert_id;

		PP_Activity_Logger::log( 'class_booking_created', 'booking', $id, sprintf( 'Booked class #%d on %s.', $class_session_id, $date ) );

		return self::get( $id );
	}

	/**
	 * @return object|WP_Error
	 */
	public static function create( $facility_id, $user_id, $date, $start, $end, $membership_id = 0 ) {
		global $wpdb;

		$facility_id = absint( $facility_id );
		$capacity    = (int) get_post_meta( $facility_id, '_pp_capacity', true );
		$capacity    = $capacity > 0 ? $capacity : 1;

		$counts = self::get_booked_counts( $facility_id, $date );
		$key    = $start . '-' . $end;
		$booked = isset( $counts[ $key ] ) ? $counts[ $key ] : 0;

		if ( $booked >= $capacity ) {
			return new WP_Error( 'pp_slot_full', __( 'This time slot is fully booked.', 'passpress' ) );
		}

		$wpdb->insert(
			self::table(),
			array(
				'facility_id'   => $facility_id,
				'user_id'       => absint( $user_id ),
				'membership_id' => absint( $membership_id ),
				'booking_date'  => $date,
				'start_time'    => $start . ':00',
				'end_time'      => $end . ':00',
				'status'        => self::STATUS_CONFIRMED,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$id = (int) $wpdb->insert_id;

		PP_Activity_Logger::log( 'booking_created', 'booking', $id, sprintf( 'Booking created for facility #%d on %s %s-%s.', $facility_id, $date, $start, $end ) );

		return self::get( $id );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ) );
	}

	public static function get_for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY booking_date DESC, start_time DESC', absint( $user_id ) ) );
	}

	/**
	 * @param array $args status, facility_id, class_session_id, per_page, paged
	 */
	public static function get_list( $args = array() ) {
		global $wpdb;
		$table    = self::table();
		$defaults = array(
			'status'           => '',
			'facility_id'      => 0,
			'class_session_id' => 0,
			'per_page'         => 20,
			'paged'            => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['facility_id'] ) {
			$where[]  = 'facility_id = %d';
			$params[] = absint( $args['facility_id'] );
		}
		if ( $args['class_session_id'] ) {
			$where[]  = 'class_session_id = %d';
			$params[] = absint( $args['class_session_id'] );
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['paged'] ) - 1 ) * $per_page;

		$list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY booking_date DESC, start_time DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$items       = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		return array(
			'items'    => $items,
			'total'    => $total,
			'per_page' => $per_page,
		);
	}

	/**
	 * @param int $by_user_id 0 to bypass the cancellation lead-time check (admin/staff override).
	 * @return true|WP_Error
	 */
	public static function cancel( $id, $by_user_id = 0 ) {
		$booking = self::get( $id );
		if ( ! $booking ) {
			return new WP_Error( 'pp_not_found', __( 'Booking not found.', 'passpress' ) );
		}

		if ( $by_user_id ) {
			$lead_hours = (int) get_post_meta( $booking->facility_id, '_pp_cancellation_lead_hours', true );
			$start_ts   = strtotime( $booking->booking_date . ' ' . $booking->start_time );
			if ( $lead_hours > 0 && ( $start_ts - time() ) < ( $lead_hours * HOUR_IN_SECONDS ) ) {
				/* translators: %d: hours notice required */
				return new WP_Error( 'pp_lead_time', sprintf( __( 'Cancellations require at least %d hours notice.', 'passpress' ), $lead_hours ) );
			}
		}

		self::set_status( $id, self::STATUS_CANCELLED );

		if ( $booking->class_session_id ) {
			PP_Booking_Waitlist::maybe_promote_class( $booking->class_session_id, $booking->booking_date );
		} else {
			PP_Booking_Waitlist::maybe_promote( $booking->facility_id, $booking->booking_date, substr( $booking->start_time, 0, 5 ), substr( $booking->end_time, 0, 5 ) );
		}

		return true;
	}

	/**
	 * Transitioning to STATUS_COMPLETED also captures `checked_in_at` — the
	 * exact moment staff marked the member present. For class bookings this
	 * is the only attendance signal that exists (see PP_Attendance's
	 * docblock on why "Early Exit" isn't tracked); for plain facility
	 * bookings the column is simply unused.
	 */
	public static function set_status( $id, $status ) {
		global $wpdb;

		$data   = array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) );
		$format = array( '%s', '%s' );

		if ( self::STATUS_COMPLETED === $status ) {
			$data['checked_in_at'] = current_time( 'mysql' );
			$format[]              = '%s';
		}

		$updated = $wpdb->update(
			self::table(),
			$data,
			array( 'id' => absint( $id ) ),
			$format,
			array( '%d' )
		);

		if ( false !== $updated ) {
			PP_Activity_Logger::log( 'booking_status_changed', 'booking', $id, sprintf( 'Status changed to %s.', $status ) );
		}

		return false !== $updated;
	}
}
