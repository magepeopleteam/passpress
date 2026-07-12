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
		<p class="passpress-no-membership"><?php esc_html_e( 'You do not have an active membership yet. Visit the front desk to get started.', 'passpress' ); ?></p>
	<?php else : ?>
		<?php foreach ( $memberships as $membership ) : ?>
			<div class="passpress-pass-card passpress-status-<?php echo esc_attr( $membership->status ); ?>">
				<div class="passpress-pass-qr" data-token="<?php echo esc_attr( $membership->pass_token ); ?>"></div>
				<div class="passpress-pass-details">
					<h3><?php echo esc_html( get_the_title( $membership->plan_id ) ); ?></h3>
					<p><strong><?php esc_html_e( 'Membership #', 'passpress' ); ?></strong> <?php echo esc_html( $membership->membership_number ); ?></p>
					<p><strong><?php esc_html_e( 'Status', 'passpress' ); ?></strong> <?php echo esc_html( pp_status_label( $membership->status ) ); ?></p>
					<p><strong><?php esc_html_e( 'Expires', 'passpress' ); ?></strong> <?php echo esc_html( pp_format_date( $membership->expiry_date ) ); ?></p>
					<?php if ( ! empty( $settings['show_pin_on_pass'] ) ) : ?>
						<p><strong><?php esc_html_e( 'PIN', 'passpress' ); ?></strong> <?php echo esc_html( $membership->pin_code ); ?></p>
					<?php endif; ?>
					<?php if ( PP_Billing::is_billing_available() && in_array( $membership->status, array( PP_Membership::STATUS_ACTIVE, PP_Membership::STATUS_EXPIRED ), true ) ) : ?>
						<p><a class="button button-primary" href="<?php echo esc_url( PP_Billing::checkout_url( $membership->plan_id, $membership->id ) ); ?>"><?php esc_html_e( 'Renew Now', 'passpress' ); ?></a></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
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

	<div class="passpress-birthdate">
		<h3><?php esc_html_e( 'Birthday', 'passpress' ); ?></h3>
		<p class="description"><?php esc_html_e( "Let us know your birthday and we'll send you a greeting.", 'passpress' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'pp_save_birthdate', 'pp_birthdate_nonce' ); ?>
			<p>
				<input type="date" name="pp_birthdate" value="<?php echo esc_attr( $birthdate ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Save', 'passpress' ); ?></button>
			</p>
		</form>
	</div>

	<div class="passpress-invite-guest">
		<h3><?php esc_html_e( 'Invite a Guest', 'passpress' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Your guest can pick up their pass at the front desk.', 'passpress' ); ?></p>
		<form class="passpress-invite-guest-form">
			<p>
				<input type="text" name="guest_name" placeholder="<?php esc_attr_e( "Guest's name", 'passpress' ); ?>" required>
				<input type="email" name="guest_email" placeholder="<?php esc_attr_e( 'Email (optional)', 'passpress' ); ?>">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Send Invitation', 'passpress' ); ?></button>
			</p>
		</form>
		<div class="passpress-invite-guest-message passpress-checkout-notice" style="display:none;"></div>
	</div>
</div>
