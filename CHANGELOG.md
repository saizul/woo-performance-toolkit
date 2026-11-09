# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-09

### Added
- Disable `wc-cart-fragments` with a post ID / URL / path-prefix allow-list.
- Dequeue WooCommerce scripts and styles on non-shop pages, with keep-list and filters.
- Disable the WooCommerce Marketing hub and Marketplace suggestions via documented filters.
- Heartbeat limiter for admin and front end (post editor keeps a slow heartbeat).
- Cleanup tasks: expired WC sessions, expired transients, old Action Scheduler
  completed/failed actions and logs, orphaned order item meta, each with dry-run counts.
- WP-CLI: `wp woo-perf status`, `wp woo-perf cleanup [--dry-run] [--task=<tasks>] [--yes]`.
- Diagnostics screen with recommendations (HPOS, object cache, autoloaded options,
  Action Scheduler backlog, variation outliers, PHP version).
- HPOS and Cart/Checkout blocks compatibility declarations.
- PHPUnit suite for the WordPress-independent logic in `src/`.
