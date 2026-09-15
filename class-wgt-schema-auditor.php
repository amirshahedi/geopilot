<?php
/**
 * Audits WordPress posts/pages for Schema.org Article/WebPage
 * completeness — the structured-data signals AI answer engines lean on
 * most heavily when deciding whether (and how) to cite a page.
 *
 * @package WordPress_GEO_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Schema_Auditor {

	/**
	 * Audit a batch of published content and return a per-item list of gaps.
	 *
	 * @param int $limit Max items to check in one pass (keeps admin page fast).
	 * @return array<int, array{id:int,name:string,url:string,issues:string[]}>
	 */
	public static function audit( $limit = 100 ) {
		$query = new WP_Query(
			array(
				'post_type'      => WGT_LLMS_Generator::get_included_post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'no_found_rows'  => true,
			)
		);

		$results = array();

		foreach ( $query->posts as $item ) {
			$issues = self::check_item( $item );

			if ( ! empty( $issues ) ) {
				$results[] = array(
					'id'     => $item->ID,
					'name'   => get_the_title( $item ),
					'url'    => get_permalink( $item ),
					'issues' => $issues,
				);
			}
		}

		wp_reset_postdata();

		return $results;
	}

	/**
	 * Check a single item against the fields AI answer engines and
	 * Google rich results both expect on Article/WebPage schema.
	 *
	 * @param WP_Post $item
	 * @return string[]
	 */
	protected static function check_item( $item ) {
		$issues = array();

		if ( ! has_post_thumbnail( $item ) ) {
			$issues[] = 'Missing featured image (schema image field will be empty)';
		}

		if ( ! has_excerpt( $item ) ) {
			$issues[] = 'No excerpt set — AI engines fall back to auto-trimmed content, which is often a poor summary';
		}

		$word_count = str_word_count( wp_strip_all_tags( $item->post_content ) );
		if ( $word_count < 100 ) {
			$issues[] = 'Thin content (' . $word_count . ' words) — little for an AI engine to summarize or cite';
		}

		if ( ! get_the_author_meta( 'display_name', $item->post_author ) ) {
			$issues[] = 'No author display name set — weakens author/entity (E-E-A-T) signals';
		}

		if ( 'post' === get_post_type( $item ) && ! has_category( '', $item ) ) {
			$issues[] = 'Not assigned to a category — weakens topical/entity context';
		}

		$title_length = strlen( get_the_title( $item ) );
		if ( $title_length < 10 ) {
			$issues[] = 'Very short title — may not carry enough context for AI citation';
		}

		return $issues;
	}
}
