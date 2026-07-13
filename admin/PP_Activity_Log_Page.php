<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Activity_Log_Page {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$logs = PP_Activity_Logger::get_recent( 100 );
		?>
		<div class="wrap passpress-wrap passpress-activity-page">
			<div class="passpress-activity-page-header">
				<div class="passpress-activity-page-copy">
					<p class="passpress-activity-page-eyebrow"><?php esc_html_e( 'Audit', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Activity Log', 'passpress' ); ?></h1>
					<p class="passpress-activity-page-desc">
						<?php
						printf(
							/* translators: %d: number of recent log entries shown */
							esc_html( _n( 'Showing the %d most recent event', 'Showing the %d most recent events', count( $logs ), 'passpress' ) ),
							count( $logs )
						);
						?>
					</p>
				</div>
			</div>

			<?php if ( ! $logs ) : ?>
				<div class="passpress-activity-empty">
					<p class="passpress-activity-empty-eyebrow"><?php esc_html_e( 'Quiet', 'passpress' ); ?></p>
					<h2 class="passpress-activity-empty-title"><?php esc_html_e( 'No activity yet', 'passpress' ); ?></h2>
					<p class="passpress-activity-empty-desc"><?php esc_html_e( 'Memberships, bookings, payments, and other PassPress actions will show up here.', 'passpress' ); ?></p>
				</div>
			<?php else : ?>
				<div class="passpress-activity-table-wrap">
					<table class="passpress-activity-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'When', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Event', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Message', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'User', 'passpress' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<?php
								$user_name = self::user_name( $log->user_id );
								$category  = self::event_category( $log );
								?>
								<tr>
									<td>
										<div class="passpress-activity-when">
											<strong><?php echo esc_html( self::format_date( $log->created_at ) ); ?></strong>
											<span><?php echo esc_html( self::format_time( $log->created_at ) ); ?></span>
										</div>
									</td>
									<td>
										<span class="passpress-activity-event passpress-activity-event-<?php echo esc_attr( $category ); ?>">
											<span class="passpress-activity-event-dot"></span>
											<?php echo esc_html( self::event_label( $log->event ) ); ?>
										</span>
									</td>
									<td>
										<p class="passpress-activity-message"><?php echo esc_html( $log->message ); ?></p>
									</td>
									<td>
										<div class="passpress-activity-person">
											<span class="passpress-activity-avatar"><?php echo esc_html( self::initials( $user_name ) ); ?></span>
											<strong><?php echo esc_html( $user_name ); ?></strong>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function user_name( $user_id ) {
		if ( ! $user_id ) {
			return __( 'System', 'passpress' );
		}
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown', 'passpress' );
	}

	private static function format_date( $datetime ) {
		$date = substr( (string) $datetime, 0, 10 );
		return $date ? pp_format_date( $date ) : '';
	}

	private static function format_time( $datetime ) {
		$ts = strtotime( (string) $datetime );
		return $ts ? date_i18n( get_option( 'time_format' ), $ts ) : '';
	}

	private static function event_label( $event ) {
		$event = (string) $event;
		$known = array(
			'visitor_pass_issued'              => __( 'Visitor pass issued', 'passpress' ),
			'visitor_account_created'          => __( 'Visitor account created', 'passpress' ),
			'membership_issued'                => __( 'Membership issued', 'passpress' ),
			'membership_renewed'               => __( 'Membership renewed', 'passpress' ),
			'membership_status_changed'        => __( 'Membership status changed', 'passpress' ),
			'facility_created'                 => __( 'Facility created', 'passpress' ),
			'facility_updated'                 => __( 'Facility updated', 'passpress' ),
			'class_session_created'            => __( 'Class created', 'passpress' ),
			'class_session_updated'            => __( 'Class updated', 'passpress' ),
			'class_booking_created'            => __( 'Class booked', 'passpress' ),
			'booking_created'                  => __( 'Booking created', 'passpress' ),
			'booking_status_changed'           => __( 'Booking status changed', 'passpress' ),
			'billing_paid'                     => __( 'Payment confirmed', 'passpress' ),
			'billing_pending_manual_confirmation' => __( 'Payment pending', 'passpress' ),
			'business_template_imported'       => __( 'Template imported', 'passpress' ),
			'waitlist_joined'                  => __( 'Waitlist joined', 'passpress' ),
			'waitlist_notified'                => __( 'Waitlist notified', 'passpress' ),
			'membership_plan_created'          => __( 'Plan created', 'passpress' ),
			'membership_plan_updated'          => __( 'Plan updated', 'passpress' ),
			'checkout_gift'                    => __( 'Gift checkout', 'passpress' ),
			'renewal_reminder_sent'            => __( 'Renewal reminder sent', 'passpress' ),
			'booking_reminder_sent'            => __( 'Booking reminder sent', 'passpress' ),
			'birthday_greeting_sent'           => __( 'Birthday greeting sent', 'passpress' ),
		);

		if ( isset( $known[ $event ] ) ) {
			return $known[ $event ];
		}

		return ucwords( str_replace( '_', ' ', $event ) );
	}

	/**
	 * @param object $log
	 */
	private static function event_category( $log ) {
		$type = isset( $log->object_type ) ? sanitize_key( $log->object_type ) : '';
		if ( $type ) {
			$map = array(
				'membership' => 'membership',
				'user'       => 'visitor',
				'billing'    => 'billing',
				'booking'    => 'booking',
				'waitlist'   => 'booking',
				'facility'   => 'facility',
				'class'      => 'class',
				'plan'       => 'plan',
				'template'   => 'system',
				'order'      => 'billing',
			);
			if ( isset( $map[ $type ] ) ) {
				return $map[ $type ];
			}
		}

		$event = isset( $log->event ) ? (string) $log->event : '';
		if ( 0 === strpos( $event, 'visitor_' ) ) {
			return 'visitor';
		}
		if ( 0 === strpos( $event, 'billing_' ) || 0 === strpos( $event, 'checkout_' ) || 0 === strpos( $event, 'shop_' ) ) {
			return 'billing';
		}
		if ( 0 === strpos( $event, 'membership_' ) ) {
			return 'membership';
		}
		if ( false !== strpos( $event, 'booking' ) || 0 === strpos( $event, 'waitlist_' ) ) {
			return 'booking';
		}
		if ( 0 === strpos( $event, 'facility_' ) ) {
			return 'facility';
		}
		if ( 0 === strpos( $event, 'class_' ) ) {
			return 'class';
		}
		if ( 0 === strpos( $event, 'membership_plan_' ) ) {
			return 'plan';
		}

		return 'system';
	}

	private static function initials( $name ) {
		$parts = preg_split( '/\s+/', trim( (string) $name ) );
		if ( ! $parts ) {
			return '?';
		}
		$first = mb_substr( $parts[0], 0, 1 );
		$last  = count( $parts ) > 1 ? mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) : '';
		return strtoupper( $first . $last );
	}
}
