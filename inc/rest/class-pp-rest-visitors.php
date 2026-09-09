<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/visitors — direct REST port of admin/PP_Visitors_List.php.
 */
class PP_REST_Visitors extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/visitors',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_visitors' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_visitor' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/visitors/invitations/(?P<user_id>\d+)/finalize',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'finalize_invitation' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/visitors/(?P<id>\d+)/(?P<action>renew|cancel)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'do_action' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);
	}

	public function list_visitors( $request ) {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$search = sanitize_text_field( (string) $request->get_param( 's' ) );
		$paged  = max( 1, absint( $request->get_param( 'paged' ) ?: 1 ) );

		$result = PP_Visitor::get_history( array( 'status' => $status, 'search' => $search, 'paged' => $paged, 'per_page' => 20 ) );
		$items  = array_map( array( __CLASS__, 'build_row' ), $result['items'] );

		$invitations = array_map( array( __CLASS__, 'build_invitation' ), PP_Visitor::get_pending_invitations() );

		$plans = get_posts( array( 'post_type' => 'pp_membership_plan', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ) );

		return rest_ensure_response(
			array(
				'items'       => $items,
				'total'       => (int) $result['total'],
				'per_page'    => (int) $result['per_page'],
				'invitations' => $invitations,
				'plans'       => array_map( function ( $p ) { return array( 'id' => (int) $p->ID, 'name' => $p->post_title ); }, $plans ),
			)
		);
	}

	public function register_visitor( $request ) {
		$name    = sanitize_text_field( (string) $request->get_param( 'visitor_name' ) );
		$email   = sanitize_email( (string) $request->get_param( 'visitor_email' ) );
		$phone   = sanitize_text_field( (string) $request->get_param( 'visitor_phone' ) );
		$plan_id = absint( $request->get_param( 'plan_id' ) );

		$result = PP_Visitor::register( $name, $email, $phone, $plan_id );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array( 'message' => sprintf( __( 'Visitor pass %s issued.', 'passpress' ), $result->membership_number ) )
		);
	}

	public function finalize_invitation( $request ) {
		$guest_user_id = (int) $request['user_id'];
		$plan_id       = absint( $request->get_param( 'plan_id' ) );

		$result = PP_Visitor::finalize_invitation( $guest_user_id, $plan_id );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array( 'message' => sprintf( __( 'Visitor pass %s issued.', 'passpress' ), $result->membership_number ) )
		);
	}

	public function do_action( $request ) {
		$id     = (int) $request['id'];
		$action = (string) $request['action'];

		$result = ( 'renew' === $action ) ? PP_Membership_Renewal::renew( $id ) : PP_Membership_Status::cancel( $id );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}
		if ( false === $result ) {
			return new WP_Error( 'pp_not_found', __( 'Visitor pass not found.', 'passpress' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'message' => __( 'Visitor pass updated.', 'passpress' ) ) );
	}

	/**
	 * @param object $membership
	 */
	private static function build_row( $membership ) {
		$host  = PP_Visitor::get_host( $membership->user_id );
		$user  = get_userdata( $membership->user_id );
		$name  = $user ? $user->display_name : __( 'Unknown', 'passpress' );
		$email = ( $user && ! str_ends_with( $user->user_email, '@passpress.invalid' ) ) ? $user->user_email : '';

		return array(
			'id'                => (int) $membership->id,
			'membership_number' => $membership->membership_number,
			'visitor_name'      => $name,
			'visitor_email'     => $email,
			'host_name'         => $host ? $host->display_name : '',
			'plan_id'           => (int) $membership->plan_id,
			'plan_title'        => get_the_title( $membership->plan_id ),
			'status'            => $membership->status,
			'status_label'      => pp_status_label( $membership->status ),
			'expiry_date_label' => pp_format_date( $membership->expiry_date ),
			'pin_code'          => $membership->pin_code,
		);
	}

	/**
	 * @param WP_User $guest
	 */
	private static function build_invitation( $guest ) {
		$host = PP_Visitor::get_host( $guest->ID );
		return array(
			'guest_user_id' => (int) $guest->ID,
			'guest_name'    => $guest->display_name,
			'guest_email'   => str_ends_with( $guest->user_email, '@passpress.invalid' ) ? '' : $guest->user_email,
			'host_name'     => $host ? $host->display_name : '',
		);
	}
}
