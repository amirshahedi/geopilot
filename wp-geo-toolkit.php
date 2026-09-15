<?php
/**
 * Plugin Name:       WordPress GEO Toolkit
 * Plugin URI:         https://github.com/amirshahedi/wp-geo-toolkit
 * Description:        Generative & Answer Engine Optimization (GEO/AEO) toolkit for WordPress. Generates llms.txt / llms-full.txt content exports, audits Article/Page schema.org markup, and checks AI-crawler access — so ChatGPT, Perplexity, Gemini, and Claude can read and cite your site.
 * Version:            0.2.0
 * Requires at least:  6.4
 * Requires PHP:       7.4
 * Author:             Amir Shahedi
 * Author URI:         https://amirshahedi.com
 * License:            MIT
 * License URI:        https://opensource.org/licenses/MIT
 * Text Domain:        wp-geo-toolkit
 * Domain Path:        /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'WGT_VERSION', '0.2.0' );
define( 'WGT_PLUGIN_FILE', __FILE__ );
define( 'WGT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WGT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function wgt_init() {
	require_once WGT_PLUGIN_DIR . 'includes/class-wgt-llms-generator.php';
	require_once WGT_PLUGIN_DIR . 'includes/class-wgt-admin.php';
	require_once WGT_PLUGIN_DIR . 'includes/class-wgt-schema-auditor.php';
	require_once WGT_PLUGIN_DIR . 'includes/class-wgt-crawler-audit.php';

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once WGT_PLUGIN_DIR . 'includes/class-wgt-cli.php';
		WP_CLI::add_command( 'geo', 'WGT_CLI' );
	}

	WGT_Admin::init();
}
add_action( 'plugins_loaded', 'wgt_init' );

/**
 * On activation, create the /geo-exports upload subdirectory so the
 * generator always has somewhere writable to put llms.txt output.
 */
function wgt_activate() {
	$upload_dir = wp_upload_dir();
	$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'geo-exports';

	if ( ! file_exists( $target_dir ) ) {
		wp_mkdir_p( $target_dir );
	}

	// Prevent directory listing / accidental PHP execution of anything dropped here.
	$htaccess = $target_dir . '/.htaccess';
	if ( ! file_exists( $htaccess ) ) {
		file_put_contents( $htaccess, "Options -Indexes\n<FilesMatch \"\\.php$\">\nDeny from all\n</FilesMatch>\n" );
	}
}
register_activation_hook( __FILE__, 'wgt_activate' );
