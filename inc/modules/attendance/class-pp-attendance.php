<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attendance is entirely derived/aggregated from pp_access_logs (and, for
 * class late-arrivals, pp_bookings) — no independent data entry, per
 * CLAUDE.md's module-map note. This class is read-only queries; the actual
 * check-in events are recorded by PP_Access_Control (general entries) and by
 * staff clicking "Complete" on a class booking (PP_Booking::set_status()).
 *
 * "Early Exit" is deliberately not implemented — there is no dedicated
 * checkout step for classes (only Complete/No-show), so there's no reliable
 * signal for when someone actually left early. See CLAUDE.md Phase 3 notes.
 */
class PP_Attendance {

	private static function access_logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_access_logs';
	}

	public static function get_daily_count( $date ) {
		global $wpdb;
		$table = self::access_logs_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE direction = 'entry' AND result = 'allowed' AND DATE(created_at) = %s",
				$date
			)
		);
	}

	/**
	 * @return array 'Y-m-d' => count, one entry per day in the range (inclusive).
	 */
	public static function get_range_counts( $start_date, $end_date ) {
		global $wpdb;
		$table = self::access_logs_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as day, COUNT(*) as cnt FROM {$table} WHERE direction = 'entry' AND result = 'allowed' AND DATE(created_at) BETWEEN %s AND %s GROUP BY DATE(created_at)",
				$start_date,
				$end_date
			)
		);

		$by_day = array();
		foreach ( $rows as $row ) {
			$by_day[ $row->day ] = (int) $row->cnt;
		}

		$counts = array();
		$cursor = strtotime( $start_date );
		$end_ts = strtotime( $end_date );
		while ( $cursor <= $end_ts ) {
			$day             = gmdate( 'Y-m-d', $cursor );
			$counts[ $day ]  = isset( $by_day[ $day ] ) ? $by_day[ $day ] : 0;
			$cursor         += DAY_IN_SECONDS;
		}

		return $counts;
	}

	public static function get_monthly_total( $year, $month ) {
		global $wpdb;
		$table = self::access_logs_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE direction = 'entry' AND result = 'allowed' AND YEAR(created_at) = %d AND MONTH(created_at) = %d",
				$year,
				$month
			)
		);
	}

	/**
	 * @return array 0-23 => count, for entries within the date range.
	 */
	public static function get_peak_hours( $start_date, $end_date ) {
		global $wpdb;
		$table = self::access_logs_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT HOUR(created_at) as hr, COUNT(*) as cnt FROM {$table} WHERE direction = 'entry' AND result = 'allowed' AND DATE(created_at) BETWEEN %s AND %s GROUP BY HOUR(created_at)",
				$start_date,
				$end_date
			)
		);

		$by_hour = array_fill( 0, 24, 0 );
		foreach ( $rows as $row ) {
			$by_hour[ (int) $row->hr ] = (int) $row->cnt;
		}

		return $by_hour;
	}

	/**
	 * Class bookings marked "completed" (attended) where the timestamp
	 * captured at that moment (checked_in_at) is later than the class's
	 * scheduled start time — i.e., staff marked them present after the
	 * class had already started.
	 *
	 * @return object[] Rows augmented with `late_minutes`, `class_title`, `member_name`.
	 */
	public static function get_late_class_arrivals( $start_date, $end_date ) {
		global $wpdb;
		$bookings = $wpdb->prefix . 'pp_bookings';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$bookings}
				WHERE class_session_id != 0
				AND status = 'completed'
				AND checked_in_at IS NOT NULL
				AND booking_date BETWEEN %s AND %s
				AND TIME(checked_in_at) > start_time
				ORDER BY booking_date DESC, checked_in_at DESC",
				$start_date,
				$end_date
			)
		);

		foreach ( $rows as $row ) {
			$scheduled          = strtotime( $row->booking_date . ' ' . $row->start_time );
			$actual             = strtotime( $row->checked_in_at );
			$row->late_minutes  = max( 0, (int) round( ( $actual - $scheduled ) / 60 ) );
			$row->class_title   = get_the_title( $row->class_session_id );
			$user               = get_userdata( $row->user_id );
			$row->member_name   = $user ? $user->display_name : __( 'Unknown', 'passpress' );
		}

		return $rows;
	}
}
