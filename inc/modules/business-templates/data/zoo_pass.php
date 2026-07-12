<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Zoo Pass', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Single Day Pass', 'passpress' ),
			'price'             => 20,
			'plan_type'         => 'one_time',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Student Day Pass', 'passpress' ),
			'price'             => 12,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Annual Zoo Pass', 'passpress' ),
			'price'             => 90,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Family Annual Pass', 'passpress' ),
			'price'             => 220,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Kids Corner', 'passpress' ),
			'facility_type' => 'childrens_park',
			'capacity'      => 50,
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
