<?php
/**
 * Plugin Name:       Virtual Media Folders - Media Cleanup
 * Plugin URI:        https://github.com/soderlind/vmfa-media-cleanup
 * Description:       Media maintenance add-on for Virtual Media Folders. Detect unused, duplicate, and oversized media — then archive, trash, or flag for review.
 * Version:           0.5.0
 * Requires at least: 6.8
 * Requires PHP:      8.3
 * Requires Plugins:  virtual-media-folders
 * Author:            Per Soderlind
 * Author URI:        https://soderlind.no
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vmfa-media-cleanup
 * Domain Path:       /languages
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup;

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'VMFA_MEDIA_CLEANUP_VERSION', '0.5.0' );
define( 'VMFA_MEDIA_CLEANUP_FILE', __FILE__ );
define( 'VMFA_MEDIA_CLEANUP_PATH', plugin_dir_path( __FILE__ ) );
define( 'VMFA_MEDIA_CLEANUP_URL', plugin_dir_url( __FILE__ ) );
define( 'VMFA_MEDIA_CLEANUP_BASENAME', plugin_basename( __FILE__ ) );

// Require Composer autoloader.
if ( file_exists( VMFA_MEDIA_CLEANUP_PATH . 'vendor/autoload.php' ) ) {
	require_once VMFA_MEDIA_CLEANUP_PATH . 'vendor/autoload.php';
}

// Initialize Action Scheduler early (must be loaded before plugins_loaded).
// Action Scheduler uses its own version management, so it's safe to load even if another plugin bundles it.
if ( ! function_exists( 'as_schedule_single_action' ) ) {
	$action_scheduler_paths = array(
		VMFA_MEDIA_CLEANUP_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php',
		VMFA_MEDIA_CLEANUP_PATH . 'woocommerce/action-scheduler/action-scheduler.php',
	);

	foreach ( $action_scheduler_paths as $action_scheduler_path ) {
		if ( file_exists( $action_scheduler_path ) ) {
			require_once $action_scheduler_path;
			break;
		}
	}
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function init(): void {
	// Update checker via GitHub releases.
	Update\GitHubPluginUpdater::create_with_assets(
		'https://github.com/soderlind/vmfa-media-cleanup',
		__FILE__,
		'vmfa-media-cleanup',
		'/vmfa-media-cleanup\.zip/',
		'main'
	);

	// Boot the plugin.
	Plugin::get_instance()->init();
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\init', 20 );

/**
 * Activation hook.
 *
 * @return void
 */
function activate(): void {
	// Set default settings if not exists.
	if ( false === get_option( 'vmfa_media_cleanup_settings' ) ) {
		update_option(
			'vmfa_media_cleanup_settings',
			array(
				'image_size_threshold'    => 2097152,    // 2 MB.
				'video_size_threshold'    => 52428800,   // 50 MB.
				'audio_size_threshold'    => 10485760,   // 10 MB.
				'document_size_threshold' => 10485760,   // 10 MB.
				'hash_algorithm'          => 'sha256',
				'scan_batch_size'         => 200,
				'archive_folder_name'     => 'Archive',
				'extra_meta_keys'         => array(),
			)
		);
	}

	// Initialize scan progress option.
	if ( false === get_option( 'vmfa_cleanup_scan_progress' ) ) {
		update_option(
			'vmfa_cleanup_scan_progress',
			array(
				'status'       => 'idle',
				'phase'        => '',
				'total'        => 0,
				'processed'    => 0,
				'started_at'   => null,
				'completed_at' => null,
			)
		);
	}

	// Create the reference index table.
	Services\ReferenceIndex::create_table();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivation hook.
 *
 * @return void
 */
function deactivate(): void {
	// Unschedule all pending actions.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'vmfa_cleanup_build_index_batch' );
		as_unschedule_all_actions( 'vmfa_cleanup_run_detectors' );
		as_unschedule_all_actions( 'vmfa_cleanup_finalize_scan' );
	}

	// Reset scan progress.
	update_option(
		'vmfa_cleanup_scan_progress',
		array(
			'status'       => 'idle',
			'phase'        => '',
			'total'        => 0,
			'processed'    => 0,
			'started_at'   => null,
			'completed_at' => null,
		)
	);
}

register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
