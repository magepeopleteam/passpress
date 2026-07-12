<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * No exhibit-hall-specific facility_type exists in PP_Facility_CPT, and a
 * museum's rooms/galleries aren't booked/scanned as separate facilities in
 * practice — this template seeds only plans (the single "front door" entry
 * pass), unlike the sports/gym templates which book specific facilities.
 */
return array(
	'label'          => __( 'Museum Pass', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Single Visit Pass', 'passpress' ),
			'price'             => 15,
			'plan_type'         => 'one_time',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Student Pass', 'passpress' ),
			'price'             => 8,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Annual Museum Pass', 'passpress' ),
			'price'             => 60,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Family Annual Pass', 'passpress' ),
			'price'             => 150,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
	),
	'facilities'     => array(),
	'class_sessions' => array(),
	'pages'          => array(
		array(
			'title'     => __( 'My Pass', 'passpress' ),
			'shortcode' => 'passpress_my_pass',
		),
		array(
			'title'     => __( 'Membership Plans', 'passpress' ),
			'shortcode' => 'passpress_membership_plans',
		),
	),
);
