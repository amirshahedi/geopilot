<?php
/**
 * Admin UI: a single page under Settings → GEO Toolkit with a
 * "Generate now" action, download links, schema audit, and crawler audit.
 *
 * @package WordPress_GEO_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_wgt_generate', array( __CLASS__, 'handle_generate' ) );
	}

	public static function add_menu() {
		add_options_page(
			__( 'GEO Toolkit', 'wp-geo-toolkit' ),
			__( 'GEO Toolkit', 'wp-geo-toolkit' ),
			'manage_options',
			'wgt-geo-toolkit',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function handle_generate() {
		check_admin_referer( 'wgt_generate' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-geo-toolkit' ) );
		}

		WGT_LLMS_Generator::generate();

		wp_safe_redirect( add_query_arg( array( 'page' => 'wgt-geo-toolkit', 'generated' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public static function render_page() {
		$last_generated = get_option( 'wgt_last_generated' );
		$schema_issues  = WGT_Schema_Auditor::audit( 100 );
		$crawler_report = WGT_Crawler_Audit::audit();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WordPress GEO Toolkit', 'wp-geo-toolkit' ); ?></h1>
			<p><?php esc_html_e( 'Make this site readable and citable by ChatGPT, Perplexity, Gemini, and Claude.', 'wp-geo-toolkit' ); ?></p>

			<?php if ( isset( $_GET['generated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'llms.txt and llms-full.txt regenerated.', 'wp-geo-toolkit' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( '1. Content export', 'wp-geo-toolkit' ); ?></h2>
			<p>
				<?php
				if ( $last_generated ) {
					printf(
						/* translators: %s: human-readable time diff */
						esc_html__( 'Last generated %s ago.', 'wp-geo-toolkit' ),
						esc_html( human_time_diff( $last_generated ) )
					);
				} else {
					esc_html_e( 'Not generated yet.', 'wp-geo-toolkit' );
				}
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wgt_generate" />
				<?php wp_nonce_field( 'wgt_generate' ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate llms.txt now', 'wp-geo-toolkit' ); ?></button>
			</form>
			<?php if ( $last_generated ) : ?>
				<p>
					<a href="<?php echo esc_url( WGT_LLMS_Generator::export_url( 'llms.txt' ) ); ?>" target="_blank"><?php esc_html_e( 'View llms.txt', 'wp-geo-toolkit' ); ?></a>
					&nbsp;|&nbsp;
					<a href="<?php echo esc_url( WGT_LLMS_Generator::export_url( 'llms-full.txt' ) ); ?>" target="_blank"><?php esc_html_e( 'View llms-full.txt', 'wp-geo-toolkit' ); ?></a>
				</p>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( '2. Content schema audit (first 100 published items)', 'wp-geo-toolkit' ); ?></h2>
			<?php if ( empty( $schema_issues ) ) : ?>
				<p>✅ <?php esc_html_e( 'No gaps found in the sampled content.', 'wp-geo-toolkit' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Content', 'wp-geo-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Issues', 'wp-geo-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $schema_issues as $row ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $row['url'] ); ?>" target="_blank"><?php echo esc_html( $row['name'] ); ?></a></td>
							<td><?php echo esc_html( implode( '; ', $row['issues'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( '3. AI crawler access (robots.txt)', 'wp-geo-toolkit' ); ?></h2>
			<?php if ( ! $crawler_report['fetched'] ) : ?>
				<p><?php esc_html_e( 'Could not fetch robots.txt.', 'wp-geo-toolkit' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Bot', 'wp-geo-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Engine', 'wp-geo-toolkit' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-geo-toolkit' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $crawler_report['bots'] as $agent => $data ) : ?>
						<tr>
							<td><code><?php echo esc_html( $agent ); ?></code></td>
							<td><?php echo esc_html( $data['engine'] ); ?></td>
							<td><?php echo $data['allowed'] ? '✅ ' . esc_html__( 'Allowed', 'wp-geo-toolkit' ) : '⛔ ' . esc_html__( 'Blocked', 'wp-geo-toolkit' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
