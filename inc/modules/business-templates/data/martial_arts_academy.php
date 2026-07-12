<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Martial Arts Academy', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Membership', 'passpress' ),
			'price'             => 50,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Membership', 'passpress' ),
			'price'             => 500,
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
			'name'              => __( 'Family Membership', 'passpress' ),
			'price'             => 89,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Dojo', 'passpress' ),
			'facility_type' => 'indoor_games',
			'capacity'      => 30,
		),
	),
	'class_sessions' => array(
		array(
			'name'          => __( 'Karate Class', 'passpress' ),
			'class_type'    => 'karate',
			'facility_name' => __( 'Dojo', 'passpress' ),
			'capacity'      => 20,
			'day_of_week'   => 2,
			'start_time'    => '18:00',
			'end_time'      => '19:00',
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
