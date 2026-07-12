<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Football Academy', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Training Membership', 'passpress' ),
			'price'             => 35,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Training Membership', 'passpress' ),
			'price'             => 350,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Trial Session', 'passpress' ),
			'price'             => 10,
			'plan_type'         => 'one_time',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Main Football Pitch', 'passpress' ),
			'facility_type' => 'football_ground',
			'capacity'      => 30,
		),
	),
	'class_sessions' => array(
		array(
			'name'          => __( 'Youth Football Training', 'passpress' ),
			'class_type'    => 'football_training',
			'facility_name' => __( 'Main Football Pitch', 'passpress' ),
			'capacity'      => 22,
			'day_of_week'   => 6,
			'start_time'    => '09:00',
			'end_time'      => '11:00',
		),
	),
	'pages'          => array(
		array(
			'title'     => __( 'My Pass', 'passpress' ),
			'shortcode' => 'passpress_my_pass',
		),
		array(
			'title'     => __( 'Membership Plans', 'passpress' ),
			'shortcode' => 'passpress_membership_plans',
		),
		array(
			'title'     => __( 'Class Schedule', 'passpress' ),
			'shortcode' => 'passpress_class_schedule',
		),
	),
);
