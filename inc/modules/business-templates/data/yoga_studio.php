<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Yoga Studio', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Unlimited Membership', 'passpress' ),
			'price'             => 45,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Unlimited Membership', 'passpress' ),
			'price'             => 450,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Student Membership', 'passpress' ),
			'price'             => 30,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Drop-In Class', 'passpress' ),
			'price'             => 15,
			'plan_type'         => 'daily_pass',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Studio A', 'passpress' ),
			'facility_type' => 'indoor_games',
			'capacity'      => 20,
		),
	),
	'class_sessions' => array(
		array(
			'name'          => __( 'Morning Yoga', 'passpress' ),
			'class_type'    => 'yoga',
			'facility_name' => __( 'Studio A', 'passpress' ),
			'capacity'      => 15,
			'day_of_week'   => 1,
			'start_time'    => '07:00',
			'end_time'      => '08:00',
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
