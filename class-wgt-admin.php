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
			__( 'GEO Toolkit', 'geopilot' ),
			__( 'GEO Toolkit', 'geopilot' ),
			'manage_options',
			'wgt-geo-toolkit',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function handle_generate() {
		check_admin_referer( 'wgt_generate' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'geopilot' ) );
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
			<h1><?php esc_html_e( 'GeoPilot', 'geopilot' ); ?></h1>
			<p><?php esc_html_e( 'Make this site readable and citable by ChatGPT, Perplexity, Gemini, and Claude.', 'geopilot' ); ?></p>

			<?php if ( isset( $_GET['generated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'llms.txt and llms-full.txt regenerated.', 'geopilot' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( '1. Content export', 'geopilot' ); ?></h2>
			<p>
				<?php
				if ( $last_generated ) {
					printf(
						/* translators: %s: human-readable time diff */
						esc_html__( 'Last generated %s ago.', 'geopilot' ),
						esc_html( human_time_diff( $last_generated ) )
					);
				} else {
					esc_html_e( 'Not generated yet.', 'geopilot' );
				}
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wgt_generate" />
				<?php wp_nonce_field( 'wgt_generate' ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate llms.txt now', 'geopilot' ); ?></button>
			</form>
			<?php if ( $last_generated ) : ?>
				<p>
					<a href="<?php echo esc_url( WGT_LLMS_Generator::export_url( 'llms.txt' ) ); ?>" target="_blank"><?php esc_html_e( 'View llms.txt', 'geopilot' ); ?></a>
					&nbsp;|&nbsp;
					<a href="<?php echo esc_url( WGT_LLMS_Generator::export_url( 'llms-full.txt' ) ); ?>" target="_blank"><?php esc_html_e( 'View llms-full.txt', 'geopilot' ); ?></a>
				</p>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( '2. Content schema audit (first 100 published items)', 'geopilot' ); ?></h2>
			<?php if ( empty( $schema_issues ) ) : ?>
				<p>✅ <?php esc_html_e( 'No gaps found in the sampled content.', 'geopilot' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Content', 'geopilot' ); ?></th>
							<th><?php esc_html_e( 'Issues', 'geopilot' ); ?></th>
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

			<h2><?php esc_html_e( '3. AI crawler access (robots.txt)', 'geopilot' ); ?></h2>
			<?php if ( ! $crawler_report['fetched'] ) : ?>
				<p><?php esc_html_e( 'Could not fetch robots.txt.', 'geopilot' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Bot', 'geopilot' ); ?></th>
							<th><?php esc_html_e( 'Engine', 'geopilot' ); ?></th>
							<th><?php esc_html_e( 'Status', 'geopilot' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $crawler_report['bots'] as $agent => $data ) : ?>
						<tr>
							<td><code><?php echo esc_html( $agent ); ?></code></td>
							<td><?php echo esc_html( $data['engine'] ); ?></td>
							<td><?php echo $data['allowed'] ? '✅ ' . esc_html__( 'Allowed', 'geopilot' ) : '⛔ ' . esc_html__( 'Blocked', 'geopilot' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( '4. AI content negotiation', 'geopilot' ); ?></h2>
			<p><?php esc_html_e( 'When a declared AI bot (GPTBot, ClaudeBot, PerplexityBot, etc.) requests a post or page, this serves a clean, chrome-free version of the same content, no nav, sidebar, ads, or scripts, instead of the normal themed page.', 'geopilot' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'wgt_settings' ); ?>
				<label>
					<input type="checkbox" name="wgt_content_negotiation_enabled" value="1" <?php checked( get_option( 'wgt_content_negotiation_enabled', true ) ); ?> />
					<?php esc_html_e( 'Enabled', 'geopilot' ); ?>
				</label>
				<?php submit_button( __( 'Save', 'geopilot' ) ); ?>
			</form>
			<?php
			$hit_counts = WGT_AI_Content_Negotiation::get_hit_counts();
			if ( ! empty( $hit_counts ) ) :
				arsort( $hit_counts );
				?>
				<table class="widefat striped" style="max-width:400px;">
					<thead><tr><th><?php esc_html_e( 'Bot', 'geopilot' ); ?></th><th><?php esc_html_e( 'Clean-render requests', 'geopilot' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $hit_counts as $bot => $count ) : ?>
						<tr><td><code><?php echo esc_html( $bot ); ?></code></td><td><?php echo esc_html( number_format_i18n( $count ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p><em><?php esc_html_e( 'No AI bot requests recorded yet.', 'geopilot' ); ?></em></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
