<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * No golf-course-specific facility_type exists in PP_Facility_CPT — the
 * clubhouse ('club_house') is what actually gets scanned/booked in practice
 * (tee times aren't modeled as a facility booking in this build), so that's
 * the facility seeded here.
 */
return array(
	'label'          => __( 'Golf Club', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Golf Membership', 'passpress' ),
			'price'             => 150,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Yearly Golf Membership', 'passpress' ),
			'price'             => 1500,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Corporate Membership', 'passpress' ),
			'price'             => 3000,
			'plan_type'         => 'corporate',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'weekday_only',
		),
		array(
			'name'              => __( 'Lifetime Membership', 'passpress' ),
			'price'             => 15000,
			'plan_type'         => 'lifetime',
			'duration_value'    => 0,
			'duration_unit'     => 'lifetime',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Clubhouse', 'passpress' ),
			'facility_type' => 'club_house',
			'capacity'      => 80,
		),
	),
	'class_sessions' => array(),
	'pages'          => array(
		array(
			'title'     => __( 'My Pass', 'passpress' ),
			'shortcode' => 'passpress_my_pass',
		),
		array(
			'title'     => __( 'Membership Plans', 'passpress' ),
			'shortcode' => 'passpress_membership_plans',
		),
	),
);
