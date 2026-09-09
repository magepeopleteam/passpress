<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/coupons — direct REST port of admin/PP_Coupons_List.php.
 * Note the pp_coupon CPT itself has show_in_rest => false; irrelevant here
 * since this is a bespoke controller, not the default posts controller.
 */
class PP_REST_Coupons extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/coupons',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_coupons' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_coupon' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/coupons/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_coupon' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_coupon' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);
	}

	public function list_coupons( $request ) {
		$coupons = get_posts(
			array(
				'post_type'      => 'pp_coupon',
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'draft' ),
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$usage = self::get_usage_counts();
		$items = array();
		foreach ( $coupons as $coupon ) {
			$item         = self::build_payload( $coupon );
			$item['used'] = isset( $usage[ $coupon->post_title ] ) ? $usage[ $coupon->post_title ] : 0;
			$items[]      = $item;
		}

		$plans = get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$settings = pp_get_settings();

		return rest_ensure_response(
			array(
				'items'           => $items,
				'plans'           => array_map(
					function ( $plan ) {
						return array( 'id' => (int) $plan->ID, 'name' => $plan->post_title );
					},
					$plans
				),
				'currency_symbol' => $settings['currency_symbol'],
			)
		);
	}

	public function get_coupon( $request ) {
		$payload = self::build_payload_by_id( (int) $request['id'] );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		return rest_ensure_response( $payload );
	}

	public function create_coupon( $request ) {
		$title = strtoupper( sanitize_text_field( (string) $request->get_param( 'title' ) ) );
		if ( ! $title ) {
			return new WP_Error( 'pp_missing_title', __( 'Please enter a coupon code.', 'passpress' ), array( 'status' => 400 ) );
		}
		if ( self::code_exists( $title ) ) {
			return new WP_Error( 'pp_duplicate_code', __( 'That coupon code already exists.', 'passpress' ), array( 'status' => 400 ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'pp_coupon',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		self::save_meta( $post_id, $request );
		PP_Activity_Logger::log( 'coupon_created', 'coupon', $post_id, sprintf( 'Coupon "%s" created.', $title ) );

		return rest_ensure_response(
			array(
				'message'   => __( 'Coupon created!', 'passpress' ),
				'coupon_id' => $post_id,
			)
		);
	}

	public function update_coupon( $request ) {
		$coupon_id = (int) $request['id'];
		$coupon    = get_post( $coupon_id );
		if ( ! $coupon || 'pp_coupon' !== $coupon->post_type ) {
			return new WP_Error( 'pp_not_found', __( 'Coupon not found.', 'passpress' ), array( 'status' => 404 ) );
		}

		$title = strtoupper( sanitize_text_field( (string) $request->get_param( 'title' ) ) );
		if ( ! $title ) {
			return new WP_Error( 'pp_missing_title', __( 'Please enter a coupon code.', 'passpress' ), array( 'status' => 400 ) );
		}
		if ( self::code_exists( $title, $coupon_id ) ) {
			return new WP_Error( 'pp_duplicate_code', __( 'That coupon code already exists.', 'passpress' ), array( 'status' => 400 ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $coupon_id,
				'post_title'  => $title,
				'post_status' => $request->get_param( 'is_live' ) ? 'publish' : 'draft',
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		self::save_meta( $coupon_id, $request );
		PP_Activity_Logger::log( 'coupon_updated', 'coupon', $coupon_id, sprintf( 'Coupon "%s" updated.', $title ) );

		return rest_ensure_response(
			array(
				'message'   => __( 'Coupon saved!', 'passpress' ),
				'coupon_id' => $coupon_id,
			)
		);
	}

	/**
	 * @param int             $post_id
	 * @param WP_REST_Request $request
	 */
	private static function save_meta( $post_id, $request ) {
		update_post_meta( $post_id, '_pp_active', $request->get_param( '_pp_active' ) ? 1 : 0 );
		update_post_meta( $post_id, '_pp_discount_type', ( 'fixed' === $request->get_param( '_pp_discount_type' ) ) ? 'fixed' : 'percent' );
		update_post_meta( $post_id, '_pp_discount_amount', (float) $request->get_param( '_pp_discount_amount' ) );

		$applicable_plans = array_map( 'absint', (array) $request->get_param( '_pp_applicable_plans' ) );
		update_post_meta( $post_id, '_pp_applicable_plans', $applicable_plans );

		update_post_meta( $post_id, '_pp_usage_limit_total', absint( $request->get_param( '_pp_usage_limit_total' ) ) );
		update_post_meta( $post_id, '_pp_usage_limit_per_user', absint( $request->get_param( '_pp_usage_limit_per_user' ) ?: 1 ) );
		update_post_meta( $post_id, '_pp_expiry_date', sanitize_text_field( (string) $request->get_param( '_pp_expiry_date' ) ) );
	}

	/**
	 * @param WP_Post $coupon
	 */
	private static function build_payload( $coupon ) {
		$active = get_post_meta( $coupon->ID, '_pp_active', true );
		$active = '' === $active ? 1 : (int) $active;

		$per_user = get_post_meta( $coupon->ID, '_pp_usage_limit_per_user', true );
		$per_user = '' === $per_user ? 1 : (int) $per_user;

		$plans = get_post_meta( $coupon->ID, '_pp_applicable_plans', true );
		$plans = is_array( $plans ) ? array_map( 'intval', $plans ) : array();

		return array(
			'coupon_id'                => (int) $coupon->ID,
			'title'                    => $coupon->post_title,
			'status'                   => $coupon->post_status,
			'is_live'                  => ( 'publish' === $coupon->post_status ) ? 1 : 0,
			'_pp_active'               => $active,
			'_pp_discount_type'        => (string) ( get_post_meta( $coupon->ID, '_pp_discount_type', true ) ?: 'percent' ),
			'_pp_discount_amount'      => (float) get_post_meta( $coupon->ID, '_pp_discount_amount', true ),
			'_pp_applicable_plans'     => $plans,
			'_pp_usage_limit_total'    => (int) get_post_meta( $coupon->ID, '_pp_usage_limit_total', true ),
			'_pp_usage_limit_per_user' => $per_user,
			'_pp_expiry_date'          => (string) get_post_meta( $coupon->ID, '_pp_expiry_date', true ),
		);
	}

	private static function build_payload_by_id( $coupon_id ) {
		$coupon = get_post( $coupon_id );
		if ( ! $coupon || 'pp_coupon' !== $coupon->post_type ) {
			return new WP_Error( 'pp_not_found', __( 'Coupon not found.', 'passpress' ), array( 'status' => 404 ) );
		}
		return self::build_payload( $coupon );
	}

	/**
	 * @param string $code
	 * @param int    $exclude_id
	 */
	private static function code_exists( $code, $exclude_id = 0 ) {
		global $wpdb;
		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'pp_coupon' AND post_status IN ('publish','draft') AND UPPER(post_title) = %s",
			$code
		);
		if ( $exclude_id ) {
			$sql .= $wpdb->prepare( ' AND ID != %d', $exclude_id );
		}
		$sql .= ' LIMIT 1';
		return (bool) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @return array code => paid redemption count
	 */
	private static function get_usage_counts() {
		global $wpdb;
		$table = $wpdb->prefix . 'pp_billing_history';
		$rows  = $wpdb->get_results( "SELECT coupon_code, COUNT(*) AS cnt FROM {$table} WHERE coupon_code != '' AND status = 'paid' GROUP BY coupon_code" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$counts = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$counts[ $row->coupon_code ] = (int) $row->cnt;
			}
		}
		return $counts;
	}
}
