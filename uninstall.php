<?php
/**
 * Removes plugin data on uninstall.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete this plugin's data for the current site.
 */
function woo_performance_toolkit_uninstall_site(): void {
	global $wpdb;

	delete_option( 'woo_performance_toolkit_settings' );
	delete_option( 'woo_performance_toolkit_last_run' );
	wp_clear_scheduled_hook( 'woo_performance_toolkit_cleanup' );

	// Per-user one-off notices (transients).
	$patterns = array(
		$wpdb->esc_like( '_transient_woo_perf_notice_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_woo_perf_notice_' ) . '%',
	);

	foreach ( $patterns as $pattern ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, $pattern ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $woo_perf_site_id ) {
		switch_to_blog( (int) $woo_perf_site_id );
		woo_performance_toolkit_uninstall_site();
		restore_current_blog();
	}
} else {
	woo_performance_toolkit_uninstall_site();
}
