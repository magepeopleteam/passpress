<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General + QR Code settings panel (rendered inside PP_Settings_Page).
 */
class PP_Settings {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_settings() {
		register_setting( 'passpress_settings_group', 'passpress_settings', array( __CLASS__, 'sanitize' ) );
	}

	public static function sanitize( $input ) {
		$input = (array) $input;
		return array(
			'currency_symbol'  => isset( $input['currency_symbol'] ) ? sanitize_text_field( $input['currency_symbol'] ) : '$',
			'currency_code'    => isset( $input['currency_code'] ) ? strtolower( sanitize_text_field( $input['currency_code'] ) ) : 'usd',
			'date_format'      => isset( $input['date_format'] ) ? sanitize_text_field( $input['date_format'] ) : 'F j, Y',
			'qr_size'          => isset( $input['qr_size'] ) ? max( 100, min( 400, absint( $input['qr_size'] ) ) ) : 200,
			'show_pin_on_pass' => ! empty( $input['show_pin_on_pass'] ) ? 1 : 0,
		);
	}

	/**
	 * @deprecated Use PP_Settings_Page::render() — kept as alias for old callbacks.
	 */
	public static function render() {
		PP_Settings_Page::render();
	}

	public static function render_panel() {
		$settings = pp_get_settings();
		?>
		<div class="passpress-settings-panel" id="passpress-panel-general">
			<header class="passpress-settings-panel-header">
				<h2><?php esc_html_e( 'General', 'passpress' ); ?></h2>
				<p><?php esc_html_e( 'Currency, dates, and how passes appear to members.', 'passpress' ); ?></p>
			</header>

			<form method="post" action="options.php">
				<?php settings_fields( 'passpress_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pp_currency_symbol"><?php esc_html_e( 'Currency Symbol', 'passpress' ); ?></label></th>
						<td><input type="text" id="pp_currency_symbol" name="passpress_settings[currency_symbol]" value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>" class="small-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_currency_code"><?php esc_html_e( 'Currency Code (ISO 4217)', 'passpress' ); ?></label></th>
						<td>
							<input type="text" id="pp_currency_code" name="passpress_settings[currency_code]" value="<?php echo esc_attr( $settings['currency_code'] ); ?>" class="small-text" maxlength="3">
							<p class="description"><?php esc_html_e( 'e.g. usd, eur, gbp — required by Stripe/PayPal for real charges.', 'passpress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_date_format"><?php esc_html_e( 'Date Format', 'passpress' ); ?></label></th>
						<td>
							<input type="text" id="pp_date_format" name="passpress_settings[date_format]" value="<?php echo esc_attr( $settings['date_format'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'PHP date() format used to display expiry dates on My Pass and in admin lists.', 'passpress' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pp_qr_size"><?php esc_html_e( 'QR Code Size (px)', 'passpress' ); ?></label></th>
						<td><input type="number" id="pp_qr_size" name="passpress_settings[qr_size]" value="<?php echo esc_attr( $settings['qr_size'] ); ?>" min="100" max="400" step="10"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Show PIN on My Pass', 'passpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="passpress_settings[show_pin_on_pass]" value="1" <?php checked( ! empty( $settings['show_pin_on_pass'] ) ); ?>>
								<?php esc_html_e( 'Display the PIN code alongside the QR code', 'passpress' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save General Settings', 'passpress' ) ); ?>
			</form>
		</div>
		<?php
	}
}
