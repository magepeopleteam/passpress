<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Memberships admin screen: issue a new membership (the Phase 1 "sign this
 * member up at the counter" flow, since Billing/payment gateways are a
 * Phase 2 concern), search/filter existing ones, and act on row status.
 */
class PP_Memberships_List {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		self::maybe_handle_actions();

		$status      = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
		$search      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$member_type = isset( $_GET['member_type'] ) ? sanitize_key( $_GET['member_type'] ) : 'member';

		$result = PP_Query::get_memberships(
			array(
				'status'      => $status,
				'search'      => $search,
				'paged'       => $paged,
				'per_page'    => 20,
				'member_type' => $member_type,
			)
		);

		settings_errors( 'passpress' );
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'Memberships', 'passpress' ); ?></h1>

			<details class="passpress-issue-membership" <?php echo empty( $result['total'] ) ? 'open' : ''; ?>>
				<summary><?php esc_html_e( 'Issue New Membership', 'passpress' ); ?></summary>
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
			</details>

			<ul class="subsubsub">
				<?php foreach ( array( 'member' => __( 'Members', 'passpress' ), 'visitor' => __( 'Visitors', 'passpress' ), 'all' => __( 'All', 'passpress' ) ) as $key => $label ) : ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'member_type', $key, admin_url( 'admin.php?page=passpress-memberships' ) ) ); ?>" class="<?php echo $member_type === $key ? 'current' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
						<?php if ( 'all' !== $key ) : ?> | <?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<ul class="subsubsub">
				<?php foreach ( self::status_filters() as $key => $label ) : ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( array( 'status' => $key, 'member_type' => $member_type ), admin_url( 'admin.php?page=passpress-memberships' ) ) ); ?>" class="<?php echo $status === $key ? 'current' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
						<?php if ( 'cancelled' !== $key ) : ?> | <?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<form method="get">
				<input type="hidden" name="page" value="passpress-memberships">
				<?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
				<input type="hidden" name="member_type" value="<?php echo esc_attr( $member_type ); ?>">
				<p class="search-box">
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search membership #', 'passpress' ); ?>">
					<?php submit_button( __( 'Search', 'passpress' ), '', '', false ); ?>
				</p>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Membership #', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Member', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Plan', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Status', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Start', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Expiry', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'PIN', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'passpress' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No memberships found.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['items'] as $membership ) : ?>
							<tr>
								<td><?php echo esc_html( $membership->membership_number ); ?></td>
								<td><?php echo esc_html( self::user_name( $membership->user_id ) ); ?></td>
								<td><?php echo esc_html( get_the_title( $membership->plan_id ) ); ?></td>
								<td><span class="passpress-badge passpress-badge-<?php echo esc_attr( $membership->status ); ?>"><?php echo esc_html( pp_status_label( $membership->status ) ); ?></span></td>
								<td><?php echo esc_html( pp_format_date( $membership->start_date ) ); ?></td>
								<td><?php echo esc_html( pp_format_date( $membership->expiry_date ) ); ?></td>
								<td><?php echo esc_html( $membership->pin_code ); ?></td>
								<td><?php self::render_row_actions( $membership ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php self::render_pagination( $result, $status, $search, $paged, $member_type ); ?>
		</div>
		<?php
	}

	private static function status_filters() {
		return array(
			''          => __( 'All', 'passpress' ),
			'active'    => __( 'Active', 'passpress' ),
			'frozen'    => __( 'Frozen', 'passpress' ),
			'suspended' => __( 'Suspended', 'passpress' ),
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
			)
		);
	}

	private static function user_name( $user_id ) {
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown', 'passpress' );
	}

	private static function render_row_actions( $membership ) {
		$actions = array();

		foreach ( array(
			'renew'      => __( 'Renew', 'passpress' ),
			'freeze'     => __( 'Freeze', 'passpress' ),
			'suspend'    => __( 'Suspend', 'passpress' ),
			'reactivate' => __( 'Reactivate', 'passpress' ),
			'cancel'     => __( 'Cancel', 'passpress' ),
		) as $action => $label ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'page'      => 'passpress-memberships',
						'pp_action' => $action,
						'id'        => $membership->id,
					),
					admin_url( 'admin.php' )
				),
				'pp_membership_action_' . $membership->id
			);
			$actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}

		echo wp_kses_post( implode( ' | ', $actions ) );
	}

	private static function render_pagination( $result, $status, $search, $paged, $member_type = 'member' ) {
		$total_pages = (int) ceil( $result['total'] / max( 1, $result['per_page'] ) );
		if ( $total_pages <= 1 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'add_args'  => array(
						'status'      => $status,
						's'           => $search,
						'member_type' => $member_type,
					),
				)
			)
		);
		echo '</div></div>';
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
