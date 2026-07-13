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

	public static function init() {
		add_action( 'wp_ajax_pp_filter_members', array( __CLASS__, 'ajax_filter_members' ) );
	}

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
		$result = self::get_filtered_result( $status, $search, $plan_scope, $paged );

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap passpress-members-page">
			<div class="passpress-members-page-header">
				<div class="passpress-members-page-copy">
					<p class="passpress-members-page-eyebrow"><?php esc_html_e( 'Directory', 'passpress' ); ?></p>
					<h1><?php esc_html_e( 'Members', 'passpress' ); ?></h1>
					<p class="passpress-members-page-desc"><?php esc_html_e( 'Filter, search, and manage memberships from one place.', 'passpress' ); ?></p>
				</div>
				<button type="button" class="passpress-members-issue-btn" id="passpress-issue-membership-trigger">
					<?php esc_html_e( 'Issue membership', 'passpress' ); ?>
				</button>
			</div>

			<div class="passpress-stat-row">
				<?php self::render_stat_tile( 'active', __( 'Active', 'passpress' ), $counts['active'], 'shield', $status ); ?>
				<?php self::render_stat_tile( 'frozen', __( 'Frozen', 'passpress' ), $counts['frozen'], 'controls-pause', $status ); ?>
				<?php self::render_stat_tile( 'suspended', __( 'Suspended', 'passpress' ), $counts['suspended'], 'warning', $status ); ?>
				<?php self::render_stat_tile( 'expired', __( 'Expired', 'passpress' ), $counts['expired'], 'calendar-alt', $status ); ?>
				<?php self::render_stat_tile( 'cancelled', __( 'Cancelled', 'passpress' ), $counts['cancelled'], 'dismiss', $status ); ?>
			</div>

			<div class="passpress-members-toolbar">
				<div class="passpress-members-toolbar-top">
					<div class="passpress-scope-tabs" id="passpress-scope-tabs" data-nonce="<?php echo esc_attr( wp_create_nonce( 'pp_filter_members' ) ); ?>" data-status="<?php echo esc_attr( $status ); ?>" data-plan-scope="<?php echo esc_attr( $plan_scope ); ?>">
						<?php foreach ( self::quick_filter_tabs() as $tab ) : ?>
							<?php
							if ( 'reset' === $tab['type'] ) {
								$is_active      = ( '' === $plan_scope && '' === $status );
								$new_status     = '';
								$new_plan_scope = '';
							} elseif ( 'plan_scope' === $tab['type'] ) {
								$is_active      = ( $plan_scope === $tab['value'] );
								$new_status     = $status;
								$new_plan_scope = $tab['value'];
							} else {
								$is_active      = ( $status === $tab['value'] );
								$new_status     = $tab['value'];
								$new_plan_scope = $plan_scope;
							}
							$tab_url = self::filter_url( array( 'plan_scope' => $new_plan_scope, 'status' => $new_status, 's' => $search ) );
							?>
							<a class="passpress-scope-tab<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $tab_url ); ?>" data-status="<?php echo esc_attr( $new_status ); ?>" data-plan-scope="<?php echo esc_attr( $new_plan_scope ); ?>">
								<?php echo esc_html( $tab['label'] ); ?>
							</a>
						<?php endforeach; ?>
					</div>

					<form method="get" class="passpress-members-search-form">
						<input type="hidden" name="page" value="passpress-memberships">
						<?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
						<?php if ( $plan_scope ) : ?><input type="hidden" name="plan_scope" value="<?php echo esc_attr( $plan_scope ); ?>"><?php endif; ?>
						<input type="search" name="s" id="passpress-members-search-input" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search membership #…', 'passpress' ); ?>" class="passpress-members-search">
						<button type="submit" class="passpress-members-search-btn"><?php esc_html_e( 'Search', 'passpress' ); ?></button>
					</form>
				</div>
			</div>

			<div class="passpress-members-table-wrap">
				<table class="passpress-members-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Membership #', 'passpress' ); ?></th>
							<th><?php esc_html_e( 'Member', 'passpress' ); ?></th>
							<th><?php esc_html_e( 'Plan', 'passpress' ); ?></th>
							<th><?php esc_html_e( 'Status', 'passpress' ); ?></th>
							<th><?php esc_html_e( 'Validity', 'passpress' ); ?></th>
							<th><?php esc_html_e( 'PIN', 'passpress' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'passpress' ); ?></span></th>
						</tr>
					</thead>
					<tbody id="passpress-members-tbody">
						<?php self::render_tbody_rows( $result ); ?>
					</tbody>
				</table>
			</div>

			<?php self::render_pagination( $result, $status, $search, $paged, $plan_scope ); ?>

			<?php self::render_issue_modal(); ?>
		</div>
		<?php
	}

	private static function render_issue_modal() {
		?>
		<div id="passpress-issue-membership-modal" class="passpress-modal-overlay" hidden>
			<div class="passpress-modal passpress-issue-modal" role="dialog" aria-modal="true" aria-labelledby="passpress-issue-membership-title">
				<div class="pp-modal-header">
					<div>
						<p class="pp-modal-eyebrow"><?php esc_html_e( 'Front desk', 'passpress' ); ?></p>
						<h2 id="passpress-issue-membership-title"><?php esc_html_e( 'Issue membership', 'passpress' ); ?></h2>
					</div>
					<button type="button" class="passpress-modal-close" aria-label="<?php esc_attr_e( 'Close', 'passpress' ); ?>">&times;</button>
				</div>
				<form method="post" class="pp-plan-form">
					<?php wp_nonce_field( 'pp_issue_membership' ); ?>
					<div class="pp-field">
						<label class="pp-label" for="pp_user_id"><?php esc_html_e( 'Member', 'passpress' ); ?></label>
						<?php
						wp_dropdown_users(
							array(
								'name'             => 'user_id',
								'id'               => 'pp_user_id',
								'class'            => 'pp-input pp-input-select',
								'show_option_none' => __( 'Select a member', 'passpress' ),
							)
						);
						?>
					</div>
					<div class="pp-field">
						<label class="pp-label" for="pp_plan_id"><?php esc_html_e( 'Plan', 'passpress' ); ?></label>
						<select name="plan_id" id="pp_plan_id" class="pp-input pp-input-select">
							<option value=""><?php esc_html_e( 'Select a plan', 'passpress' ); ?></option>
							<?php foreach ( self::get_plans() as $plan ) : ?>
								<option value="<?php echo esc_attr( $plan->ID ); ?>"><?php echo esc_html( $plan->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="pp-modal-footer">
						<button type="button" class="pp-btn-outline passpress-modal-cancel"><?php esc_html_e( 'Cancel', 'passpress' ); ?></button>
						<button type="submit" name="pp_issue_membership" class="pp-btn-solid"><?php esc_html_e( 'Issue membership', 'passpress' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * The combined quick-filter tab bar: "All Members" resets both filters,
	 * the plan_scope tabs (Corporate/Individual) and the status tabs
	 * (Active/Frozen/Suspended/Cancelled) each only touch their own filter,
	 * preserving whatever the other one is currently set to — so "Corporate"
	 * + "Frozen" can be combined by clicking one then the other.
	 */
	private static function quick_filter_tabs() {
		return array(
			array(
				'label' => __( 'All Members', 'passpress' ),
				'type'  => 'reset',
			),
			array(
				'label' => __( 'Corporate', 'passpress' ),
				'type'  => 'plan_scope',
				'value' => 'corporate',
			),
			array(
				'label' => __( 'Individual', 'passpress' ),
				'type'  => 'plan_scope',
				'value' => 'individual',
			),
			array(
				'label' => __( 'Active', 'passpress' ),
				'type'  => 'status',
				'value' => 'active',
			),
			array(
				'label' => __( 'Frozen', 'passpress' ),
				'type'  => 'status',
				'value' => 'frozen',
			),
			array(
				'label' => __( 'Suspended', 'passpress' ),
				'type'  => 'status',
				'value' => 'suspended',
			),
			array(
				'label' => __( 'Cancelled', 'passpress' ),
				'type'  => 'status',
				'value' => 'cancelled',
			),
		);
	}

	private static function render_stat_tile( $key, $label, $count, $dashicon, $current_status ) {
		?>
		<a class="passpress-stat-tile passpress-stat-tile-<?php echo esc_attr( $key ); ?><?php echo $current_status === $key ? ' is-active' : ''; ?>" data-status="<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( self::filter_url( array( 'status' => $current_status === $key ? '' : $key ) ) ); ?>">
			<div class="passpress-stat-tile-text">
				<span class="passpress-stat-tile-label"><?php echo esc_html( $label ); ?></span>
				<span class="passpress-stat-tile-number"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
			</div>
			<span class="passpress-stat-tile-icon" aria-hidden="true"><span class="dashicons dashicons-<?php echo esc_attr( $dashicon ); ?>"></span></span>
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
			<td><code class="passpress-membership-code"><?php echo esc_html( $membership->membership_number ); ?></code></td>
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
			<td><code class="passpress-pin-code"><?php echo esc_html( $membership->pin_code ); ?></code></td>
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

	private static function get_filtered_result( $status, $search, $plan_scope, $paged ) {
		return PP_Query::get_memberships(
			array(
				'status'      => $status,
				'search'      => $search,
				'paged'       => $paged,
				'per_page'    => 10,
				'member_type' => 'member',
				'plan_scope'  => $plan_scope,
			)
		);
	}

	private static function render_tbody_rows( $result ) {
		if ( empty( $result['items'] ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No members found.', 'passpress' ) . '</td></tr>';
			return;
		}
		foreach ( $result['items'] as $membership ) {
			self::render_row( $membership );
		}
	}

	/**
	 * Backs the JS-driven quick-filter tabs + pagination (see
	 * assets/admin/passpress-admin.js) — same query/render logic as
	 * render()'s initial page load, just returned as HTML fragments instead
	 * of a full page, so switching tabs/pages doesn't reload the screen.
	 */
	public static function ajax_filter_members() {
		check_ajax_referer( 'pp_filter_members', 'nonce' );

		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'passpress' ) ) );
		}

		$status     = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		$search     = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$plan_scope = isset( $_POST['plan_scope'] ) ? sanitize_key( $_POST['plan_scope'] ) : '';
		$paged      = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;

		$result = self::get_filtered_result( $status, $search, $plan_scope, $paged );

		ob_start();
		self::render_tbody_rows( $result );
		$rows_html = ob_get_clean();

		ob_start();
		self::render_pagination( $result, $status, $search, $paged, $plan_scope );
		$footer_html = ob_get_clean();

		wp_send_json_success(
			array(
				'rows'   => $rows_html,
				'footer' => $footer_html,
			)
		);
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
		<div class="passpress-members-footer" id="passpress-members-footer">
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
								'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php?page=passpress-memberships' ) ),
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
