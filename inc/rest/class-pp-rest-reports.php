<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/reports — direct REST port of admin/PP_Reports_Page.php.
 * Bar-chart widths are computed server-side (same max-based round() as the
 * old render_bar_list()) so the React bars match pixel-for-pixel rather than
 * re-deriving the max in JS.
 */
class PP_REST_Reports extends PP_REST_Controller {

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/reports',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_reports' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);
	}

	public function get_reports( $request ) {
		$end_date   = sanitize_text_field( (string) ( $request->get_param( 'end_date' ) ?: current_time( 'Y-m-d' ) ) );
		$start_date = sanitize_text_field( (string) ( $request->get_param( 'start_date' ) ?: gmdate( 'Y-m-d', strtotime( $end_date . ' -29 days' ) ) ) );

		$settings = pp_get_settings();
		$symbol   = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';

		$revenue     = PP_Reports::get_revenue( $start_date, $end_date );
		$growth      = PP_Reports::get_membership_growth( $start_date, $end_date );
		$expired     = PP_Reports::get_expired_members( 20 );
		$renewal     = PP_Reports::get_renewal_rate( $start_date, $end_date );
		$facilities  = PP_Reports::get_facility_usage( $start_date, $end_date );
		$plans       = PP_Reports::get_popular_plans();
		$payments    = PP_Reports::get_payment_report( $start_date, $end_date );
		$instructors = PP_Reports::get_trainer_performance( $start_date, $end_date );

		$gw_labels = array( 'offline' => 'Offline', 'stripe' => 'Stripe', 'paypal' => 'PayPal' );

		$payment_rows = array();
		foreach ( $payments as $gateway => $statuses ) {
			foreach ( $statuses as $status => $data ) {
				$key            = strtolower( (string) $gateway );
				$payment_rows[] = array(
					'gateway'       => $key,
					'gateway_label' => isset( $gw_labels[ $key ] ) ? $gw_labels[ $key ] : ucfirst( $key ),
					'status'        => $status,
					'status_label'  => ucfirst( $status ),
					'count'         => $data['count'],
					'total_label'   => $symbol . number_format_i18n( $data['total'], 2 ),
				);
			}
		}

		$expired_rows = array();
		foreach ( $expired as $m ) {
			$user           = get_userdata( $m->user_id );
			$expired_rows[] = array(
				'member_name'      => $user ? $user->display_name : __( 'Unknown', 'passpress' ),
				'plan_title'       => get_the_title( $m->plan_id ),
				'expiry_date_label'=> pp_format_date( $m->expiry_date ),
			);
		}

		return rest_ensure_response(
			array(
				'start_date'       => $start_date,
				'end_date'         => $end_date,
				'currency_symbol'  => $symbol,
				'revenue_total'    => $symbol . number_format_i18n( $revenue['total'], 2 ),
				'new_members'      => (int) $growth['total'],
				'renewal_display'  => null === $renewal['rate_percent'] ? '—' : $renewal['rate_percent'] . '%',
				'renewal_renewed'  => (int) $renewal['renewed'],
				'renewal_lapsed'   => (int) $renewal['lapsed'],
				'revenue_by_day'   => self::bar_list( $revenue['by_day'], function ( $v ) use ( $symbol ) { return $symbol . number_format_i18n( $v, 2 ); } ),
				'growth_by_day'    => self::bar_list( $growth['by_day'] ),
				'expired_members'  => $expired_rows,
				'facilities'       => array_values( $facilities ),
				'plans'            => array_values( $plans ),
				'payments'         => $payment_rows,
				'instructors'      => array_values( $instructors ),
			)
		);
	}

	/**
	 * @param array         $by_day
	 * @param callable|null $format_callback
	 */
	private static function bar_list( $by_day, $format_callback = null ) {
		if ( ! $by_day ) {
			return array();
		}
		$max  = max( 1, (float) max( $by_day ) );
		$rows = array();
		foreach ( $by_day as $day => $value ) {
			$rows[] = array(
				'day'        => $day,
				'date_label' => pp_format_date( $day ),
				'value_label'=> $format_callback ? call_user_func( $format_callback, $value ) : number_format_i18n( $value ),
				'width_pct'  => round( ( (float) $value / $max ) * 100 ),
			);
		}
		return $rows;
	}
}
