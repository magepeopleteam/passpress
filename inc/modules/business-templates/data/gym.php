<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gym business template: the one business type wired up end-to-end in
 * Phase 1. Other templates in the product plan (Fitness Center, Theme
 * Park, Yoga Studio, ...) will follow this same data shape.
 */
return array(
	'label'      => __( 'Gym', 'passpress' ),
	'plans'      => array(
		array(
			'name'              => __( 'Monthly Gym Membership', 'passpress' ),
			'price'             => 49,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Gym Membership', 'passpress' ),
			'price'             => 499,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Daily Gym Pass', 'passpress' ),
			'price'             => 10,
			'plan_type'         => 'daily_pass',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Guest Day Pass', 'passpress' ),
			'price'             => 5,
			'plan_type'         => 'one_time',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'one_per_day',
		),
	),
	'facilities' => array(
		array(
			'name'          => __( 'Main Gym Floor', 'passpress' ),
			'facility_type' => 'gym',
			'capacity'      => 50,
		),
	),
	'class_sessions' => array(
		array(
			'name'         => __( 'Morning Yoga', 'passpress' ),
			'class_type'   => 'yoga',
			'facility_name' => __( 'Main Gym Floor', 'passpress' ),
			'capacity'     => 15,
			'day_of_week'  => 1,
			'start_time'   => '07:00',
			'end_time'     => '08:00',
		),
	),
	'pages'      => array(
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
