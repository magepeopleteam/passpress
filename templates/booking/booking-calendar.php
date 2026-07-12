<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var WP_Post $facility
 * @var int     $facility_id
 */
?>
<div class="passpress-booking-calendar" data-facility-id="<?php echo esc_attr( $facility_id ); ?>">
	<h3><?php echo esc_html( $facility->post_title ); ?></h3>
	<p>
		<label><?php esc_html_e( 'Choose a date', 'passpress' ); ?>
			<input type="date" class="pp-booking-date" min="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
		</label>
	</p>
	<div class="pp-booking-slots passpress-booking-slots"></div>
	<div class="pp-booking-message passpress-checkout-notice" style="display:none;"></div>
</div>
