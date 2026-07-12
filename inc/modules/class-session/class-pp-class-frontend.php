<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [passpress_class_schedule] shortcode + its book/waitlist AJAX handlers.
 * Occurrences are rendered server-side at page load (deterministic from
 * today's date, no date-picker needed unlike the general facility
 * calendar) — only the Book/Waitlist actions themselves are AJAX.
 * Cancelling a class booking reuses the EXISTING wp_ajax_pp_cancel_booking
 * handler in PP_Booking_Frontend unchanged, since it just operates on a
 * booking id regardless of whether it's a facility or class booking.
 */
class PP_Class_Frontend {

	public function __construct() {
		add_shortcode( 'passpress_class_schedule', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_pp_book_class', array( $this, 'ajax_book_class' ) );
		add_action( 'wp_ajax_pp_join_class_waitlist', array( $this, 'ajax_join_waitlist' ) );
	}

	public function render_shortcode( $atts ) {
		PP_Frontend::enqueue_class_schedule_assets();

		$classes = PP_Class_Session::get_all();

		ob_start();
		include PASSPRESS_PLUGIN_DIR . '/templates/class-session/class-schedule.php';
		return ob_get_clean();
	}

	public function ajax_book_class() {
		check_ajax_referer( 'pp_booking', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to book.', 'passpress' ) ) );
		}

		$class_session_id = isset( $_POST['class_session_id'] ) ? absint( $_POST['class_session_id'] ) : 0;
		$date              = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		if ( ! $class_session_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'passpress' ) ) );
		}

		$booking = PP_Class_Session::book( $class_session_id, get_current_user_id(), $date );

		if ( is_wp_error( $booking ) ) {
			wp_send_json_error( array( 'message' => $booking->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Booking confirmed!', 'passpress' ), 'booking_id' => $booking->id ) );
	}

	public function ajax_join_waitlist() {
		check_ajax_referer( 'pp_booking', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to join the waitlist.', 'passpress' ) ) );
		}

		$class_session_id = isset( $_POST['class_session_id'] ) ? absint( $_POST['class_session_id'] ) : 0;
		$date              = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		if ( ! $class_session_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'passpress' ) ) );
		}

		PP_Booking_Waitlist::join_class( $class_session_id, get_current_user_id(), $date );

		wp_send_json_success( array( 'message' => __( "You're on the waitlist. We'll email you if a spot opens up.", 'passpress' ) ) );
	}
}
