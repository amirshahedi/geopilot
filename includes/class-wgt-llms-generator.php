<?php
/**
 * Generates llms.txt and llms-full.txt exports of the site's published
 * content (posts, pages, and any other public post type) - no
 * WooCommerce dependency required.
 *
 * llms.txt spec: a concise, curated index (site name, summary, key links).
 * llms-full.txt: the same structure but with every published item
 * expanded into a short Markdown block, so an LLM can ingest the whole
 * site without crawling each page individually.
 *
 * @package WordPress_GEO_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_LLMS_Generator {

	/** @var int Items per batch when paging through content. */
	const BATCH_SIZE = 200;

	/**
	 * Generate both files and write them to the export directory.
	 *
	 * @return array{llms:string,llms_full:string,item_count:int} Paths written and how many items were included.
	 */
	public static function generate() {
		$items = self::get_published_content();

		$llms      = self::build_llms_txt( $items );
		$llms_full = self::build_llms_full_txt( $items );

		$paths = self::write_files( $llms, $llms_full );
		$paths['item_count'] = count( $items );

		update_option( 'wgt_last_generated', time() );

		return $paths;
	}

	/**
	 * Which post types to include. Defaults to every public, queryable
	 * post type (posts, pages, and most custom post types) minus
	 * attachments. Filterable so a site can narrow or widen this.
	 *
	 * @return string[]
	 */
	public static function get_included_post_types() {
		$post_types = get_post_types(
			array(
				'public'   => true,
				'_builtin' => null,
			),
			'names'
		);

		// Exclude non-content, structural, and page-builder post types.
		// These are never something an AI should cite, they're template
		// scaffolding, not published content.
		$excluded = array(
			'attachment',
			'elementor_library',
			'e-landing-page',
			'e-floating-buttons',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_navigation',
			'wp_font_family',
			'wp_font_face',
			'wpcf7_contact_form',
			'acf-field-group',
			'acf-field',
		);

		foreach ( $excluded as $type ) {
			unset( $post_types[ $type ] );
		}

		/**
		 * Filter which post types are included in the llms.txt export.
		 *
		 * @param string[] $post_types
		 */
		return apply_filters( 'wgt_included_post_types', array_values( $post_types ) );
	}

	/**
	 * Pull every published item across the included post types.
	 * Batches the query so large sites don't exhaust memory.
	 *
	 * @return WP_Post[]
	 */
	protected static function get_published_content() {
		$all_items = array();
		$page      = 1;

		do {
			$query = new WP_Query(
				array(
					'post_type'      => self::get_included_post_types(),
					'post_status'    => 'publish',
					'posts_per_page' => self::BATCH_SIZE,
					'paged'          => $page,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);

			$all_items = array_merge( $all_items, $query->posts );
			$found     = count( $query->posts );
			$page++;
			wp_reset_postdata();
		} while ( $found === self::BATCH_SIZE );

		return $all_items;
	}

	/**
	 * The concise llms.txt index: site identity + one line per featured/recent item.
	 *
	 * @param WP_Post[] $items
	 * @return string
	 */
	protected static function build_llms_txt( $items ) {
		$site_name = get_bloginfo( 'name' );
		$tagline   = get_bloginfo( 'description' );
		$home_url  = home_url( '/' );

		$lines   = array();
		$lines[] = '# ' . $site_name;
		$lines[] = '';
		if ( $tagline ) {
			$lines[] = '> ' . $tagline;
			$lines[] = '';
		}
		$lines[] = 'A WordPress site exported for AI/LLM discovery. ' . count( $items ) . ' published items as of ' . gmdate( 'Y-m-d' ) . '.';
		$lines[] = '';
		$lines[] = '## Site';
		$lines[] = '- Homepage: ' . $home_url;
		$lines[] = '- Full content export (Markdown, one block per item): ' . self::export_url( 'llms-full.txt' );
		$lines[] = '';

		$categories = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
			)
		);

		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			$lines[] = '## Categories';
			foreach ( $categories as $cat ) {
				$lines[] = sprintf( '- [%s](%s)', $cat->name, get_term_link( $cat ) );
			}
			$lines[] = '';
		}

		$lines[] = '## Recent content';

		$recent = array_slice( $items, 0, 30 );

		foreach ( $recent as $item ) {
			$excerpt = has_excerpt( $item ) ? get_the_excerpt( $item ) : wp_trim_words( wp_strip_all_tags( $item->post_content ), 25 );
			$lines[] = sprintf(
				'- [%s](%s) - %s',
				get_the_title( $item ),
				get_permalink( $item ),
				wp_strip_all_tags( $excerpt )
			);
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * The expanded llms-full.txt: every item as its own Markdown block
	 * with type, date, author, and content - enough for an LLM to
	 * answer questions without fetching the live page.
	 *
	 * @param WP_Post[] $items
	 * @return string
	 */
	protected static function build_llms_full_txt( $items ) {
		$lines   = array();
		$lines[] = '# ' . get_bloginfo( 'name' ) . ' - Full Content Export';
		$lines[] = '';
		$lines[] = 'Generated ' . gmdate( 'c' ) . '. ' . count( $items ) . ' items.';
		$lines[] = '';

		foreach ( $items as $item ) {
			$lines[] = '## ' . get_the_title( $item );
			$lines[] = '';
			$lines[] = '- URL: ' . get_permalink( $item );
			$lines[] = '- Type: ' . get_post_type( $item );
			$lines[] = '- Published: ' . get_the_date( 'Y-m-d', $item );
			$lines[] = '- Author: ' . get_the_author_meta( 'display_name', $item->post_author );

			$cats = get_the_category( $item->ID );
			if ( ! empty( $cats ) ) {
				$lines[] = '- Category: ' . implode( ', ', wp_list_pluck( $cats, 'name' ) );
			}

			$tags = get_the_tags( $item->ID );
			if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
				$lines[] = '- Tags: ' . implode( ', ', wp_list_pluck( $tags, 'name' ) );
			}

			$content = wp_strip_all_tags( strip_shortcodes( $item->post_content ) );
			if ( $content ) {
				$lines[] = '';
				$lines[] = $content;
			}

			$lines[] = '';
			$lines[] = '---';
			$lines[] = '';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Write both files to /wp-content/uploads/geo-exports/ and mirror
	 * llms.txt to the site root if it's writable (spec expects it at /llms.txt).
	 *
	 * @param string $llms
	 * @param string $llms_full
	 * @return array{llms:string,llms_full:string}
	 */
	protected static function write_files( $llms, $llms_full ) {
		$upload_dir = wp_upload_dir();
		$target_dir = trailingslashit( $upload_dir['basedir'] ) . 'geo-exports';

		$llms_path      = $target_dir . '/llms.txt';
		$llms_full_path = $target_dir . '/llms-full.txt';

		file_put_contents( $llms_path, $llms );
		file_put_contents( $llms_full_path, $llms_full );

		// Best-effort mirror to site root so it's reachable at /llms.txt per spec.
		$root_path = ABSPATH . 'llms.txt';
		if ( is_writable( ABSPATH ) ) {
			@file_put_contents( $root_path, $llms );
		}

		return array(
			'llms'      => $llms_path,
			'llms_full' => $llms_full_path,
		);
	}

	/**
	 * Public URL for a file in the export directory.
	 *
	 * @param string $filename
	 * @return string
	 */
	public static function export_url( $filename ) {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['baseurl'] ) . 'geo-exports/' . $filename;
	}
}
