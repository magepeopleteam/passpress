<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/attendance — direct REST port of
 * admin/PP_Attendance_Reports_Page.php.
 */
class PP_REST_Attendance extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/attendance',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_attendance' ),
				'permission_callback' => array( __CLASS__, 'permission_manage_or_classes' ),
			)
		);
	}

	public function get_attendance( $request ) {
		$today      = current_time( 'Y-m-d' );
		$week_ago   = gmdate( 'Y-m-d', strtotime( $today . ' -6 days' ) );
		$month_ago  = gmdate( 'Y-m-d', strtotime( $today . ' -29 days' ) );
		$this_year  = (int) current_time( 'Y' );
		$this_month = (int) current_time( 'n' );

		$today_count   = PP_Attendance::get_daily_count( $today );
		$week_counts   = PP_Attendance::get_range_counts( $week_ago, $today );
		$month_total   = PP_Attendance::get_monthly_total( $this_year, $this_month );
		$peak_hours    = PP_Attendance::get_peak_hours( $month_ago, $today );
		$late_arrivals = PP_Attendance::get_late_class_arrivals( $month_ago, $today );

		$week_total = array_sum( $week_counts );
		$week_days  = array();
		foreach ( $week_counts as $day => $count ) {
			$week_days[] = array( 'date' => $day, 'date_label' => pp_format_date( $day ), 'count' => $count, 'is_today' => ( $day === $today ) );
		}

		$peak_rows = array();
		foreach ( $peak_hours as $hour => $count ) {
			if ( ! $count ) {
				continue;
			}
			$peak_rows[] = array( 'hour' => $hour, 'label' => sprintf( '%02d:00–%02d:00', $hour, ( $hour + 1 ) % 24 ), 'count' => $count );
		}

		$late_rows = array();
		foreach ( $late_arrivals as $row ) {
			$late_rows[] = array(
				'date_label'    => pp_format_date( $row->booking_date ),
				'class_title'   => $row->class_title,
				'member_name'   => $row->member_name,
				'scheduled'     => substr( $row->start_time, 0, 5 ),
				'late_minutes'  => (int) $row->late_minutes,
			);
		}

		return rest_ensure_response(
			array(
				'today_count'   => $today_count,
				'week_total'    => $week_total,
				'month_total'   => $month_total,
				'week_days'     => $week_days,
				'peak_hours'    => $peak_rows,
				'late_arrivals' => $late_rows,
			)
		);
	}
}
