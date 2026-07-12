<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Dance Academy', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Membership', 'passpress' ),
			'price'             => 40,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Membership', 'passpress' ),
			'price'             => 400,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Student Membership', 'passpress' ),
			'price'             => 25,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Family Membership', 'passpress' ),
			'price'             => 75,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Dance Studio', 'passpress' ),
			'facility_type' => 'indoor_games',
			'capacity'      => 25,
		),
	),
	'class_sessions' => array(
		array(
			'name'          => __( 'Dance Class', 'passpress' ),
			'class_type'    => 'dance',
			'facility_name' => __( 'Dance Studio', 'passpress' ),
			'capacity'      => 20,
			'day_of_week'   => 5,
			'start_time'    => '17:00',
			'end_time'      => '18:00',
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
