<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates candidate time slots for a facility on a given date from its
 * open/close hours, slot duration, buffer, and open days — pure calculation,
 * no DB reads of bookings (that's PP_Booking_Calendar's job).
 */
class PP_Booking_Slots {

	/**
	 * @return array[] List of ['start' => 'H:i', 'end' => 'H:i'].
	 */
	public static function generate_for_date( $facility_id, $date ) {
		$weekday   = (int) gmdate( 'N', strtotime( $date ) );
		$days_open = get_post_meta( $facility_id, '_pp_days_open', true );
		$days_open = is_array( $days_open ) && $days_open ? array_map( 'intval', $days_open ) : array( 1, 2, 3, 4, 5, 6, 7 );

		if ( ! in_array( $weekday, $days_open, true ) ) {
			return array();
		}

		$open_time     = get_post_meta( $facility_id, '_pp_open_time', true );
		$open_time     = $open_time ? $open_time : '09:00';
		$close_time    = get_post_meta( $facility_id, '_pp_close_time', true );
		$close_time    = $close_time ? $close_time : '21:00';
		$slot_duration = (int) get_post_meta( $facility_id, '_pp_slot_duration', true );
		$slot_duration = $slot_duration > 0 ? $slot_duration : 60;
		$buffer        = (int) get_post_meta( $facility_id, '_pp_buffer_minutes', true );

		$slots  = array();
		$cursor = strtotime( "{$date} {$open_time}" );
		$end    = strtotime( "{$date} {$close_time}" );

		while ( $cursor + ( $slot_duration * 60 ) <= $end ) {
			$slot_end = $cursor + ( $slot_duration * 60 );
			$slots[]  = array(
				'start' => gmdate( 'H:i', $cursor ),
				'end'   => gmdate( 'H:i', $slot_end ),
			);
			$cursor = $slot_end + ( $buffer * 60 );
		}

		return $slots;
	}
}
