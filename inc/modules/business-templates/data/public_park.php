<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Public Park', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Daily Entry', 'passpress' ),
			'price'             => 3,
			'plan_type'         => 'daily_pass',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Monthly Pass', 'passpress' ),
			'price'             => 10,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Annual Park Pass', 'passpress' ),
			'price'             => 49,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Family Annual Pass', 'passpress' ),
			'price'             => 99,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Kids Play Area', 'passpress' ),
			'facility_type' => 'childrens_park',
			'capacity'      => 40,
		),
		array(
			'name'          => __( 'Community Room', 'passpress' ),
			'facility_type' => 'indoor_games',
			'capacity'      => 20,
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
