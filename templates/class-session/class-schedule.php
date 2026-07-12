<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var WP_Post[] $classes */
?>
<div class="passpress-class-schedule">
	<?php if ( ! $classes ) : ?>
		<p><?php esc_html_e( 'No classes are scheduled yet.', 'passpress' ); ?></p>
	<?php else : ?>
		<?php foreach ( $classes as $class ) :
			$instructor_id = (int) get_post_meta( $class->ID, '_pp_instructor_id', true );
			$instructor    = $instructor_id ? get_userdata( $instructor_id ) : null;
			$start_time    = get_post_meta( $class->ID, '_pp_start_time', true );
			$end_time      = get_post_meta( $class->ID, '_pp_end_time', true );
			$day_of_week   = (int) get_post_meta( $class->ID, '_pp_day_of_week', true );
			$weekdays      = PP_Class_Session_CPT::weekdays();
			$occurrences   = PP_Class_Session::get_upcoming_occurrences( $class->ID );
			?>
			<div class="passpress-class-card">
				<h3><?php echo esc_html( $class->post_title ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: 1: weekday name, 2: start time, 3: end time */
						esc_html__( 'Every %1$s, %2$s–%3$s', 'passpress' ),
						esc_html( isset( $weekdays[ $day_of_week ] ) ? $weekdays[ $day_of_week ] : '' ),
						esc_html( $start_time ),
						esc_html( $end_time )
					);
					?>
					<?php if ( $instructor ) : ?>
						&middot; <?php esc_html_e( 'Instructor:', 'passpress' ); ?> <?php echo esc_html( $instructor->display_name ); ?>
					<?php endif; ?>
				</p>
				<table class="passpress-class-occurrences">
					<?php foreach ( $occurrences as $occurrence ) : ?>
						<tr data-class-id="<?php echo esc_attr( $class->ID ); ?>" data-date="<?php echo esc_attr( $occurrence['date'] ); ?>">
							<td><?php echo esc_html( pp_format_date( $occurrence['date'] ) ); ?></td>
							<td><?php echo esc_html( $occurrence['available'] . '/' . $occurrence['capacity'] ); ?></td>
							<td>
								<?php if ( $occurrence['full'] ) : ?>
									<button type="button" class="button pp-class-waitlist-btn"><?php esc_html_e( 'Join Waitlist', 'passpress' ); ?></button>
								<?php else : ?>
									<button type="button" class="button button-primary pp-class-book-btn"><?php esc_html_e( 'Book', 'passpress' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
	<div class="passpress-class-message passpress-checkout-notice" style="display:none;"></div>
</div>
