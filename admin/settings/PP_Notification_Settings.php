<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * On/off toggles (and the one timing field) for the notification triggers
 * that aren't already covered by an existing setting. Renewal-reminder
 * timing stays on the Billing Settings page (renewal_reminder_days) since
 * it predates this module and already works — no reason to relocate it.
 */
class PP_Notification_Settings {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings() {
		register_setting( 'passpress_notification_settings_group', 'passpress_notification_settings', array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$input     = (array) $input;
		$defaults  = PP_Notifications::default_settings();
		$sanitized = array();

		$sanitized['welcome_enabled']          = ! empty( $input['welcome_enabled'] ) ? 1 : 0;
		$sanitized['booking_reminder_enabled']  = ! empty( $input['booking_reminder_enabled'] ) ? 1 : 0;
		$sanitized['booking_reminder_days']     = isset( $input['booking_reminder_days'] ) ? max( 1, absint( $input['booking_reminder_days'] ) ) : $defaults['booking_reminder_days'];
		$sanitized['payment_failed_enabled']    = ! empty( $input['payment_failed_enabled'] ) ? 1 : 0;
		$sanitized['birthday_enabled']          = ! empty( $input['birthday_enabled'] ) ? 1 : 0;

		return $sanitized;
	}

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$settings = PP_Notifications::get_settings();
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'Notification Settings', 'passpress' ); ?></h1>
			<p class="description"><?php esc_html_e( 'All notifications are sent by email only. Renewal reminder timing is on the Billing Settings page.', 'passpress' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'passpress_notification_settings_group' ); ?>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Welcome Email', 'passpress' ); ?></th>
						<td><label><input type="checkbox" name="passpress_notification_settings[welcome_enabled]" value="1" <?php checked( ! empty( $settings['welcome_enabled'] ) ); ?>> <?php esc_html_e( 'Send when a new membership (or visitor pass) is issued', 'passpress' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Booking Reminder', 'passpress' ); ?></th>
						<td>
							<label><input type="checkbox" name="passpress_notification_settings[booking_reminder_enabled]" value="1" <?php checked( ! empty( $settings['booking_reminder_enabled'] ) ); ?>> <?php esc_html_e( 'Send a reminder before an upcoming facility/class booking', 'passpress' ); ?></label><br>
							<label for="pp_booking_reminder_days"><?php esc_html_e( 'Days before the booking', 'passpress' ); ?></label>
							<input type="number" id="pp_booking_reminder_days" name="passpress_notification_settings[booking_reminder_days]" value="<?php echo esc_attr( $settings['booking_reminder_days'] ); ?>" min="1" max="14" class="small-text">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Payment Failed', 'passpress' ); ?></th>
						<td><label><input type="checkbox" name="passpress_notification_settings[payment_failed_enabled]" value="1" <?php checked( ! empty( $settings['payment_failed_enabled'] ) ); ?>> <?php esc_html_e( 'Send when a checkout payment attempt fails', 'passpress' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Birthday Greeting', 'passpress' ); ?></th>
						<td><label><input type="checkbox" name="passpress_notification_settings[birthday_enabled]" value="1" <?php checked( ! empty( $settings['birthday_enabled'] ) ); ?>> <?php esc_html_e( 'Send on a member\'s birthday (from the date they save on My Pass)', 'passpress' ); ?></label></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
