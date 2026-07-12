<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Cricket Academy', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Coaching Membership', 'passpress' ),
			'price'             => 40,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Coaching Membership', 'passpress' ),
			'price'             => 400,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Single Net Session', 'passpress' ),
			'price'             => 15,
			'plan_type'         => 'one_time',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Cricket Nets & Ground', 'passpress' ),
			'facility_type' => 'cricket_ground',
			'capacity'      => 25,
		),
	),
	'class_sessions' => array(
		array(
			'name'          => __( 'Cricket Coaching', 'passpress' ),
			'class_type'    => 'cricket_coaching',
			'facility_name' => __( 'Cricket Nets & Ground', 'passpress' ),
			'capacity'      => 20,
			'day_of_week'   => 7,
			'start_time'    => '08:00',
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
