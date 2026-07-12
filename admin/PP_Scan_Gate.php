<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scan Gate: staff-facing screen for checking members in/out. QR scanning
 * works via a plain focused text input — a USB/Bluetooth QR scanner types
 * the decoded token like a keyboard, so no camera/JS decoder is required.
 * PIN entry is a manual fallback for members without a scannable device.
 */
class PP_Scan_Gate {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_SCAN ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$facilities = PP_Facility::get_all();
		?>
		<div class="wrap passpress-wrap passpress-scan-gate">
			<h1><?php esc_html_e( 'Scan Gate', 'passpress' ); ?></h1>

			<div class="passpress-scan-panel">
				<div class="passpress-scan-controls">
					<label>
						<?php esc_html_e( 'Facility', 'passpress' ); ?>
						<select id="pp-scan-facility">
							<option value="0"><?php esc_html_e( '— General Entrance —', 'passpress' ); ?></option>
							<?php foreach ( $facilities as $facility ) : ?>
								<option value="<?php echo esc_attr( $facility->ID ); ?>"><?php echo esc_html( $facility->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<label>
						<?php esc_html_e( 'Direction', 'passpress' ); ?>
						<select id="pp-scan-direction">
							<option value="entry"><?php esc_html_e( 'Entry', 'passpress' ); ?></option>
							<option value="exit"><?php esc_html_e( 'Exit', 'passpress' ); ?></option>
						</select>
					</label>
				</div>

				<h2><?php esc_html_e( 'QR Scan', 'passpress' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Focus this field and scan with a USB/Bluetooth QR scanner, or paste the code and press Enter.', 'passpress' ); ?></p>
				<p>
					<input type="text" id="pp-scan-token" class="regular-text" autocomplete="off" placeholder="<?php esc_attr_e( 'Scan or paste pass code…', 'passpress' ); ?>">
				</p>

				<h2><?php esc_html_e( 'PIN Entry', 'passpress' ); ?></h2>
				<p>
					<input type="text" id="pp-pin-number" placeholder="<?php esc_attr_e( 'Membership number', 'passpress' ); ?>">
					<input type="text" id="pp-pin-code" placeholder="<?php esc_attr_e( 'PIN', 'passpress' ); ?>" maxlength="10">
					<button type="button" class="button button-primary" id="pp-pin-submit"><?php esc_html_e( 'Check In', 'passpress' ); ?></button>
				</p>

				<div id="pp-scan-result" class="passpress-scan-result" aria-live="polite"></div>
			</div>
		</div>
		<?php
	}
}
