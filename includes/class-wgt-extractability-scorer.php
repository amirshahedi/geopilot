<?php
/**
 * Answer-first extractability scoring.
 *
 * Existing SEO plugins check whether schema exists and whether content
 * hits a keyword density target. None of them check something more
 * basic and more relevant to AI citation: does the page actually give
 * an AI model a clean, standalone, quotable answer near the top,
 * instead of three paragraphs of throat-clearing before the point?
 *
 * This is a heuristic scorer, not an LLM call, so it runs free, with
 * no API key and no per-request cost. It looks at structural signals
 * that correlate with extractability: where the first real answer
 * appears, whether the intro is a wall of text, whether lists/tables
 * exist for comparative content, and whether headings are phrased as
 * the kind of questions people actually ask AI assistants.
 *
 * @package WordPress_GEO_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Extractability_Scorer {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
	}

	public static function add_meta_box() {
		foreach ( WGT_LLMS_Generator::get_included_post_types() as $post_type ) {
			add_meta_box(
				'wgt_extractability',
				__( 'AI Extractability', 'geopilot' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	public static function render_meta_box( $post ) {
		$result = self::score( $post );
		?>
		<div class="wgt-extractability-box">
			<p style="font-size:28px;font-weight:600;margin:0 0 4px;">
				<?php echo esc_html( $result['score'] ); ?>/100
			</p>
			<p style="margin:0 0 12px;color:#666;"><?php echo esc_html( self::score_label( $result['score'] ) ); ?></p>
			<?php if ( ! empty( $result['issues'] ) ) : ?>
				<ul style="margin:0;padding-left:18px;">
					<?php foreach ( $result['issues'] as $issue ) : ?>
						<li style="margin-bottom:6px;font-size:12px;"><?php echo esc_html( $issue ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p style="font-size:12px;color:#2a7d2a;">✅ <?php esc_html_e( 'No structural issues found.', 'geopilot' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	protected static function score_label( $score ) {
		if ( $score >= 85 ) {
			return __( 'Excellent, AI-ready structure', 'geopilot' );
		}
		if ( $score >= 60 ) {
			return __( 'Workable, some gaps', 'geopilot' );
		}
		return __( 'Weak, likely to be skipped or misquoted', 'geopilot' );
	}

	/**
	 * Score a post's content on AI-extractability. 100 points total,
	 * distributed across the checks below. Each failed check both
	 * subtracts points and adds a specific, actionable issue string.
	 *
	 * @param WP_Post $post
	 * @return array{score:int, issues:string[]}
	 */
	public static function score( $post ) {
		$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$content = trim( preg_replace( '/\s+/', ' ', $content ) );
		$title   = get_the_title( $post );

		$score  = 100;
		$issues = array();

		// 1. Lead-answer check (25 pts): is there a complete, substantial
		// sentence within the first 60 words, rather than throat-clearing?
		$lead_words = implode( ' ', array_slice( explode( ' ', $content ), 0, 60 ) );
		$first_sentence_end = self::find_sentence_end( $content );
		if ( false === $first_sentence_end || $first_sentence_end > 400 ) {
			$score -= 25;
			$issues[] = 'No complete, substantial sentence within the first ~400 characters. Lead with a direct answer, not scene-setting.';
		}

		// 2. Wall-of-text intro check (20 pts): is there a heading before
		// word 150? Content with no structure for 150+ words is hard to
		// excerpt cleanly.
		$post_html   = $post->post_content;
		$first_heading_pos = self::find_first_heading_word_position( $post_html );
		if ( null === $first_heading_pos || $first_heading_pos > 150 ) {
			$score -= 20;
			$issues[] = 'No heading in the first 150 words. AI extraction favors content broken into labeled sections early.';
		}

		// 3. List/table presence for comparative or stepwise content (15 pts).
		// Heuristic: titles containing comparison/list-like words should
		// have at least one <ul>, <ol>, or <table>.
		$implies_list = (bool) preg_match( '/\b(best|top|vs\.?|versus|compare|comparison|ways|steps|tips|how to|checklist)\b/i', $title );
		$has_list     = (bool) preg_match( '/<(ul|ol|table)[\s>]/i', $post_html );
		if ( $implies_list && ! $has_list ) {
			$score -= 15;
			$issues[] = 'Title implies a list or comparison, but no <ul>, <ol>, or <table> found. AI models extract lists far more reliably than prose enumeration.';
		}

		// 4. Question-phrased subheadings (15 pts): at least one H2/H3
		// phrased as a question matches how people actually prompt AI
		// assistants, and gives the model a directly liftable Q/A pair.
		$has_question_heading = (bool) preg_match( '/<h[2-4][^>]*>[^<]*\?[^<]*<\/h[2-4]>/i', $post_html );
		if ( ! $has_question_heading ) {
			$score -= 15;
			$issues[] = 'No subheading phrased as a question. A question-style H2/H3 with a direct answer beneath it is the most commonly cited pattern in AI answers.';
		}

		// 5. Length sanity check (10 pts): extremely short content rarely
		// carries enough substance to be cited on its own.
		$word_count = str_word_count( $content );
		if ( $word_count < 150 ) {
			$score -= 10;
			$issues[] = 'Only ' . $word_count . ' words. Thin content is rarely cited as a standalone source.';
		}

		// 6. Paragraph length check (15 pts): flag if average paragraph
		// length is very long, dense paragraphs are harder to excerpt
		// as a clean quote.
		$paragraphs = preg_split( '/<\/p>/i', $post_html );
		$para_word_counts = array();
		foreach ( $paragraphs as $para ) {
			$text = trim( wp_strip_all_tags( $para ) );
			if ( '' !== $text ) {
				$para_word_counts[] = str_word_count( $text );
			}
		}
		$avg_para_words = ! empty( $para_word_counts ) ? array_sum( $para_word_counts ) / count( $para_word_counts ) : 0;
		if ( $avg_para_words > 120 ) {
			$score -= 15;
			$issues[] = 'Average paragraph length is ' . round( $avg_para_words ) . ' words. Paragraphs over ~100 words are harder for AI models to lift as a clean, quotable excerpt. Break them up.';
		}

		$score = max( 0, $score );

		return array(
			'score'  => $score,
			'issues' => $issues,
		);
	}

	/**
	 * Find the character position where the first complete sentence ends.
	 *
	 * @param string $text
	 * @return int|false
	 */
	protected static function find_sentence_end( $text ) {
		if ( preg_match( '/^.{20,}?[.!?](\s|$)/s', $text, $matches ) ) {
			return strlen( $matches[0] );
		}
		return false;
	}

	/**
	 * Approximate word position of the first H2-H4 heading in raw post HTML.
	 *
	 * @param string $html
	 * @return int|null
	 */
	protected static function find_first_heading_word_position( $html ) {
		if ( ! preg_match( '/<h[2-4][^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		$before = substr( $html, 0, $matches[0][1] );
		$before_text = wp_strip_all_tags( $before );
		return str_word_count( trim( $before_text ) );
	}
}
