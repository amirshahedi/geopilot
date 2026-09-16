<?php
/**
 * Content negotiation for AI crawlers.
 *
 * None of the major SEO plugins do this today. The idea is simple and
 * not new, sites already serve a different rendering to mobile
 * user-agents, this does the same thing for declared AI bots: strip
 * navigation, sidebars, ads, and theme chrome, and serve the same
 * substantive content in a clean, semantic, easily-extractable form.
 *
 * This is content negotiation, not cloaking. Cloaking means showing
 * different SUBSTANTIVE content to crawlers than to humans in order to
 * manipulate rankings. This shows identical information (same title,
 * same body content, same facts) in a different presentation, the way
 * a print stylesheet or an AMP page does. The plugin only activates for
 * user-agents that self-identify as AI bots via their UA string, never
 * for anything claiming to be a browser or a human visitor.
 *
 * @package WordPress_GEO_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_AI_Content_Negotiation {

	/**
	 * User-agent substrings that identify a request as coming from a
	 * known AI crawler. Matched case-insensitively against $_SERVER['HTTP_USER_AGENT'].
	 *
	 * @var string[]
	 */
	const AI_BOT_SIGNATURES = array(
		'GPTBot',
		'ChatGPT-User',
		'OAI-SearchBot',
		'PerplexityBot',
		'ClaudeBot',
		'anthropic-ai',
		'Google-Extended',
		'Amazonbot',
		'Applebot-Extended',
		'CCBot',
		'Bytespider',
		'Diffbot',
		'FacebookBot',
	);

	public static function init() {
		add_action( 'wp_ajax_nopriv_wgt_ai_bot_hit', array( __CLASS__, 'noop' ) ); // reserved for future stats endpoint
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_clean_render' ), 0 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function noop() {}

	public static function register_settings() {
		register_setting( 'wgt_settings', 'wgt_content_negotiation_enabled', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
		) );
	}

	/**
	 * Detect whether this request came from a known AI bot.
	 *
	 * @return string|null The matched signature, or null if no match.
	 */
	public static function detect_ai_bot() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		if ( '' === $ua ) {
			return null;
		}

		foreach ( self::AI_BOT_SIGNATURES as $signature ) {
			if ( false !== stripos( $ua, $signature ) ) {
				return $signature;
			}
		}

		return null;
	}

	/**
	 * On singular posts/pages, if the request is from a known AI bot
	 * and the feature is enabled, short-circuit normal theme rendering
	 * and output a clean document instead.
	 */
	public static function maybe_serve_clean_render() {
		if ( ! get_option( 'wgt_content_negotiation_enabled', true ) ) {
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$bot = self::detect_ai_bot();
		if ( ! $bot ) {
			return;
		}

		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		self::increment_hit_count( $bot );

		$html = self::render_clean_document( $post, $bot );

		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Wgt-Served-To: ' . $bot );
		header( 'Vary: User-Agent' );
		echo $html; // phpcs:ignore -- already escaped field by field in build_clean_body()
		exit;
	}

	/**
	 * Build the clean, chrome-free HTML document for a single post.
	 * Same facts as the normal page, no nav/sidebar/ads/scripts.
	 *
	 * @param WP_Post $post
	 * @param string  $bot
	 * @return string
	 */
	protected static function render_clean_document( $post, $bot ) {
		$title       = get_the_title( $post );
		$permalink   = get_permalink( $post );
		$date        = get_the_date( 'c', $post );
		$modified    = get_the_modified_date( 'c', $post );
		$author      = get_the_author_meta( 'display_name', $post->post_author );
		$site_name   = get_bloginfo( 'name' );
		$description = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';

		$categories = get_the_category( $post->ID );
		$cat_names  = ! empty( $categories ) ? wp_list_pluck( $categories, 'name' ) : array();

		$body = self::extract_clean_content( $post );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html( $title ); ?></title>
<link rel="canonical" href="<?php echo esc_url( $permalink ); ?>">
<meta name="wgt-served-to" content="<?php echo esc_attr( $bot ); ?>">
</head>
<body>
<article>
<h1><?php echo esc_html( $title ); ?></h1>
<p><strong>Source:</strong> <?php echo esc_html( $site_name ); ?> (<?php echo esc_html( $permalink ); ?>)</p>
<?php if ( $author ) : ?><p><strong>Author:</strong> <?php echo esc_html( $author ); ?></p><?php endif; ?>
<p><strong>Published:</strong> <?php echo esc_html( $date ); ?> | <strong>Updated:</strong> <?php echo esc_html( $modified ); ?></p>
<?php if ( ! empty( $cat_names ) ) : ?><p><strong>Category:</strong> <?php echo esc_html( implode( ', ', $cat_names ) ); ?></p><?php endif; ?>
<?php if ( $description ) : ?><p><em><?php echo esc_html( wp_strip_all_tags( $description ) ); ?></em></p><?php endif; ?>
<hr>
<?php echo $body; // phpcs:ignore -- already sanitized in extract_clean_content() ?>
</article>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Strip the post content down to a small allow-list of semantic
	 * tags, dropping scripts, styles, iframes, forms, and any
	 * class/id/style attributes that don't help an AI parse the text.
	 *
	 * @param WP_Post $post
	 * @return string Safe HTML fragment.
	 */
	protected static function extract_clean_content( $post ) {
		$content = apply_filters( 'the_content', $post->post_content );

		$allowed = array(
			'p'          => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'strong'     => array(),
			'em'         => array(),
			'blockquote' => array(),
			'table'      => array(),
			'thead'      => array(),
			'tbody'      => array(),
			'tr'         => array(),
			'th'         => array(),
			'td'         => array(),
			'a'          => array( 'href' => true ),
			'img'        => array( 'src' => true, 'alt' => true ),
			'code'       => array(),
			'pre'        => array(),
		);

		return wp_kses( $content, $allowed );
	}

	/**
	 * Track how many times each bot has requested a clean render.
	 * Lightweight counter, not a full analytics system, just enough
	 * to show the admin their content is actually being fetched.
	 *
	 * @param string $bot
	 */
	protected static function increment_hit_count( $bot ) {
		$counts = get_option( 'wgt_ai_bot_hit_counts', array() );
		if ( ! is_array( $counts ) ) {
			$counts = array();
		}
		$counts[ $bot ] = isset( $counts[ $bot ] ) ? $counts[ $bot ] + 1 : 1;
		update_option( 'wgt_ai_bot_hit_counts', $counts, false );
	}

	/**
	 * @return array<string,int>
	 */
	public static function get_hit_counts() {
		$counts = get_option( 'wgt_ai_bot_hit_counts', array() );
		return is_array( $counts ) ? $counts : array();
	}
}
