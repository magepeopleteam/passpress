<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read queries against the custom tables, shared by the admin dashboard and
 * the memberships list screen.
 */
class PP_Query {

	public static function memberships_table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_memberships';
	}

	public static function access_logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'pp_access_logs';
	}

	public static function dashboard_stats() {
		global $wpdb;
		$m     = self::memberships_table();
		$l     = self::access_logs_table();
		$today = current_time( 'Y-m-d' );

		// Visitor passes (member_type = 'visitor') are excluded from these
		// "real member" stats — see the Phase 3 notes in CLAUDE.md. Today's
		// check-ins intentionally counts everyone (members and visitors) since
		// it's a general "how many people came through the door" stat.
		return array(
			'active_memberships' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$m} WHERE status = 'active' AND member_type = 'member'" ),
			'expiring_soon'      => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$m} WHERE status = 'active' AND member_type = 'member' AND expiry_date BETWEEN %s AND %s",
					$today,
					gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) )
				)
			),
			'frozen_suspended'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$m} WHERE status IN ('frozen','suspended') AND member_type = 'member'" ),
			'todays_checkins'    => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$l} WHERE direction = 'entry' AND result = 'allowed' AND DATE(created_at) = %s",
					$today
				)
			),
		);
	}

	/**
	 * Per-status counts for the Members page stat tiles. Same member_type
	 * exclusion of visitor passes as dashboard_stats() (see Phase 3 notes).
	 *
	 * @return array{active:int, frozen:int, suspended:int, expired:int, cancelled:int}
	 */
	public static function membership_status_counts( $member_type = 'member' ) {
		global $wpdb;
		$table  = self::memberships_table();
		$counts = array();

		foreach ( array( 'active', 'frozen', 'suspended', 'expired', 'cancelled' ) as $status ) {
			if ( 'all' === $member_type ) {
				$counts[ $status ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
			} else {
				$counts[ $status ] = (int) $wpdb->get_var(
					$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s AND member_type = %s", $status, 'visitor' === $member_type ? 'visitor' : 'member' )
				);
			}
		}

		return $counts;
	}

	/**
	 * Plan IDs whose plan_type meta is 'corporate' — used by get_memberships()'s
	 * plan_scope filter. Not cached: this list only matters for an admin-facing
	 * filter click, not a hot path.
	 */
	private static function corporate_plan_ids() {
		$plans = get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_pp_plan_type',
						'value' => 'corporate',
					),
				),
			)
		);
		return array_map( 'absint', $plans );
	}

	/**
	 * @param array $args status, search, per_page, paged,
	 *                    member_type ('member'|'visitor'|'all', default 'member'),
	 *                    plan_scope ('', 'corporate', 'individual' — 'individual'
	 *                    means "on a plan whose type is anything but corporate")
	 */
	public static function get_memberships( $args = array() ) {
		global $wpdb;
		$table    = self::memberships_table();
		$defaults = array(
			'status'      => '',
			'search'      => '',
			'per_page'    => 20,
			'paged'       => 1,
			'member_type' => 'member',
			'plan_scope'  => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['search'] ) {
			$where[]  = 'membership_number LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}
		if ( 'all' !== $args['member_type'] ) {
			$where[]  = 'member_type = %s';
			$params[] = 'visitor' === $args['member_type'] ? 'visitor' : 'member';
		}
		if ( 'corporate' === $args['plan_scope'] || 'individual' === $args['plan_scope'] ) {
			$corporate_ids = self::corporate_plan_ids();
			if ( $corporate_ids ) {
				$placeholders = implode( ',', array_fill( 0, count( $corporate_ids ), '%d' ) );
				$operator     = 'corporate' === $args['plan_scope'] ? 'IN' : 'NOT IN';
				$where[]  = "plan_id {$operator} ({$placeholders})";
				$params   = array_merge( $params, $corporate_ids );
			} elseif ( 'corporate' === $args['plan_scope'] ) {
				$where[] = '1=0'; // No corporate plans exist at all — no rows can match.
			}
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

		$per_page = max( 1, (int) $args['per_page'] );
		$offset   = ( max( 1, (int) $args['paged'] ) - 1 ) * $per_page;

		$list_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$list_params = array_merge( $params, array( $per_page, $offset ) );
		$items       = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		return array(
			'items'    => $items,
			'total'    => $total,
			'per_page' => $per_page,
		);
	}
}
