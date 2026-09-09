<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * /passpress/v1/activity-log — direct REST port of
 * admin/PP_Activity_Log_Page.php (event labels + category classification
 * kept identical so the colored badges match).
 */
class PP_REST_Activity_Log extends PP_REST_Controller {

	const EVENT_LABELS = array(
		'visitor_pass_issued'                 => 'Visitor pass issued',
		'visitor_account_created'             => 'Visitor account created',
		'membership_issued'                   => 'Membership issued',
		'membership_renewed'                  => 'Membership renewed',
		'membership_status_changed'           => 'Membership status changed',
		'facility_created'                    => 'Facility created',
		'facility_updated'                    => 'Facility updated',
		'class_session_created'               => 'Class created',
		'class_session_updated'               => 'Class updated',
		'class_booking_created'               => 'Class booked',
		'booking_created'                     => 'Booking created',
		'booking_status_changed'              => 'Booking status changed',
		'billing_paid'                        => 'Payment confirmed',
		'billing_pending_manual_confirmation' => 'Payment pending',
		'business_template_imported'          => 'Template imported',
		'waitlist_joined'                     => 'Waitlist joined',
		'waitlist_notified'                   => 'Waitlist notified',
		'membership_plan_created'             => 'Plan created',
		'membership_plan_updated'             => 'Plan updated',
		'checkout_gift'                       => 'Gift checkout',
		'renewal_reminder_sent'               => 'Renewal reminder sent',
		'booking_reminder_sent'               => 'Booking reminder sent',
		'birthday_greeting_sent'              => 'Birthday greeting sent',
	);

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/activity-log',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_logs' ),
				'permission_callback' => array( __CLASS__, 'permission_manage' ),
			)
		);
	}

	public function list_logs( $request ) {
		$logs  = PP_Activity_Logger::get_recent( 100 );
		$items = array_map( array( __CLASS__, 'build_row' ), $logs );
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * @param object $log
	 */
	private static function build_row( $log ) {
		$user_name = self::user_name( $log->user_id );

		return array(
			'date_label'  => pp_format_date( substr( (string) $log->created_at, 0, 10 ) ),
			'time_label'  => ( $ts = strtotime( (string) $log->created_at ) ) ? date_i18n( get_option( 'time_format' ), $ts ) : '',
			'event_label' => self::event_label( $log->event ),
			'category'    => self::activity_category( $log ),
			'message'     => $log->message,
			'user_name'   => $user_name,
		);
	}

	private static function user_name( $user_id ) {
		if ( ! $user_id ) {
			return __( 'System', 'passpress' );
		}
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown', 'passpress' );
	}

	private static function event_label( $event ) {
		$event = (string) $event;
		if ( isset( self::EVENT_LABELS[ $event ] ) ) {
			return self::EVENT_LABELS[ $event ];
		}
		return ucwords( str_replace( '_', ' ', $event ) );
	}
}
