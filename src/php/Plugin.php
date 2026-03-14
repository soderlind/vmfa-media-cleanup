<?php
/**
 * Main plugin class.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup;

defined( 'ABSPATH' ) || exit;

use VirtualMediaFolders\Addon\AbstractPlugin;
use VmfaMediaCleanup\Admin\SettingsTab;
use VmfaMediaCleanup\Detectors\UnusedDetector;
use VmfaMediaCleanup\Detectors\DuplicateDetector;
use VmfaMediaCleanup\Detectors\OversizedDetector;
use VmfaMediaCleanup\REST\ScanController;
use VmfaMediaCleanup\REST\ResultsController;
use VmfaMediaCleanup\REST\ActionsController;
use VmfaMediaCleanup\REST\SettingsController;
use VmfaMediaCleanup\Services\ScanService;
use VmfaMediaCleanup\Services\ReferenceIndex;
use VmfaMediaCleanup\Services\HashService;

/**
 * Plugin bootstrap class.
 */
final class Plugin extends AbstractPlugin {

	private ?ReferenceIndex $reference_index     = null;
	private ?HashService $hash_service           = null;
	private ?ScanService $scan_service           = null;
	private ?UnusedDetector $unused_detector     = null;
	private ?DuplicateDetector $duplicate_detector = null;
	private ?OversizedDetector $oversized_detector = null;
	private ?SettingsTab $settings_tab           = null;

	/** @inheritDoc */
	protected function get_text_domain(): string {
		return 'vmfa-media-cleanup';
	}

	/** @inheritDoc */
	protected function get_plugin_file(): string {
		return VMFA_MEDIA_CLEANUP_FILE;
	}

	/** @inheritDoc */
	protected function init_services(): void {
		$this->hash_service       = new HashService();
		$this->reference_index    = new ReferenceIndex();
		$this->unused_detector    = new UnusedDetector( $this->reference_index );
		$this->duplicate_detector = new DuplicateDetector( $this->hash_service );
		$this->oversized_detector = new OversizedDetector();
		$this->scan_service       = new ScanService(
			$this->reference_index,
			$this->hash_service,
			$this->unused_detector,
			$this->duplicate_detector,
			$this->oversized_detector
		);
		$this->settings_tab = new SettingsTab();
	}

	/** @inheritDoc */
	protected function init_hooks(): void {
		// Admin hooks.
		if ( is_admin() ) {
			if ( $this->supports_parent_tabs() ) {
				add_filter( 'vmfo_settings_tabs', array( $this->settings_tab, 'register_tab' ) );
				add_action( 'vmfo_settings_enqueue_scripts', array( $this->settings_tab, 'enqueue_tab_scripts' ), 10, 2 );
			} else {
				add_action( 'admin_menu', array( $this->settings_tab, 'register_admin_menu' ) );
				add_action( 'admin_enqueue_scripts', array( $this->settings_tab, 'enqueue_admin_assets' ) );
			}
		}

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Action Scheduler hooks.
		$this->scan_service->register_hooks();
	}

	/** @inheritDoc */
	protected function init_cli(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'vmfa-cleanup', CLI\Commands::class );
		}
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$scan_controller     = new ScanController( $this->scan_service );
		$results_controller  = new ResultsController();
		$actions_controller  = new ActionsController();
		$settings_controller = new SettingsController();

		$scan_controller->register_routes();
		$results_controller->register_routes();
		$actions_controller->register_routes();
		$settings_controller->register_routes();
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings(): array {
		$defaults = array(
			'image_size_threshold'    => 2097152,
			'video_size_threshold'    => 52428800,
			'audio_size_threshold'    => 10485760,
			'document_size_threshold' => 10485760,
			'hash_algorithm'          => 'sha256',
			'scan_batch_size'         => 200,
			'archive_folder_name'     => 'Archive',
			'extra_meta_keys'         => array(),
		);

		$settings = get_option( 'vmfa_media_cleanup_settings', array() );

		return wp_parse_args( $settings, $defaults );
	}

	public function get_scan_service(): ScanService {
		return $this->scan_service;
	}

	public function get_reference_index(): ReferenceIndex {
		return $this->reference_index;
	}

	public function get_hash_service(): HashService {
		return $this->hash_service;
	}
}
