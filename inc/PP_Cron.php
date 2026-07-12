<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Daily WP-Cron job: three independent checks (renewal reminders, booking
 * reminders, birthday greetings), each idempotent via a same-day activity-log
 * guard. All actual sending goes through PP_Notifications — this class only
 * owns the "who/when to check" scheduling logic.
 */
class PP_Cron {

	const HOOK = 'passpress_daily_renewal_check';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'send_renewal_reminders' ) );
		add_action( self::HOOK, array( __CLASS__, 'send_booking_reminders' ) );
		add_action( self::HOOK, array( __CLASS__, 'send_birthday_greetings' ) );
	}

	public static function maybe_schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	public static function send_renewal_reminders() {
		global $wpdb;

		$billing_settings = PP_Billing::get_settings();
		$days             = max( 1, (int) $billing_settings['renewal_reminder_days'] );
		$table            = PP_Membership::table();
		$today            = current_time( 'Y-m-d' );
		$target_date      = gmdate( 'Y-m-d', strtotime( "{$today} +{$days} days" ) );

		$memberships = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND expiry_date = %s",
				PP_Membership::STATUS_ACTIVE,
				$target_date
			)
		);

		foreach ( $memberships as $membership ) {
			self::send_reminder( $membership );
		}
	}

	private static function send_reminder( $membership ) {
		// Idempotency: skip if we already logged a reminder for this membership today.
		global $wpdb;
		$table       = $wpdb->prefix . 'pp_activity_log';
		$today       = current_time( 'Y-m-d' );
		$sent_today  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE event = 'renewal_reminder_sent' AND object_id = %d AND DATE(created_at) = %s",
				$membership->id,
				$today
			)
		);
		if ( $sent_today > 0 ) {
			return;
		}

		$user = get_userdata( $membership->user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		PP_Notifications::expiry_reminder( $membership, $user );

		PP_Activity_Logger::log( 'renewal_reminder_sent', 'membership', $membership->id, sprintf( 'Renewal reminder emailed to %s.', $user->user_email ) );
	}

	/**
	 * Daily check: emails members with a confirmed booking N days out
	 * (default 1, see passpress_notification_settings). Same idempotency
	 * pattern as send_renewal_reminders() — a logged activity event guards
	 * against double-sending if the cron somehow fires twice in one day.
	 */
	public static function send_booking_reminders() {
		global $wpdb;

		$settings    = PP_Notifications::get_settings();
		$days        = max( 1, (int) $settings['booking_reminder_days'] );
		$table       = PP_Booking::table();
		$today       = current_time( 'Y-m-d' );
		$target_date = gmdate( 'Y-m-d', strtotime( "{$today} +{$days} days" ) );

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s AND booking_date = %s",
				PP_Booking::STATUS_CONFIRMED,
				$target_date
			)
		);

		foreach ( $bookings as $booking ) {
			self::maybe_send_booking_reminder( $booking );
		}
	}

	private static function maybe_send_booking_reminder( $booking ) {
		global $wpdb;
		$log_table  = $wpdb->prefix . 'pp_activity_log';
		$today      = current_time( 'Y-m-d' );
		$sent_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$log_table} WHERE event = 'booking_reminder_sent' AND object_id = %d AND DATE(created_at) = %s",
				$booking->id,
				$today
			)
		);
		if ( $sent_today > 0 ) {
			return;
		}

		PP_Notifications::booking_reminder( $booking );

		PP_Activity_Logger::log( 'booking_reminder_sent', 'booking', $booking->id, 'Booking reminder emailed.' );
	}

	/**
	 * Daily check: emails members whose pp_birthdate usermeta (set from the
	 * My Pass page) matches today's month/day, in any birth year.
	 */
	public static function send_birthday_greetings() {
		global $wpdb;

		$today_month_day = current_time( 'm-d' );

		$user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'pp_birthdate' AND DATE_FORMAT(meta_value, '%%m-%%d') = %s",
				$today_month_day
			)
		);

		foreach ( $user_ids as $user_id ) {
			self::maybe_send_birthday_greeting( (int) $user_id );
		}
	}

	private static function maybe_send_birthday_greeting( $user_id ) {
		global $wpdb;
		$log_table  = $wpdb->prefix . 'pp_activity_log';
		$today      = current_time( 'Y-m-d' );
		$sent_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$log_table} WHERE event = 'birthday_greeting_sent' AND object_id = %d AND DATE(created_at) = %s",
				$user_id,
				$today
			)
		);
		if ( $sent_today > 0 ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		PP_Notifications::birthday( $user );

		PP_Activity_Logger::log( 'birthday_greeting_sent', 'user', $user_id, sprintf( 'Birthday greeting emailed to %s.', $user->user_email ) );
	}
}
