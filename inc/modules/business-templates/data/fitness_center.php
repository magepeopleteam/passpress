<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Fitness Center', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Fitness Membership', 'passpress' ),
			'price'             => 39,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Fitness Membership', 'passpress' ),
			'price'             => 399,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Student Fitness Membership', 'passpress' ),
			'price'             => 25,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Day Pass', 'passpress' ),
			'price'             => 12,
			'plan_type'         => 'daily_pass',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Main Weight Room', 'passpress' ),
			'facility_type' => 'gym',
			'capacity'      => 60,
		),
		array(
			'name'          => __( 'Indoor Games Room', 'passpress' ),
			'facility_type' => 'indoor_games',
			'capacity'      => 20,
		),
	),
	'class_sessions' => array(
		array(
			'name'          => __( 'Zumba Blast', 'passpress' ),
			'class_type'    => 'zumba',
			'facility_name' => __( 'Main Weight Room', 'passpress' ),
			'capacity'      => 25,
			'day_of_week'   => 2,
			'start_time'    => '18:00',
			'end_time'      => '19:00',
		),
		array(
			'name'          => __( 'Group Fitness Circuit', 'passpress' ),
			'class_type'    => 'fitness',
			'facility_name' => __( 'Main Weight Room', 'passpress' ),
			'capacity'      => 20,
			'day_of_week'   => 4,
			'start_time'    => '06:30',
			'end_time'      => '07:30',
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
