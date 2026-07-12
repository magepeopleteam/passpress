<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Water Park', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Single-Day Ticket', 'passpress' ),
			'price'             => 35,
			'plan_type'         => 'one_time',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Student Single-Day Ticket', 'passpress' ),
			'price'             => 25,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Family Day Pass', 'passpress' ),
			'price'             => 99,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Season Pass', 'passpress' ),
			'price'             => 199,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Wave Pool', 'passpress' ),
			'facility_type' => 'swimming_pool',
			'capacity'      => 150,
		),
		array(
			'name'          => __( 'Water Slides', 'passpress' ),
			'facility_type' => 'amusement_ride',
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
