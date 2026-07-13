<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single dispatcher every notification-worthy event funnels through. Phase 4
 * scope is deliberately email-only (see CLAUDE.md) — send() is the one place
 * a future SMS/WhatsApp/push channel would be added, so every trigger below
 * already goes through it instead of calling wp_mail() directly.
 */
class PP_Notifications {

	public static function default_settings() {
		return array(
			'welcome_enabled'          => 1,
			'booking_reminder_enabled' => 1,
			'booking_reminder_days'    => 1,
			'payment_failed_enabled'   => 1,
			'birthday_enabled'         => 1,
		);
	}

	public static function get_settings() {
		return wp_parse_args( get_option( 'passpress_notification_settings', array() ), self::default_settings() );
	}

	private static function send( $to_email, $subject, $body ) {
		if ( ! is_email( $to_email ) ) {
			return false;
		}
		return wp_mail( $to_email, $subject, $body );
	}

	/**
	 * Fired from PP_Membership::issue() for every new (non-renewal) membership,
	 * member or visitor alike — visitors with a placeholder @passpress.invalid
	 * address simply fail the is_email() check in send() and are silently skipped.
	 */
	public static function welcome( $membership ) {
		$settings = self::get_settings();
		if ( empty( $settings['welcome_enabled'] ) ) {
			return;
		}

		$user = get_userdata( $membership->user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Welcome!', 'passpress' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: 1: display name, 2: plan name, 3: membership number */
			__( "Hi %1\$s,\n\nWelcome! Your \"%2\$s\" membership is now active.\nMembership #: %3\$s\n\nYou can view your pass any time from your account.", 'passpress' ),
			$user->display_name,
			get_the_title( $membership->plan_id ),
			$membership->membership_number
		);

		self::send( $user->user_email, $subject, $body );
	}

	/**
	 * Refactored out of PP_Billing::send_receipt_email() — same content, just
	 * routed through the shared dispatcher.
	 */
	public static function receipt( $membership, $billing_row ) {
		$user = get_userdata( $membership->user_id );
		if ( ! $user ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Payment received — your membership is active', 'passpress' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: 1: plan name, 2: membership number, 3: expiry date, 4: amount, 5: currency */
			__( "Thanks for your payment!\n\nPlan: %1\$s\nMembership #: %2\$s\nExpires: %3\$s\nAmount charged: %4\$s %5\$s\n\nView your pass any time from your account.", 'passpress' ),
			get_the_title( $membership->plan_id ),
			$membership->membership_number,
			pp_format_date( $membership->expiry_date ),
			number_format_i18n( (float) $billing_row->amount, 2 ),
			strtoupper( $billing_row->currency )
		);

		self::send( $user->user_email, $subject, $body );
	}

	/**
	 * Refactored out of PP_Cron::send_reminder() — same content, just routed
	 * through the shared dispatcher. Gating (whether/when to send) stays in
	 * PP_Cron, driven by the existing renewal_reminder_days billing setting.
	 */
	public static function expiry_reminder( $membership, $user ) {
		$checkout_url = PP_Billing::checkout_url( $membership->plan_id, $membership->id );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Your membership expires soon', 'passpress' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: 1: display name, 2: plan name, 3: expiry date, 4: renewal link */
			__( "Hi %1\$s,\n\nYour \"%2\$s\" membership expires on %3\$s. Renew now to avoid losing access:\n%4\$s\n\nThanks!", 'passpress' ),
			$user->display_name,
			get_the_title( $membership->plan_id ),
			pp_format_date( $membership->expiry_date ),
			$checkout_url
		);

		self::send( $user->user_email, $subject, $body );
	}

	/**
	 * Refactored out of PP_Booking_Waitlist's two maybe_promote*() methods.
	 */
	public static function waitlist_spot_opened( $user, $title, $date_label ) {
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] A spot opened up!', 'passpress' ),
			get_bloginfo( 'name' )
		);
		$body = sprintf(
			/* translators: 1: display name, 2: facility/class name, 3: date/time label */
			__( "Hi %1\$s,\n\nA spot just opened for %2\$s on %3\$s. Book again soon before it's taken.", 'passpress' ),
			$user->display_name,
			$title,
			$date_label
		);

		self::send( $user->user_email, $subject, $body );
	}

	/**
	 * New trigger: a member's upcoming facility/class booking. Checked daily
	 * (see PP_Cron::send_booking_reminders()), so "reminder window" is whole
	 * days, not hours — consistent with the existing renewal-reminder granularity.
	 */
	public static function booking_reminder( $booking ) {
		$settings = self::get_settings();
		if ( empty( $settings['booking_reminder_enabled'] ) ) {
			return;
		}

		$user = get_userdata( $booking->user_id );
		if ( ! $user ) {
			return;
		}

		$title = $booking->class_session_id ? get_the_title( $booking->class_session_id ) : get_the_title( $booking->facility_id );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Upcoming booking reminder', 'passpress' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: 1: display name, 2: facility/class name, 3: date, 4: start-end time */
			__( "Hi %1\$s,\n\nJust a reminder — you're booked for %2\$s on %3\$s, %4\$s.", 'passpress' ),
			$user->display_name,
			$title,
			pp_format_date( $booking->booking_date ),
			substr( $booking->start_time, 0, 5 ) . '–' . substr( $booking->end_time, 0, 5 )
		);

		self::send( $user->user_email, $subject, $body );
	}

	/**
	 * New trigger: a checkout payment attempt failed (gateway decline, or
	 * staff manually marking an offline payment failed).
	 */
	public static function payment_failed( $billing_row, $reason = '' ) {
		$settings = self::get_settings();
		if ( empty( $settings['payment_failed_enabled'] ) ) {
			return;
		}

		$user = get_userdata( $billing_row->user_id );
		if ( ! $user ) {
			return;
		}

		$retry_url = PP_Billing::checkout_url( $billing_row->plan_id, $billing_row->membership_id );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Your payment could not be processed', 'passpress' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: 1: display name, 2: plan name, 3: reason, 4: retry link */
			__( "Hi %1\$s,\n\nWe couldn't process your payment for \"%2\$s\".\n%3\$s\n\nYou can try again here:\n%4\$s", 'passpress' ),
			$user->display_name,
			get_the_title( $billing_row->plan_id ),
			$reason,
			$retry_url
		);

		self::send( $user->user_email, $subject, $body );
	}

	/**
	 * New trigger: a member's birthday. Checked daily (see
	 * PP_Cron::send_birthday_greetings()) against the pp_birthdate usermeta
	 * saved from the My Pass page.
	 */
	public static function birthday( $user ) {
		$settings = self::get_settings();
		if ( empty( $settings['birthday_enabled'] ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Happy Birthday!', 'passpress' ),
			get_bloginfo( 'name' )
		);

		$body = sprintf(
			/* translators: %s: display name */
			__( "Hi %s,\n\nHappy birthday from all of us! We hope you have a great day.", 'passpress' ),
			$user->display_name
		);

		self::send( $user->user_email, $subject, $body );
	}

	/**
	 * Saves the current user's birthdate from the My Pass page form. Plain
	 * POST + nonce, not AJAX — the shortcode callback already runs during
	 * normal content rendering, so there's no need for a separate script/
	 * localize round trip (and nothing to hit the FSE enqueue-ordering issue
	 * documented in CLAUDE.md's Phase 2 notes).
	 */
	public static function maybe_save_birthdate_from_post() {
		if ( ! isset( $_POST['pp_birthdate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pp_birthdate_nonce'] ) ), 'pp_save_birthdate' ) ) {
			return false;
		}
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$birthdate = isset( $_POST['pp_birthdate'] ) ? sanitize_text_field( wp_unslash( $_POST['pp_birthdate'] ) ) : '';
		if ( $birthdate && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birthdate ) ) {
			update_user_meta( get_current_user_id(), 'pp_birthdate', $birthdate );
			return true;
		}

		return false;
	}
}
