<?php
/**
 * Checks whether known AI-search crawlers are allowed to fetch the store,
 * by parsing the site's live robots.txt.
 *
 * @package WooCommerce_GEO_Toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Crawler_Audit {

	/**
	 * User-agents worth checking for, mapped to the engine they feed.
	 *
	 * @var array<string,string>
	 */
	const KNOWN_BOTS = array(
		'GPTBot'         => 'ChatGPT / OpenAI',
		'ChatGPT-User'   => 'ChatGPT (browsing plugin)',
		'OAI-SearchBot'  => 'ChatGPT Search',
		'PerplexityBot'  => 'Perplexity',
		'ClaudeBot'      => 'Claude / Anthropic',
		'anthropic-ai'   => 'Claude (legacy tag)',
		'Google-Extended' => 'Google AI Overviews / Gemini training',
		'Bingbot'        => 'Bing / Copilot',
		'Amazonbot'      => 'Amazon (Rufus / shopping agents)',
	);

	/**
	 * Fetch robots.txt and report allow/disallow status per known bot.
	 *
	 * @return array{fetched:bool, bots: array<string,array{engine:string, allowed:bool}>}
	 */
	public static function audit() {
		$robots_url = home_url( '/robots.txt' );
		$response   = wp_remote_get( $robots_url, array( 'timeout' => 8 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array(
				'fetched' => false,
				'bots'    => array(),
			);
		}

		$body   = wp_remote_retrieve_body( $response );
		$blocks = self::parse_robots( $body );

		$results = array();
		foreach ( self::KNOWN_BOTS as $agent => $engine ) {
			$results[ $agent ] = array(
				'engine'  => $engine,
				'allowed' => self::is_allowed( $agent, $blocks ),
			);
		}

		return array(
			'fetched' => true,
			'bots'    => $results,
		);
	}

	/**
	 * Minimal robots.txt parser: groups Disallow rules under each
	 * User-agent block. Good enough for a top-level allow/block signal —
	 * not a full RFC 9309 implementation.
	 *
	 * @param string $body
	 * @return array<string,string[]>
	 */
	protected static function parse_robots( $body ) {
		$blocks  = array();
		$current = array();

		foreach ( preg_split( '/\R/', $body ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			if ( preg_match( '/^User-agent:\s*(.+)$/i', $line, $m ) ) {
				// A new User-agent line after rules starts a new block,
				// but consecutive User-agent lines share the block that follows.
				if ( ! empty( $current['rules'] ) ) {
					$current = array();
				}
				$current['agents'][] = trim( $m[1] );
				foreach ( $current['agents'] as $agent ) {
					if ( ! isset( $blocks[ $agent ] ) ) {
						$blocks[ $agent ] = array();
					}
				}
			} elseif ( preg_match( '/^Disallow:\s*(.*)$/i', $line, $m ) && ! empty( $current['agents'] ) ) {
				$path = trim( $m[1] );
				foreach ( $current['agents'] as $agent ) {
					$blocks[ $agent ][] = $path;
				}
				$current['rules'] = true;
			}
		}

		return $blocks;
	}

	/**
	 * A bot is considered blocked if it has an explicit "Disallow: /" rule
	 * under its own name or under "*".
	 *
	 * @param string                $agent
	 * @param array<string,string[]> $blocks
	 * @return bool
	 */
	protected static function is_allowed( $agent, $blocks ) {
		if ( isset( $blocks[ $agent ] ) && in_array( '/', $blocks[ $agent ], true ) ) {
			return false;
		}
		if ( isset( $blocks['*'] ) && in_array( '/', $blocks['*'], true ) && ! isset( $blocks[ $agent ] ) ) {
			return false;
		}
		return true;
	}
}
