<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [passpress_booking_calendar facility_id="X"] shortcode + its AJAX
 * handlers. Kept separate from PP_Booking (CRUD) so that class stays a
 * plain data-layer class — not in the original CLAUDE.md tree, added here
 * for the same reason PP_Install.php was added in Phase 1 (a cohesive home
 * for request-handling code the CRUD class shouldn't own).
 *
 * Booking currently only requires being logged in — it does not check for
 * an active membership. Gating facility booking on membership status is a
 * reasonable future enhancement, out of scope for this pass.
 */
class PP_Booking_Frontend {

	public function __construct() {
		add_shortcode( 'passpress_booking_calendar', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_pp_get_availability', array( $this, 'ajax_get_availability' ) );
		add_action( 'wp_ajax_nopriv_pp_get_availability', array( $this, 'ajax_get_availability' ) );
		add_action( 'wp_ajax_pp_create_booking', array( $this, 'ajax_create_booking' ) );
		add_action( 'wp_ajax_pp_join_waitlist', array( $this, 'ajax_join_waitlist' ) );
		add_action( 'wp_ajax_pp_cancel_booking', array( $this, 'ajax_cancel_booking' ) );
	}

	public function render_shortcode( $atts ) {
		$atts        = shortcode_atts( array( 'facility_id' => 0 ), $atts );
		$facility_id = absint( $atts['facility_id'] );
		$facility    = $facility_id ? get_post( $facility_id ) : null;

		if ( ! $facility || 'pp_facility' !== get_post_type( $facility ) ) {
			return '<p>' . esc_html__( 'Facility not found.', 'passpress' ) . '</p>';
		}

		// Redundant fallback for shortcode usage outside normal post content
		// (e.g. a template calling do_shortcode() directly) — the primary
		// path is PP_Frontend::maybe_enqueue_for_current_page(), which runs
		// early enough to survive block-theme content pre-rendering. See
		// PP_Frontend's class docblock.
		PP_Frontend::enqueue_booking_assets();

		ob_start();
		include PASSPRESS_PLUGIN_DIR . '/templates/booking/booking-calendar.php';
		return ob_get_clean();
	}

	public function ajax_get_availability() {
		check_ajax_referer( 'pp_booking', 'nonce' );

		$facility_id = isset( $_POST['facility_id'] ) ? absint( $_POST['facility_id'] ) : 0;
		$date        = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		if ( ! $facility_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'passpress' ) ) );
		}

		if ( strtotime( $date ) < strtotime( current_time( 'Y-m-d' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a future date.', 'passpress' ) ) );
		}

		wp_send_json_success( array( 'slots' => PP_Booking_Calendar::get_availability( $facility_id, $date ) ) );
	}

	public function ajax_create_booking() {
		check_ajax_referer( 'pp_booking', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to book.', 'passpress' ) ) );
		}

		$facility_id = isset( $_POST['facility_id'] ) ? absint( $_POST['facility_id'] ) : 0;
		$date        = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$start       = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : '';
		$end         = isset( $_POST['end'] ) ? sanitize_text_field( wp_unslash( $_POST['end'] ) ) : '';

		if ( ! $facility_id || ! $date || ! $start || ! $end ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'passpress' ) ) );
		}

		$booking = PP_Booking::create( $facility_id, get_current_user_id(), $date, $start, $end );

		if ( is_wp_error( $booking ) ) {
			wp_send_json_error( array( 'message' => $booking->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'    => __( 'Booking confirmed!', 'passpress' ),
			'booking_id' => $booking->id,
		) );
	}

	public function ajax_join_waitlist() {
		check_ajax_referer( 'pp_booking', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to join the waitlist.', 'passpress' ) ) );
		}

		$facility_id = isset( $_POST['facility_id'] ) ? absint( $_POST['facility_id'] ) : 0;
		$date        = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$start       = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : '';
		$end         = isset( $_POST['end'] ) ? sanitize_text_field( wp_unslash( $_POST['end'] ) ) : '';

		if ( ! $facility_id || ! $date || ! $start || ! $end ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'passpress' ) ) );
		}

		PP_Booking_Waitlist::join( $facility_id, get_current_user_id(), $date, $start, $end );

		wp_send_json_success( array( 'message' => __( "You're on the waitlist. We'll email you if a spot opens up.", 'passpress' ) ) );
	}

	public function ajax_cancel_booking() {
		check_ajax_referer( 'pp_booking', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'passpress' ) ) );
		}

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$booking    = PP_Booking::get( $booking_id );

		if ( ! $booking || (int) $booking->user_id !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'passpress' ) ) );
		}

		$result = PP_Booking::cancel( $booking_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Booking cancelled.', 'passpress' ) ) );
	}
}
