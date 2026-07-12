<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only reporting queries over the existing tables — no new data
 * storage, same philosophy as PP_Attendance. Peak Hours specifically is
 * NOT duplicated here — the Attendance admin page already covers it via
 * PP_Attendance::get_peak_hours(); Reports links to it instead.
 */
class PP_Reports {

	private static function billing_table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_billing_history';
	}

	private static function memberships_table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_memberships';
	}

	private static function bookings_table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_bookings';
	}

	private static function access_logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_access_logs';
	}

	/** 1. Revenue: total paid amount + daily breakdown. */
	public static function get_revenue( $start_date, $end_date ) {
		global $wpdb;
		$table = self::billing_table();

		$total = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE status = 'paid' AND DATE(created_at) BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as day, SUM(amount) as total FROM {$table} WHERE status = 'paid' AND DATE(created_at) BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY day ASC",
				$start_date,
				$end_date
			)
		);

		$by_day = array();
		foreach ( $rows as $row ) {
			$by_day[ $row->day ] = (float) $row->total;
		}

		return array( 'total' => $total, 'by_day' => $by_day );
	}

	/** 2. Membership Growth: new (non-visitor) memberships issued per day. */
	public static function get_membership_growth( $start_date, $end_date ) {
		global $wpdb;
		$table = self::memberships_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as day, COUNT(*) as cnt FROM {$table} WHERE member_type = 'member' AND DATE(created_at) BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY day ASC",
				$start_date,
				$end_date
			)
		);

		$by_day = array();
		$total  = 0;
		foreach ( $rows as $row ) {
			$by_day[ $row->day ] = (int) $row->cnt;
			$total              += (int) $row->cnt;
		}

		return array( 'total' => $total, 'by_day' => $by_day );
	}

	/** 3. Expired Members: memberships currently sitting in 'expired' status. */
	public static function get_expired_members( $limit = 50 ) {
		global $wpdb;
		$table = self::memberships_table();

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'expired' AND member_type = 'member' ORDER BY expiry_date DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * 4. Renewal Rate: of memberships whose plan-duration cycle ended in the
	 * window (i.e. they either renewed or lapsed), what fraction renewed.
	 * A "renewal" is any pp_billing_history row with type='renewal' and
	 * status='paid' in the window; the denominator adds memberships that
	 * expired in the window WITHOUT a matching renewal.
	 */
	public static function get_renewal_rate( $start_date, $end_date ) {
		global $wpdb;
		$billing     = self::billing_table();
		$memberships = self::memberships_table();

		$renewed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT membership_id) FROM {$billing} WHERE type = 'renewal' AND status = 'paid' AND DATE(created_at) BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$expired_no_renewal = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$memberships} m
				WHERE m.status = 'expired' AND m.member_type = 'member'
				AND m.expiry_date BETWEEN %s AND %s
				AND NOT EXISTS (
					SELECT 1 FROM {$billing} b
					WHERE b.membership_id = m.id AND b.type = 'renewal' AND b.status = 'paid'
					AND b.created_at >= m.expiry_date
				)",
				$start_date,
				$end_date
			)
		);

		$denominator = $renewed + $expired_no_renewal;

		return array(
			'renewed'      => $renewed,
			'lapsed'       => $expired_no_renewal,
			'rate_percent' => $denominator > 0 ? round( ( $renewed / $denominator ) * 100, 1 ) : null,
		);
	}

	/** 6. Facility Usage: bookings + access-log entries per facility. */
	public static function get_facility_usage( $start_date, $end_date ) {
		global $wpdb;
		$bookings = self::bookings_table();
		$logs     = self::access_logs_table();

		$booking_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT facility_id, COUNT(*) as cnt FROM {$bookings} WHERE booking_date BETWEEN %s AND %s AND facility_id != 0 GROUP BY facility_id",
				$start_date,
				$end_date
			)
		);
		$entry_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT facility_id, COUNT(*) as cnt FROM {$logs} WHERE direction = 'entry' AND result = 'allowed' AND facility_id != 0 AND DATE(created_at) BETWEEN %s AND %s GROUP BY facility_id",
				$start_date,
				$end_date
			)
		);

		$usage = array();
		foreach ( $booking_rows as $row ) {
			$usage[ $row->facility_id ]['bookings'] = (int) $row->cnt;
		}
		foreach ( $entry_rows as $row ) {
			$usage[ $row->facility_id ]['entries'] = (int) $row->cnt;
		}

		$result = array();
		foreach ( $usage as $facility_id => $counts ) {
			$result[] = array(
				'facility_id' => $facility_id,
				'name'        => get_the_title( $facility_id ),
				'bookings'    => isset( $counts['bookings'] ) ? $counts['bookings'] : 0,
				'entries'     => isset( $counts['entries'] ) ? $counts['entries'] : 0,
			);
		}

		usort( $result, function ( $a, $b ) {
			return ( $b['bookings'] + $b['entries'] ) <=> ( $a['bookings'] + $a['entries'] );
		} );

		return $result;
	}

	/** 7. Popular Plans: active membership count per plan. */
	public static function get_popular_plans() {
		global $wpdb;
		$table = self::memberships_table();

		$rows = $wpdb->get_results(
			"SELECT plan_id, COUNT(*) as cnt FROM {$table} WHERE member_type = 'member' GROUP BY plan_id ORDER BY cnt DESC"
		);

		$result = array();
		foreach ( $rows as $row ) {
			$result[] = array(
				'plan_id' => $row->plan_id,
				'name'    => get_the_title( $row->plan_id ),
				'count'   => (int) $row->cnt,
			);
		}

		return $result;
	}

	/** 8. Payment Reports: paid/failed/refunded/cancelled counts + totals, by gateway. */
	public static function get_payment_report( $start_date, $end_date ) {
		global $wpdb;
		$table = self::billing_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT gateway, status, COUNT(*) as cnt, SUM(amount) as total FROM {$table} WHERE DATE(created_at) BETWEEN %s AND %s GROUP BY gateway, status",
				$start_date,
				$end_date
			)
		);

		$by_gateway = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $by_gateway[ $row->gateway ] ) ) {
				$by_gateway[ $row->gateway ] = array();
			}
			$by_gateway[ $row->gateway ][ $row->status ] = array(
				'count' => (int) $row->cnt,
				'total' => (float) $row->total,
			);
		}

		return $by_gateway;
	}

	/**
	 * 9. Trainer Performance: per-instructor booking/attendance counts across
	 * their class sessions in the window.
	 */
	public static function get_trainer_performance( $start_date, $end_date ) {
		global $wpdb;
		$bookings = self::bookings_table();

		$classes = get_posts(
			array(
				'post_type'      => 'pp_class_session',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$by_instructor = array();
		foreach ( $classes as $class ) {
			$instructor_id = (int) get_post_meta( $class->ID, '_pp_instructor_id', true );
			if ( ! $instructor_id ) {
				continue;
			}

			$counts = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) as total_bookings,
						SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as attended,
						SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_shows
					FROM {$bookings} WHERE class_session_id = %d AND booking_date BETWEEN %s AND %s",
					$class->ID,
					$start_date,
					$end_date
				)
			);

			if ( ! isset( $by_instructor[ $instructor_id ] ) ) {
				$user                             = get_userdata( $instructor_id );
				$by_instructor[ $instructor_id ] = array(
					'name'           => $user ? $user->display_name : __( 'Unknown', 'passpress' ),
					'classes'        => 0,
					'total_bookings' => 0,
					'attended'       => 0,
					'no_shows'       => 0,
				);
			}

			$by_instructor[ $instructor_id ]['classes']++;
			$by_instructor[ $instructor_id ]['total_bookings'] += (int) $counts->total_bookings;
			$by_instructor[ $instructor_id ]['attended']       += (int) $counts->attended;
			$by_instructor[ $instructor_id ]['no_shows']       += (int) $counts->no_shows;
		}

		return $by_instructor;
	}
}
