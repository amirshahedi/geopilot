<?php
/**
 * WP-CLI commands: `wp geo generate`, `wp geo audit-schema`, `wp geo audit-crawlers`.
 * Lets site owners wire this into cron or a deploy pipeline instead of
 * clicking a button in wp-admin every time content changes.
 *
 * @package WordPress_GEO_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_CLI {

	/**
	 * Generate llms.txt and llms-full.txt from the site's published content.
	 *
	 * ## EXAMPLES
	 *
	 *     wp geo generate
	 *
	 * @when after_wp_load
	 */
	public function generate( $args, $assoc_args ) {
		$paths = WGT_LLMS_Generator::generate();
		WP_CLI::success( sprintf(
			'Generated llms.txt (%1$s) and llms-full.txt (%2$s) — %3$d items.',
			$paths['llms'],
			$paths['llms_full'],
			$paths['item_count']
		) );
	}

	/**
	 * Audit published content for Schema.org gaps.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : How many items to check. Default 100.
	 *
	 * ## EXAMPLES
	 *
	 *     wp geo audit-schema --limit=500
	 *
	 * @when after_wp_load
	 */
	public function audit_schema( $args, $assoc_args ) {
		$limit  = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 100;
		$issues = WGT_Schema_Auditor::audit( $limit );

		if ( empty( $issues ) ) {
			WP_CLI::success( 'No schema gaps found.' );
			return;
		}

		$rows = array();
		foreach ( $issues as $row ) {
			$rows[] = array(
				'content' => $row['name'],
				'issues'  => implode( '; ', $row['issues'] ),
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'content', 'issues' ) );
	}

	/**
	 * Check whether known AI crawlers are allowed by robots.txt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp geo audit-crawlers
	 *
	 * @when after_wp_load
	 */
	public function audit_crawlers( $args, $assoc_args ) {
		$report = WGT_Crawler_Audit::audit();

		if ( ! $report['fetched'] ) {
			WP_CLI::error( 'Could not fetch robots.txt.' );
			return;
		}

		$rows = array();
		foreach ( $report['bots'] as $agent => $data ) {
			$rows[] = array(
				'bot'    => $agent,
				'engine' => $data['engine'],
				'status' => $data['allowed'] ? 'allowed' : 'blocked',
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'bot', 'engine', 'status' ) );
	}
}
