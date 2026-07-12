<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activation/deactivation. Creates the custom tables PassPress needs for
 * high-volume data (memberships, access logs, activity log) that don't
 * belong as postmeta.
 */
class PP_Install {

	public static function activate() {
		self::create_tables();
		PP_Roles::register_roles();
		self::maybe_set_defaults();
		set_transient( 'passpress_activation_redirect', true, 60 );
	}

	public static function deactivate() {
		// Intentionally non-destructive: memberships, logs, and roles are kept
		// so reactivating the plugin doesn't lose data. See uninstall.php for
		// the destructive cleanup path.
		PP_Cron::unschedule();
	}

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$memberships     = $wpdb->prefix . 'pp_memberships';
		$access_logs     = $wpdb->prefix . 'pp_access_logs';
		$activity        = $wpdb->prefix . 'pp_activity_log';
		$billing_history = $wpdb->prefix . 'pp_billing_history';
		$bookings        = $wpdb->prefix . 'pp_bookings';
		$waitlist        = $wpdb->prefix . 'pp_booking_waitlist';

		$sql = "CREATE TABLE {$memberships} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			plan_id BIGINT UNSIGNED NOT NULL,
			membership_number VARCHAR(40) NOT NULL,
			pass_token VARCHAR(64) NOT NULL,
			pin_code VARCHAR(10) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			member_type VARCHAR(20) NOT NULL DEFAULT 'member',
			start_date DATE NOT NULL,
			expiry_date DATE NOT NULL,
			auto_renew TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY membership_number (membership_number),
			UNIQUE KEY pass_token (pass_token),
			KEY user_id (user_id),
			KEY plan_id (plan_id),
			KEY status (status)
		) {$charset_collate};

		CREATE TABLE {$access_logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			membership_id BIGINT UNSIGNED NOT NULL,
			facility_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			direction VARCHAR(10) NOT NULL DEFAULT 'entry',
			method VARCHAR(10) NOT NULL DEFAULT 'qr',
			result VARCHAR(20) NOT NULL DEFAULT 'allowed',
			reason VARCHAR(191) NOT NULL DEFAULT '',
			operator_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY membership_id (membership_id),
			KEY facility_id (facility_id),
			KEY created_at (created_at)
		) {$charset_collate};

		CREATE TABLE {$activity} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event VARCHAR(60) NOT NULL,
			object_type VARCHAR(40) NOT NULL DEFAULT '',
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			message TEXT NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY event (event),
			KEY created_at (created_at)
		) {$charset_collate};

		CREATE TABLE {$billing_history} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			membership_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_id BIGINT UNSIGNED NOT NULL,
			plan_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'initial',
			gateway VARCHAR(30) NOT NULL DEFAULT 'offline',
			gateway_ref VARCHAR(191) NOT NULL DEFAULT '',
			amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			currency VARCHAR(10) NOT NULL DEFAULT 'usd',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			checkout_token VARCHAR(64) NOT NULL DEFAULT '',
			coupon_code VARCHAR(40) NOT NULL DEFAULT '',
			discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			raw_response TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY checkout_token (checkout_token),
			KEY membership_id (membership_id),
			KEY user_id (user_id),
			KEY plan_id (plan_id),
			KEY status (status)
		) {$charset_collate};

		CREATE TABLE {$bookings} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			facility_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			membership_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			class_session_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			booking_date DATE NOT NULL,
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'confirmed',
			checked_in_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY facility_id (facility_id),
			KEY user_id (user_id),
			KEY booking_date (booking_date),
			KEY status (status),
			KEY class_session_id (class_session_id)
		) {$charset_collate};

		CREATE TABLE {$waitlist} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			facility_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			class_session_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			booking_date DATE NOT NULL,
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'waiting',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY facility_id (facility_id),
			KEY booking_date (booking_date),
			KEY status (status),
			KEY class_session_id (class_session_id)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'passpress_db_version', PASSPRESS_DB_VERSION );
	}

	private static function maybe_set_defaults() {
		if ( false === get_option( 'passpress_settings' ) ) {
			update_option(
				'passpress_settings',
				array(
					'currency_symbol'  => '$',
					'currency_code'    => 'usd',
					'date_format'      => 'F j, Y',
					'qr_size'          => 200,
					'show_pin_on_pass' => 1,
				)
			);
		}

		if ( false === get_option( 'passpress_billing_settings' ) ) {
			update_option( 'passpress_billing_settings', PP_Billing::default_settings() );
		}
	}
}
