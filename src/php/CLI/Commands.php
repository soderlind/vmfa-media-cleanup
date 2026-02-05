<?php
/**
 * WP-CLI commands for VMFA Media Cleanup.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\CLI;

use VmfaMediaCleanup\Plugin;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Manage media library cleanup operations.
 *
 * ## EXAMPLES
 *
 *     # Run a full scan
 *     wp vmfa-cleanup scan
 *
 *     # List unused media
 *     wp vmfa-cleanup list --type=unused
 *
 *     # Show scan statistics
 *     wp vmfa-cleanup stats
 *
 *     # Archive unused media
 *     wp vmfa-cleanup archive --type=unused --yes
 */
class Commands {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Run a media library scan.
	 *
	 * Scans the entire media library for unused, duplicate, and oversized items.
	 *
	 * ## OPTIONS
	 *
	 * [--async]
	 * : Run the scan asynchronously using Action Scheduler.
	 *
	 * [--batch-size=<number>]
	 * : Number of attachments to process per batch. Default 100.
	 *
	 * ## EXAMPLES
	 *
	 *     # Run a synchronous scan
	 *     wp vmfa-cleanup scan
	 *
	 *     # Run an async scan
	 *     wp vmfa-cleanup scan --async
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function scan( array $args, array $assoc_args ): void {
		$async = Utils\get_flag_value( $assoc_args, 'async', false );

		if ( $async ) {
			$this->plugin->scan_service()->start_scan();
			WP_CLI::success( 'Async scan started. Run `wp vmfa-cleanup stats` to check progress.' );
			return;
		}

		WP_CLI::log( 'Starting synchronous scan...' );

		// Phase 1: Build reference index.
		WP_CLI::log( 'Phase 1/3: Building reference index...' );
		$reference_index = $this->plugin->reference_index();
		$reference_index->clear_index();

		$total_posts = $this->count_content_posts();
		$batch_size  = (int) Utils\get_flag_value( $assoc_args, 'batch-size', 100 );
		$offset      = 0;

		$progress = Utils\make_progress_bar( 'Indexing content', $total_posts );

		while ( $offset < $total_posts ) {
			$reference_index->build_index_batch( $offset, $batch_size );
			$offset += $batch_size;
			$progress->tick( $batch_size );
		}

		$reference_index->build_global_references();
		$progress->finish();

		// Phase 2: Hash attachments.
		WP_CLI::log( 'Phase 2/3: Computing file hashes...' );
		$hash_service = $this->plugin->hash_service();
		$attachments  = $this->get_all_attachment_ids();
		$total        = count( $attachments );

		$progress = Utils\make_progress_bar( 'Hashing files', $total );

		foreach ( array_chunk( $attachments, $batch_size ) as $chunk ) {
			$hash_service->hash_batch( $chunk );
			$progress->tick( count( $chunk ) );
		}

		$progress->finish();

		// Phase 3: Run detectors.
		WP_CLI::log( 'Phase 3/3: Running detectors...' );
		$results = array(
			'unused'    => array(),
			'duplicate' => array(),
			'oversized' => array(),
		);

		foreach ( $this->plugin->detectors() as $detector ) {
			$type             = $detector->get_type();
			$label            = $detector->get_label();
			$detected         = $detector->detect( $attachments );
			$results[ $type ] = $detected;

			WP_CLI::log( sprintf( '  %s: %d items found', $label, count( $detected ) ) );
		}

		// Store results.
		update_option( 'vmfa_cleanup_results', $results, false );

		$total_issues = array_sum( array_map( 'count', $results ) );
		WP_CLI::success( sprintf( 'Scan complete. %d issues found across %d attachments.', $total_issues, $total ) );
	}

	/**
	 * List detected media issues.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Filter by issue type: unused, duplicate, oversized, flagged. Default: all.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts: table, csv, json, yaml, count. Default: table.
	 *
	 * [--fields=<fields>]
	 * : Fields to display. Default varies by type.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vmfa-cleanup list
	 *     wp vmfa-cleanup list --type=unused --format=csv
	 *     wp vmfa-cleanup list --type=oversized --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function list_( array $args, array $assoc_args ): void {
		$type    = Utils\get_flag_value( $assoc_args, 'type', 'all' );
		$format  = Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$results = get_option( 'vmfa_cleanup_results', array() );

		if ( empty( $results ) ) {
			WP_CLI::warning( 'No scan results found. Run `wp vmfa-cleanup scan` first.' );
			return;
		}

		$items = array();

		if ( 'all' === $type || 'flagged' === $type ) {
			$flagged_query = new \WP_Query(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'meta_key'       => '_vmfa_flagged_for_review',
					'fields'         => 'ids',
				)
			);

			foreach ( $flagged_query->posts as $post_id ) {
				$items[] = array(
					'id'    => $post_id,
					'title' => get_the_title( $post_id ),
					'type'  => 'flagged',
					'info'  => get_post_meta( $post_id, '_vmfa_flagged_for_review', true ),
				);
			}
		}

		$types_to_show = 'all' === $type
			? array( 'unused', 'duplicate', 'oversized' )
			: array( $type );

		foreach ( $types_to_show as $t ) {
			if ( 'flagged' === $t || ! isset( $results[ $t ] ) ) {
				continue;
			}

			foreach ( $results[ $t ] as $item ) {
				$id      = $item[ 'id' ] ?? $item[ 'attachment_id' ] ?? 0;
				$items[] = array(
					'id'    => $id,
					'title' => get_the_title( $id ),
					'type'  => $t,
					'info'  => $this->format_item_info( $t, $item ),
				);
			}
		}

		if ( empty( $items ) ) {
			WP_CLI::log( 'No issues found.' );
			return;
		}

		$fields = Utils\get_flag_value( $assoc_args, 'fields', 'id,title,type,info' );

		Utils\format_items( $format, $items, $fields );
	}

	/**
	 * Show scan statistics.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Accepts: table, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vmfa-cleanup stats
	 *     wp vmfa-cleanup stats --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function stats( array $args, array $assoc_args ): void {
		$format   = Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$progress = get_option( 'vmfa_cleanup_scan_progress', array() );
		$results  = get_option( 'vmfa_cleanup_results', array() );

		$stats = array(
			array(
				'metric' => 'Status',
				'value'  => $progress[ 'status' ] ?? 'idle',
			),
			array(
				'metric' => 'Phase',
				'value'  => $progress[ 'phase' ] ?? 'none',
			),
			array(
				'metric' => 'Progress',
				'value'  => isset( $progress[ 'processed' ], $progress[ 'total' ] )
					? sprintf( '%d / %d', $progress[ 'processed' ], $progress[ 'total' ] )
					: 'N/A',
			),
			array(
				'metric' => 'Unused',
				'value'  => count( $results[ 'unused' ] ?? array() ),
			),
			array(
				'metric' => 'Duplicates',
				'value'  => count( $results[ 'duplicate' ] ?? array() ),
			),
			array(
				'metric' => 'Oversized',
				'value'  => count( $results[ 'oversized' ] ?? array() ),
			),
			array(
				'metric' => 'Last scan',
				'value'  => $progress[ 'completed_at' ] ?? 'never',
			),
		);

		Utils\format_items( $format, $stats, 'metric,value' );
	}

	/**
	 * Archive media items to the Archive virtual folder.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Issue type to archive: unused, duplicate, oversized. Required.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated list of specific attachment IDs.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vmfa-cleanup archive --type=unused --yes
	 *     wp vmfa-cleanup archive --ids=42,56,78 --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function archive( array $args, array $assoc_args ): void {
		$ids = $this->resolve_ids( $assoc_args );

		if ( empty( $ids ) ) {
			WP_CLI::warning( 'No matching media items found.' );
			return;
		}

		WP_CLI::confirm(
			sprintf( 'Archive %d media item(s) to the Archive folder?', count( $ids ) ),
			$assoc_args
		);

		$folder_id = $this->get_or_create_archive_folder();

		do_action( 'vmfa_cleanup_before_bulk_action', 'archive', $ids );

		$progress = Utils\make_progress_bar( 'Archiving', count( $ids ) );
		$success  = 0;

		foreach ( $ids as $id ) {
			$result = wp_set_object_terms( $id, $folder_id, 'vmfo_folder' );

			if ( ! is_wp_error( $result ) ) {
				++$success;
				do_action( 'vmfa_cleanup_media_archived', $id, $folder_id );
			}

			$progress->tick();
		}

		$progress->finish();
		WP_CLI::success( sprintf( 'Archived %d of %d items.', $success, count( $ids ) ) );
	}

	/**
	 * Trash media items.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Issue type to trash: unused, duplicate, oversized. Required.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated list of specific attachment IDs.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vmfa-cleanup trash --type=unused --yes
	 *     wp vmfa-cleanup trash --ids=42,56 --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function trash( array $args, array $assoc_args ): void {
		$ids = $this->resolve_ids( $assoc_args );

		if ( empty( $ids ) ) {
			WP_CLI::warning( 'No matching media items found.' );
			return;
		}

		WP_CLI::confirm(
			sprintf( 'Move %d media item(s) to trash?', count( $ids ) ),
			$assoc_args
		);

		do_action( 'vmfa_cleanup_before_bulk_action', 'trash', $ids );

		$progress = Utils\make_progress_bar( 'Trashing', count( $ids ) );
		$success  = 0;

		foreach ( $ids as $id ) {
			if ( wp_trash_post( $id ) ) {
				++$success;
				do_action( 'vmfa_cleanup_media_trashed', $id );
			}

			$progress->tick();
		}

		$progress->finish();
		WP_CLI::success( sprintf( 'Trashed %d of %d items.', $success, count( $ids ) ) );
	}

	/**
	 * Flag media items for review.
	 *
	 * ## OPTIONS
	 *
	 * <ids>...
	 * : One or more attachment IDs to flag.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vmfa-cleanup flag 42 56 78
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function flag( array $args, array $assoc_args ): void {
		$ids       = array_map( 'absint', $args );
		$timestamp = current_time( 'mysql', true );

		foreach ( $ids as $id ) {
			update_post_meta( $id, '_vmfa_flagged_for_review', $timestamp );
			do_action( 'vmfa_cleanup_media_flagged', $id );
		}

		WP_CLI::success( sprintf( 'Flagged %d item(s) for review.', count( $ids ) ) );
	}

	/**
	 * Remove review flag from media items.
	 *
	 * ## OPTIONS
	 *
	 * <ids>...
	 * : One or more attachment IDs to unflag.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vmfa-cleanup unflag 42 56 78
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function unflag( array $args, array $assoc_args ): void {
		$ids = array_map( 'absint', $args );

		foreach ( $ids as $id ) {
			delete_post_meta( $id, '_vmfa_flagged_for_review' );
		}

		WP_CLI::success( sprintf( 'Unflagged %d item(s).', count( $ids ) ) );
	}

	/**
	 * List duplicate groups.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Accepts: table, csv, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vmfa-cleanup duplicates
	 *     wp vmfa-cleanup duplicates --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function duplicates( array $args, array $assoc_args ): void {
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$groups = $this->plugin->detectors()[ 'duplicate' ]->get_groups();

		if ( empty( $groups ) ) {
			WP_CLI::log( 'No duplicate groups found. Run `wp vmfa-cleanup scan` first.' );
			return;
		}

		$items = array();
		foreach ( $groups as $hash => $group ) {
			$primary = $group[ 'primary' ] ?? $group[ 'members' ][ 0 ][ 'id' ];

			foreach ( $group[ 'members' ] as $member ) {
				$items[] = array(
					'group_hash' => substr( $hash, 0, 12 ),
					'id'         => $member[ 'id' ],
					'title'      => get_the_title( $member[ 'id' ] ),
					'is_primary' => $member[ 'id' ] === $primary ? 'yes' : 'no',
					'file_size'  => size_format( $member[ 'file_size' ] ?? 0 ),
				);
			}
		}

		Utils\format_items( $format, $items, 'group_hash,id,title,is_primary,file_size' );
	}

	/**
	 * Recompute file hashes for all or specific attachments.
	 *
	 * ## OPTIONS
	 *
	 * [--ids=<ids>]
	 * : Comma-separated list of attachment IDs. Default: all.
	 *
	 * [--batch-size=<number>]
	 * : Number of attachments per batch. Default: 100.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vmfa-cleanup rehash
	 *     wp vmfa-cleanup rehash --ids=42,56
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function rehash( array $args, array $assoc_args ): void {
		$ids_str = Utils\get_flag_value( $assoc_args, 'ids', '' );
		$ids     = ! empty( $ids_str )
			? array_map( 'absint', explode( ',', $ids_str ) )
			: $this->get_all_attachment_ids();

		$batch_size   = (int) Utils\get_flag_value( $assoc_args, 'batch-size', 100 );
		$hash_service = $this->plugin->hash_service();

		// Clear existing hashes.
		foreach ( $ids as $id ) {
			delete_post_meta( $id, '_vmfa_file_hash' );
			delete_post_meta( $id, '_vmfa_hash_algo' );
		}

		$progress = Utils\make_progress_bar( 'Computing hashes', count( $ids ) );

		foreach ( array_chunk( $ids, $batch_size ) as $chunk ) {
			$hash_service->hash_batch( $chunk );
			$progress->tick( count( $chunk ) );
		}

		$progress->finish();
		WP_CLI::success( sprintf( 'Rehashed %d attachment(s).', count( $ids ) ) );
	}

	/**
	 * Resolve attachment IDs from command arguments.
	 *
	 * @param array $assoc_args Named arguments.
	 * @return int[]
	 */
	private function resolve_ids( array $assoc_args ): array {
		$ids_str = Utils\get_flag_value( $assoc_args, 'ids', '' );

		if ( ! empty( $ids_str ) ) {
			return array_map( 'absint', explode( ',', $ids_str ) );
		}

		$type    = Utils\get_flag_value( $assoc_args, 'type', '' );
		$results = get_option( 'vmfa_cleanup_results', array() );

		if ( empty( $type ) || ! isset( $results[ $type ] ) ) {
			WP_CLI::error( 'Specify --type or --ids.' );
			return array();
		}

		return array_map(
			function ( $item ) {
				return (int) ( $item[ 'id' ] ?? $item[ 'attachment_id' ] ?? 0 );
			},
			$results[ $type ]
		);
	}

	/**
	 * Get all attachment IDs.
	 *
	 * @return int[]
	 */
	private function get_all_attachment_ids(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return array_map( 'intval', $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
		) );
	}

	/**
	 * Count content posts for indexing.
	 *
	 * @return int
	 */
	private function count_content_posts(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type NOT IN ('attachment', 'revision') AND post_status IN ('publish', 'draft', 'private')"
		);
	}

	/**
	 * Get or create the Archive folder.
	 *
	 * @return int The folder term ID.
	 */
	private function get_or_create_archive_folder(): int {
		$settings    = get_option( 'vmfa_media_cleanup_settings', array() );
		$folder_name = $settings[ 'archive_folder_name' ] ?? 'Archive';

		/** This filter is documented in src/php/REST/ActionsController.php */
		$folder_name = apply_filters( 'vmfa_cleanup_archive_folder_name', $folder_name );

		$existing = get_term_by( 'name', $folder_name, 'vmfo_folder' );

		if ( $existing ) {
			return $existing->term_id;
		}

		$result = wp_insert_term( $folder_name, 'vmfo_folder', array( 'parent' => 0 ) );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( 'Could not create Archive folder: ' . $result->get_error_message() );
		}

		return $result[ 'term_id' ];
	}

	/**
	 * Format item info for display.
	 *
	 * @param string $type Issue type.
	 * @param array  $item Issue data.
	 * @return string
	 */
	private function format_item_info( string $type, array $item ): string {
		return match ( $type ) {
			'unused'    => 'No references found',
			'duplicate' => sprintf( 'Hash: %s', substr( $item[ 'hash' ] ?? 'unknown', 0, 12 ) ),
			'oversized' => sprintf( 'Size: %s (over by %s)', size_format( $item[ 'file_size' ] ?? 0 ), size_format( $item[ 'over_by' ] ?? 0 ) ),
			default     => '',
		};
	}
}
