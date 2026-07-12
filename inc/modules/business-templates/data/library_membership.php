<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Library Membership', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Annual Library Card', 'passpress' ),
			'price'             => 20,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Student Library Card', 'passpress' ),
			'price'             => 10,
			'plan_type'         => 'student',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Family Library Card', 'passpress' ),
			'price'             => 35,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Lifetime Library Card', 'passpress' ),
			'price'             => 150,
			'plan_type'         => 'lifetime',
			'duration_value'    => 0,
			'duration_unit'     => 'lifetime',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Reading Hall', 'passpress' ),
			'facility_type' => 'library',
			'capacity'      => 60,
		),
	),
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
