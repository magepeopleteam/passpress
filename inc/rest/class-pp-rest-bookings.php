<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/bookings — direct REST port of admin/PP_Bookings_List.php.
 * Admin actions bypass the facility cancellation lead-time check (by_user_id=0),
 * same as the legacy page — that check only applies to member self-service
 * cancellation on the frontend "My Bookings" list.
 */
class PP_REST_Bookings extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/bookings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_bookings' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/bookings/(?P<id>\d+)/(?P<action>complete|no_show|cancel)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'do_action' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);
	}

	public function list_bookings( $request ) {
		$status           = sanitize_key( (string) $request->get_param( 'status' ) );
		$facility_id      = absint( $request->get_param( 'facility_id' ) );
		$class_session_id = absint( $request->get_param( 'class_session_id' ) );
		$paged            = max( 1, absint( $request->get_param( 'paged' ) ?: 1 ) );
		$per_page         = 20;

		$result = PP_Booking::get_list(
			array(
				'status'           => $status,
				'facility_id'      => $facility_id,
				'class_session_id' => $class_session_id,
				'paged'            => $paged,
				'per_page'         => $per_page,
			)
		);

		$items = array_map( array( __CLASS__, 'build_row' ), $result['items'] );

		return rest_ensure_response(
			array(
				'items'      => $items,
				'total'      => (int) $result['total'],
				'per_page'   => (int) $result['per_page'],
				'counts'     => self::status_counts( $facility_id, $class_session_id ),
				'facilities' => array_map(
					function ( $f ) { return array( 'id' => (int) $f->ID, 'name' => $f->post_title ); },
					PP_Facility::get_all()
				),
				'classes'    => array_map(
					function ( $c ) { return array( 'id' => (int) $c->ID, 'name' => $c->post_title ); },
					PP_Class_Session::get_all()
				),
			)
		);
	}

	public function do_action( $request ) {
		$id     = (int) $request['id'];
		$action = (string) $request['action'];

		if ( 'cancel' === $action ) {
			$result = PP_Booking::cancel( $id, 0 );
			if ( is_wp_error( $result ) ) {
				return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
			}
		} else {
			$status  = 'complete' === $action ? PP_Booking::STATUS_COMPLETED : PP_Booking::STATUS_NO_SHOW;
			$updated = PP_Booking::set_status( $id, $status );
			if ( ! $updated ) {
				return new WP_Error( 'pp_not_found', __( 'Booking not found.', 'passpress' ), array( 'status' => 404 ) );
			}
		}

		return rest_ensure_response( array( 'message' => __( 'Booking updated.', 'passpress' ) ) );
	}

	/**
	 * @param object $booking
	 */
	private static function build_row( $booking ) {
		$is_class = ! empty( $booking->class_session_id );
		$user     = get_userdata( $booking->user_id );

		$labels = array(
			'confirmed' => __( 'Confirmed', 'passpress' ),
			'completed' => __( 'Completed', 'passpress' ),
			'no_show'   => __( 'No show', 'passpress' ),
			'cancelled' => __( 'Cancelled', 'passpress' ),
		);

		return array(
			'id'                => (int) $booking->id,
			'is_class'          => $is_class,
			'session_type'      => $is_class ? __( 'Class', 'passpress' ) : __( 'Facility', 'passpress' ),
			'session_title'     => $is_class ? get_the_title( $booking->class_session_id ) : get_the_title( $booking->facility_id ),
			'member_name'       => $user ? $user->display_name : __( 'Unknown', 'passpress' ),
			'member_email'      => $user ? $user->user_email : '',
			'date_label'        => pp_format_date( $booking->booking_date ),
			'time_label'        => substr( $booking->start_time, 0, 5 ) . '–' . substr( $booking->end_time, 0, 5 ),
			'checked_in_label'  => $booking->checked_in_at ? pp_format_datetime( $booking->checked_in_at ) : null,
			'status'            => $booking->status,
			'status_label'      => isset( $labels[ $booking->status ] ) ? $labels[ $booking->status ] : ucfirst( str_replace( '_', ' ', (string) $booking->status ) ),
		);
	}

	private static function status_counts( $facility_id, $class_session_id ) {
		$counts = array( 'confirmed' => 0, 'completed' => 0, 'no_show' => 0, 'cancelled' => 0 );
		foreach ( array_keys( $counts ) as $status ) {
			$result             = PP_Booking::get_list(
				array(
					'status'           => $status,
					'facility_id'      => $facility_id,
					'class_session_id' => $class_session_id,
					'paged'            => 1,
					'per_page'         => 1,
				)
			);
			$counts[ $status ] = isset( $result['total'] ) ? (int) $result['total'] : 0;
		}
		return $counts;
	}
}
