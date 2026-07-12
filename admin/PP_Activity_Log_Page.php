<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PP_Activity_Log_Page {

	public static function render() {
		if ( ! current_user_can( PP_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'passpress' ) );
		}

		$logs = PP_Activity_Logger::get_recent( 100 );
		?>
		<div class="wrap passpress-wrap">
			<h1><?php esc_html_e( 'Activity Log', 'passpress' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'When', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Event', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'Message', 'passpress' ); ?></th>
						<th><?php esc_html_e( 'User', 'passpress' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $logs ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No activity yet.', 'passpress' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( pp_format_datetime( $log->created_at ) ); ?></td>
								<td><?php echo esc_html( $log->event ); ?></td>
								<td><?php echo esc_html( $log->message ); ?></td>
								<td><?php echo esc_html( self::user_name( $log->user_id ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function user_name( $user_id ) {
		if ( ! $user_id ) {
			return __( 'System', 'passpress' );
		}
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Unknown', 'passpress' );
	}
}
