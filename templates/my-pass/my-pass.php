<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array  $memberships */
/** @var array  $settings */
/** @var array  $bookings */
/** @var string $birthdate Y-m-d, empty if not yet set. */
?>
<div class="passpress-my-pass">
	<?php if ( empty( $memberships ) ) : ?>
		<div class="passpress-no-membership">
			<p class="passpress-no-membership-eyebrow"><?php esc_html_e( 'Membership', 'passpress' ); ?></p>
			<h3 class="passpress-no-membership-title"><?php esc_html_e( 'No active pass yet', 'passpress' ); ?></h3>
			<p class="passpress-no-membership-desc"><?php esc_html_e( 'Visit the front desk or pick a plan to get started.', 'passpress' ); ?></p>
		</div>
	<?php else : ?>
		<div class="passpress-pass-list">
			<?php foreach ( $memberships as $membership ) : ?>
				<?php
				$status_label = pp_status_label( $membership->status );
				$can_renew    = PP_Billing::is_billing_available()
					&& in_array( $membership->status, array( PP_Membership::STATUS_ACTIVE, PP_Membership::STATUS_EXPIRED ), true );
				$renew_url    = '';
				if ( $can_renew ) {
					$renew_url = PP_Billing::is_woocommerce_mode() && class_exists( 'PP_Shop_WooCommerce' )
						? PP_Shop_WooCommerce::buy_url( $membership->plan_id )
						: PP_Billing::checkout_url( $membership->plan_id, $membership->id );
				}
				?>
				<article class="passpress-pass-card passpress-status-<?php echo esc_attr( $membership->status ); ?>">
					<div class="passpress-pass-qr-wrap">
						<div class="passpress-pass-qr" data-token="<?php echo esc_attr( $membership->pass_token ); ?>"></div>
						<p class="passpress-pass-qr-hint"><?php esc_html_e( 'Scan at check-in', 'passpress' ); ?></p>
					</div>
					<div class="passpress-pass-details">
						<div class="passpress-pass-details-top">
							<span class="passpress-pass-status"><?php echo esc_html( $status_label ); ?></span>
							<h3 class="passpress-pass-title"><?php echo esc_html( get_the_title( $membership->plan_id ) ); ?></h3>
						</div>
						<dl class="passpress-pass-meta">
							<div>
								<dt><?php esc_html_e( 'Membership #', 'passpress' ); ?></dt>
								<dd><?php echo esc_html( $membership->membership_number ); ?></dd>
							</div>
							<div>
								<dt><?php esc_html_e( 'Expires', 'passpress' ); ?></dt>
								<dd><?php echo esc_html( pp_format_date( $membership->expiry_date ) ); ?></dd>
							</div>
							<?php if ( ! empty( $settings['show_pin_on_pass'] ) ) : ?>
								<div class="passpress-pass-pin">
									<dt><?php esc_html_e( 'PIN', 'passpress' ); ?></dt>
									<dd><span><?php echo esc_html( $membership->pin_code ); ?></span></dd>
								</div>
							<?php endif; ?>
						</dl>
						<?php if ( $renew_url ) : ?>
							<a class="passpress-pass-renew" href="<?php echo esc_url( $renew_url ); ?>"><?php esc_html_e( 'Renew now', 'passpress' ); ?></a>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $bookings ) ) : ?>
		<div class="passpress-my-bookings">
			<h3><?php esc_html_e( 'My Bookings', 'passpress' ); ?></h3>
			<table>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Facility', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Date', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Time', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Status', 'passpress' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bookings as $booking ) : ?>
						<tr>
							<td><?php echo esc_html( get_the_title( $booking->facility_id ) ); ?></td>
							<td><?php echo esc_html( pp_format_date( $booking->booking_date ) ); ?></td>
							<td><?php echo esc_html( substr( $booking->start_time, 0, 5 ) . '–' . substr( $booking->end_time, 0, 5 ) ); ?></td>
							<td class="pp-booking-status"><?php echo esc_html( $booking->status ); ?></td>
							<td>
								<?php if ( 'confirmed' === $booking->status ) : ?>
									<button type="button" class="button pp-cancel-booking-btn" data-booking-id="<?php echo esc_attr( $booking->id ); ?>"><?php esc_html_e( 'Cancel', 'passpress' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<?php
	$birthdate_label = '';
	if ( ! empty( $birthdate ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birthdate ) ) {
		$ts = strtotime( $birthdate . ' 12:00:00' );
		if ( $ts ) {
			/* translators: date format for birthday display, e.g. March 15 */
			$birthdate_label = date_i18n( __( 'F j', 'passpress' ), $ts );
		}
	}
	$birthdate_saved = ! empty( $birthdate_saved );
	?>
	<section class="passpress-birthdate<?php echo $birthdate_label ? ' has-date' : ''; ?>">
		<div class="passpress-birthdate-copy">
			<p class="passpress-birthdate-eyebrow"><?php esc_html_e( 'Personal', 'passpress' ); ?></p>
			<h3 class="passpress-birthdate-title"><?php esc_html_e( 'Birthday', 'passpress' ); ?></h3>
			<p class="passpress-birthdate-desc">
				<?php
				if ( $birthdate_label ) {
					printf(
						/* translators: %s: month and day, e.g. March 15 */
						esc_html__( "We'll send you a greeting on %s.", 'passpress' ),
						esc_html( $birthdate_label )
					);
				} else {
					esc_html_e( "Add your birthday and we'll send you a greeting when the day comes.", 'passpress' );
				}
				?>
			</p>
		</div>

		<?php if ( $birthdate_saved ) : ?>
			<p class="passpress-birthdate-notice" role="status"><?php esc_html_e( 'Birthday saved.', 'passpress' ); ?></p>
		<?php endif; ?>

		<form method="post" class="passpress-birthdate-form">
			<?php wp_nonce_field( 'pp_save_birthdate', 'pp_birthdate_nonce' ); ?>
			<label class="passpress-birthdate-field" for="pp_birthdate_input">
				<span class="passpress-birthdate-label"><?php esc_html_e( 'Date of birth', 'passpress' ); ?></span>
				<input type="date" id="pp_birthdate_input" name="pp_birthdate" value="<?php echo esc_attr( $birthdate ); ?>" max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>">
			</label>
			<button type="submit" class="passpress-birthdate-submit">
				<?php echo $birthdate_label ? esc_html__( 'Update', 'passpress' ) : esc_html__( 'Save birthday', 'passpress' ); ?>
			</button>
		</form>
	</section>

	<section class="passpress-invite-guest">
		<div class="passpress-invite-guest-copy">
			<p class="passpress-invite-guest-eyebrow"><?php esc_html_e( 'Guests', 'passpress' ); ?></p>
			<h3 class="passpress-invite-guest-title"><?php esc_html_e( 'Invite a Guest', 'passpress' ); ?></h3>
			<p class="passpress-invite-guest-desc"><?php esc_html_e( 'Send an invite and your guest can pick up their pass at the front desk.', 'passpress' ); ?></p>
		</div>

		<form class="passpress-invite-guest-form">
			<label class="passpress-invite-guest-field" for="pp_guest_name">
				<span class="passpress-invite-guest-label"><?php esc_html_e( 'Guest name', 'passpress' ); ?></span>
				<input type="text" id="pp_guest_name" name="guest_name" placeholder="<?php esc_attr_e( 'Alex Rivera', 'passpress' ); ?>" required autocomplete="name">
			</label>
			<label class="passpress-invite-guest-field" for="pp_guest_email">
				<span class="passpress-invite-guest-label"><?php esc_html_e( 'Email', 'passpress' ); ?> <em><?php esc_html_e( '(optional)', 'passpress' ); ?></em></span>
				<input type="email" id="pp_guest_email" name="guest_email" placeholder="<?php esc_attr_e( 'alex@email.com', 'passpress' ); ?>" autocomplete="email">
			</label>
			<button type="submit" class="passpress-invite-guest-submit"><?php esc_html_e( 'Send invitation', 'passpress' ); ?></button>
			<div class="passpress-invite-guest-message" hidden role="status"></div>
		</form>
	</section>
</div>
