# WCPOS WP Multilang Integration

Adds WP Multilang-aware product filtering to WCPOS, including **fast sync route coverage** and a per-store language selector for WCPOS Pro stores.

## What it does

- Filters WCPOS product + variation REST queries by language.
- Intercepts WCPOS fast-sync routes (`posts_per_page=-1` + `fields`) so duplicate translated products are not returned.
- Free WCPOS stores default to WP Multilang default language.
- WCPOS Pro stores can save a store-specific language.
- WCPOS Pro store edit gets a **Language** section with explanatory help text.
- Store language selector UI only loads when WP Multilang is active and languages are available.
- Plugin strings use the `wcpos-wp-multilang` text domain.
- PHP integration now no-ops when WP Multilang is unavailable.
- Optional minimum version gate via `wcpos_wp_multilang_minimum_version` filter.

## Development

```bash
pnpm test
```
