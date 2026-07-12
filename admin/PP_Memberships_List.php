<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Members admin screen: stat tiles (per-status counts, each clickable as a
 * status filter), an All/Corporate/Individual plan-scope toggle (derived
 * from the plan's _pp_plan_type meta — no new schema), search, a redesigned
 * table (colored initials avatar, plan pill, status dot+pill, validity with
 * an "Expiring in N days" annotation), and a "⋮" per-row action menu.
 * Visitors have their own page (PassPress → Visitors) — this list is always
 * member_type='member'.
 */
class PP_Memberships_List {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		self::maybe_handle_actions();

		$status     = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$plan_scope = isset( $_GET['plan_scope'] ) ? sanitize_key( $_GET['plan_scope'] ) : '';

		$counts = PP_Query::membership_status_counts( 'member' );
		$result = PP_Query::get_memberships(
			array(
				'status'      => $status,
				'search'      => $search,
				'paged'       => $paged,
				'per_page'    => 10,
				'member_type' => 'member',
				'plan_scope'  => $plan_scope,
			)
		);

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap passpress-members-page">
			<h1 class="screen-reader-text"><?php esc_html_e( 'Members', 'passpress' ); ?></h1>

			<div class="passpress-stat-row">
				<?php self::render_stat_tile( 'active', __( 'Active', 'passpress' ), $counts['active'], 'shield', $status ); ?>
				<?php self::render_stat_tile( 'frozen', __( 'Frozen', 'passpress' ), $counts['frozen'], 'controls-pause', $status ); ?>
				<?php self::render_stat_tile( 'suspended', __( 'Suspended', 'passpress' ), $counts['suspended'], 'warning', $status ); ?>
				<?php self::render_stat_tile( 'expired', __( 'Expired', 'passpress' ), $counts['expired'], 'calendar-alt', $status ); ?>
				<?php self::render_stat_tile( 'cancelled', __( 'Cancelled', 'passpress' ), $counts['cancelled'], 'dismiss', $status ); ?>
			</div>

			<div class="passpress-members-toolbar">
				<div class="passpress-members-toolbar-top">
					<div class="passpress-scope-tabs">
						<?php foreach ( self::scope_tabs() as $key => $label ) : ?>
							<a class="passpress-scope-tab<?php echo $plan_scope === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::filter_url( array( 'plan_scope' => $key, 'status' => $status, 's' => $search ) ) ); ?>">
								<?php echo esc_html( $label ); ?>
							</a>
						<?php endforeach; ?>
					</div>
					<details class="passpress-issue-membership">
						<summary class="passpress-issue-btn"><?php esc_html_e( '+ Issue New Membership', 'passpress' ); ?></summary>
						<div class="passpress-issue-panel">
							<form method="post">
								<?php wp_nonce_field( 'pp_issue_membership' ); ?>
								<table class="form-table">
									<tr>
										<th><label for="pp_user_id"><?php esc_html_e( 'Member', 'passpress' ); ?></label></th>
										<td>
											<?php
											wp_dropdown_users(
												array(
													'name'             => 'user_id',
													'id'               => 'pp_user_id',
													'show_option_none' => __( 'Select a member', 'passpress' ),
												)
											);
											?>
										</td>
									</tr>
									<tr>
										<th><label for="pp_plan_id"><?php esc_html_e( 'Plan', 'passpress' ); ?></label></th>
										<td>
											<select name="plan_id" id="pp_plan_id">
												<option value=""><?php esc_html_e( 'Select a plan', 'passpress' ); ?></option>
												<?php foreach ( self::get_plans() as $plan ) : ?>
													<option value="<?php echo esc_attr( $plan->ID ); ?>"><?php echo esc_html( $plan->post_title ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
								</table>
								<?php submit_button( __( 'Issue Membership', 'passpress' ), 'primary', 'pp_issue_membership' ); ?>
							</form>
						</div>
					</details>
				</div>

				<form method="get" class="passpress-members-search-form">
					<input type="hidden" name="page" value="passpress-memberships">
					<?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
					<?php if ( $plan_scope ) : ?><input type="hidden" name="plan_scope" value="<?php echo esc_attr( $plan_scope ); ?>"><?php endif; ?>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search membership #…', 'passpress' ); ?>" class="passpress-members-search">
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'passpress' ); ?></button>
				</form>
			</div>

			<table class="passpress-members-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Membership #', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Member Name', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Plan', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Status', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Validity', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'PIN', 'passpress' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No members found.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['items'] as $membership ) : ?>
							<?php self::render_row( $membership ); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php self::render_pagination( $result, $status, $search, $paged, $plan_scope ); ?>
		</div>
		<?php
	}

	private static function scope_tabs() {
		return array(
			''          => __( 'All Members', 'passpress' ),
			'corporate' => __( 'Corporate', 'passpress' ),
			'individual' => __( 'Individual', 'passpress' ),
		);
	}

	private static function render_stat_tile( $key, $label, $count, $dashicon, $current_status ) {
		?>
		<a class="passpress-stat-tile passpress-stat-tile-<?php echo esc_attr( $key ); ?><?php echo $current_status === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url( self::filter_url( array( 'status' => $current_status === $key ? '' : $key ) ) ); ?>">
			<div class="passpress-stat-tile-text">
				<span class="passpress-stat-tile-label"><?php echo esc_html( strtoupper( $label ) ); ?></span>
				<span class="passpress-stat-tile-number"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
			</div>
			<span class="passpress-stat-tile-icon"><span class="dashicons dashicons-<?php echo esc_attr( $dashicon ); ?>"></span></span>
		</a>
		<?php
	}

	private static function render_row( $membership ) {
		$user       = get_userdata( $membership->user_id );
		$name       = $user ? $user->display_name : __( 'Unknown', 'passpress' );
		$email      = $user ? $user->user_email : '';
		$expiring   = self::is_expiring_soon( $membership );
		$days_left  = $expiring ? ceil( ( strtotime( $membership->expiry_date ) - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS ) : 0;
		?>
		<tr>
			<td><code><?php echo esc_html( $membership->membership_number ); ?></code></td>
			<td>
				<div class="passpress-member-cell">
					<span class="passpress-member-avatar passpress-avatar-color-<?php echo esc_attr( self::avatar_color_index( $membership->user_id ) ); ?>"><?php echo esc_html( self::initials( $name ) ); ?></span>
					<span class="passpress-member-info">
						<strong><?php echo esc_html( $name ); ?></strong>
						<?php if ( $email ) : ?><span class="passpress-member-email"><?php echo esc_html( $email ); ?></span><?php endif; ?>
					</span>
				</div>
			</td>
			<td><span class="passpress-plan-pill"><?php echo esc_html( get_the_title( $membership->plan_id ) ); ?></span></td>
			<td><span class="passpress-status-pill passpress-status-pill-<?php echo esc_attr( $membership->status ); ?>"><span class="passpress-status-dot"></span><?php echo esc_html( pp_status_label( $membership->status ) ); ?></span></td>
			<td>
				<div class="passpress-validity">
					<strong><?php echo esc_html( pp_format_date( $membership->start_date ) ); ?></strong>
					<?php if ( $expiring ) : ?>
						<span class="passpress-expiring-soon">
							<?php
							/* translators: %d: days until expiry */
							echo esc_html( sprintf( _n( 'Expiring in %d day', 'Expiring in %d days', $days_left, 'passpress' ), $days_left ) );
							?>
						</span>
					<?php else : ?>
						<span class="passpress-validity-until">
							<?php
							/* translators: %s: expiry date */
							echo esc_html( sprintf( __( 'Until %s', 'passpress' ), pp_format_date( $membership->expiry_date ) ) );
							?>
						</span>
					<?php endif; ?>
				</div>
			</td>
			<td><code><?php echo esc_html( $membership->pin_code ); ?></code></td>
			<td class="passpress-row-menu-cell">
				<details class="passpress-row-menu">
					<summary aria-label="<?php esc_attr_e( 'Actions', 'passpress' ); ?>">&#8942;</summary>
					<div class="passpress-row-menu-panel">
						<?php foreach ( self::all_actions() as $action => $label ) : ?>
							<a href="<?php echo esc_url( self::action_url( $membership->id, $action ) ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</div>
				</details>
			</td>
		</tr>
		<?php
	}

	private static function all_actions() {
		return array(
			'renew'      => __( 'Renew', 'passpress' ),
			'freeze'     => __( 'Freeze', 'passpress' ),
			'suspend'    => __( 'Suspend', 'passpress' ),
			'reactivate' => __( 'Reactivate', 'passpress' ),
			'cancel'     => __( 'Cancel', 'passpress' ),
		);
	}

	/**
	 * Active + expiry within the Billing Settings renewal-reminder window —
	 * a derived read-only annotation, not a stored status (the status badge
	 * itself stays "Active"; see class docblock).
	 */
	private static function is_expiring_soon( $membership ) {
		if ( PP_Membership::STATUS_ACTIVE !== $membership->status ) {
			return false;
		}
		$billing_settings = PP_Billing::get_settings();
		$days             = max( 1, (int) $billing_settings['renewal_reminder_days'] );
		$today            = current_time( 'Y-m-d' );
		$soon             = gmdate( 'Y-m-d', strtotime( "{$today} +{$days} days" ) );
		return $membership->expiry_date >= $today && $membership->expiry_date <= $soon;
	}

	private static function avatar_color_index( $user_id ) {
		return ( (int) $user_id ) % 5;
	}

	private static function initials( $name ) {
		$name  = trim( (string) $name );
		$words = preg_split( '/\s+/', $name );
		if ( count( $words ) >= 2 ) {
			return strtoupper( mb_substr( $words[0], 0, 1 ) . mb_substr( $words[ count( $words ) - 1 ], 0, 1 ) );
		}
		return strtoupper( mb_substr( $name, 0, 2 ) );
	}

	private static function filter_url( $args ) {
		return add_query_arg( array_merge( array( 'page' => 'passpress-memberships' ), array_filter( $args, function( $v ) { return '' !== $v; } ) ), admin_url( 'admin.php' ) );
	}

	private static function action_url( $id, $action ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'page'      => 'passpress-memberships',
					'pp_action' => $action,
					'id'        => $id,
				),
				admin_url( 'admin.php' )
			),
			'pp_membership_action_' . $id
		);
	}

	private static function get_plans() {
		return get_posts(
			array(
				'post_type'      => 'pp_membership_plan',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);
	}

	private static function render_pagination( $result, $status, $search, $paged, $plan_scope ) {
		$total_pages = (int) ceil( $result['total'] / max( 1, $result['per_page'] ) );

		$first = $result['total'] ? ( ( $paged - 1 ) * $result['per_page'] ) + 1 : 0;
		$last  = min( $result['total'], $paged * $result['per_page'] );
		?>
		<div class="passpress-members-footer">
			<p class="passpress-members-count">
				<?php
				printf(
					/* translators: 1: first row number, 2: last row number, 3: total members */
					esc_html__( 'Showing %1$s-%2$s of %3$s members', 'passpress' ),
					'<strong>' . esc_html( number_format_i18n( $first ) ) . '</strong>',
					esc_html( number_format_i18n( $last ) ),
					'<strong>' . esc_html( number_format_i18n( $result['total'] ) ) . '</strong>'
				);
				?>
			</p>
			<?php if ( $total_pages > 1 ) : ?>
				<div class="passpress-members-pagination">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $total_pages,
								'prev_text' => '&lsaquo;',
								'next_text' => '&rsaquo;',
								'add_args'  => array(
									'status'     => $status,
									's'          => $search,
									'plan_scope' => $plan_scope,
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
		if ( isset( $_POST['pp_issue_membership'] ) && check_admin_referer( 'pp_issue_membership' ) ) {
			$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
			$plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
			$result  = PP_Membership::issue( $user_id, $plan_id );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'passpress', 'pp_issue_error', $result->get_error_message(), 'error' );
			} else {
				/* translators: %s: membership number */
				add_settings_error( 'passpress', 'pp_issue_success', sprintf( __( 'Membership %s issued.', 'passpress' ), $result->membership_number ), 'success' );
			}
		}

		if ( isset( $_GET['pp_action'], $_GET['id'], $_GET['_wpnonce'] ) ) {
			$id     = absint( $_GET['id'] );
			$action = sanitize_key( $_GET['pp_action'] );

			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'pp_membership_action_' . $id ) ) {
				switch ( $action ) {
					case 'renew':
						PP_Membership_Renewal::renew( $id );
						break;
					case 'freeze':
						PP_Membership_Status::freeze( $id );
						break;
					case 'suspend':
						PP_Membership_Status::suspend( $id );
						break;
					case 'reactivate':
						PP_Membership_Status::reactivate( $id );
						break;
					case 'cancel':
						PP_Membership_Status::cancel( $id );
						break;
				}
				add_settings_error( 'passpress', 'pp_action_done', __( 'Membership updated.', 'passpress' ), 'success' );
			}
		}
	}
}
