<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Swimming Pool', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Swim Pass', 'passpress' ),
			'price'             => 45,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Swim Pass', 'passpress' ),
			'price'             => 450,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Family Swim Pass', 'passpress' ),
			'price'             => 99,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Daily Swim Entry', 'passpress' ),
			'price'             => 8,
			'plan_type'         => 'daily_pass',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Lap Pool', 'passpress' ),
			'facility_type' => 'swimming_pool',
			'capacity'      => 40,
		),
	),
	'class_sessions' => array(
		array(
			'name'          => __( 'Learn to Swim', 'passpress' ),
			'class_type'    => 'swimming',
			'facility_name' => __( 'Lap Pool', 'passpress' ),
			'capacity'      => 12,
			'day_of_week'   => 6,
			'start_time'    => '09:00',
			'end_time'      => '10:00',
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
