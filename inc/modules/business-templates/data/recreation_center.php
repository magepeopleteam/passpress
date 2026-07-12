<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Recreation Center', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Membership', 'passpress' ),
			'price'             => 45,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Membership', 'passpress' ),
			'price'             => 450,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Family Membership', 'passpress' ),
			'price'             => 99,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Student Membership', 'passpress' ),
			'price'             => 25,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Workout Floor', 'passpress' ),
			'facility_type' => 'gym',
			'capacity'      => 40,
		),
		array(
			'name'          => __( 'Leisure Pool', 'passpress' ),
			'facility_type' => 'swimming_pool',
			'capacity'      => 30,
		),
		array(
			'name'          => __( 'Games Room', 'passpress' ),
			'facility_type' => 'indoor_games',
			'capacity'      => 20,
		),
	),
	'class_sessions' => array(
		array(
			'name'          => __( 'Group Fitness', 'passpress' ),
			'class_type'    => 'fitness',
			'facility_name' => __( 'Workout Floor', 'passpress' ),
			'capacity'      => 20,
			'day_of_week'   => 1,
			'start_time'    => '18:00',
			'end_time'      => '19:00',
		),
		array(
			'name'          => __( 'Zumba', 'passpress' ),
			'class_type'    => 'zumba',
			'facility_name' => __( 'Workout Floor', 'passpress' ),
			'capacity'      => 25,
			'day_of_week'   => 3,
			'start_time'    => '19:00',
			'end_time'      => '20:00',
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
