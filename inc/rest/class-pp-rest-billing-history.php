<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/billing-history — direct REST port of
 * admin/PP_Billing_History_Page.php. Read-mostly ledger; the only writes are
 * confirming/failing a pending Offline payment (Stripe/PayPal confirm
 * themselves via their gateway return/webhook handlers, untouched here).
 */
class PP_REST_Billing_History extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/billing-history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_rows' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/billing-history/(?P<id>\d+)/(?P<action>confirm|fail)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'do_action' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);
	}

	public function list_rows( $request ) {
		$rows  = PP_Billing_History::get_recent( 100 );
		$items = array_map( array( __CLASS__, 'build_row' ), $rows );

		$counts = array( 'paid' => 0, 'pending' => 0, 'failed' => 0, 'other' => 0 );
		foreach ( $rows as $row ) {
			if ( PP_Billing_History::STATUS_PAID === $row->status ) {
				++$counts['paid'];
			} elseif ( PP_Billing_History::STATUS_PENDING === $row->status ) {
				++$counts['pending'];
			} elseif ( PP_Billing_History::STATUS_FAILED === $row->status ) {
				++$counts['failed'];
			} else {
				++$counts['other'];
			}
		}

		return rest_ensure_response( array( 'items' => $items, 'counts' => $counts ) );
	}

	public function do_action( $request ) {
		$id     = (int) $request['id'];
		$action = (string) $request['action'];

		$row = PP_Billing_History::get( $id );
		if ( ! $row || PP_Billing_History::STATUS_PENDING !== $row->status ) {
			return new WP_Error( 'pp_not_pending', __( 'This payment is not pending.', 'passpress' ), array( 'status' => 400 ) );
		}

		if ( 'confirm' === $action ) {
			PP_Billing::complete_payment( $row->checkout_token, 'offline', 'manual-admin-confirm-' . get_current_user_id(), 'Manually confirmed by staff.' );
			return rest_ensure_response( array( 'message' => __( 'Payment confirmed and membership issued.', 'passpress' ) ) );
		}

		$reason = 'Manually marked failed by staff.';
		PP_Billing_History::mark_failed( $id, $reason );
		PP_Notifications::payment_failed( $row, $reason );
		return rest_ensure_response( array( 'message' => __( 'Payment marked as failed.', 'passpress' ) ) );
	}

	/**
	 * @param object $row
	 */
	private static function build_row( $row ) {
		$user  = get_userdata( $row->user_id );
		$gw    = array( 'offline' => 'Offline', 'stripe' => 'Stripe', 'paypal' => 'PayPal' );
		$key   = strtolower( (string) $row->gateway );

		return array(
			'id'              => (int) $row->id,
			'date_label'      => pp_format_date( $row->created_at ),
			'time_label'      => ( $ts = strtotime( $row->created_at ) ) ? date_i18n( get_option( 'time_format' ), $ts ) : '',
			'member_name'     => $user ? $user->display_name : __( 'Unknown', 'passpress' ),
			'member_email'    => $user ? $user->user_email : '',
			'plan_title'      => get_the_title( $row->plan_id ),
			'type'            => ucfirst( $row->type ),
			'gateway'         => $key,
			'gateway_label'   => isset( $gw[ $key ] ) ? $gw[ $key ] : ucfirst( (string) $row->gateway ),
			'amount'          => number_format_i18n( (float) $row->amount, 2 ),
			'currency'        => strtoupper( $row->currency ),
			'coupon_code'     => $row->coupon_code,
			'discount_amount' => $row->coupon_code ? number_format_i18n( (float) $row->discount_amount, 2 ) : '',
			'status'          => $row->status,
			'status_label'    => ucfirst( $row->status ),
			'can_confirm'     => ( PP_Billing_History::STATUS_PENDING === $row->status && 'offline' === $key ),
		);
	}
}
