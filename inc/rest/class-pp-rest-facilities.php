<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/facilities — direct REST port of admin/PP_Facilities_List.php.
 */
class PP_REST_Facilities extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/facilities',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_facilities' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_facility' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/facilities/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_facility' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_facility' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);
	}

	public function list_facilities( $request ) {
		$facilities = get_posts(
			array(
				'post_type'      => 'pp_facility',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		$items = array_map( array( __CLASS__, 'build_payload' ), $facilities );

		$staff_users = get_users( array( 'role__in' => array( 'pp_staff', 'pp_trainer', 'administrator' ) ) );

		return rest_ensure_response(
			array(
				'items'   => $items,
				'options' => array(
					'facility_types' => PP_Facility_CPT::facility_types(),
					'weekdays'       => PP_Facility_CPT::weekdays(),
				),
				'staff'   => array_map(
					function ( $user ) {
						return array( 'id' => (int) $user->ID, 'name' => $user->display_name );
					},
					$staff_users
				),
			)
		);
	}

	public function get_facility( $request ) {
		$payload = self::build_payload_by_id( (int) $request['id'] );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		return rest_ensure_response( $payload );
	}

	public function create_facility( $request ) {
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( ! $title ) {
			return new WP_Error( 'pp_missing_title', __( 'Please enter a facility name.', 'passpress' ), array( 'status' => 400 ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'pp_facility',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::save_meta( $post_id, $request );
		PP_Activity_Logger::log( 'facility_created', 'facility', $post_id, sprintf( 'Facility "%s" created.', $title ) );

		return rest_ensure_response(
			array(
				'message'     => __( 'Facility created!', 'passpress' ),
				'facility_id' => $post_id,
			)
		);
	}

	public function update_facility( $request ) {
		$facility_id = (int) $request['id'];
		$facility    = get_post( $facility_id );
		if ( ! $facility || 'pp_facility' !== $facility->post_type ) {
			return new WP_Error( 'pp_not_found', __( 'Facility not found.', 'passpress' ), array( 'status' => 404 ) );
		}

		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( ! $title ) {
			return new WP_Error( 'pp_missing_title', __( 'Please enter a facility name.', 'passpress' ), array( 'status' => 400 ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $facility_id,
				'post_title'  => $title,
				'post_status' => $request->get_param( 'is_live' ) ? 'publish' : 'draft',
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		self::save_meta( $facility_id, $request );
		PP_Activity_Logger::log( 'facility_updated', 'facility', $facility_id, sprintf( 'Facility "%s" updated.', $title ) );

		return rest_ensure_response(
			array(
				'message'     => __( 'Facility saved!', 'passpress' ),
				'facility_id' => $facility_id,
			)
		);
	}

	/**
	 * @param int             $post_id
	 * @param WP_REST_Request $request
	 */
	private static function save_meta( $post_id, $request ) {
		update_post_meta( $post_id, '_pp_facility_type', sanitize_key( (string) ( $request->get_param( '_pp_facility_type' ) ?: 'gym' ) ) );
		update_post_meta( $post_id, '_pp_capacity', absint( $request->get_param( '_pp_capacity' ) ) );
		update_post_meta( $post_id, '_pp_booking_required', $request->get_param( '_pp_booking_required' ) ? 1 : 0 );
		update_post_meta( $post_id, '_pp_slot_duration', max( 5, absint( $request->get_param( '_pp_slot_duration' ) ?: 60 ) ) );
		update_post_meta( $post_id, '_pp_buffer_minutes', absint( $request->get_param( '_pp_buffer_minutes' ) ) );
		update_post_meta( $post_id, '_pp_open_time', sanitize_text_field( (string) ( $request->get_param( '_pp_open_time' ) ?: '09:00' ) ) );
		update_post_meta( $post_id, '_pp_close_time', sanitize_text_field( (string) ( $request->get_param( '_pp_close_time' ) ?: '21:00' ) ) );

		$days_open = (array) $request->get_param( '_pp_days_open' );
		update_post_meta( $post_id, '_pp_days_open', array_map( 'absint', $days_open ) );

		update_post_meta( $post_id, '_pp_cancellation_lead_hours', absint( $request->get_param( '_pp_cancellation_lead_hours' ) ?: 2 ) );

		$staff_ids = (array) $request->get_param( '_pp_staff_ids' );
		update_post_meta( $post_id, '_pp_staff_ids', array_map( 'absint', $staff_ids ) );
	}

	/**
	 * @param WP_Post $facility
	 */
	private static function build_payload( $facility ) {
		$days_open = get_post_meta( $facility->ID, '_pp_days_open', true );
		$days_open = is_array( $days_open ) ? array_map( 'intval', $days_open ) : array();
		$staff_ids = get_post_meta( $facility->ID, '_pp_staff_ids', true );
		$staff_ids = is_array( $staff_ids ) ? array_map( 'intval', $staff_ids ) : array();

		return array(
			'facility_id'                 => (int) $facility->ID,
			'title'                       => $facility->post_title,
			'status'                      => $facility->post_status,
			'is_live'                     => ( 'publish' === $facility->post_status ) ? 1 : 0,
			'_pp_facility_type'           => (string) get_post_meta( $facility->ID, '_pp_facility_type', true ),
			'_pp_capacity'                => (int) get_post_meta( $facility->ID, '_pp_capacity', true ),
			'_pp_booking_required'        => (int) get_post_meta( $facility->ID, '_pp_booking_required', true ),
			'_pp_slot_duration'           => (int) get_post_meta( $facility->ID, '_pp_slot_duration', true ),
			'_pp_buffer_minutes'          => (int) get_post_meta( $facility->ID, '_pp_buffer_minutes', true ),
			'_pp_open_time'               => (string) get_post_meta( $facility->ID, '_pp_open_time', true ),
			'_pp_close_time'              => (string) get_post_meta( $facility->ID, '_pp_close_time', true ),
			'_pp_days_open'               => $days_open,
			'_pp_cancellation_lead_hours' => (int) get_post_meta( $facility->ID, '_pp_cancellation_lead_hours', true ),
			'_pp_staff_ids'               => $staff_ids,
		);
	}

	private static function build_payload_by_id( $facility_id ) {
		$facility = get_post( $facility_id );
		if ( ! $facility || 'pp_facility' !== $facility->post_type ) {
			return new WP_Error( 'pp_not_found', __( 'Facility not found.', 'passpress' ), array( 'status' => 404 ) );
		}
		return self::build_payload( $facility );
	}
}
