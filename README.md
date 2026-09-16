# WordPress GEO Toolkit

**Open-source GEO/AEO toolkit for WordPress.** Generates `llms.txt` and `llms-full.txt` content exports, audits Article/Page schema.org markup, and checks whether AI crawlers (GPTBot, PerplexityBot, ClaudeBot, Google-Extended, Amazonbot) can actually reach your site. Works on any WordPress site - no WooCommerce or other plugin required. MIT licensed.

## Why this exists

People increasingly ask ChatGPT, Perplexity, Gemini, and Claude questions instead of searching Google directly. Whether those answers cite *your* site depends on three things most WordPress sites get wrong by default:

1. **No `llms.txt`** - the emerging standard LLMs use to efficiently discover and ingest site content, without brute-force crawling every page.
2. **Incomplete Article/Page schema** - missing featured images, excerpts, or thin content mean an AI has nothing solid to summarize or cite.
3. **Blocked crawlers** - a default or misconfigured `robots.txt` can silently block GPTBot, ClaudeBot, or PerplexityBot, making the site invisible to AI answer engines regardless of how good the content is.

This plugin fixes all three from one admin screen, with zero external dependencies.

## Features

- **`llms.txt` / `llms-full.txt` generator** - pulls every published post, page, and custom post type into spec-compliant Markdown, one click or one `wp geo generate` away.
- **Schema.org audit** - flags content missing a featured image, excerpt, category, author name, or with thin word count - the fields AI engines weight most heavily.
- **AI-crawler access audit** - parses your live `robots.txt` and reports allow/block status for every major AI bot.
- **WP-CLI support** - `wp geo generate`, `wp geo audit-schema`, `wp geo audit-crawlers` for cron jobs and deploy pipelines, not just a wp-admin button.
- No SaaS dependency, no external API calls - everything runs on your own server against your own data.

## Installation

1. Download or clone this repo into `wp-content/plugins/geopilot`.
2. Activate **WordPress GEO Toolkit** from the Plugins screen.
3. Go to **Settings → GEO Toolkit** in wp-admin.

## Usage

**From wp-admin:** Settings → GEO Toolkit → *Generate llms.txt now*. The audits run automatically on page load.

**From WP-CLI:**
```bash
wp geo generate
wp geo audit-schema --limit=500
wp geo audit-crawlers
```

Generated files live in `wp-content/uploads/geo-exports/` and `llms.txt` is mirrored to the site root (`/llms.txt`) when the filesystem permits, per the [llms.txt spec](https://llmstxt.org/).

## Filtering which content is included

By default, every public post type (posts, pages, and most custom post types) is included. Narrow or widen this with:

```php
add_filter( 'wgt_included_post_types', function( $post_types ) {
	return array( 'post', 'page' ); // Only posts and pages.
} );
```

## Roadmap

- [ ] Scheduled auto-regeneration on content save/update (WP cron hook)
- [ ] Bulk-fix suggestions for schema gaps (AI-assisted excerpt/alt-text drafts)
- [ ] Multisite batch export
- [ ] Admin dashboard widget showing AI-citation readiness score
- [ ] Optional WooCommerce add-on for product-specific export fields (price, SKU, stock)

## Contributing

Issues and PRs welcome. This is early - the roadmap above is a starting point, not a promise, and it'll shift based on what real sites need most.

## License

MIT - see [LICENSE](LICENSE).
