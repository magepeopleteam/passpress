<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes the upcoming bookable occurrences of a weekly-recurring class
 * (the CPT only stores one fixed day/time; this generates the actual future
 * dates). Booking/capacity itself is owned by PP_Booking's class-aware
 * methods (get_class_booked_count/create_for_class) — this class is the
 * orchestration layer between the CPT and the booking table.
 */
class PP_Class_Session {

	const OCCURRENCES_TO_SHOW = 8;

	public static function get_all() {
		return get_posts(
			array(
				'post_type'      => 'pp_class_session',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * @return array[] Each: ['date', 'capacity', 'booked', 'available', 'full'].
	 */
	public static function get_upcoming_occurrences( $class_session_id, $count = self::OCCURRENCES_TO_SHOW ) {
		$class_session_id = absint( $class_session_id );
		$day_of_week       = (int) get_post_meta( $class_session_id, '_pp_day_of_week', true );
		$day_of_week       = $day_of_week ? $day_of_week : 1;
		$capacity          = (int) get_post_meta( $class_session_id, '_pp_capacity', true );
		$capacity          = $capacity > 0 ? $capacity : 1;

		$cursor_ts = strtotime( current_time( 'Y-m-d' ) );
		while ( (int) gmdate( 'N', $cursor_ts ) !== $day_of_week ) {
			$cursor_ts += DAY_IN_SECONDS;
		}

		$occurrences = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$date   = gmdate( 'Y-m-d', $cursor_ts );
			$booked = PP_Booking::get_class_booked_count( $class_session_id, $date );

			$occurrences[] = array(
				'date'      => $date,
				'capacity'  => $capacity,
				'booked'    => $booked,
				'available' => max( 0, $capacity - $booked ),
				'full'      => $booked >= $capacity,
			);

			$cursor_ts += WEEK_IN_SECONDS;
		}

		return $occurrences;
	}

	/**
	 * @return object|WP_Error The pp_bookings row.
	 */
	public static function book( $class_session_id, $user_id, $date ) {
		return PP_Booking::create_for_class( $class_session_id, $user_id, $date );
	}
}
