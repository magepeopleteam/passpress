<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register walk-in visitors, finalize member-submitted guest invitations,
 * and view visitor pass history. Row actions (renew/freeze/suspend/
 * reactivate/cancel) reuse the exact same PP_Membership_Renewal /
 * PP_Membership_Status calls PP_Memberships_List uses — a visitor pass is a
 * real pp_memberships row, so nothing membership-specific needed duplicating.
 */
class PP_Visitors_List {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		self::maybe_handle_actions();

		$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$result       = PP_Visitor::get_history( array( 'status' => $status, 'search' => $search, 'paged' => $paged, 'per_page' => 20 ) );
		$invitations  = PP_Visitor::get_pending_invitations();

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'Visitors', 'passpress' ); ?></h1>

			<details class="passpress-issue-membership" open>
				<summary><?php esc_html_e( 'Register Walk-in Visitor', 'passpress' ); ?></summary>
				<form method="post">
					<?php wp_nonce_field( 'pp_register_visitor' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="pp_visitor_name"><?php esc_html_e( 'Name', 'passpress' ); ?></label></th>
							<td><input type="text" id="pp_visitor_name" name="visitor_name" class="regular-text" required></td>
						</tr>
						<tr>
							<th><label for="pp_visitor_email"><?php esc_html_e( 'Email (optional)', 'passpress' ); ?></label></th>
							<td><input type="email" id="pp_visitor_email" name="visitor_email" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="pp_visitor_phone"><?php esc_html_e( 'Phone (optional)', 'passpress' ); ?></label></th>
							<td><input type="text" id="pp_visitor_phone" name="visitor_phone" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="pp_visitor_plan_id"><?php esc_html_e( 'Pass Type', 'passpress' ); ?></label></th>
							<td>
								<select name="plan_id" id="pp_visitor_plan_id" required>
									<option value=""><?php esc_html_e( 'Select a pass type', 'passpress' ); ?></option>
									<?php foreach ( self::get_plans() as $plan ) : ?>
										<option value="<?php echo esc_attr( $plan->ID ); ?>"><?php echo esc_html( $plan->post_title ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Register & Issue Pass', 'passpress' ), 'primary', 'pp_register_visitor' ); ?>
				</form>
			</details>

			<?php if ( $invitations ) : ?>
				<h2><?php esc_html_e( 'Pending Guest Invitations', 'passpress' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Guest', 'passpress' ); ?></th>
							<th><?php esc_html_e( 'Email', 'passpress' ); ?></th>
							<th><?php esc_html_e( 'Invited By', 'passpress' ); ?></th>
							<th><?php esc_html_e( 'Issue Pass', 'passpress' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $invitations as $guest ) : $host = PP_Visitor::get_host( $guest->ID ); ?>
							<tr>
								<td><?php echo esc_html( $guest->display_name ); ?></td>
								<td><?php echo esc_html( str_ends_with( $guest->user_email, '@passpress.invalid' ) ? '—' : $guest->user_email ); ?></td>
								<td><?php echo esc_html( $host ? $host->display_name : '—' ); ?></td>
								<td>
									<form method="post" style="display:flex;gap:6px;">
										<?php wp_nonce_field( 'pp_finalize_invitation_' . $guest->ID ); ?>
										<input type="hidden" name="guest_user_id" value="<?php echo esc_attr( $guest->ID ); ?>">
										<select name="plan_id" required>
											<option value=""><?php esc_html_e( 'Select a pass type', 'passpress' ); ?></option>
											<?php foreach ( self::get_plans() as $plan ) : ?>
												<option value="<?php echo esc_attr( $plan->ID ); ?>"><?php echo esc_html( $plan->post_title ); ?></option>
											<?php endforeach; ?>
										</select>
										<button type="submit" name="pp_finalize_invitation" value="1" class="button"><?php esc_html_e( 'Issue', 'passpress' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Visitor Pass History', 'passpress' ); ?></h2>

			<ul class="subsubsub">
				<?php foreach ( self::status_filters() as $key => $label ) : ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'status', $key, admin_url( 'admin.php?page=passpress-visitors' ) ) ); ?>" class="<?php echo $status === $key ? 'current' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
						<?php if ( 'cancelled' !== $key ) : ?> | <?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<form method="get">
				<input type="hidden" name="page" value="passpress-visitors">
				<?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search pass #', 'passpress' ); ?>">
					<?php submit_button( __( 'Search', 'passpress' ), '', '', false ); ?>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Pass #', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Visitor', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Host', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Pass Type', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Status', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Expiry', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'PIN', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'passpress' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No visitor passes found.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['items'] as $membership ) :
							$host = PP_Visitor::get_host( $membership->user_id );
							?>
							<tr>
								<td><?php echo esc_html( $membership->membership_number ); ?></td>
								<td><?php echo esc_html( self::user_name( $membership->user_id ) ); ?></td>
								<td><?php echo esc_html( $host ? $host->display_name : '—' ); ?></td>
								<td><?php echo esc_html( get_the_title( $membership->plan_id ) ); ?></td>
								<td><span class="passpress-badge passpress-badge-<?php echo esc_attr( $membership->status ); ?>"><?php echo esc_html( pp_status_label( $membership->status ) ); ?></span></td>
								<td><?php echo esc_html( pp_format_date( $membership->expiry_date ) ); ?></td>
								<td><?php echo esc_html( $membership->pin_code ); ?></td>
								<td><?php self::render_row_actions( $membership ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function status_filters() {
		return array(
			''          => __( 'All', 'passpress' ),
			'active'    => __( 'Active', 'passpress' ),
			'expired'   => __( 'Expired', 'passpress' ),
			'cancelled' => __( 'Cancelled', 'passpress' ),
		);
	}

	private static function get_plans() {
		return get_posts( array( 'post_type' => 'pp_membership_plan', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
	}

	private static function user_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown', 'passpress' );
	}

	private static function render_row_actions( $membership ) {
		$actions = array();
		foreach ( array(
			'renew'      => __( 'Renew', 'passpress' ),
			'cancel'     => __( 'Cancel', 'passpress' ),
		) as $action => $label ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'page'      => 'passpress-visitors',
						'pp_action' => $action,
						'id'        => $membership->id,
					),
					admin_url( 'admin.php' )
				),
				'pp_visitor_action_' . $membership->id
			);
			$actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo wp_kses_post( implode( ' | ', $actions ) );
	}

	private static function maybe_handle_actions() {
		if ( isset( $_POST['pp_register_visitor'] ) && check_admin_referer( 'pp_register_visitor' ) ) {
			$name    = isset( $_POST['visitor_name'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_name'] ) ) : '';
			$email   = isset( $_POST['visitor_email'] ) ? sanitize_email( wp_unslash( $_POST['visitor_email'] ) ) : '';
			$phone   = isset( $_POST['visitor_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_phone'] ) ) : '';
			$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;

			$result = PP_Visitor::register( $name, $email, $phone, $plan_id );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'passpress', 'pp_visitor_error', $result->get_error_message(), 'error' );
			} else {
				/* translators: %s: pass number */
				add_settings_error( 'passpress', 'pp_visitor_success', sprintf( __( 'Visitor pass %s issued.', 'passpress' ), $result->membership_number ), 'success' );
			}
		}

		if ( isset( $_POST['pp_finalize_invitation'] ) ) {
			$guest_user_id = isset( $_POST['guest_user_id'] ) ? absint( $_POST['guest_user_id'] ) : 0;
			if ( $guest_user_id && check_admin_referer( 'pp_finalize_invitation_' . $guest_user_id ) ) {
				$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
				$result  = PP_Visitor::finalize_invitation( $guest_user_id, $plan_id );

				if ( is_wp_error( $result ) ) {
					add_settings_error( 'passpress', 'pp_invite_error', $result->get_error_message(), 'error' );
				} else {
					add_settings_error( 'passpress', 'pp_invite_success', sprintf( __( 'Visitor pass %s issued.', 'passpress' ), $result->membership_number ), 'success' );
				}
			}
		}

		if ( isset( $_GET['pp_action'], $_GET['id'], $_GET['_wpnonce'] ) ) {
			$id     = absint( $_GET['id'] );
			$action = sanitize_key( $_GET['pp_action'] );

			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'pp_visitor_action_' . $id ) ) {
				switch ( $action ) {
					case 'renew':
						PP_Membership_Renewal::renew( $id );
						break;
					case 'cancel':
						PP_Membership_Status::cancel( $id );
						break;
				}
				add_settings_error( 'passpress', 'pp_action_done', __( 'Visitor pass updated.', 'passpress' ), 'success' );
			}
		}
	}
}
