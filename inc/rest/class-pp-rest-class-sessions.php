<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/class-sessions — direct REST port of
 * admin/PP_Class_Sessions_List.php.
 */
class PP_REST_Class_Sessions extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/class-sessions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_classes' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_class' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/class-sessions/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_class' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_class' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);
	}

	public function list_classes( $request ) {
		$classes = get_posts(
			array(
				'post_type'      => 'pp_class_session',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		$items = array_map( array( __CLASS__, 'build_payload' ), $classes );

		$instructors = get_users( array( 'role__in' => array( 'pp_staff', 'pp_trainer', 'administrator' ) ) );
		$facilities  = get_posts(
			array(
				'post_type'      => 'pp_facility',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		return rest_ensure_response(
			array(
				'items'       => $items,
				'options'     => array(
					'class_types' => PP_Class_Session_CPT::class_types(),
					'weekdays'    => PP_Class_Session_CPT::weekdays(),
				),
				'instructors' => array_map(
					function ( $user ) {
						return array( 'id' => (int) $user->ID, 'name' => $user->display_name );
					},
					$instructors
				),
				'facilities'  => array_map(
					function ( $facility ) {
						return array( 'id' => (int) $facility->ID, 'name' => $facility->post_title );
					},
					$facilities
				),
			)
		);
	}

	public function get_class( $request ) {
		$payload = self::build_payload_by_id( (int) $request['id'] );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		return rest_ensure_response( $payload );
	}

	public function create_class( $request ) {
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( ! $title ) {
			return new WP_Error( 'pp_missing_title', __( 'Please enter a class name.', 'passpress' ), array( 'status' => 400 ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'pp_class_session',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::save_meta( $post_id, $request );
		PP_Activity_Logger::log( 'class_session_created', 'class', $post_id, sprintf( 'Class "%s" created.', $title ) );

		return rest_ensure_response(
			array(
				'message'  => __( 'Class created!', 'passpress' ),
				'class_id' => $post_id,
			)
		);
	}

	public function update_class( $request ) {
		$class_id = (int) $request['id'];
		$class    = get_post( $class_id );
		if ( ! $class || 'pp_class_session' !== $class->post_type ) {
			return new WP_Error( 'pp_not_found', __( 'Class session not found.', 'passpress' ), array( 'status' => 404 ) );
		}

		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( ! $title ) {
			return new WP_Error( 'pp_missing_title', __( 'Please enter a class name.', 'passpress' ), array( 'status' => 400 ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $class_id,
				'post_title'  => $title,
				'post_status' => $request->get_param( 'is_live' ) ? 'publish' : 'draft',
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		self::save_meta( $class_id, $request );
		PP_Activity_Logger::log( 'class_session_updated', 'class', $class_id, sprintf( 'Class "%s" updated.', $title ) );

		return rest_ensure_response(
			array(
				'message'  => __( 'Class saved!', 'passpress' ),
				'class_id' => $class_id,
			)
		);
	}

	/**
	 * @param int             $post_id
	 * @param WP_REST_Request $request
	 */
	private static function save_meta( $post_id, $request ) {
		update_post_meta( $post_id, '_pp_class_type', sanitize_key( (string) ( $request->get_param( '_pp_class_type' ) ?: 'yoga' ) ) );
		update_post_meta( $post_id, '_pp_instructor_id', absint( $request->get_param( '_pp_instructor_id' ) ) );
		update_post_meta( $post_id, '_pp_facility_id', absint( $request->get_param( '_pp_facility_id' ) ) );
		update_post_meta( $post_id, '_pp_capacity', max( 1, absint( $request->get_param( '_pp_capacity' ) ?: 10 ) ) );
		update_post_meta( $post_id, '_pp_day_of_week', absint( $request->get_param( '_pp_day_of_week' ) ?: 1 ) );
		update_post_meta( $post_id, '_pp_start_time', sanitize_text_field( (string) ( $request->get_param( '_pp_start_time' ) ?: '09:00' ) ) );
		update_post_meta( $post_id, '_pp_end_time', sanitize_text_field( (string) ( $request->get_param( '_pp_end_time' ) ?: '10:00' ) ) );
	}

	/**
	 * @param WP_Post $class
	 */
	private static function build_payload( $class ) {
		return array(
			'class_id'          => (int) $class->ID,
			'title'             => $class->post_title,
			'status'            => $class->post_status,
			'is_live'           => ( 'publish' === $class->post_status ) ? 1 : 0,
			'_pp_class_type'    => (string) get_post_meta( $class->ID, '_pp_class_type', true ),
			'_pp_instructor_id' => (int) get_post_meta( $class->ID, '_pp_instructor_id', true ),
			'_pp_facility_id'   => (int) get_post_meta( $class->ID, '_pp_facility_id', true ),
			'_pp_capacity'      => (int) get_post_meta( $class->ID, '_pp_capacity', true ),
			'_pp_day_of_week'   => (int) get_post_meta( $class->ID, '_pp_day_of_week', true ),
			'_pp_start_time'    => (string) get_post_meta( $class->ID, '_pp_start_time', true ),
			'_pp_end_time'      => (string) get_post_meta( $class->ID, '_pp_end_time', true ),
		);
	}

	private static function build_payload_by_id( $class_id ) {
		$class = get_post( $class_id );
		if ( ! $class || 'pp_class_session' !== $class->post_type ) {
			return new WP_Error( 'pp_not_found', __( 'Class session not found.', 'passpress' ), array( 'status' => 404 ) );
		}
		return self::build_payload( $class );
	}
}
