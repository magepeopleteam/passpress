<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates a plan's entry-restriction rule against a membership's scan
 * history. Only called on entry — exits are never restricted.
 */
class PP_Entry_Restrictions {

	/**
	 * @return array{allowed: bool, reason?: string}
	 */
	public static function check( $membership, $plan_id ) {
		$restriction = get_post_meta( $plan_id, '_pp_entry_restriction', true );

		if ( $restriction && 'none' !== $restriction ) {
			$now = current_time( 'timestamp' );

			switch ( $restriction ) {
				case 'one_per_day':
					if ( self::has_entry_today( $membership->id ) ) {
						return array(
							'allowed' => false,
							'reason'  => __( 'Only one entry allowed per day. Already checked in today.', 'passpress' ),
						);
					}
					break;

				case 'weekday_only':
					if ( (int) date( 'N', $now ) >= 6 ) {
						return array(
							'allowed' => false,
							'reason'  => __( 'This pass is valid on weekdays only.', 'passpress' ),
						);
					}
					break;

				case 'weekend_only':
					if ( (int) date( 'N', $now ) < 6 ) {
						return array(
							'allowed' => false,
							'reason'  => __( 'This pass is valid on weekends only.', 'passpress' ),
						);
					}
					break;

				case 'time_restricted':
					$start = get_post_meta( $plan_id, '_pp_time_restriction_start', true );
					$end   = get_post_meta( $plan_id, '_pp_time_restriction_end', true );
					if ( $start && $end ) {
						$current_time_only = date( 'H:i', $now );
						if ( $current_time_only < $start || $current_time_only > $end ) {
							return array(
								'allowed' => false,
								/* translators: 1: window start time, 2: window end time */
								'reason'  => sprintf( __( 'This pass is only valid between %1$s and %2$s.', 'passpress' ), $start, $end ),
							);
						}
					}
					break;
			}
		}

		$max_per_day = (int) get_post_meta( $plan_id, '_pp_max_entries_per_day', true );
		if ( $max_per_day > 0 && self::entries_today_count( $membership->id ) >= $max_per_day ) {
			return array(
				'allowed' => false,
				'reason'  => __( 'Daily entry limit reached for this pass.', 'passpress' ),
			);
		}

		return array( 'allowed' => true );
	}

	public static function has_entry_today( $membership_id ) {
		return self::entries_today_count( $membership_id ) > 0;
	}

	public static function entries_today_count( $membership_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pp_access_logs';
		$today = current_time( 'Y-m-d' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE membership_id = %d AND direction = 'entry' AND result = 'allowed' AND DATE(created_at) = %s",
				$membership_id,
				$today
			)
		);
	}
}
