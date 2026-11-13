<?php
/**
 * WooCommerce > Performance admin screen (Settings, Cleanup, Diagnostics).
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Integration\Admin;

use SaizulAmin\WooPerformanceToolkit\Cleanup\ResultSet;
use SaizulAmin\WooPerformanceToolkit\Cleanup\Tasks;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Export;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Recommendation;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Recommendations;
use SaizulAmin\WooPerformanceToolkit\Diagnostics\Thresholds;
use SaizulAmin\WooPerformanceToolkit\Integration\Cleanup\Runner;
use SaizulAmin\WooPerformanceToolkit\Integration\Cleanup\Scheduler;
use SaizulAmin\WooPerformanceToolkit\Integration\Diagnostics\Collector;
use SaizulAmin\WooPerformanceToolkit\Integration\SettingsStore;
use SaizulAmin\WooPerformanceToolkit\Settings\Options;
use SaizulAmin\WooPerformanceToolkit\Support\Bytes;

final class SettingsPage {

	public const SLUG          = 'woo-performance-toolkit';
	public const GROUP         = 'woo_performance_toolkit';
	public const CAPABILITY    = 'manage_woocommerce';
	public const ACTION_RUN    = 'woo_perf_run_cleanup';
	public const ACTION_EXPORT = 'woo_perf_export_diagnostics';

	/** @var array<string,mixed> */
	private array $settings = array();

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( $this, 'capability' ) );
		add_action( 'admin_post_' . self::ACTION_RUN, array( $this, 'handle_cleanup' ) );
		add_action( 'admin_post_' . self::ACTION_EXPORT, array( $this, 'handle_export' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WOO_PERF_FILE ), array( $this, 'action_links' ) );
	}

	public function capability(): string {
		return self::CAPABILITY;
	}

	/**
	 * @param mixed $links Existing plugin action links.
	 * @return array<int|string,string>
	 */
	public function action_links( $links ): array {
		$links = is_array( $links ) ? $links : array();
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
				esc_html__( 'Settings', 'woo-performance-toolkit' )
			)
		);

		return $links;
	}

	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Performance', 'woo-performance-toolkit' ),
			__( 'Performance', 'woo-performance-toolkit' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function register_setting(): void {
		register_setting(
			self::GROUP,
			SettingsStore::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => static fn( $value ): array => Options::sanitize( is_array( $value ) ? $value : array() ),
				'default'           => Options::defaults(),
			)
		);
	}

	/* ------------------------------------------------------------------ Render */

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-performance-toolkit' ), 403 );
		}

		$this->settings = SettingsStore::get();

		$tabs = array(
			'settings'    => __( 'Settings', 'woo-performance-toolkit' ),
			'cleanup'     => __( 'Cleanup', 'woo-performance-toolkit' ),
			'diagnostics' => __( 'Diagnostics', 'woo-performance-toolkit' ),
		);

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'settings';
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Woo Performance Toolkit', 'woo-performance-toolkit' ) . '</h1>';
		$this->render_notice();

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( add_query_arg( array( 'page' => self::SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) ) ),
				$slug === $tab ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'cleanup':
				$this->render_cleanup();
				break;
			case 'diagnostics':
				$this->render_diagnostics();
				break;
			default:
				$this->render_settings();
		}

		echo '</div>';
	}

	private function render_notice(): void {
		$key    = 'woo_perf_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) ) {
			return;
		}

		delete_transient( $key );

		$result = ResultSet::fromArray( $notice );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of rows removed. */
					__( 'Cleanup finished. %d rows removed.', 'woo-performance-toolkit' ),
					$result->total()
				)
			)
		);
	}

	private function render_settings(): void {
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );

		echo '<h2>' . esc_html__( 'Front end', 'woo-performance-toolkit' ) . '</h2><table class="form-table" role="presentation">';
		$this->row_checkbox( 'cart_fragments_disable', __( 'Disable cart fragments', 'woo-performance-toolkit' ), __( 'Dequeue wc-cart-fragments except on cart/checkout, pages with a cart widget or block, and the allow-list below. Disable only if your theme does not rely on the AJAX mini-cart.', 'woo-performance-toolkit' ) );
		$this->row_textarea( 'cart_fragments_allowlist', __( 'Allow-list', 'woo-performance-toolkit' ), __( 'One post ID, URL or path per line. Use a trailing * for prefixes (e.g. /shop/*). Lines starting with # are ignored. Listed pages keep cart fragments and WooCommerce assets.', 'woo-performance-toolkit' ) );
		$this->row_checkbox( 'assets_dequeue', __( 'Dequeue WooCommerce assets on non-shop pages', 'woo-performance-toolkit' ), __( 'Removes WooCommerce scripts and styles from pages that are not shop, product, cart, checkout or account pages and contain no WooCommerce shortcodes or blocks.', 'woo-performance-toolkit' ) );
		$this->row_textarea( 'assets_keep_handles', __( 'Always keep these handles', 'woo-performance-toolkit' ), __( 'One script/style handle per line. Developers can also use the woo_performance_toolkit_keep_handles filter.', 'woo-performance-toolkit' ) );
		echo '</table>';

		echo '<h2>' . esc_html__( 'Admin', 'woo-performance-toolkit' ) . '</h2><table class="form-table" role="presentation">';
		$this->row_checkbox( 'marketing_disable', __( 'Disable the Marketing hub', 'woo-performance-toolkit' ), __( 'Removes the WooCommerce Admin "marketing" feature and Marketplace suggestions via core WooCommerce filters.', 'woo-performance-toolkit' ) );
		$this->row_checkbox( 'wc_admin_disable', __( 'Disable WooCommerce Admin entirely', 'woo-performance-toolkit' ), __( 'Turns off the React-based admin (Analytics, Home, Inbox). Advanced: some extensions depend on it.', 'woo-performance-toolkit' ) );
		echo '</table>';

		echo '<h2>' . esc_html__( 'Heartbeat', 'woo-performance-toolkit' ) . '</h2><table class="form-table" role="presentation">';
		$modes = array(
			Options::MODE_DEFAULT => __( 'WordPress default', 'woo-performance-toolkit' ),
			Options::MODE_SLOW    => __( 'Slow down', 'woo-performance-toolkit' ),
			Options::MODE_DISABLE => __( 'Disable (post editor keeps a slow heartbeat)', 'woo-performance-toolkit' ),
		);
		$this->row_select( 'heartbeat_admin_mode', __( 'Admin heartbeat', 'woo-performance-toolkit' ), $modes );
		$this->row_number( 'heartbeat_admin_interval', __( 'Admin interval (seconds)', 'woo-performance-toolkit' ), 15, 300 );
		$this->row_select( 'heartbeat_frontend_mode', __( 'Front-end heartbeat', 'woo-performance-toolkit' ), $modes );
		$this->row_number( 'heartbeat_frontend_interval', __( 'Front-end interval (seconds)', 'woo-performance-toolkit' ), 15, 300 );
		echo '</table>';

		echo '<h2>' . esc_html__( 'Scheduled cleanup', 'woo-performance-toolkit' ) . '</h2><table class="form-table" role="presentation">';
		$this->row_checkbox( 'cleanup_enabled', __( 'Run cleanup on a schedule', 'woo-performance-toolkit' ), __( 'Off by default. You can always preview and run the cleanup manually from the Cleanup tab or with WP-CLI.', 'woo-performance-toolkit' ) );
		$this->row_select(
			'cleanup_frequency',
			__( 'Frequency', 'woo-performance-toolkit' ),
			array(
				Options::FREQ_DAILY  => __( 'Daily', 'woo-performance-toolkit' ),
				Options::FREQ_WEEKLY => __( 'Weekly', 'woo-performance-toolkit' ),
			)
		);
		$this->row_checkbox( 'cleanup_task_sessions', __( 'Task: expired sessions', 'woo-performance-toolkit' ), '' );
		$this->row_checkbox( 'cleanup_task_transients', __( 'Task: expired transients', 'woo-performance-toolkit' ), '' );
		$this->row_checkbox( 'cleanup_task_action_scheduler', __( 'Task: Action Scheduler completed/failed actions', 'woo-performance-toolkit' ), '' );
		$this->row_checkbox( 'cleanup_task_orphan_item_meta', __( 'Task: orphaned order item meta', 'woo-performance-toolkit' ), '' );
		$this->row_checkbox( 'cleanup_task_orphan_order_meta', __( 'Task: orphaned HPOS order meta', 'woo-performance-toolkit' ), __( 'Rows in wc_orders_meta whose order no longer exists. Skipped when the HPOS tables are absent.', 'woo-performance-toolkit' ) );
		$this->row_checkbox( 'cleanup_task_orphan_post_meta', __( 'Task: orphaned post meta', 'woo-performance-toolkit' ), __( 'Rows in postmeta whose post (product, order, page, etc.) no longer exists. Off by default; check the dry run first.', 'woo-performance-toolkit' ) );
		$this->row_checkbox( 'cleanup_task_orphan_term_meta', __( 'Task: orphaned term meta', 'woo-performance-toolkit' ), '' );
		$this->row_checkbox( 'cleanup_task_orphan_comment_meta', __( 'Task: orphaned comment meta', 'woo-performance-toolkit' ), __( 'Includes meta left behind by deleted product reviews and order notes.', 'woo-performance-toolkit' ) );
		$this->row_number( 'cleanup_retention_days', __( 'Action Scheduler retention (days)', 'woo-performance-toolkit' ), 1, 3650 );
		$this->row_number( 'cleanup_batch_size', __( 'Batch size (rows per query)', 'woo-performance-toolkit' ), 100, 10000 );
		echo '</table>';

		submit_button();
		echo '</form>';
	}

	private function render_cleanup(): void {
		global $wpdb;

		$runner  = new Runner( $wpdb );
		$tasks   = Tasks::all();
		$counts  = $runner->count( $tasks, $this->settings );
		$labels  = Tasks::labels();
		$last    = get_option( Scheduler::LAST_RUN );
		$next_ts = wp_next_scheduled( Scheduler::HOOK );

		echo '<h2>' . esc_html__( 'Dry run: rows that would be removed', 'woo-performance-toolkit' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>' . esc_html__( 'Task', 'woo-performance-toolkit' ) . '</th><th>' . esc_html__( 'Rows', 'woo-performance-toolkit' ) . '</th><th>' . esc_html__( 'In schedule', 'woo-performance-toolkit' ) . '</th></tr></thead><tbody>';

		foreach ( $tasks as $task ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $labels[ $task ] ),
				esc_html( number_format_i18n( $counts->get( $task ) ) ),
				! empty( $this->settings[ Tasks::settingKey( $task ) ] ) ? esc_html__( 'Yes', 'woo-performance-toolkit' ) : esc_html__( 'No', 'woo-performance-toolkit' )
			);
		}

		echo '</tbody></table>';

		echo '<p>';
		if ( $next_ts ) {
			printf(
				/* translators: %s: date/time */
				esc_html__( 'Next scheduled run: %s', 'woo-performance-toolkit' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_ts ) )
			);
		} else {
			esc_html_e( 'Scheduled cleanup is not active.', 'woo-performance-toolkit' );
		}
		echo '</p>';

		if ( is_array( $last ) && isset( $last['time'], $last['results'] ) ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: date/time, 2: removed row count */
						__( 'Last scheduled run: %1$s (%2$d rows removed)', 'woo-performance-toolkit' ),
						wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['time'] ),
						ResultSet::fromArray( (array) $last['results'] )->total()
					)
				)
			);
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RUN ) . '" />';
		wp_nonce_field( self::ACTION_RUN );
		submit_button( __( 'Run enabled cleanup tasks now', 'woo-performance-toolkit' ), 'secondary', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Delete the rows listed above for the enabled tasks? This cannot be undone.', 'woo-performance-toolkit' ) ) . "');" ) );
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'CLI: wp woo-perf cleanup --dry-run, wp woo-perf status', 'woo-performance-toolkit' ) . '</p>';
	}

	private function render_diagnostics(): void {
		global $wpdb;

		$snapshot = ( new Collector( $wpdb ) )->snapshot( $this->settings );
		$recs     = Recommendations::build( $snapshot );

		$yes_no = static fn( ?bool $v ): string => null === $v ? __( 'Unknown', 'woo-performance-toolkit' ) : ( $v ? __( 'Yes', 'woo-performance-toolkit' ) : __( 'No', 'woo-performance-toolkit' ) );

		$rows = array(
			__( 'HPOS enabled', 'woo-performance-toolkit' ) => $yes_no( $snapshot['hpos_enabled'] ),
			__( 'Persistent object cache', 'woo-performance-toolkit' ) => $yes_no( (bool) $snapshot['object_cache'] ),
			__( 'Autoloaded options size', 'woo-performance-toolkit' ) => Bytes::format( (int) $snapshot['autoload_bytes'] ),
			__( 'PHP version', 'woo-performance-toolkit' ) => (string) $snapshot['php_version'],
			__( 'Action Scheduler pending', 'woo-performance-toolkit' ) => null === $snapshot['as_pending'] ? __( 'n/a', 'woo-performance-toolkit' ) : number_format_i18n( (int) $snapshot['as_pending'] ),
			__( 'Action Scheduler past due', 'woo-performance-toolkit' ) => null === $snapshot['as_past_due'] ? __( 'n/a', 'woo-performance-toolkit' ) : number_format_i18n( (int) $snapshot['as_past_due'] ),
			__( 'Action Scheduler failed', 'woo-performance-toolkit' ) => null === $snapshot['as_failed'] ? __( 'n/a', 'woo-performance-toolkit' ) : number_format_i18n( (int) $snapshot['as_failed'] ),
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="float:right">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_EXPORT ) . '" />';
		wp_nonce_field( self::ACTION_EXPORT );
		submit_button( __( 'Download diagnostics (JSON)', 'woo-performance-toolkit' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Recommendations', 'woo-performance-toolkit' ) . '</h2>';
		foreach ( $recs as $rec ) {
			printf(
				'<div class="notice %1$s inline"><p><strong>%2$s</strong><br>%3$s</p></div>',
				esc_attr( $this->notice_class( $rec ) ),
				esc_html( $rec->title ),
				esc_html( $rec->detail )
			);
		}

		echo '<h2>' . esc_html__( 'Metrics', 'woo-performance-toolkit' ) . '</h2><table class="widefat striped" style="max-width:720px"><tbody>';
		foreach ( $rows as $label => $value ) {
			printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( $value ) );
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Largest autoloaded options', 'woo-performance-toolkit' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>' . esc_html__( 'Option', 'woo-performance-toolkit' ) . '</th><th>' . esc_html__( 'Size', 'woo-performance-toolkit' ) . '</th></tr></thead><tbody>';
		foreach ( $snapshot['largest_options'] as $option ) {
			printf( '<tr><td><code>%s</code></td><td>%s</td></tr>', esc_html( $option['name'] ), esc_html( Bytes::format( $option['size'] ) ) );
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Products with many variations', 'woo-performance-toolkit' ) . '</h2>';
		if ( array() === $snapshot['variation_outliers'] ) {
			echo '<p>' . esc_html__( 'No outliers found.', 'woo-performance-toolkit' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>' . esc_html__( 'Product', 'woo-performance-toolkit' ) . '</th><th>' . esc_html__( 'Variations', 'woo-performance-toolkit' ) . '</th></tr></thead><tbody>';
		foreach ( $snapshot['variation_outliers'] as $row ) {
			$id   = (int) $row['product_id'];
			$link = get_edit_post_link( $id );
			printf(
				'<tr><td>%s</td><td>%s</td></tr>',
				$link ? '<a href="' . esc_url( $link ) . '">#' . esc_html( (string) $id ) . ' ' . esc_html( get_the_title( $id ) ) . '</a>' : '#' . esc_html( (string) $id ),
				esc_html( number_format_i18n( (int) $row['variations'] ) )
			);
		}
		echo '</tbody></table>';
	}

	private function notice_class( Recommendation $rec ): string {
		return match ( $rec->severity ) {
			Thresholds::CRITICAL => 'notice-error',
			Thresholds::WARNING  => 'notice-warning',
			default              => 'notice-info',
		};
	}

	/* --------------------------------------------------------- Field renderers */

	private function name( string $key ): string {
		return SettingsStore::OPTION . '[' . $key . ']';
	}

	private function row_start( string $key, string $label ): void {
		printf( '<tr><th scope="row"><label for="%s">%s</label></th><td>', esc_attr( 'wpt-' . $key ), esc_html( $label ) );
	}

	private function row_end( string $description ): void {
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	private function row_checkbox( string $key, string $label, string $description ): void {
		$this->row_start( $key, $label );
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
			esc_attr( 'wpt-' . $key ),
			esc_attr( $this->name( $key ) ),
			checked( ! empty( $this->settings[ $key ] ), true, false )
		);
		$this->row_end( $description );
	}

	private function row_textarea( string $key, string $label, string $description ): void {
		$this->row_start( $key, $label );
		printf(
			'<textarea id="%1$s" name="%2$s" rows="5" class="large-text code">%3$s</textarea>',
			esc_attr( 'wpt-' . $key ),
			esc_attr( $this->name( $key ) ),
			esc_textarea( (string) $this->settings[ $key ] )
		);
		$this->row_end( $description );
	}

	private function row_number( string $key, string $label, int $min, int $max ): void {
		$this->row_start( $key, $label );
		printf(
			'<input type="number" id="%1$s" name="%2$s" value="%3$d" min="%4$d" max="%5$d" class="small-text" />',
			esc_attr( 'wpt-' . $key ),
			esc_attr( $this->name( $key ) ),
			(int) $this->settings[ $key ],
			absint( $min ),
			absint( $max )
		);
		$this->row_end( '' );
	}

	/**
	 * @param array<string,string> $choices Value => label.
	 */
	private function row_select( string $key, string $label, array $choices ): void {
		$this->row_start( $key, $label );
		printf( '<select id="%s" name="%s">', esc_attr( 'wpt-' . $key ), esc_attr( $this->name( $key ) ) );
		foreach ( $choices as $value => $text ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( (string) $value ),
				selected( $this->settings[ $key ], (string) $value, false ),
				esc_html( $text )
			);
		}
		echo '</select>';
		$this->row_end( '' );
	}

	/* ----------------------------------------------------------- Form handlers */

	/**
	 * Send the support export as a JSON download. Contains no URLs, users or option values.
	 */
	public function handle_export(): void {
		global $wpdb, $wp_version;

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'woo-performance-toolkit' ), 403 );
		}

		check_admin_referer( self::ACTION_EXPORT );

		$settings    = SettingsStore::get();
		$environment = array(
			'plugin_version'      => defined( 'WOO_PERF_VERSION' ) ? WOO_PERF_VERSION : '',
			'wp_version'          => (string) $wp_version,
			'wc_version'          => defined( 'WC_VERSION' ) ? (string) WC_VERSION : '',
			'php_version'         => PHP_VERSION,
			'db_version'          => (string) $wpdb->db_version(),
			'memory_limit'        => (string) ini_get( 'memory_limit' ),
			'multisite'           => is_multisite(),
			'object_cache'        => (bool) wp_using_ext_object_cache(),
			'active_plugin_count' => count( (array) get_option( 'active_plugins', array() ) ),
		);

		$report = Export::build(
			( new Collector( $wpdb ) )->snapshot( $settings ),
			$settings,
			$environment,
			gmdate( 'c' )
		);

		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="' . Export::fileName( $host, gmdate( 'Ymd' ) ) . '"' );

		echo Export::toJson( $report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		exit;
	}

	public function handle_cleanup(): void {
		global $wpdb;

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'woo-performance-toolkit' ), 403 );
		}

		check_admin_referer( self::ACTION_RUN );

		$settings = SettingsStore::get();
		$result   = ( new Runner( $wpdb ) )->run( Tasks::enabledFromSettings( $settings ), $settings );

		set_transient( 'woo_perf_notice_' . get_current_user_id(), $result->toArray(), MINUTE_IN_SECONDS );

		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'tab' => 'cleanup' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
