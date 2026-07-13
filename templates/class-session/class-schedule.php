<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $classes */
?>
<div class="passpress-class-schedule">
	<?php if ( ! $classes ) : ?>
		<div class="passpress-class-empty">
			<p class="passpress-class-empty-eyebrow"><?php esc_html_e( 'Schedule', 'passpress' ); ?></p>
			<h3 class="passpress-class-empty-title"><?php esc_html_e( 'No classes yet', 'passpress' ); ?></h3>
			<p class="passpress-class-empty-desc"><?php esc_html_e( 'Check back soon — new sessions will show up here.', 'passpress' ); ?></p>
		</div>
	<?php else : ?>
		<?php foreach ( $classes as $class ) :
			$instructor_id = (int) get_post_meta( $class->ID, '_pp_instructor_id', true );
			$instructor    = $instructor_id ? get_userdata( $instructor_id ) : null;
			$start_time    = get_post_meta( $class->ID, '_pp_start_time', true );
			$end_time      = get_post_meta( $class->ID, '_pp_end_time', true );
			$day_of_week   = (int) get_post_meta( $class->ID, '_pp_day_of_week', true );
			$weekdays      = PP_Class_Session_CPT::weekdays();
			$occurrences   = PP_Class_Session::get_upcoming_occurrences( $class->ID );
			$weekday_name  = isset( $weekdays[ $day_of_week ] ) ? $weekdays[ $day_of_week ] : '';
			?>
			<section class="passpress-class-card">
				<header class="passpress-class-card-header">
					<p class="passpress-class-card-eyebrow"><?php esc_html_e( 'Class', 'passpress' ); ?></p>
					<h3 class="passpress-class-card-title"><?php echo esc_html( $class->post_title ); ?></h3>
					<p class="passpress-class-card-meta">
						<span class="passpress-class-card-when">
							<?php
							printf(
								/* translators: 1: weekday name, 2: start time, 3: end time */
								esc_html__( 'Every %1$s · %2$s–%3$s', 'passpress' ),
								esc_html( $weekday_name ),
								esc_html( $start_time ),
								esc_html( $end_time )
							);
							?>
						</span>
						<?php if ( $instructor ) : ?>
							<span class="passpress-class-card-instructor">
								<?php
								printf(
									/* translators: %s: instructor display name */
									esc_html__( 'with %s', 'passpress' ),
									esc_html( $instructor->display_name )
								);
								?>
							</span>
						<?php endif; ?>
					</p>
				</header>

				<?php if ( empty( $occurrences ) ) : ?>
					<p class="passpress-class-no-dates"><?php esc_html_e( 'No upcoming dates.', 'passpress' ); ?></p>
				<?php else : ?>
					<ul class="passpress-class-occurrences">
						<?php foreach ( $occurrences as $occurrence ) :
							$available = (int) $occurrence['available'];
							$capacity  = max( 1, (int) $occurrence['capacity'] );
							$booked    = isset( $occurrence['booked'] ) ? (int) $occurrence['booked'] : max( 0, $capacity - $available );
							$pct_full  = (int) round( min( 100, ( $booked / $capacity ) * 100 ) );
							$is_full   = ! empty( $occurrence['full'] );
							?>
							<li
								class="passpress-class-occurrence<?php echo $is_full ? ' is-full' : ''; ?>"
								data-class-id="<?php echo esc_attr( $class->ID ); ?>"
								data-date="<?php echo esc_attr( $occurrence['date'] ); ?>"
							>
								<div class="passpress-class-occurrence-info">
									<p class="passpress-class-occurrence-date"><?php echo esc_html( pp_format_date( $occurrence['date'] ) ); ?></p>
									<div class="passpress-class-occurrence-capacity" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: available spots, 2: capacity */ __( '%1$d of %2$d spots open', 'passpress' ), $available, $capacity ) ); ?>">
										<span class="passpress-class-occurrence-bar" aria-hidden="true">
											<span class="passpress-class-occurrence-bar-fill" style="width: <?php echo esc_attr( (string) $pct_full ); ?>%;"></span>
										</span>
										<span class="passpress-class-occurrence-spots">
											<?php
											if ( $is_full ) {
												esc_html_e( 'Full', 'passpress' );
											} else {
												printf(
													/* translators: 1: available spots, 2: capacity */
													esc_html__( '%1$d / %2$d open', 'passpress' ),
													$available,
													$capacity
												);
											}
											?>
										</span>
									</div>
								</div>
								<div class="passpress-class-occurrence-action">
									<?php if ( $is_full ) : ?>
										<button type="button" class="passpress-class-btn passpress-class-btn-secondary pp-class-waitlist-btn"><?php esc_html_e( 'Join waitlist', 'passpress' ); ?></button>
									<?php else : ?>
										<button type="button" class="passpress-class-btn passpress-class-btn-primary pp-class-book-btn"><?php esc_html_e( 'Book', 'passpress' ); ?></button>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
	<div class="passpress-class-message" hidden role="status"></div>
</div>
