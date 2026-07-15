<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $plans */
/** @var array $settings */
?>
<div class="passpress-plan-list-wrap">
	<div class="passpress-plan-header">
		<p class="passpress-plan-eyebrow"><?php esc_html_e( 'Membership', 'passpress' ); ?></p>
		<h2><?php esc_html_e( 'Choose your plan', 'passpress' ); ?></h2>
		<p class="passpress-plan-subtext"><?php esc_html_e( 'Pick the pass that fits — subscribe online and get instant access.', 'passpress' ); ?></p>
	</div>

	<?php if ( ! $plans ) : ?>
		<div class="passpress-plan-empty">
			<p class="passpress-plan-eyebrow"><?php esc_html_e( 'Coming soon', 'passpress' ); ?></p>
			<h3 class="passpress-plan-empty-title"><?php esc_html_e( 'No plans available yet', 'passpress' ); ?></h3>
			<p class="passpress-plan-empty-desc"><?php esc_html_e( 'Membership plans will appear here once they are published.', 'passpress' ); ?></p>
		</div>
	<?php else : ?>
		<div class="passpress-plan-list">
			<?php
			foreach ( $plans as $plan ) :
				$price          = (float) get_post_meta( $plan->ID, '_pp_price', true );
				$duration_value = (int) get_post_meta( $plan->ID, '_pp_duration_value', true );
				$duration_unit  = get_post_meta( $plan->ID, '_pp_duration_unit', true );
				$features       = array_filter( array_map( 'trim', explode( "\n", (string) get_post_meta( $plan->ID, '_pp_features', true ) ) ) );
				$most_popular   = (bool) get_post_meta( $plan->ID, '_pp_most_popular', true );
				$shop_url       = class_exists( 'PP_Shop_WooCommerce' ) ? PP_Shop_WooCommerce::buy_url( $plan->ID ) : '';
				$price_label    = $settings['currency_symbol'] . number_format_i18n( $price, 2 );

				$duration_label = '';
				if ( 'lifetime' === $duration_unit ) {
					$duration_label = __( 'Lifetime', 'passpress' );
				} elseif ( $duration_unit ) {
					$duration_label = $duration_value . ' ' . $duration_unit . ( $duration_value > 1 ? 's' : '' );
				}
				?>
				<article class="passpress-plan-card<?php echo $most_popular ? ' passpress-plan-card-featured' : ''; ?>">
					<div class="passpress-plan-card-top">
						<?php if ( $duration_label ) : ?>
							<span class="passpress-plan-duration-label"><?php echo esc_html( $duration_label ); ?></span>
						<?php endif; ?>
						<?php if ( $most_popular ) : ?>
							<span class="passpress-plan-badge"><?php esc_html_e( 'Most popular', 'passpress' ); ?></span>
						<?php endif; ?>
					</div>

					<h3 class="passpress-plan-name"><?php echo esc_html( $plan->post_title ); ?></h3>

					<p class="passpress-plan-price">
						<span class="passpress-plan-price-value"><?php echo esc_html( $price_label ); ?></span>
						<?php if ( $duration_unit && in_array( $duration_unit, array( 'month', 'year' ), true ) ) : ?>
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
						<?php if ( PP_Billing::is_woocommerce_mode() ) : ?>
							<?php if ( $shop_url ) : ?>
								<a class="passpress-plan-btn passpress-plan-btn-primary" href="<?php echo esc_url( $shop_url ); ?>">
									<?php esc_html_e( 'Get this pass', 'passpress' ); ?>
								</a>
							<?php else : ?>
								<p class="passpress-plan-cta-fallback"><?php esc_html_e( 'This plan is not ready for checkout yet. Please try again shortly or contact the front desk.', 'passpress' ); ?></p>
							<?php endif; ?>
						<?php elseif ( PP_Billing::is_native_mode() && PP_Billing::is_billing_available() ) : ?>
							<a
								class="passpress-plan-btn passpress-plan-btn-primary passpress-open-checkout"
								href="#"
								role="button"
								data-checkout-url="<?php echo esc_url( PP_Billing::checkout_url( $plan->ID ) ); ?>"
								data-plan-id="<?php echo esc_attr( $plan->ID ); ?>"
								data-plan-name="<?php echo esc_attr( $plan->post_title ); ?>"
								data-plan-price="<?php echo esc_attr( number_format( $price, 2, '.', '' ) ); ?>"
								data-plan-price-label="<?php echo esc_attr( $price_label ); ?>"
								data-nonce="<?php echo esc_attr( wp_create_nonce( 'passpress_checkout_' . $plan->ID ) ); ?>"
							>
								<?php esc_html_e( 'Get this pass', 'passpress' ); ?>
							</a>
						<?php else : ?>
							<p class="passpress-plan-cta-fallback"><?php esc_html_e( 'Visit the front desk to join this plan.', 'passpress' ); ?></p>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
