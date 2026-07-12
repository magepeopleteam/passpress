<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin bookings list: filter by facility/status, and mark bookings
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

		$result = PP_Booking::get_list(
			array(
				'status'           => $status,
				'facility_id'      => $facility_id,
				'class_session_id' => $class_session_id,
				'paged'            => $paged,
				'per_page'         => 20,
			)
		);

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'Bookings', 'passpress' ); ?></h1>

			<form method="get" style="margin-bottom:12px;">
				<input type="hidden" name="page" value="passpress-bookings">
				<select name="facility_id">
					<option value="0"><?php esc_html_e( 'All Facilities', 'passpress' ); ?></option>
					<?php foreach ( PP_Facility::get_all() as $facility ) : ?>
						<option value="<?php echo esc_attr( $facility->ID ); ?>" <?php selected( $facility_id, $facility->ID ); ?>><?php echo esc_html( $facility->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'passpress' ); ?></option>
					<?php foreach ( array( 'confirmed', 'cancelled', 'completed', 'no_show' ) as $status_option ) : ?>
						<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $status, $status_option ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $status_option ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="class_session_id">
					<option value="0"><?php esc_html_e( 'All Classes', 'passpress' ); ?></option>
					<?php foreach ( PP_Class_Session::get_all() as $class ) : ?>
						<option value="<?php echo esc_attr( $class->ID ); ?>" <?php selected( $class_session_id, $class->ID ); ?>><?php echo esc_html( $class->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Filter', 'passpress' ), '', '', false ); ?>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Facility / Class', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Member', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Date', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Time', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Checked In', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Status', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'passpress' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No bookings found.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['items'] as $booking ) : ?>
							<tr>
								<td><?php echo esc_html( $booking->class_session_id ? get_the_title( $booking->class_session_id ) : get_the_title( $booking->facility_id ) ); ?></td>
								<td><?php echo esc_html( self::user_name( $booking->user_id ) ); ?></td>
								<td><?php echo esc_html( pp_format_date( $booking->booking_date ) ); ?></td>
								<td><?php echo esc_html( substr( $booking->start_time, 0, 5 ) . '–' . substr( $booking->end_time, 0, 5 ) ); ?></td>
								<td><?php echo esc_html( $booking->checked_in_at ? pp_format_datetime( $booking->checked_in_at ) : '—' ); ?></td>
								<td><span class="passpress-badge"><?php echo esc_html( $booking->status ); ?></span></td>
								<td><?php self::render_row_actions( $booking ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function user_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown', 'passpress' );
	}

	private static function render_row_actions( $booking ) {
		if ( 'confirmed' !== $booking->status ) {
			echo '&mdash;';
			return;
		}

		$actions = array();
		foreach ( array(
			'cancel'   => __( 'Cancel', 'passpress' ),
			'complete' => __( 'Complete', 'passpress' ),
			'no_show'  => __( 'No-show', 'passpress' ),
		) as $action => $label ) {
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
			$actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}

		echo wp_kses_post( implode( ' | ', $actions ) );
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
