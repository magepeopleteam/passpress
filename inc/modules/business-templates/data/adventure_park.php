<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Adventure Park', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Single-Day Adventure Pass', 'passpress' ),
			'price'             => 55,
			'plan_type'         => 'one_time',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Student Adventure Pass', 'passpress' ),
			'price'             => 40,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Multi-Activity Day Pass', 'passpress' ),
			'price'             => 75,
			'plan_type'         => 'daily_pass',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Annual Adventure Pass', 'passpress' ),
			'price'             => 349,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Adventure Zone', 'passpress' ),
			'facility_type' => 'childrens_park',
			'capacity'      => 60,
		),
		array(
			'name'          => __( 'Zip Line & Rides', 'passpress' ),
			'facility_type' => 'amusement_ride',
			'capacity'      => 40,
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
