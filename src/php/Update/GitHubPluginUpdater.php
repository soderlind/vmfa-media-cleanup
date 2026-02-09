<?php
/**
 * GitHub Plugin Updater wrapper.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\Update;

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Creates a GitHub-based auto-updater using Plugin Update Checker.
 */
class GitHubPluginUpdater {

	/**
	 * Create updater with release asset support.
	 *
	 * @param string $github_url   Full GitHub repository URL.
	 * @param string $plugin_file  Absolute path to the main plugin file.
	 * @param string $slug         Plugin slug.
	 * @return void
	 */
	public static function create_with_assets( string $github_url, string $plugin_file, string $slug ): void {
		if ( ! class_exists( PucFactory::class) ) {
			return;
		}

		$updater = PucFactory::buildUpdateChecker(
			$github_url,
			$plugin_file,
			$slug
		);

		// Use release assets instead of source archives.
		$updater->getVcsApi()->enableReleaseAssets();
	}
}
