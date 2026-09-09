<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/memberships — direct REST port of admin/PP_Memberships_List.php
 * (member_type='member' only; visitor passes are inc/rest/class-pp-rest-visitors.php).
 * Row fields are pre-formatted server-side (dates, expiring-soon label, status
 * label) so the React table matches the old PHP output exactly rather than
 * re-implementing date_i18n()/_n() pluralization in JS.
 */
class PP_REST_Memberships extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/memberships',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_memberships' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'issue_membership' ),
					'permission_callback' => array( __CLASS__, 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/memberships/(?P<id>\d+)/(?P<action>renew|freeze|suspend|reactivate|cancel)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'do_action' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);
	}

	public function list_memberships( $request ) {
		$status     = sanitize_key( (string) $request->get_param( 'status' ) );
		$search     = sanitize_text_field( (string) $request->get_param( 's' ) );
		$plan_scope = sanitize_key( (string) $request->get_param( 'plan_scope' ) );
		$paged      = max( 1, absint( $request->get_param( 'paged' ) ?: 1 ) );

		$counts = PP_Query::membership_status_counts( 'member' );
		$result = PP_Query::get_memberships(
			array(
				'status'      => $status,
				'search'      => $search,
				'paged'       => $paged,
				'per_page'    => 10,
				'member_type' => 'member',
				'plan_scope'  => $plan_scope,
			)
		);

		$items = array_map( array( __CLASS__, 'build_row' ), $result['items'] );

		$plans = get_posts( array( 'post_type' => 'pp_membership_plan', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
		$users = get_users( array( 'fields' => array( 'ID', 'display_name' ) ) );

		return rest_ensure_response(
			array(
				'items'    => $items,
				'total'    => (int) $result['total'],
				'per_page' => (int) $result['per_page'],
				'counts'   => $counts,
				'plans'    => array_map( function ( $p ) { return array( 'id' => (int) $p->ID, 'name' => $p->post_title ); }, $plans ),
				'users'    => array_map( function ( $u ) { return array( 'id' => (int) $u->ID, 'name' => $u->display_name ); }, $users ),
			)
		);
	}

	public function issue_membership( $request ) {
		$user_id = absint( $request->get_param( 'user_id' ) );
		$plan_id = absint( $request->get_param( 'plan_id' ) );

		$result = PP_Membership::issue( $user_id, $plan_id );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}

		return rest_ensure_response(
			array(
				'message' => sprintf( __( 'Membership %s issued.', 'passpress' ), $result->membership_number ),
			)
		);
	}

	public function do_action( $request ) {
		$id     = (int) $request['id'];
		$action = (string) $request['action'];

		switch ( $action ) {
			case 'renew':
				$result = PP_Membership_Renewal::renew( $id );
				break;
			case 'freeze':
				$result = PP_Membership_Status::freeze( $id );
				break;
			case 'suspend':
				$result = PP_Membership_Status::suspend( $id );
				break;
			case 'reactivate':
				$result = PP_Membership_Status::reactivate( $id );
				break;
			case 'cancel':
				$result = PP_Membership_Status::cancel( $id );
				break;
			default:
				return new WP_Error( 'pp_invalid_action', __( 'Unknown action.', 'passpress' ), array( 'status' => 400 ) );
		}

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}
		if ( false === $result ) {
			return new WP_Error( 'pp_not_found', __( 'Membership not found.', 'passpress' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( array( 'message' => __( 'Membership updated.', 'passpress' ) ) );
	}

	/**
	 * @param object $membership pp_memberships row.
	 */
	private static function build_row( $membership ) {
		$user   = get_userdata( $membership->user_id );
		$name   = $user ? $user->display_name : __( 'Unknown', 'passpress' );
		$email  = $user ? $user->user_email : '';

		$expiring  = self::is_expiring_soon( $membership );
		$days_left = $expiring ? (int) ceil( ( strtotime( $membership->expiry_date ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS ) : 0;

		return array(
			'id'                 => (int) $membership->id,
			'membership_number'  => $membership->membership_number,
			'member_name'        => $name,
			'member_email'       => $email,
			'plan_id'            => (int) $membership->plan_id,
			'plan_title'         => get_the_title( $membership->plan_id ),
			'status'             => $membership->status,
			'status_label'       => pp_status_label( $membership->status ),
			'start_date_label'   => pp_format_date( $membership->start_date ),
			'expiry_date_label'  => pp_format_date( $membership->expiry_date ),
			'is_expiring'        => $expiring,
			'expiring_label'     => $expiring
				? sprintf( _n( 'Expiring in %d day', 'Expiring in %d days', $days_left, 'passpress' ), $days_left )
				: sprintf( __( 'Until %s', 'passpress' ), pp_format_date( $membership->expiry_date ) ),
			'pin_code'           => $membership->pin_code,
		);
	}

	/**
	 * @param object $membership
	 */
	private static function is_expiring_soon( $membership ) {
		if ( PP_Membership::STATUS_ACTIVE !== $membership->status ) {
			return false;
		}
		$billing_settings = PP_Billing::get_settings();
		$days             = max( 1, (int) $billing_settings['renewal_reminder_days'] );
		$today            = current_time( 'Y-m-d' );
		$soon             = gmdate( 'Y-m-d', strtotime( "{$today} +{$days} days" ) );
		return $membership->expiry_date >= $today && $membership->expiry_date <= $soon;
	}
}
