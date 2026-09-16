=== GeoPilot ===
Contributors: amirshahedi
Tags: geo, aeo, seo, llms-txt, ai-seo
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.2
License: MIT
License URI: https://opensource.org/licenses/MIT

Generate llms.txt, audit your content for AI-citation readiness, and check whether ChatGPT, Claude, Perplexity, and Gemini can actually crawl your site.

== Description ==

People increasingly ask ChatGPT, Perplexity, Gemini, and Claude questions instead of searching Google directly. Whether those answers cite your site depends on three things most WordPress sites get wrong by default:

1. **No llms.txt** - the emerging standard LLMs use to efficiently discover and ingest site content, without brute-force crawling every page.
2. **Incomplete Article/Page schema** - missing featured images, excerpts, or thin content mean an AI has nothing solid to summarize or cite.
3. **Blocked crawlers** - a default or misconfigured robots.txt can silently block GPTBot, ClaudeBot, or PerplexityBot, making the site invisible to AI answer engines regardless of how good the content is.

WordPress GEO Toolkit fixes all three from one admin screen, with zero external dependencies and zero data leaving your server.

= Features =

* **llms.txt / llms-full.txt generator** - pulls every published post, page, and custom post type into spec-compliant Markdown, one click or one `wp geo generate` away.
* **AI content negotiation** - serves a clean, chrome-free rendering of your content to declared AI bots (GPTBot, ClaudeBot, PerplexityBot, and more), stripping navigation, sidebars, ads, and scripts while keeping the same substantive content. No other major SEO plugin does this.
* **Answer-first extractability scoring** - a per-post score (shown right in the editor sidebar) checking whether your content actually leads with a clean, quotable answer, has question-phrased subheadings, uses lists for comparative content, and avoids dense wall-of-text paragraphs. Different from a readability score, this measures how easy your content is for an AI model to extract and cite.
* **Schema.org audit** - flags content missing a featured image, excerpt, category, author name, or with thin word count.
* **AI-crawler access audit** - parses your live robots.txt and reports allow/block status for GPTBot, ClaudeBot, PerplexityBot, Google-Extended, Amazonbot, Bingbot, and more.
* **WP-CLI support** - `wp geo generate`, `wp geo audit-schema`, `wp geo audit-crawlers` for cron jobs and deploy pipelines.
* No SaaS dependency, no external API calls, no account required.

= Works with any content type =

By default this scans every public post type, posts, pages, and most custom post types. Narrow it with a filter if you only want posts and pages:

`add_filter( 'wgt_included_post_types', function( $post_types ) {
    return array( 'post', 'page' );
} );`

= Source code =

Development happens on GitHub: https://github.com/amirshahedi/geopilot

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-geo-toolkit` directory, or install directly through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings → GEO Toolkit** to generate your first export.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. This version works on any WordPress site, posts, pages, and custom post types. If you run a WooCommerce store and want product-specific fields (price, SKU, stock) in the export, see the companion plugin WooCommerce GEO Toolkit instead.

= Where do the generated files go? =

`/wp-content/uploads/geo-exports/llms.txt` and `llms-full.txt`. The plugin also attempts to mirror `llms.txt` to your site root so it's reachable at `/llms.txt`, per the llms.txt specification, if your server permissions allow it.

= Does this send my content anywhere? =

No. Everything runs locally on your server. No external API calls, no third-party service, no account.

= Can I automate this instead of clicking a button? =

Yes, if you have WP-CLI available, `wp geo generate` can be scheduled via cron.

== Screenshots ==

1. The GEO Toolkit admin screen under Settings, showing the generator, schema audit, and crawler audit in one place.

== Changelog ==

= 0.3.2 =
* Fixed text domain mismatch (now correctly "geopilot" throughout).
* Removed the Domain Path header since no translation files are bundled yet.
* Updated "Tested up to" to 7.1.

= 0.3.1 =
* Fixed: excluded page-builder and structural post types (Elementor template parts, reusable blocks, block templates, ACF field groups, and similar) from the content export and schema audit. Only real published content is included now.

= 0.3.0 =
* Added AI content negotiation, serves a clean, chrome-free rendering to declared AI bots (GPTBot, ClaudeBot, PerplexityBot, and more).
* Added answer-first extractability scoring in the post editor sidebar, a heuristic score checking whether content leads with a clean answer, uses question-phrased subheadings, and avoids dense paragraphs.

= 0.2.0 =
* Removed WooCommerce dependency, now works on any WordPress site.
* Schema audit now checks Article/Page fields (featured image, excerpt, author, category, word count) instead of Product fields.
* Admin page moved to Settings → GEO Toolkit.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.3.0 =
Adds AI content negotiation and extractability scoring, two features no other major SEO plugin currently offers.
