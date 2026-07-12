<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Tennis Club', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Court Membership', 'passpress' ),
			'price'             => 60,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Court Membership', 'passpress' ),
			'price'             => 600,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Family Membership', 'passpress' ),
			'price'             => 150,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'VIP Coaching Membership', 'passpress' ),
			'price'             => 1200,
			'plan_type'         => 'vip',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Center Court', 'passpress' ),
			'facility_type' => 'tennis_court',
			'capacity'      => 4,
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
