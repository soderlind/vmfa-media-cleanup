<?php
/**
 * Main plugin class.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup;

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
final class Plugin {

	/**
	 * Tab slug for registration with parent plugin.
	 */
	private const TAB_SLUG = 'media-cleanup';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Reference index service.
	 *
	 * @var ReferenceIndex|null
	 */
	private ?ReferenceIndex $reference_index = null;

	/**
	 * Hash service.
	 *
	 * @var HashService|null
	 */
	private ?HashService $hash_service = null;

	/**
	 * Scan service.
	 *
	 * @var ScanService|null
	 */
	private ?ScanService $scan_service = null;

	/**
	 * Unused detector.
	 *
	 * @var UnusedDetector|null
	 */
	private ?UnusedDetector $unused_detector = null;

	/**
	 * Duplicate detector.
	 *
	 * @var DuplicateDetector|null
	 */
	private ?DuplicateDetector $duplicate_detector = null;

	/**
	 * Oversized detector.
	 *
	 * @var OversizedDetector|null
	 */
	private ?OversizedDetector $oversized_detector = null;

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_services();
		$this->init_hooks();
		$this->init_cli();

		// Load textdomain on init hook when locale is set.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Ensure Action Scheduler is loaded.
	 *
	 * @return bool True if Action Scheduler scheduling functions are available.
	 */
	public static function maybe_load_action_scheduler(): bool {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			return true;
		}

		if ( ! defined( 'VMFA_MEDIA_CLEANUP_PATH' ) ) {
			return false;
		}

		if ( ! function_exists( 'add_action' ) ) {
			return false;
		}

		$paths = array(
			VMFA_MEDIA_CLEANUP_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php',
			VMFA_MEDIA_CLEANUP_PATH . 'woocommerce/action-scheduler/action-scheduler.php',
		);

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				break;
			}
		}

		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'vmfa-media-cleanup',
			false,
			dirname( plugin_basename( VMFA_MEDIA_CLEANUP_FILE ) ) . '/languages'
		);
	}

	/**
	 * Initialize services.
	 *
	 * @return void
	 */
	private function init_services(): void {
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
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		// Admin hooks.
		if ( is_admin() ) {
			if ( $this->supports_parent_tabs() ) {
				// Register as a tab in the parent plugin.
				add_filter( 'vmfo_settings_tabs', array( $this, 'register_tab' ) );
				add_action( 'vmfo_settings_enqueue_scripts', array( $this, 'enqueue_tab_scripts' ), 10, 2 );
			} else {
				// Fall back to standalone menu.
				add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			}
		}

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Action Scheduler hooks.
		$this->scan_service->register_hooks();
	}

	/**
	 * Initialize WP-CLI commands.
	 *
	 * @return void
	 */
	private function init_cli(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'vmfa-cleanup', CLI\Commands::class);
		}
	}

	/**
	 * Check if the parent plugin supports add-on tabs.
	 *
	 * @return bool True if parent supports tabs, false otherwise.
	 */
	private function supports_parent_tabs(): bool {
		return defined( 'VirtualMediaFolders\Settings::SUPPORTS_ADDON_TABS' )
			&& \VirtualMediaFolders\Settings::SUPPORTS_ADDON_TABS;
	}

	/**
	 * Register tab with parent plugin.
	 *
	 * @param array $tabs Existing tabs array.
	 * @return array Modified tabs array.
	 */
	public function register_tab( array $tabs ): array {
		$tabs[ self::TAB_SLUG ] = array(
			'title'    => __( 'Media Cleanup', 'vmfa-media-cleanup' ),
			'callback' => array( $this, 'render_tab_content' ),
		);
		return $tabs;
	}

	/**
	 * Render tab content within parent plugin's settings page.
	 *
	 * @param string $active_tab    The currently active tab slug.
	 * @param string $active_subtab The currently active subtab slug.
	 * @return void
	 */
	public function render_tab_content( string $active_tab, string $active_subtab ): void {
		?>
		<div id="vmfa-media-cleanup-app"></div>
		<?php
	}

	/**
	 * Enqueue scripts when Media Cleanup tab is active.
	 *
	 * @param string $active_tab    The currently active tab slug.
	 * @param string $active_subtab The currently active subtab slug.
	 * @return void
	 */
	public function enqueue_tab_scripts( string $active_tab, string $active_subtab ): void {
		if ( self::TAB_SLUG !== $active_tab ) {
			return;
		}

		$this->do_enqueue_assets();
	}

	/**
	 * Register admin menu (fallback when parent doesn't support tabs).
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_submenu_page(
			'upload.php',
			__( 'Virtual Media Folders Media Cleanup', 'vmfa-media-cleanup' ),
			__( 'Media Cleanup', 'vmfa-media-cleanup' ),
			'manage_options',
			'vmfa-media-cleanup',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page (fallback for standalone page).
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Virtual Media Folders Media Cleanup', 'vmfa-media-cleanup' ); ?></h1>
			<div id="vmfa-media-cleanup-app"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets (fallback for standalone page).
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'media_page_vmfa-media-cleanup' !== $hook_suffix ) {
			return;
		}

		$this->do_enqueue_assets();
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @return void
	 */
	private function do_enqueue_assets(): void {
		$asset_file = VMFA_MEDIA_CLEANUP_PATH . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'vmfa-media-cleanup-admin',
			VMFA_MEDIA_CLEANUP_URL . 'build/index.js',
			$asset[ 'dependencies' ],
			$asset[ 'version' ],
			true
		);

		wp_set_script_translations(
			'vmfa-media-cleanup-admin',
			'vmfa-media-cleanup',
			VMFA_MEDIA_CLEANUP_PATH . 'languages'
		);

		wp_enqueue_style(
			'vmfa-media-cleanup-admin',
			VMFA_MEDIA_CLEANUP_URL . 'build/index.css',
			array( 'wp-components' ),
			$asset[ 'version' ]
		);

		wp_localize_script(
			'vmfa-media-cleanup-admin',
			'vmfaMediaCleanup',
			array(
				'restUrl'  => rest_url( 'vmfa-cleanup/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'settings' => $this->get_settings(),
				'folders'  => $this->get_folders(),
				'adminUrl' => admin_url(),
			)
		);
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

	/**
	 * Get folders from parent plugin.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_folders(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'vmfo_folder',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$folders = array();
		foreach ( $terms as $term ) {
			$folders[] = array(
				'id'     => $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'parent' => $term->parent,
			);
		}

		return $folders;
	}

	/**
	 * Get scan service instance.
	 *
	 * @return ScanService
	 */
	public function get_scan_service(): ScanService {
		return $this->scan_service;
	}

	/**
	 * Get reference index instance.
	 *
	 * @return ReferenceIndex
	 */
	public function get_reference_index(): ReferenceIndex {
		return $this->reference_index;
	}

	/**
	 * Get hash service instance.
	 *
	 * @return HashService
	 */
	public function get_hash_service(): HashService {
		return $this->hash_service;
	}
}
