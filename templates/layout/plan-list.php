<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $plans */
/** @var array $settings */
?>
<div class="passpress-plan-list-wrap">
	<div class="passpress-plan-header">
		<h2><?php esc_html_e( 'Choose Your Plan', 'passpress' ); ?></h2>
		<p class="passpress-plan-subtext"><?php esc_html_e( 'Pick the pass that fits — subscribe online and get instant access.', 'passpress' ); ?></p>
	</div>

	<?php if ( ! $plans ) : ?>
		<p><?php esc_html_e( 'No membership plans are available yet.', 'passpress' ); ?></p>
	<?php else : ?>
		<div class="passpress-plan-list">
			<?php foreach ( $plans as $plan ) :
				$price          = (float) get_post_meta( $plan->ID, '_pp_price', true );
				$duration_value = (int) get_post_meta( $plan->ID, '_pp_duration_value', true );
				$duration_unit  = get_post_meta( $plan->ID, '_pp_duration_unit', true );
				$features       = array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( $plan->ID, '_pp_features', true ) ) ) );
				$most_popular   = (bool) get_post_meta( $plan->ID, '_pp_most_popular', true );
				$shop_url       = class_exists( 'PP_Shop_WooCommerce' ) ? PP_Shop_WooCommerce::buy_url( $plan->ID ) : '';
				?>
				<div class="passpress-plan-card<?php echo $most_popular ? ' passpress-plan-card-featured' : ''; ?>">
					<?php if ( $most_popular ) : ?>
						<span class="passpress-plan-badge"><?php esc_html_e( 'Most Popular', 'passpress' ); ?></span>
					<?php endif; ?>

					<h3 class="passpress-plan-name"><?php echo esc_html( $plan->post_title ); ?></h3>
					<?php if ( $duration_unit && 'lifetime' !== $duration_unit ) : ?>
						<p class="passpress-plan-duration-label">
							<?php echo esc_html( $duration_value . ' ' . $duration_unit . ( $duration_value > 1 ? 's' : '' ) ); ?>
						</p>
					<?php endif; ?>

					<p class="passpress-plan-price">
						<span class="passpress-plan-price-value"><?php echo esc_html( $settings['currency_symbol'] . number_format_i18n( $price, 2 ) ); ?></span>
						<?php if ( $duration_unit && 'lifetime' !== $duration_unit && in_array( $duration_unit, array( 'month', 'year' ), true ) ) : ?>
							<span class="passpress-plan-duration">/ <?php echo esc_html( $duration_unit ); ?></span>
						<?php endif; ?>
					</p>

					<?php if ( $plan->post_content ) : ?>
						<div class="passpress-plan-description"><?php echo wp_kses_post( $plan->post_content ); ?></div>
					<?php endif; ?>

					<?php if ( $features ) : ?>
						<ul class="passpress-plan-features">
							<?php foreach ( $features as $feature ) : ?>
								<li><?php echo esc_html( $feature ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<div class="passpress-plan-cta-group">
						<?php if ( PP_Billing::is_billing_available() ) : ?>
							<a class="passpress-plan-btn passpress-plan-btn-primary" href="<?php echo esc_url( PP_Billing::checkout_url( $plan->ID ) ); ?>">
								<?php
								/* translators: %s: plan name */
								echo esc_html( sprintf( __( 'Buy %s', 'passpress' ), $plan->post_title ) );
								?>
							</a>
						<?php else : ?>
							<p class="passpress-plan-cta-fallback"><?php esc_html_e( 'Visit the front desk to join this plan.', 'passpress' ); ?></p>
						<?php endif; ?>
						<?php if ( $shop_url ) : ?>
							<a class="passpress-plan-btn passpress-plan-btn-secondary" href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Buy via Shop', 'passpress' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
