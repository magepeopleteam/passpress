<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Health Club', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Health Club Membership', 'passpress' ),
			'price'             => 59,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Health Club Membership', 'passpress' ),
			'price'             => 599,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Family Health Club Membership', 'passpress' ),
			'price'             => 149,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'VIP Membership', 'passpress' ),
			'price'             => 999,
			'plan_type'         => 'vip',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Fitness Floor', 'passpress' ),
			'facility_type' => 'gym',
			'capacity'      => 50,
		),
		array(
			'name'          => __( 'Indoor Pool', 'passpress' ),
			'facility_type' => 'swimming_pool',
			'capacity'      => 30,
		),
	),
	'class_sessions' => array(
		array(
			'name'          => __( 'Morning Yoga', 'passpress' ),
			'class_type'    => 'yoga',
			'facility_name' => __( 'Fitness Floor', 'passpress' ),
			'capacity'      => 15,
			'day_of_week'   => 3,
			'start_time'    => '07:00',
			'end_time'      => '08:00',
		),
		array(
			'name'          => __( 'Swim Lessons', 'passpress' ),
			'class_type'    => 'swimming',
			'facility_name' => __( 'Indoor Pool', 'passpress' ),
			'capacity'      => 10,
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
