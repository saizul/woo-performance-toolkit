<?php
/**
 * Applies the heartbeat throttle on the admin and the front end.
 *
 * @package WooPerformanceToolkit
 */

declare(strict_types=1);

namespace SaizulAmin\WooPerformanceToolkit\Integration\Heartbeat;

use SaizulAmin\WooPerformanceToolkit\Heartbeat\HeartbeatPolicy;
use SaizulAmin\WooPerformanceToolkit\Settings\Options;

final class Controller {

	/**
	 * @param array<string,mixed> $settings Resolved settings.
	 */
	public function __construct( private array $settings ) {
	}

	public function register(): void {
		$admin    = Options::MODE_DEFAULT !== $this->settings['heartbeat_admin_mode'];
		$frontend = Options::MODE_DEFAULT !== $this->settings['heartbeat_frontend_mode'];

		if ( ! $admin && ! $frontend ) {
			return;
		}

		add_filter( 'heartbeat_settings', array( $this, 'filter_settings' ) );
		add_action( 'init', array( $this, 'maybe_disable' ), 1 );
	}

	/**
	 * @param mixed $settings Heartbeat settings.
	 * @return array<string,mixed>
	 */
	public function filter_settings( $settings ): array {
		$settings = is_array( $settings ) ? $settings : array();
		$decision = $this->decision();

		if ( HeartbeatPolicy::ACTION_INTERVAL === $decision['action'] ) {
			$settings['interval'] = $decision['interval'];
		}

		return $settings;
	}

	public function maybe_disable(): void {
		if ( HeartbeatPolicy::ACTION_DISABLE === $this->decision()['action'] ) {
			wp_deregister_script( 'heartbeat' );
		}
	}

	/**
	 * @return array{action:string,interval:int}
	 */
	private function decision(): array {
		if ( is_admin() ) {
			return HeartbeatPolicy::decide(
				(string) $this->settings['heartbeat_admin_mode'],
				(int) $this->settings['heartbeat_admin_interval'],
				$this->is_post_editor()
			);
		}

		return HeartbeatPolicy::decide(
			(string) $this->settings['heartbeat_frontend_mode'],
			(int) $this->settings['heartbeat_frontend_interval'],
			false
		);
	}

	private function is_post_editor(): bool {
		global $pagenow;

		return in_array( $pagenow ?? '', array( 'post.php', 'post-new.php' ), true );
	}
}
