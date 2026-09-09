<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/plans — direct REST port of admin/PP_Plans_List.php's AJAX
 * handlers (pp_create_plan/pp_get_plan/pp_update_plan) and card-grid data.
 * Same meta keys/sanitization as PP_Plans_List::save_plan_meta_from_request(),
 * just reading from a WP_REST_Request instead of $_POST.
 */
class PP_REST_Plans extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/plans',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_plans' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_plan' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/plans/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_plan' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_plan' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);
	}

	public function list_plans( $request ) {
		$plans = get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		$sold  = self::get_sold_counts();
		$items = array();
		foreach ( $plans as $plan ) {
			$item          = self::build_payload( $plan );
			$item['sold']  = isset( $sold[ $plan->ID ] ) ? $sold[ $plan->ID ] : 0;
			$items[]       = $item;
		}

		$settings = pp_get_settings();

		return rest_ensure_response(
			array(
				'items'           => $items,
				'options'         => array(
					'plan_types'         => PP_Membership_Plan_CPT::plan_types(),
					'duration_units'     => PP_Membership_Plan_CPT::duration_units(),
					'entry_restrictions' => PP_Membership_Plan_CPT::entry_restrictions(),
				),
				'currency_symbol' => $settings['currency_symbol'],
			)
		);
	}

	public function get_plan( $request ) {
		$payload = self::build_payload_by_id( (int) $request['id'] );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		return rest_ensure_response( $payload );
	}

	public function create_plan( $request ) {
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( ! $title ) {
			return new WP_Error( 'pp_missing_title', __( 'Please enter a plan name.', 'passpress' ), array( 'status' => 400 ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'pp_membership_plan',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::save_meta( $post_id, $request );
		PP_Activity_Logger::log( 'membership_plan_created', 'plan', $post_id, sprintf( 'Plan "%s" created.', $title ) );

		return rest_ensure_response(
			array(
				'message' => __( 'Plan created!', 'passpress' ),
				'plan_id' => $post_id,
			)
		);
	}

	public function update_plan( $request ) {
		$plan_id = (int) $request['id'];
		$plan    = get_post( $plan_id );
		if ( ! $plan || 'pp_membership_plan' !== $plan->post_type ) {
			return new WP_Error( 'pp_not_found', __( 'Plan not found.', 'passpress' ), array( 'status' => 404 ) );
		}

		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( ! $title ) {
			return new WP_Error( 'pp_missing_title', __( 'Please enter a plan name.', 'passpress' ), array( 'status' => 400 ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $plan_id,
				'post_title'  => $title,
				'post_status' => $request->get_param( 'is_live' ) ? 'publish' : 'draft',
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		self::save_meta( $plan_id, $request );
		PP_Activity_Logger::log( 'membership_plan_updated', 'plan', $plan_id, sprintf( 'Plan "%s" updated.', $title ) );

		return rest_ensure_response(
			array(
				'message' => __( 'Plan saved!', 'passpress' ),
				'plan_id' => $plan_id,
			)
		);
	}

	/**
	 * @param int             $post_id
	 * @param WP_REST_Request $request
	 */
	private static function save_meta( $post_id, $request ) {
		update_post_meta( $post_id, '_pp_price', (float) $request->get_param( '_pp_price' ) );
		update_post_meta( $post_id, '_pp_plan_type', sanitize_key( (string) ( $request->get_param( '_pp_plan_type' ) ?: 'monthly' ) ) );
		update_post_meta( $post_id, '_pp_duration_value', absint( $request->get_param( '_pp_duration_value' ) ?: 1 ) );
		update_post_meta( $post_id, '_pp_duration_unit', sanitize_key( (string) ( $request->get_param( '_pp_duration_unit' ) ?: 'month' ) ) );
		update_post_meta( $post_id, '_pp_entry_restriction', sanitize_key( (string) ( $request->get_param( '_pp_entry_restriction' ) ?: 'none' ) ) );
		update_post_meta( $post_id, '_pp_time_restriction_start', sanitize_text_field( (string) $request->get_param( '_pp_time_restriction_start' ) ) );
		update_post_meta( $post_id, '_pp_time_restriction_end', sanitize_text_field( (string) $request->get_param( '_pp_time_restriction_end' ) ) );
		update_post_meta( $post_id, '_pp_max_entries_per_day', absint( $request->get_param( '_pp_max_entries_per_day' ) ) );
		update_post_meta( $post_id, '_pp_features', sanitize_textarea_field( (string) $request->get_param( '_pp_features' ) ) );
		update_post_meta( $post_id, '_pp_most_popular', $request->get_param( '_pp_most_popular' ) ? 1 : 0 );

		if ( class_exists( 'PP_Shop_WooCommerce' ) && PP_Shop_WooCommerce::is_available() ) {
			PP_Shop_WooCommerce::sync_product_for_plan( $post_id );
		}
	}

	/**
	 * @param WP_Post $plan
	 */
	private static function build_payload( $plan ) {
		return array(
			'plan_id'                    => (int) $plan->ID,
			'title'                      => $plan->post_title,
			'status'                     => $plan->post_status,
			'is_live'                    => ( 'publish' === $plan->post_status ) ? 1 : 0,
			'_pp_price'                  => (float) get_post_meta( $plan->ID, '_pp_price', true ),
			'_pp_plan_type'              => (string) get_post_meta( $plan->ID, '_pp_plan_type', true ),
			'_pp_duration_value'         => (int) get_post_meta( $plan->ID, '_pp_duration_value', true ),
			'_pp_duration_unit'          => (string) get_post_meta( $plan->ID, '_pp_duration_unit', true ),
			'_pp_entry_restriction'      => (string) get_post_meta( $plan->ID, '_pp_entry_restriction', true ),
			'_pp_time_restriction_start' => (string) get_post_meta( $plan->ID, '_pp_time_restriction_start', true ),
			'_pp_time_restriction_end'   => (string) get_post_meta( $plan->ID, '_pp_time_restriction_end', true ),
			'_pp_max_entries_per_day'    => (int) get_post_meta( $plan->ID, '_pp_max_entries_per_day', true ),
			'_pp_features'               => (string) get_post_meta( $plan->ID, '_pp_features', true ),
			'_pp_most_popular'           => (int) get_post_meta( $plan->ID, '_pp_most_popular', true ),
		);
	}

	private static function build_payload_by_id( $plan_id ) {
		$plan = get_post( $plan_id );
		if ( ! $plan || 'pp_membership_plan' !== $plan->post_type ) {
			return new WP_Error( 'pp_not_found', __( 'Plan not found.', 'passpress' ), array( 'status' => 404 ) );
		}
		return self::build_payload( $plan );
	}

	private static function get_sold_counts() {
		$counts = array();
		foreach ( PP_Reports::get_popular_plans() as $row ) {
			$counts[ $row['plan_id'] ] = $row['count'];
		}
		return $counts;
	}
}
