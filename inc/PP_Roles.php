<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the roles/capabilities PassPress needs. Kept separate from PP_Install
 * so uninstall.php can remove roles without loading the whole plugin.
 */
class PP_Roles {

	const CAP_MANAGE  = 'pp_manage_memberships';
	const CAP_SCAN    = 'pp_scan_access';
	const CAP_CLASSES = 'pp_manage_classes';

	public static function register_roles() {
		add_role(
			'pp_member',
			__( 'Member', 'passpress' ),
			array( 'read' => true )
		);

		add_role(
			'pp_gate_operator',
			__( 'Gate Operator', 'passpress' ),
			array(
				'read'         => true,
				self::CAP_SCAN => true,
			)
		);

		add_role(
			'pp_trainer',
			__( 'Trainer', 'passpress' ),
			array(
				'read'            => true,
				self::CAP_SCAN    => true,
				self::CAP_CLASSES => true,
			)
		);

		add_role(
			'pp_staff',
			__( 'PassPress Staff', 'passpress' ),
			array(
				'read'            => true,
				self::CAP_SCAN    => true,
				self::CAP_MANAGE  => true,
				self::CAP_CLASSES => true,
			)
		);

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( self::CAP_MANAGE );
			$administrator->add_cap( self::CAP_SCAN );
			$administrator->add_cap( self::CAP_CLASSES );
		}
	}

	public static function remove_roles() {
		remove_role( 'pp_member' );
		remove_role( 'pp_gate_operator' );
		remove_role( 'pp_trainer' );
		remove_role( 'pp_staff' );

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->remove_cap( self::CAP_MANAGE );
			$administrator->remove_cap( self::CAP_SCAN );
			$administrator->remove_cap( self::CAP_CLASSES );
		}
	}
}
