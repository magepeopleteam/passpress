<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visitor Pass module. Per an explicit product decision (see CLAUDE.md Phase 3
 * notes), every visitor gets a real WP user account and a real pp_memberships
 * row (member_type = 'visitor') — NOT a parallel table. This means the
 * existing QR/PIN Scan Gate, PP_Access_Control, and entry-restriction engine
 * work for visitors completely unchanged; nothing in the access-control path
 * needed to be touched for this module.
 */
class PP_Visitor {

	const META_IS_VISITOR   = '_pp_is_visitor_account';
	const META_PHONE        = '_pp_visitor_phone';
	const META_HOST_USER_ID = '_pp_visitor_host_user_id';
	const META_INVITE_STATUS = '_pp_invite_status';

	/**
	 * Creates (or reuses, if the email matches an existing user) the WP user
	 * account backing a visitor pass.
	 *
	 * @return WP_User|WP_Error
	 */
	public static function create_visitor_user( $name, $email = '', $phone = '', $host_user_id = 0 ) {
		$name = trim( wp_strip_all_tags( $name ) );
		if ( ! $name ) {
			return new WP_Error( 'pp_invalid_name', __( 'Please enter the visitor\'s name.', 'passpress' ) );
		}

		if ( $email && is_email( $email ) ) {
			$existing = get_user_by( 'email', $email );
			if ( $existing ) {
				return $existing;
			}
		} else {
			$email = self::generate_placeholder_email();
		}

		$username = self::generate_unique_username( $name );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 20 ),
				'display_name' => $name,
				'first_name'   => $name,
				'role'         => 'pp_member',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, self::META_IS_VISITOR, 1 );
		if ( $phone ) {
			update_user_meta( $user_id, self::META_PHONE, sanitize_text_field( $phone ) );
		}
		if ( $host_user_id ) {
			update_user_meta( $user_id, self::META_HOST_USER_ID, absint( $host_user_id ) );
		}

		PP_Activity_Logger::log( 'visitor_account_created', 'user', $user_id, sprintf( 'Visitor account created for "%s".', $name ) );

		return get_userdata( $user_id );
	}

	/**
	 * Registers a walk-in visitor and immediately issues their guest pass.
	 *
	 * @return object|WP_Error The pp_memberships row.
	 */
	public static function register( $name, $email, $phone, $plan_id, $host_user_id = 0 ) {
		$user = self::create_visitor_user( $name, $email, $phone, $host_user_id );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$membership = PP_Membership::issue( $user->ID, $plan_id, '', PP_Membership::TYPE_VISITOR );
		if ( is_wp_error( $membership ) ) {
			return $membership;
		}

		PP_Activity_Logger::log( 'visitor_pass_issued', 'membership', $membership->id, sprintf( 'Visitor pass issued to "%s".', $user->display_name ) );

		return $membership;
	}

	/**
	 * A member invites a guest ahead of time — creates the account but does
	 * NOT issue a pass yet; staff finalize it (pick a plan) when the guest
	 * arrives, via finalize_invitation(). Deliberately not fully automated:
	 * a real front desk still wants to choose the plan/collect a fee/check ID.
	 *
	 * @return WP_User|WP_Error
	 */
	public static function invite_guest( $guest_name, $guest_email, $host_user_id ) {
		$user = self::create_visitor_user( $guest_name, $guest_email, '', $host_user_id );
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		update_user_meta( $user->ID, self::META_INVITE_STATUS, 'pending' );

		$host = get_userdata( $host_user_id );
		PP_Activity_Logger::log(
			'guest_invited',
			'user',
			$user->ID,
			sprintf( '%s invited guest "%s".', $host ? $host->display_name : "User #{$host_user_id}", $guest_name ),
			$host_user_id
		);

		return $user;
	}

	/**
	 * @return WP_User[]
	 */
	public static function get_pending_invitations() {
		return get_users(
			array(
				'meta_key'   => self::META_INVITE_STATUS,
				'meta_value' => 'pending',
			)
		);
	}

	/**
	 * Staff action: turn a pending invitation into a real issued pass.
	 *
	 * @return object|WP_Error The pp_memberships row.
	 */
	public static function finalize_invitation( $user_id, $plan_id ) {
		$user_id = absint( $user_id );
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'pp_invalid_user', __( 'Guest account not found.', 'passpress' ) );
		}

		$host_user_id = (int) get_user_meta( $user_id, self::META_HOST_USER_ID, true );

		$membership = PP_Membership::issue( $user_id, $plan_id, '', PP_Membership::TYPE_VISITOR );
		if ( is_wp_error( $membership ) ) {
			return $membership;
		}

		delete_user_meta( $user_id, self::META_INVITE_STATUS );

		PP_Activity_Logger::log( 'visitor_pass_issued', 'membership', $membership->id, sprintf( 'Invitation finalized, visitor pass issued to "%s".', $user->display_name ) );

		return $membership;
	}

	/**
	 * @param array $args status, search, per_page, paged (member_type is forced to 'visitor')
	 */
	public static function get_history( $args = array() ) {
		$args['member_type'] = 'visitor';
		return PP_Query::get_memberships( $args );
	}

	public static function is_visitor_account( $user_id ) {
		return (bool) get_user_meta( $user_id, self::META_IS_VISITOR, true );
	}

	public static function get_host( $user_id ) {
		$host_id = (int) get_user_meta( $user_id, self::META_HOST_USER_ID, true );
		return $host_id ? get_userdata( $host_id ) : null;
	}

	private static function generate_placeholder_email() {
		// .invalid is IANA/RFC 2606-reserved specifically for addresses that
		// are guaranteed to never resolve — the correct choice for "this
		// visitor gave no email but WordPress requires one."
		return 'visitor-' . wp_generate_password( 10, false, false ) . '@passpress.invalid';
	}

	private static function generate_unique_username( $name ) {
		$base = sanitize_user( sanitize_title( $name ), true );
		if ( ! $base ) {
			$base = 'visitor';
		}
		do {
			$candidate = $base . '-' . wp_generate_password( 5, false, false );
		} while ( username_exists( $candidate ) );
		return $candidate;
	}
}
