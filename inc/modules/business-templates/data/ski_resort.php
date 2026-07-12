<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * No ski-slope-specific facility_type exists in PP_Facility_CPT — the lodge
 * ('club_house') is what's seeded here, and the plan lineup instead carries
 * the real differentiation (daily lift ticket / weekly pass / season pass),
 * which is how ski resorts actually structure pricing.
 */
return array(
	'label'          => __( 'Ski Resort', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Daily Lift Ticket', 'passpress' ),
			'price'             => 65,
			'plan_type'         => 'daily_pass',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Weekly Ski Pass', 'passpress' ),
			'price'             => 350,
			'plan_type'         => 'weekly',
			'duration_value'    => 1,
			'duration_unit'     => 'week',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Season Pass', 'passpress' ),
			'price'             => 899,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Family Season Pass', 'passpress' ),
			'price'             => 2499,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Ski Lodge', 'passpress' ),
			'facility_type' => 'club_house',
			'capacity'      => 120,
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
