<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Badminton Club', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Court Membership', 'passpress' ),
			'price'             => 30,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Court Membership', 'passpress' ),
			'price'             => 300,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Student Membership', 'passpress' ),
			'price'             => 20,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Daily Court Pass', 'passpress' ),
			'price'             => 8,
			'plan_type'         => 'daily_pass',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Badminton Hall', 'passpress' ),
			'facility_type' => 'badminton_court',
			'capacity'      => 12,
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
