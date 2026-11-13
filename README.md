# Woo Performance Toolkit

Trim WooCommerce overhead from one settings page: cart fragments, front-end
assets, heartbeat, scheduled database cleanup and a diagnostics screen that
tells you what to fix next.

![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb4)
![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-21759b)
![WooCommerce 8+](https://img.shields.io/badge/WooCommerce-8%2B-96588a)
![License GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

Every feature is toggleable under **WooCommerce > Performance** and defaults are
conservative: only the heartbeat throttle is active after activation.

## Features

| Area | What it does | Default |
| --- | --- | --- |
| Cart fragments | Dequeues `wc-cart-fragments` except on cart/checkout, pages with a cart widget/block, and an allow-list of post IDs, URLs or path prefixes (`/shop/*`) | off |
| Front-end assets | Dequeues WooCommerce scripts and styles on non-shop pages without Woo shortcodes/blocks; keep-list and filters | off |
| Marketing hub | Removes the WooCommerce Admin `marketing` feature and Marketplace suggestions through documented filters | off |
| WooCommerce Admin | Optional full disable via `woocommerce_admin_disabled` (advanced) | off |
| Heartbeat | Slow down or disable on admin and front end; the post editor always keeps a slow heartbeat | slow |
| Cleanup | Expired WC sessions, expired transients, old Action Scheduler completed/failed actions and logs, orphaned order item meta; opt-in orphaned HPOS order meta, post meta, term meta and comment meta; dry run, batching, daily/weekly schedule | schedule off |
| Diagnostics | HPOS, object cache, autoloaded options size and largest options, Action Scheduler backlog, variation-count outliers, PHP version, with recommendations | always available |
| Diagnostics export | "Download diagnostics (JSON)" on the Diagnostics tab: versions, settings, metrics and recommendations to attach to a support request. Contains no URLs, users, emails or option values | on demand |

It declares compatibility with HPOS (custom order tables) and the Cart and
Checkout blocks.

## Install

```bash
cd wp-content/plugins
git clone https://github.com/saizul/woo-performance-toolkit.git
wp plugin activate woo-performance-toolkit
```

Requires WooCommerce to be active.

## WP-CLI

```bash
wp woo-perf status                    # features, pending cleanup counts, diagnostics, recommendations
wp woo-perf status --format=json
wp woo-perf cleanup --dry-run         # counts only, nothing is deleted
wp woo-perf cleanup --yes             # run the tasks enabled in settings
wp woo-perf cleanup --task=sessions,transients --yes
```

Tasks: `sessions`, `transients`, `action_scheduler`, `orphan_item_meta`,
`orphan_order_meta`, `orphan_post_meta`, `orphan_term_meta`, `orphan_comment_meta`.

| Orphan task | Rows removed | Default |
| --- | --- | --- |
| `orphan_item_meta` | `woocommerce_order_itemmeta` rows whose order item is gone | on |
| `orphan_order_meta` | `wc_orders_meta` (HPOS) rows whose order is gone; skipped when the HPOS tables do not exist | off |
| `orphan_post_meta` | `postmeta` rows whose post (product, variation, legacy order, page...) is gone | off |
| `orphan_term_meta` | `termmeta` rows whose term is gone | off |
| `orphan_comment_meta` | `commentmeta` rows whose comment (review, order note...) is gone | off |

The four new orphan tasks are off by default so an existing cleanup schedule
does not start touching core WordPress tables after an update. Preview them with
`wp woo-perf cleanup --task=orphan_post_meta --dry-run`, then enable them on the
Settings tab or run them with `--task`.

## Hooks

| Filter | Purpose |
| --- | --- |
| `woo_performance_toolkit_is_woocommerce_page` `( bool $is_woo, int $post_id )` | Treat a page as a WooCommerce page (keeps assets) |
| `woo_performance_toolkit_keep_cart_fragments` `( bool $keep, int $post_id )` | Keep cart fragments on the current page |
| `woo_performance_toolkit_keep_handles` `( string[] $handles, string $type )` | Handles never dequeued (`script` or `style`) |
| `woo_performance_toolkit_disabled_admin_features` `( string[] $features )` | WooCommerce Admin features removed by "Disable Marketing hub" (default `marketing`) |
| `woo_performance_toolkit_variation_threshold` `( int $threshold )` | Variation count above which a product is flagged (default 50) |

```php
// Keep cart fragments on landing pages.
add_filter( 'woo_performance_toolkit_keep_cart_fragments', function ( $keep, $post_id ) {
	return $keep || has_term( 'landing', 'page_type', $post_id );
}, 10, 2 );
```

## How cleanup stays safe

- Counts are shown first (admin Cleanup tab and `--dry-run`).
- Only expired sessions, expired transients, orphaned meta rows (meta whose
  parent order item, order, post, term or comment no longer exists) and Action
  Scheduler `complete`/`failed` rows older than the retention period are touched.
- Orphaned meta deletes repeat the parent check inside the `DELETE`, so a row
  whose parent reappears between the batch `SELECT` and the `DELETE` is kept.
- Deletes run in batches (`LIMIT`), never one giant query.
- All SQL is built by tested classes using `%i`/`%s`/`%d` placeholders and run
  through `$wpdb->prepare()`.
- Admin actions require the `manage_woocommerce` capability and a nonce.

## Development

Logic that does not need WordPress lives in `src/` and is tested with PHPUnit
without loading WordPress. WordPress glue lives in `includes/`.

```bash
composer install
composer test
composer lint
composer phpcs
```

See [CONTRIBUTING.md](CONTRIBUTING.md) and [CHANGELOG.md](CHANGELOG.md).

## Author

Built by [Saizul Amin](https://saizul.com), e-commerce web developer.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
