<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'          => __( 'Kids Play Zone', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Daily Play Pass', 'passpress' ),
			'price'             => 15,
			'plan_type'         => 'daily_pass',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Monthly Unlimited Play Membership', 'passpress' ),
			'price'             => 60,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Birthday Party Pass', 'passpress' ),
			'price'             => 25,
			'plan_type'         => 'one_time',
			'duration_value'    => 1,
			'duration_unit'     => 'day',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Family Play Membership', 'passpress' ),
			'price'             => 99,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'none',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Indoor Play Area', 'passpress' ),
			'facility_type' => 'indoor_games',
			'capacity'      => 40,
		),
		array(
			'name'          => __( 'Outdoor Play Zone', 'passpress' ),
			'facility_type' => 'childrens_park',
			'capacity'      => 30,
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
