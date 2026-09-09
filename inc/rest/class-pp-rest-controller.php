<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helpers for passpress/v1 REST controllers. Permission checks here
 * mirror the current_user_can() checks already used in admin/*.php and the
 * wp_ajax_* handlers verbatim — this is a transport change, not a
 * permissions redesign.
 */
abstract class PP_REST_Controller {

	const NAMESPACE_V1 = 'passpress/v1';

	/**
	 * Shared with the Activity Log page's colored category badges
	 * (.passpress-activity-event-{category} in passpress-admin.css) so any
	 * screen showing activity-log rows — the full log or the Dashboard's
	 * recent-activity feed — colors them identically.
	 */
	const ACTIVITY_OBJECT_TYPE_CATEGORY = array(
		'membership' => 'membership',
		'user'       => 'visitor',
		'billing'    => 'billing',
		'booking'    => 'booking',
		'waitlist'   => 'booking',
		'facility'   => 'facility',
		'class'      => 'class',
		'plan'       => 'plan',
		'template'   => 'system',
		'order'      => 'billing',
	);

	abstract public function register_routes();

	public static function permission_manage() {
		return current_user_can( PP_Roles::CAP_MANAGE );
	}

	public static function permission_scan() {
		return current_user_can( PP_Roles::CAP_SCAN );
	}

	public static function permission_manage_or_classes() {
		return current_user_can( PP_Roles::CAP_MANAGE ) || current_user_can( PP_Roles::CAP_CLASSES );
	}

	/**
	 * @param object $log Activity log row (needs ->object_type, ->event).
	 */
	protected static function activity_category( $log ) {
		$type = isset( $log->object_type ) ? sanitize_key( $log->object_type ) : '';
		if ( $type && isset( self::ACTIVITY_OBJECT_TYPE_CATEGORY[ $type ] ) ) {
			return self::ACTIVITY_OBJECT_TYPE_CATEGORY[ $type ];
		}

		$event = isset( $log->event ) ? (string) $log->event : '';
		if ( 0 === strpos( $event, 'visitor_' ) ) {
			return 'visitor';
		}
		if ( 0 === strpos( $event, 'billing_' ) || 0 === strpos( $event, 'checkout_' ) || 0 === strpos( $event, 'shop_' ) ) {
			return 'billing';
		}
		if ( 0 === strpos( $event, 'membership_' ) ) {
			return 'membership';
		}
		if ( false !== strpos( $event, 'booking' ) || 0 === strpos( $event, 'waitlist_' ) ) {
			return 'booking';
		}
		if ( 0 === strpos( $event, 'facility_' ) ) {
			return 'facility';
		}
		if ( 0 === strpos( $event, 'class_' ) ) {
			return 'class';
		}
		if ( 0 === strpos( $event, 'membership_plan_' ) ) {
			return 'plan';
		}

		return 'system';
	}
}
