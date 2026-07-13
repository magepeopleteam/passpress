<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register walk-in visitors, finalize member-submitted guest invitations,
 * and view visitor pass history. Row actions (renew/cancel) reuse
 * PP_Membership_Renewal / PP_Membership_Status — a visitor pass is a real
 * pp_memberships row.
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

		$result      = PP_Visitor::get_history( array( 'status' => $status, 'search' => $search, 'paged' => $paged, 'per_page' => 20 ) );
		$invitations = PP_Visitor::get_pending_invitations();
		$plans       = self::get_plans();
		$items       = isset( $result['items'] ) ? $result['items'] : array();

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap passpress-visitors-page">
			<div class="passpress-visitors-page-header">
				<div class="passpress-visitors-page-copy">
					<p class="passpress-visitors-page-eyebrow"><?php esc_html_e( 'Front desk', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Visitors', 'passpress' ); ?></h1>
					<p class="passpress-visitors-page-desc"><?php esc_html_e( 'Register walk-ins, finalize guest invites, and manage visitor passes.', 'passpress' ); ?></p>
				</div>
				<button type="button" class="passpress-visitors-register-btn" id="passpress-register-visitor-trigger">
					<?php esc_html_e( 'Register visitor', 'passpress' ); ?>
				</button>
			</div>

			<?php if ( $invitations ) : ?>
				<section class="passpress-visitors-invites">
					<div class="passpress-visitors-invites-header">
						<p class="passpress-visitors-invites-eyebrow"><?php esc_html_e( 'Invitations', 'passpress' ); ?></p>
						<h2><?php esc_html_e( 'Pending guest invitations', 'passpress' ); ?></h2>
					</div>
					<div class="passpress-visitors-table-wrap">
						<table class="passpress-visitors-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Guest', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'Email', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'Invited by', 'passpress' ); ?></th>
									<th><?php esc_html_e( 'Issue pass', 'passpress' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $invitations as $guest ) : ?>
									<?php $host = PP_Visitor::get_host( $guest->ID ); ?>
									<tr>
										<td>
											<div class="passpress-visitors-person">
												<span class="passpress-visitors-avatar"><?php echo esc_html( self::initials( $guest->display_name ) ); ?></span>
												<strong><?php echo esc_html( $guest->display_name ); ?></strong>
											</div>
										</td>
										<td>
											<?php echo esc_html( str_ends_with( $guest->user_email, '@passpress.invalid' ) ? '—' : $guest->user_email ); ?>
										</td>
										<td><?php echo esc_html( $host ? $host->display_name : '—' ); ?></td>
										<td>
											<form method="post" class="passpress-visitors-issue-form">
												<?php wp_nonce_field( 'pp_finalize_invitation_' . $guest->ID ); ?>
												<input type="hidden" name="guest_user_id" value="<?php echo esc_attr( (string) $guest->ID ); ?>">
												<select name="plan_id" class="pp-input pp-input-select" required>
													<option value=""><?php esc_html_e( 'Select a pass type', 'passpress' ); ?></option>
													<?php foreach ( $plans as $plan ) : ?>
														<option value="<?php echo esc_attr( (string) $plan->ID ); ?>"><?php echo esc_html( $plan->post_title ); ?></option>
													<?php endforeach; ?>
												</select>
												<button type="submit" name="pp_finalize_invitation" value="1" class="passpress-visitors-issue-btn"><?php esc_html_e( 'Issue', 'passpress' ); ?></button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
			<?php endif; ?>

			<div class="passpress-visitors-toolbar">
				<div class="passpress-visitors-toolbar-top">
					<div class="passpress-visitors-tabs">
						<?php foreach ( self::status_filters() as $key => $label ) : ?>
							<a
								class="passpress-visitors-tab<?php echo $status === $key ? ' is-active' : ''; ?>"
								href="<?php echo esc_url( add_query_arg( array( 'page' => 'passpress-visitors', 'status' => $key, 's' => $search ), admin_url( 'admin.php' ) ) ); ?>"
							>
								<?php echo esc_html( $label ); ?>
							</a>
						<?php endforeach; ?>
					</div>

					<form method="get" class="passpress-visitors-search-form">
						<input type="hidden" name="page" value="passpress-visitors">
						<?php if ( $status ) : ?>
							<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
						<?php endif; ?>
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search pass #…', 'passpress' ); ?>" class="passpress-visitors-search">
						<button type="submit" class="passpress-visitors-search-btn"><?php esc_html_e( 'Search', 'passpress' ); ?></button>
					</form>
				</div>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<div class="passpress-visitors-empty">
					<p class="passpress-visitors-empty-eyebrow"><?php esc_html_e( 'History', 'passpress' ); ?></p>
					<h2 class="passpress-visitors-empty-title"><?php esc_html_e( 'No visitor passes found', 'passpress' ); ?></h2>
					<p class="passpress-visitors-empty-desc"><?php esc_html_e( 'Register a walk-in or issue a pass from a pending invitation to get started.', 'passpress' ); ?></p>
				</div>
			<?php else : ?>
				<div class="passpress-visitors-table-wrap">
					<table class="passpress-visitors-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Pass #', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Visitor', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Host', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Pass type', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Status', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'Expiry', 'passpress' ); ?></th>
								<th><?php esc_html_e( 'PIN', 'passpress' ); ?></th>
								<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'passpress' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $membership ) : ?>
								<?php
								$host      = PP_Visitor::get_host( $membership->user_id );
								$visitor   = self::user_name( $membership->user_id );
								$user      = get_userdata( $membership->user_id );
								$email     = $user && ! str_ends_with( $user->user_email, '@passpress.invalid' ) ? $user->user_email : '';
								?>
								<tr>
									<td><code class="passpress-visitors-code"><?php echo esc_html( $membership->membership_number ); ?></code></td>
									<td>
										<div class="passpress-visitors-person">
											<span class="passpress-visitors-avatar"><?php echo esc_html( self::initials( $visitor ) ); ?></span>
											<span class="passpress-visitors-person-info">
												<strong><?php echo esc_html( $visitor ); ?></strong>
												<?php if ( $email ) : ?>
													<span><?php echo esc_html( $email ); ?></span>
												<?php endif; ?>
											</span>
										</div>
									</td>
									<td><?php echo esc_html( $host ? $host->display_name : '—' ); ?></td>
									<td><span class="passpress-visitors-plan"><?php echo esc_html( get_the_title( $membership->plan_id ) ); ?></span></td>
									<td>
										<span class="passpress-visitors-status passpress-visitors-status-<?php echo esc_attr( $membership->status ); ?>">
											<span class="passpress-visitors-status-dot"></span>
											<?php echo esc_html( pp_status_label( $membership->status ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( pp_format_date( $membership->expiry_date ) ); ?></td>
									<td><code class="passpress-visitors-pin"><?php echo esc_html( $membership->pin_code ); ?></code></td>
									<td class="passpress-visitors-actions-cell"><?php self::render_row_actions( $membership ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php self::render_register_modal( $plans ); ?>
		</div>
		<?php
	}

	/**
	 * @param WP_Post[] $plans
	 */
	private static function render_register_modal( $plans ) {
		?>
		<div id="passpress-register-visitor-modal" class="passpress-modal-overlay" hidden>
			<div class="passpress-modal passpress-visitor-modal" role="dialog" aria-modal="true" aria-labelledby="passpress-register-visitor-title">
				<div class="pp-modal-header">
					<div>
						<p class="pp-modal-eyebrow"><?php esc_html_e( 'Walk-in', 'passpress' ); ?></p>
						<h2 id="passpress-register-visitor-title"><?php esc_html_e( 'Register visitor', 'passpress' ); ?></h2>
					</div>
					<button type="button" class="passpress-modal-close" aria-label="<?php esc_attr_e( 'Close', 'passpress' ); ?>">&times;</button>
				</div>

				<form method="post" class="pp-plan-form">
					<?php wp_nonce_field( 'pp_register_visitor' ); ?>
					<div class="pp-field">
						<label class="pp-label" for="pp_visitor_name"><?php esc_html_e( 'Name', 'passpress' ); ?></label>
						<input type="text" id="pp_visitor_name" name="visitor_name" class="pp-input" placeholder="<?php esc_attr_e( 'Alex Rivera', 'passpress' ); ?>" required>
					</div>
					<div class="pp-field-row">
						<div class="pp-field">
							<label class="pp-label" for="pp_visitor_email"><?php esc_html_e( 'Email', 'passpress' ); ?> <span class="pp-label-hint"><?php esc_html_e( 'optional', 'passpress' ); ?></span></label>
							<input type="email" id="pp_visitor_email" name="visitor_email" class="pp-input" placeholder="<?php esc_attr_e( 'alex@email.com', 'passpress' ); ?>">
						</div>
						<div class="pp-field">
							<label class="pp-label" for="pp_visitor_phone"><?php esc_html_e( 'Phone', 'passpress' ); ?> <span class="pp-label-hint"><?php esc_html_e( 'optional', 'passpress' ); ?></span></label>
							<input type="text" id="pp_visitor_phone" name="visitor_phone" class="pp-input" placeholder="<?php esc_attr_e( '+1 555 0100', 'passpress' ); ?>">
						</div>
					</div>
					<div class="pp-field">
						<label class="pp-label" for="pp_visitor_plan_id"><?php esc_html_e( 'Pass type', 'passpress' ); ?></label>
						<select name="plan_id" id="pp_visitor_plan_id" class="pp-input pp-input-select" required>
							<option value=""><?php esc_html_e( 'Select a pass type', 'passpress' ); ?></option>
							<?php foreach ( $plans as $plan ) : ?>
								<option value="<?php echo esc_attr( (string) $plan->ID ); ?>"><?php echo esc_html( $plan->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="pp-modal-footer">
						<button type="button" class="pp-btn-outline passpress-modal-cancel"><?php esc_html_e( 'Cancel', 'passpress' ); ?></button>
						<button type="submit" name="pp_register_visitor" class="pp-btn-solid"><?php esc_html_e( 'Register & issue pass', 'passpress' ); ?></button>
					</div>
				</form>
			</div>
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
		return get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
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

	private static function render_row_actions( $membership ) {
		$actions = array(
			'renew'  => __( 'Renew', 'passpress' ),
			'cancel' => __( 'Cancel', 'passpress' ),
		);
		?>
		<div class="passpress-visitors-actions">
			<?php foreach ( $actions as $action => $label ) : ?>
				<?php
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
				?>
				<a class="passpress-visitors-action passpress-visitors-action-<?php echo esc_attr( $action ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
		<?php
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
