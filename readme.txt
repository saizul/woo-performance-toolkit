=== Woo Performance Toolkit ===
Contributors: saizulamin
Tags: woocommerce, performance, cleanup, cart fragments, heartbeat
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Requires Plugins: woocommerce
WC requires at least: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Trim WooCommerce overhead: cart fragments, front-end assets, heartbeat, scheduled database cleanup and a diagnostics screen with recommendations.

== Description ==

Woo Performance Toolkit collects the most common WooCommerce performance fixes in one plugin. Every feature is switchable on **WooCommerce > Performance** and the defaults are conservative: out of the box only the heartbeat is slowed down.

**Front end**

* Disable `wc-cart-fragments` on pages without a cart widget, with an allow-list of post IDs, URLs or path prefixes.
* Dequeue WooCommerce scripts and styles on pages that are not shop pages, with a keep-list and filters.

**Admin**

* Disable the WooCommerce Marketing hub and Marketplace suggestions using documented WooCommerce filters.
* Optionally disable WooCommerce Admin entirely (advanced).
* Slow down or disable the Heartbeat API on the admin and the front end. The post editor always keeps a slow heartbeat for autosave and post locking.

**Database cleanup**

* Expired WooCommerce sessions.
* Expired transients (and site transients).
* Completed and failed Action Scheduler actions and logs older than a retention period.
* Orphaned order item meta.
* Dry-run counts in the admin and in WP-CLI, optional daily or weekly schedule, batched deletes.

**Diagnostics**

* HPOS status, object cache, autoloaded options size and the largest autoloaded options, Action Scheduler backlog, products with a very high number of variations and PHP version, with prioritised recommendations.

**WP-CLI**

* `wp woo-perf status`
* `wp woo-perf cleanup --dry-run`
* `wp woo-perf cleanup --task=sessions,transients --yes`

The plugin declares compatibility with High-Performance Order Storage (custom order tables) and the Cart and Checkout blocks.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate it. WooCommerce must be active.
3. Go to WooCommerce > Performance and enable the features you want.

== Frequently Asked Questions ==

= Is it safe to enable "Disable cart fragments"? =

It is safe for most themes, but themes that render an AJAX mini-cart outside the cart widget or block may need those pages on the allow-list. Test with your theme first.

= How do I keep a script on a page that is not a WooCommerce page? =

Add the handle to "Always keep these handles", add the page to the allow-list, or use the `woo_performance_toolkit_keep_handles` and `woo_performance_toolkit_is_woocommerce_page` filters.

= Does cleanup delete data permanently? =

Yes. Use the dry run first. Only expired or orphaned rows and Action Scheduler records older than your retention period are removed.

= Which filters are available? =

`woo_performance_toolkit_is_woocommerce_page`, `woo_performance_toolkit_keep_cart_fragments`, `woo_performance_toolkit_keep_handles`, `woo_performance_toolkit_disabled_admin_features` and `woo_performance_toolkit_variation_threshold`.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
