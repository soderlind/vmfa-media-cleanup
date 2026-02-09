<?php
/**
 * Scan service.
 *
 * Orchestrates the media library scan using Action Scheduler
 * for async batch processing.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\Services;

defined( 'ABSPATH' ) || exit;

use VmfaMediaCleanup\Detectors\UnusedDetector;
use VmfaMediaCleanup\Detectors\DuplicateDetector;
use VmfaMediaCleanup\Plugin;

/**
 * Orchestrates media library scanning via Action Scheduler.
 */
class ScanService {

	/**
	 * Action Scheduler hook names.
	 */
	private const ACTION_BUILD_INDEX   = 'vmfa_cleanup_build_index_batch';
	private const ACTION_HASH_BATCH    = 'vmfa_cleanup_hash_batch';
	private const ACTION_RUN_DETECTORS = 'vmfa_cleanup_run_detectors';
	private const ACTION_FINALIZE      = 'vmfa_cleanup_finalize_scan';

	/**
	 * Option name for scan progress.
	 */
	private const PROGRESS_OPTION = 'vmfa_cleanup_scan_progress';

	/**
	 * Option name for scan results.
	 */
	private const RESULTS_OPTION = 'vmfa_cleanup_results';

	/**
	 * Reference index service.
	 *
	 * @var ReferenceIndex
	 */
	private ReferenceIndex $reference_index;

	/**
	 * Hash service.
	 *
	 * @var HashService
	 */
	private HashService $hash_service;

	/**
	 * Unused detector.
	 *
	 * @var UnusedDetector
	 */
	private UnusedDetector $unused_detector;

	/**
	 * Duplicate detector.
	 *
	 * @var DuplicateDetector
	 */
	private DuplicateDetector $duplicate_detector;

	/**
	 * Constructor.
	 *
	 * @param ReferenceIndex    $reference_index    Reference index service.
	 * @param HashService       $hash_service       Hash service.
	 * @param UnusedDetector    $unused_detector    Unused detector.
	 * @param DuplicateDetector $duplicate_detector Duplicate detector.
	 */
	public function __construct(
		ReferenceIndex $reference_index,
		HashService $hash_service,
		UnusedDetector $unused_detector,
		DuplicateDetector $duplicate_detector,
	) {
		$this->reference_index    = $reference_index;
		$this->hash_service       = $hash_service;
		$this->unused_detector    = $unused_detector;
		$this->duplicate_detector = $duplicate_detector;
	}

	/**
	 * Register Action Scheduler hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::ACTION_BUILD_INDEX, array( $this, 'handle_build_index_batch' ), 10, 1 );
		add_action( self::ACTION_HASH_BATCH, array( $this, 'handle_hash_batch' ), 10, 1 );
		add_action( self::ACTION_RUN_DETECTORS, array( $this, 'handle_run_detectors' ), 10, 1 );
		add_action( self::ACTION_FINALIZE, array( $this, 'handle_finalize_scan' ) );
	}

	/**
	 * Start a full scan.
	 *
	 * @param string[] $types Detector types to run. Empty array = all.
	 * @return bool True if scan was started.
	 */
	public function start_scan( array $types = array() ): bool {
		if ( ! Plugin::maybe_load_action_scheduler() ) {
			return false;
		}

		$progress = $this->get_progress();

		if ( 'running' === $progress[ 'status' ] ) {
			return false; // Already running.
		}

		// Clear previous results.
		delete_option( self::RESULTS_OPTION );

		// Determine total work.
		$total_posts       = $this->reference_index->get_total_posts();
		$total_attachments = $this->get_total_attachments();
		$batch_size        = $this->get_batch_size();

		// Update progress.
		$this->update_progress(
			array(
				'status'       => 'running',
				'phase'        => 'indexing',
				'total'        => $total_posts + $total_attachments,
				'processed'    => 0,
				'started_at'   => current_time( 'mysql', true ),
				'completed_at' => null,
				'types'        => empty( $types ) ? array( 'unused', 'duplicate' ) : $types,
			)
		);

		// Clear the reference index before rebuilding.
		$this->reference_index->clear();

		// Build global references first (synchronous, fast).
		$this->reference_index->build_global_references();

		// Schedule the first batch for index building.
		as_schedule_single_action(
			time(),
			self::ACTION_BUILD_INDEX,
			array( array( 'offset' => 0, 'batch_size' => $batch_size ) ),
			'vmfa-media-cleanup'
		);

		return true;
	}

	/**
	 * Handle a build-index batch via Action Scheduler.
	 *
	 * @param array $args Batch arguments (offset, batch_size).
	 * @return void
	 */
	public function handle_build_index_batch( array $args ): void {
		$offset     = $args[ 'offset' ] ?? 0;
		$batch_size = $args[ 'batch_size' ] ?? $this->get_batch_size();

		$processed = $this->reference_index->build_index_batch( $offset, $batch_size );

		// Update progress.
		$progress               = $this->get_progress();
		$progress[ 'processed' ] += $processed;
		$this->update_progress( $progress );

		if ( $processed >= $batch_size ) {
			// More posts to process — schedule next batch.
			as_schedule_single_action(
				time(),
				self::ACTION_BUILD_INDEX,
				array( array( 'offset' => $offset + $batch_size, 'batch_size' => $batch_size ) ),
				'vmfa-media-cleanup'
			);
		} else {
			// Indexing complete — move to hashing phase.
			$progress[ 'phase' ] = 'hashing';
			$this->update_progress( $progress );

			as_schedule_single_action(
				time(),
				self::ACTION_HASH_BATCH,
				array( array( 'offset' => 0, 'batch_size' => $batch_size ) ),
				'vmfa-media-cleanup'
			);
		}
	}

	/**
	 * Handle a hash-computation batch via Action Scheduler.
	 *
	 * @param array $args Batch arguments (offset, batch_size).
	 * @return void
	 */
	public function handle_hash_batch( array $args ): void {
		$offset     = $args[ 'offset' ] ?? 0;
		$batch_size = $args[ 'batch_size' ] ?? $this->get_batch_size();
		$progress   = $this->get_progress();
		$types      = $progress[ 'types' ] ?? array( 'unused', 'duplicate' );

		// Only hash if duplicate detection is requested.
		if ( ! in_array( 'duplicate', $types, true ) ) {
			// Skip hashing, go directly to detectors.
			$progress[ 'phase' ] = 'detecting';
			$this->update_progress( $progress );

			as_schedule_single_action(
				time(),
				self::ACTION_RUN_DETECTORS,
				array( array( 'offset' => 0, 'batch_size' => $batch_size ) ),
				'vmfa-media-cleanup'
			);
			return;
		}

		$attachment_ids = $this->get_attachment_ids_batch( $offset, $batch_size );

		if ( empty( $attachment_ids ) ) {
			// Hashing complete — move to detection phase.
			$progress[ 'phase' ] = 'detecting';
			$this->update_progress( $progress );

			as_schedule_single_action(
				time(),
				self::ACTION_RUN_DETECTORS,
				array( array( 'offset' => 0, 'batch_size' => $batch_size ) ),
				'vmfa-media-cleanup'
			);
			return;
		}

		$this->hash_service->hash_batch( $attachment_ids );

		$progress[ 'processed' ] += count( $attachment_ids );
		$this->update_progress( $progress );

		// Schedule next hash batch.
		as_schedule_single_action(
			time(),
			self::ACTION_HASH_BATCH,
			array( array( 'offset' => $offset + $batch_size, 'batch_size' => $batch_size ) ),
			'vmfa-media-cleanup'
		);
	}

	/**
	 * Handle running detectors via Action Scheduler.
	 *
	 * @param array $args Batch arguments (offset, batch_size).
	 * @return void
	 */
	public function handle_run_detectors( array $args ): void {
		$offset     = $args[ 'offset' ] ?? 0;
		$batch_size = $args[ 'batch_size' ] ?? $this->get_batch_size();

		$attachment_ids = $this->get_attachment_ids_batch( $offset, $batch_size );

		if ( empty( $attachment_ids ) ) {
			// Detection complete — finalize.
			as_schedule_single_action(
				time(),
				self::ACTION_FINALIZE,
				array(),
				'vmfa-media-cleanup'
			);
			return;
		}

		$progress = $this->get_progress();
		$types    = $progress[ 'types' ] ?? array( 'unused', 'duplicate' );
		$results  = get_option( self::RESULTS_OPTION, array() );

		// Run applicable detectors.
		if ( in_array( 'unused', $types, true ) ) {
			$unused_results = $this->unused_detector->detect( $attachment_ids );
			if ( ! isset( $results[ 'unused' ] ) ) {
				$results[ 'unused' ] = array();
			}
			$results[ 'unused' ] = array_replace( $results[ 'unused' ], $unused_results );
		}

		if ( in_array( 'duplicate', $types, true ) ) {
			$duplicate_results = $this->duplicate_detector->detect( $attachment_ids );
			if ( ! isset( $results[ 'duplicate' ] ) ) {
				$results[ 'duplicate' ] = array();
			}
			$results[ 'duplicate' ] = array_replace( $results[ 'duplicate' ], $duplicate_results );
		}

		update_option( self::RESULTS_OPTION, $results, false );

		$progress[ 'processed' ] += count( $attachment_ids );
		$this->update_progress( $progress );

		// Schedule next detection batch.
		as_schedule_single_action(
			time(),
			self::ACTION_RUN_DETECTORS,
			array( array( 'offset' => $offset + $batch_size, 'batch_size' => $batch_size ) ),
			'vmfa-media-cleanup'
		);
	}

	/**
	 * Finalize the scan.
	 *
	 * @return void
	 */
	public function handle_finalize_scan(): void {
		$progress = $this->get_progress();
		$results  = get_option( self::RESULTS_OPTION, array() );

		$progress[ 'status' ]       = 'complete';
		$progress[ 'phase' ]        = 'done';
		$progress[ 'completed_at' ] = current_time( 'mysql', true );

		$this->update_progress( $progress );

		/**
		 * Fires when a full scan completes.
		 *
		 * @param array $results The complete scan results grouped by type.
		 */
		do_action( 'vmfa_cleanup_scan_complete', $results );
	}

	/**
	 * Cancel a running scan.
	 *
	 * @return bool True if cancelled.
	 */
	public function cancel_scan(): bool {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return false;
		}

		as_unschedule_all_actions( self::ACTION_BUILD_INDEX );
		as_unschedule_all_actions( self::ACTION_HASH_BATCH );
		as_unschedule_all_actions( self::ACTION_RUN_DETECTORS );
		as_unschedule_all_actions( self::ACTION_FINALIZE );

		$this->update_progress(
			array(
				'status'       => 'cancelled',
				'phase'        => '',
				'total'        => 0,
				'processed'    => 0,
				'started_at'   => null,
				'completed_at' => null,
			)
		);

		return true;
	}

	/**
	 * Reset scan (clear results and progress).
	 *
	 * @return void
	 */
	public function reset_scan(): void {
		$this->cancel_scan();

		delete_option( self::RESULTS_OPTION );
		$this->update_progress(
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

	/**
	 * Get scan progress.
	 *
	 * @return array<string, mixed>
	 */
	public function get_progress(): array {
		$defaults = array(
			'status'       => 'idle',
			'phase'        => '',
			'total'        => 0,
			'processed'    => 0,
			'started_at'   => null,
			'completed_at' => null,
			'types'        => array(),
		);

		return wp_parse_args( get_option( self::PROGRESS_OPTION, array() ), $defaults );
	}

	/**
	 * Get scan results.
	 *
	 * @param string $type Optional. Filter by detector type.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_results( string $type = '' ): array {
		$results = get_option( self::RESULTS_OPTION, array() );

		if ( ! empty( $type ) && isset( $results[ $type ] ) ) {
			return array( $type => $results[ $type ] );
		}

		return $results;
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @return array<string, int>
	 */
	public function get_stats(): array {
		$results = get_option( self::RESULTS_OPTION, array() );

		// Count flagged attachments.
		global $wpdb;
		$flagged_count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_vmfa_flagged_for_review'"
		);

		// Count duplicate groups (unique hashes).
		$duplicate_groups = 0;
		if ( ! empty( $results[ 'duplicate' ] ) ) {
			$hashes           = array_unique( array_column( $results[ 'duplicate' ], 'hash' ) );
			$duplicate_groups = count( $hashes );
		}

		return array(
			'total_media'      => (int) wp_count_posts( 'attachment' )->inherit,
			'unused_count'     => count( $results[ 'unused' ] ?? array() ),
			'duplicate_count'  => count( $results[ 'duplicate' ] ?? array() ),
			'duplicate_groups' => $duplicate_groups,
			'flagged_count'    => $flagged_count,
		);
	}

	/**
	 * Update scan progress.
	 *
	 * @param array<string, mixed> $progress Progress data.
	 * @return void
	 */
	private function update_progress( array $progress ): void {
		update_option( self::PROGRESS_OPTION, $progress, false );
	}

	/**
	 * Get the configured batch size.
	 *
	 * @return int
	 */
	private function get_batch_size(): int {
		$settings   = get_option( 'vmfa_media_cleanup_settings', array() );
		$batch_size = $settings[ 'scan_batch_size' ] ?? 200;

		/**
		 * Filter the scan batch size.
		 *
		 * @param int $batch_size The number of items to process per batch.
		 */
		return (int) apply_filters( 'vmfa_cleanup_scan_batch_size', $batch_size );
	}

	/**
	 * Get total number of media attachments.
	 *
	 * @return int
	 */
	private function get_total_attachments(): int {
		return (int) wp_count_posts( 'attachment' )->inherit;
	}

	/**
	 * Get a batch of attachment IDs.
	 *
	 * @param int $offset     Offset for the query.
	 * @param int $batch_size Number of items to retrieve.
	 * @return int[] Array of attachment IDs.
	 */
	private function get_attachment_ids_batch( int $offset, int $batch_size ): array {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return array_map( 'intval', $attachments );
	}
}
