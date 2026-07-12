<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Theme Park', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Single-Day Ticket', 'passpress' ),
			'price'             => 45,
			'plan_type'         => 'one_time',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Student Single-Day Ticket', 'passpress' ),
			'price'             => 35,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Annual Pass', 'passpress' ),
			'price'             => 299,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Family Annual Pass', 'passpress' ),
			'price'             => 799,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Main Ride Zone', 'passpress' ),
			'facility_type' => 'amusement_ride',
			'capacity'      => 200,
		),
		array(
			'name'          => __( 'Family Zone', 'passpress' ),
			'facility_type' => 'childrens_park',
			'capacity'      => 100,
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
