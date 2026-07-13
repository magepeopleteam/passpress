<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin bookings list: filter by facility/status/class, and mark bookings
 * cancelled/completed/no-show. Admin cancellations bypass the facility's
 * cancellation lead-time check (that only applies to member self-service
 * cancellation via the frontend "My Bookings" list).
 */
class PP_Bookings_List {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		self::maybe_handle_actions();

		$status           = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$facility_id      = isset( $_GET['facility_id'] ) ? absint( $_GET['facility_id'] ) : 0;
		$class_session_id = isset( $_GET['class_session_id'] ) ? absint( $_GET['class_session_id'] ) : 0;
		$paged            = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page         = 20;

		$result = PP_Booking::get_list(
			array(
				'status'           => $status,
				'facility_id'      => $facility_id,
				'class_session_id' => $class_session_id,
				'paged'            => $paged,
				'per_page'         => $per_page,
			)
		);

		$items  = isset( $result['items'] ) ? $result['items'] : array();
		$total  = isset( $result['total'] ) ? (int) $result['total'] : 0;
		$counts = self::status_counts( $facility_id, $class_session_id );

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap passpress-bookings-page">
			<div class="passpress-bookings-page-header">
				<div class="passpress-bookings-page-copy">
					<p class="passpress-bookings-page-eyebrow"><?php esc_html_e( 'Schedule', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Bookings', 'passpress' ); ?></h1>
					<p class="passpress-bookings-page-desc"><?php esc_html_e( 'Review facility and class bookings, check-ins, and attendance outcomes.', 'passpress' ); ?></p>
				</div>
			</div>

			<div class="passpress-bookings-stat-row">
				<div class="passpress-bookings-stat">
					<span class="passpress-bookings-stat-label"><?php esc_html_e( 'Confirmed', 'passpress' ); ?></span>
					<span class="passpress-bookings-stat-number is-confirmed"><?php echo esc_html( number_format_i18n( $counts['confirmed'] ) ); ?></span>
				</div>
				<div class="passpress-bookings-stat">
					<span class="passpress-bookings-stat-label"><?php esc_html_e( 'Completed', 'passpress' ); ?></span>
					<span class="passpress-bookings-stat-number is-completed"><?php echo esc_html( number_format_i18n( $counts['completed'] ) ); ?></span>
				</div>
				<div class="passpress-bookings-stat">
					<span class="passpress-bookings-stat-label"><?php esc_html_e( 'No show', 'passpress' ); ?></span>
					<span class="passpress-bookings-stat-number is-no-show"><?php echo esc_html( number_format_i18n( $counts['no_show'] ) ); ?></span>
				</div>
				<div class="passpress-bookings-stat">
					<span class="passpress-bookings-stat-label"><?php esc_html_e( 'Cancelled', 'passpress' ); ?></span>
					<span class="passpress-bookings-stat-number is-cancelled"><?php echo esc_html( number_format_i18n( $counts['cancelled'] ) ); ?></span>
				</div>
			</div>

			<div class="passpress-bookings-toolbar">
				<div class="passpress-bookings-toolbar-top">
					<div class="passpress-bookings-tabs">
						<?php foreach ( self::status_filters() as $key => $label ) : ?>
							<a
								class="passpress-bookings-tab<?php echo $status === $key ? ' is-active' : ''; ?>"
								href="<?php echo esc_url( self::filter_url( $key, $facility_id, $class_session_id ) ); ?>"
							>
								<?php echo esc_html( $label ); ?>
							</a>
						<?php endforeach; ?>
					</div>

					<form method="get" class="passpress-bookings-filters">
						<input type="hidden" name="page" value="passpress-bookings">
						<?php if ( $status ) : ?>
							<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
						<?php endif; ?>
						<select name="facility_id" class="passpress-bookings-select">
							<option value="0"><?php esc_html_e( 'All facilities', 'passpress' ); ?></option>
							<?php foreach ( PP_Facility::get_all() as $facility ) : ?>
								<option value="<?php echo esc_attr( $facility->ID ); ?>" <?php selected( $facility_id, $facility->ID ); ?>><?php echo esc_html( $facility->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="class_session_id" class="passpress-bookings-select">
							<option value="0"><?php esc_html_e( 'All classes', 'passpress' ); ?></option>
							<?php foreach ( PP_Class_Session::get_all() as $class ) : ?>
								<option value="<?php echo esc_attr( $class->ID ); ?>" <?php selected( $class_session_id, $class->ID ); ?>><?php echo esc_html( $class->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="passpress-bookings-filter-btn"><?php esc_html_e( 'Filter', 'passpress' ); ?></button>
					</form>
				</div>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<div class="passpress-bookings-empty">
					<p class="passpress-bookings-empty-eyebrow"><?php esc_html_e( 'Calendar', 'passpress' ); ?></p>
					<h2 class="passpress-bookings-empty-title"><?php esc_html_e( 'No bookings found', 'passpress' ); ?></h2>
					<p class="passpress-bookings-empty-desc"><?php esc_html_e( 'Try a different status or clear the facility and class filters.', 'passpress' ); ?></p>
				</div>
			<?php else : ?>
				<div class="passpress-bookings-table-wrap">
					<table class="passpress-bookings-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Facility / Class', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Member', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'When', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Checked in', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Status', 'passpress' ); ?></th>
								<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'passpress' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $booking ) : ?>
								<?php
								$is_class = ! empty( $booking->class_session_id );
								$title    = $is_class ? get_the_title( $booking->class_session_id ) : get_the_title( $booking->facility_id );
								$type     = $is_class ? __( 'Class', 'passpress' ) : __( 'Facility', 'passpress' );
								$member   = self::user_name( $booking->user_id );
								$user     = get_userdata( $booking->user_id );
								$email    = $user ? $user->user_email : '';
								$time     = substr( $booking->start_time, 0, 5 ) . '–' . substr( $booking->end_time, 0, 5 );
								?>
								<tr>
									<td>
										<div class="passpress-bookings-session">
											<span class="passpress-bookings-session-type"><?php echo esc_html( $type ); ?></span>
											<strong><?php echo esc_html( $title ); ?></strong>
										</div>
									</td>
									<td>
										<div class="passpress-bookings-person">
											<span class="passpress-bookings-avatar"><?php echo esc_html( self::initials( $member ) ); ?></span>
											<span class="passpress-bookings-person-info">
												<strong><?php echo esc_html( $member ); ?></strong>
												<?php if ( $email ) : ?>
													<span><?php echo esc_html( $email ); ?></span>
												<?php endif; ?>
											</span>
										</div>
									</td>
									<td>
										<div class="passpress-bookings-when">
											<strong><?php echo esc_html( pp_format_date( $booking->booking_date ) ); ?></strong>
											<span><?php echo esc_html( $time ); ?></span>
										</div>
									</td>
									<td>
										<?php if ( $booking->checked_in_at ) : ?>
											<span class="passpress-bookings-checkin"><?php echo esc_html( pp_format_datetime( $booking->checked_in_at ) ); ?></span>
										<?php else : ?>
											<span class="passpress-bookings-empty-cell">—</span>
										<?php endif; ?>
									</td>
									<td>
										<span class="passpress-bookings-status passpress-bookings-status-<?php echo esc_attr( sanitize_html_class( $booking->status ) ); ?>">
											<span class="passpress-bookings-status-dot"></span>
											<?php echo esc_html( self::status_label( $booking->status ) ); ?>
										</span>
									</td>
									<td class="passpress-bookings-actions-cell"><?php self::render_row_actions( $booking ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php self::render_footer( $total, $paged, $per_page, $status, $facility_id, $class_session_id ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return array{confirmed:int,completed:int,no_show:int,cancelled:int}
	 */
	private static function status_counts( $facility_id, $class_session_id ) {
		$counts = array(
			'confirmed' => 0,
			'completed' => 0,
			'no_show'   => 0,
			'cancelled' => 0,
		);

		foreach ( array_keys( $counts ) as $status ) {
			$result = PP_Booking::get_list(
				array(
					'status'           => $status,
					'facility_id'      => $facility_id,
					'class_session_id' => $class_session_id,
					'paged'            => 1,
					'per_page'         => 1,
				)
			);
			$counts[ $status ] = isset( $result['total'] ) ? (int) $result['total'] : 0;
		}

		return $counts;
	}

	private static function status_filters() {
		return array(
			''          => __( 'All', 'passpress' ),
			'confirmed' => __( 'Confirmed', 'passpress' ),
			'completed' => __( 'Completed', 'passpress' ),
			'no_show'   => __( 'No show', 'passpress' ),
			'cancelled' => __( 'Cancelled', 'passpress' ),
		);
	}

	private static function status_label( $status ) {
		$labels = array(
			'confirmed' => __( 'Confirmed', 'passpress' ),
			'completed' => __( 'Completed', 'passpress' ),
			'no_show'   => __( 'No show', 'passpress' ),
			'cancelled' => __( 'Cancelled', 'passpress' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( '_', ' ', (string) $status ) );
	}

	private static function filter_url( $status, $facility_id, $class_session_id ) {
		$args = array( 'page' => 'passpress-bookings' );
		if ( $status ) {
			$args['status'] = $status;
		}
		if ( $facility_id ) {
			$args['facility_id'] = $facility_id;
		}
		if ( $class_session_id ) {
			$args['class_session_id'] = $class_session_id;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	private static function user_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown', 'passpress' );
	}

	private static function initials( $name ) {
		$parts = preg_split( '/\s+/', trim( (string) $name ) );
		if ( ! $parts ) {
			return '?';
		}
		$first = mb_substr( $parts[0], 0, 1 );
		$last  = count( $parts ) > 1 ? mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 ) : '';
		return strtoupper( $first . $last );
	}

	private static function render_row_actions( $booking ) {
		if ( 'confirmed' !== $booking->status ) {
			echo '<span class="passpress-bookings-empty-cell">&mdash;</span>';
			return;
		}

		$actions = array(
			'complete' => __( 'Complete', 'passpress' ),
			'no_show'  => __( 'No-show', 'passpress' ),
			'cancel'   => __( 'Cancel', 'passpress' ),
		);
		?>
		<div class="passpress-bookings-actions">
			<?php foreach ( $actions as $action => $label ) : ?>
				<?php
				$url = wp_nonce_url(
					add_query_arg(
						array(
							'page'         => 'passpress-bookings',
							'pp_bk_action' => $action,
							'id'           => $booking->id,
						),
						admin_url( 'admin.php' )
					),
					'pp_booking_action_' . $booking->id
				);
				?>
				<a class="passpress-bookings-action passpress-bookings-action-<?php echo esc_attr( $action ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function render_footer( $total, $paged, $per_page, $status, $facility_id, $class_session_id ) {
		if ( $total < 1 ) {
			return;
		}

		$first       = ( ( $paged - 1 ) * $per_page ) + 1;
		$last        = min( $total, $paged * $per_page );
		$total_pages = (int) ceil( $total / $per_page );
		?>
		<div class="passpress-bookings-footer">
			<p class="passpress-bookings-count">
				<?php
				printf(
					/* translators: 1: first row number, 2: last row number, 3: total bookings */
					esc_html__( 'Showing %1$s–%2$s of %3$s bookings', 'passpress' ),
					'<strong>' . esc_html( number_format_i18n( $first ) ) . '</strong>',
					esc_html( number_format_i18n( $last ) ),
					'<strong>' . esc_html( number_format_i18n( $total ) ) . '</strong>'
				);
				?>
			</p>
			<?php if ( $total_pages > 1 ) : ?>
				<div class="passpress-bookings-pagination">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php?page=passpress-bookings' ) ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $total_pages,
								'prev_text' => '&lsaquo;',
								'next_text' => '&rsaquo;',
								'add_args'  => array_filter(
									array(
										'status'           => $status,
										'facility_id'      => $facility_id,
										'class_session_id' => $class_session_id,
									)
								),
							)
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function maybe_handle_actions() {
		if ( ! isset( $_GET['pp_bk_action'], $_GET['id'], $_GET['_wpnonce'] ) ) {
			return;
		}

		$id     = absint( $_GET['id'] );
		$action = sanitize_key( $_GET['pp_bk_action'] );

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'pp_booking_action_' . $id ) ) {
			return;
		}

		switch ( $action ) {
			case 'cancel':
				PP_Booking::cancel( $id, 0 );
				break;
			case 'complete':
				PP_Booking::set_status( $id, PP_Booking::STATUS_COMPLETED );
				break;
			case 'no_show':
				PP_Booking::set_status( $id, PP_Booking::STATUS_NO_SHOW );
				break;
		}

		add_settings_error( 'passpress', 'pp_booking_done', __( 'Booking updated.', 'passpress' ), 'success' );
	}
}
