<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Multi-sport club: unlike the single-facility clubs (Tennis, Badminton,
 * Basketball), this template seeds all three court types since a "sports
 * club" typically offers a bundle of them under one membership.
 */
return array(
	'label'          => __( 'Sports Club', 'passpress' ),
	'plans'          => array(
		array(
			'name'              => __( 'Monthly Sports Club Membership', 'passpress' ),
			'price'             => 55,
			'plan_type'         => 'monthly',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Yearly Sports Club Membership', 'passpress' ),
			'price'             => 550,
			'plan_type'         => 'yearly',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'one_per_day',
		),
		array(
			'name'              => __( 'Family Sports Club Membership', 'passpress' ),
			'price'             => 149,
			'plan_type'         => 'family',
			'duration_value'    => 1,
			'duration_unit'     => 'month',
			'entry_restriction' => 'none',
		),
		array(
			'name'              => __( 'Corporate Membership', 'passpress' ),
			'price'             => 999,
			'plan_type'         => 'corporate',
			'duration_value'    => 1,
			'duration_unit'     => 'year',
			'entry_restriction' => 'weekday_only',
		),
	),
	'facilities'     => array(
		array(
			'name'          => __( 'Tennis Courts', 'passpress' ),
			'facility_type' => 'tennis_court',
			'capacity'      => 8,
		),
		array(
			'name'          => __( 'Basketball Court', 'passpress' ),
			'facility_type' => 'basketball_court',
			'capacity'      => 20,
		),
		array(
			'name'          => __( 'Badminton Court', 'passpress' ),
			'facility_type' => 'badminton_court',
			'capacity'      => 8,
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
