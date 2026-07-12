<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Combines PP_Booking_Slots (what slots exist) with PP_Booking (what's
 * already booked) into the availability list the frontend calendar and its
 * AJAX handler both use.
 */
class PP_Booking_Calendar {

	public static function get_availability( $facility_id, $date ) {
		$slots    = PP_Booking_Slots::generate_for_date( $facility_id, $date );
		$capacity = (int) get_post_meta( $facility_id, '_pp_capacity', true );
		$capacity = $capacity > 0 ? $capacity : 1;

		$booked_counts = PP_Booking::get_booked_counts( $facility_id, $date );

		$availability = array();
		foreach ( $slots as $slot ) {
			$key    = $slot['start'] . '-' . $slot['end'];
			$booked = isset( $booked_counts[ $key ] ) ? $booked_counts[ $key ] : 0;

			$availability[] = array(
				'start'     => $slot['start'],
				'end'       => $slot['end'],
				'capacity'  => $capacity,
				'booked'    => $booked,
				'available' => max( 0, $capacity - $booked ),
				'full'      => $booked >= $capacity,
			);
		}

		return $availability;
	}
}
