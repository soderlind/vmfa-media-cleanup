<?php
/**
 * Duplicate media detector.
 *
 * Groups media by file hash to identify duplicate files.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\Detectors;

defined( 'ABSPATH' ) || exit;

use VmfaMediaCleanup\Services\HashService;

/**
 * Detects duplicate media files using content hashing.
 */
class DuplicateDetector implements DetectorInterface {

	/**
	 * Hash service.
	 *
	 * @var HashService
	 */
	private HashService $hash_service;

	/**
	 * Constructor.
	 *
	 * @param HashService $hash_service Hash service instance.
	 */
	public function __construct( HashService $hash_service ) {
		$this->hash_service = $hash_service;
	}

	/**
	 * Get the detector type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'duplicate';
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Duplicate', 'vmfa-media-cleanup' );
	}

	/**
	 * Detect duplicates among the given attachments.
	 *
	 * Returns results for all attachment IDs that have at least one duplicate.
	 * Each result includes the group hash and whether it's the primary (original).
	 *
	 * @param int[] $attachment_ids Array of attachment IDs to check.
	 * @return array<int, array<string, mixed>> Results keyed by attachment ID.
	 */
	public function detect( array $attachment_ids ): array {
		// Build a hash → attachment IDs map.
		$hash_map = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment || 'trash' === $attachment->post_status ) {
				continue;
			}

			$hash = $this->hash_service->get_hash( $attachment_id );
			if ( empty( $hash ) ) {
				continue;
			}

			if ( ! isset( $hash_map[ $hash ] ) ) {
				$hash_map[ $hash ] = array();
			}

			$hash_map[ $hash ][] = $attachment_id;
		}

		// Filter to groups with more than one file (actual duplicates).
		$results = array();

		foreach ( $hash_map as $hash => $group_ids ) {
			if ( count( $group_ids ) < 2 ) {
				continue;
			}

			// Determine primary: oldest upload date.
			$primary_id = $this->determine_primary( $group_ids );

			foreach ( $group_ids as $attachment_id ) {
				$attachment = get_post( $attachment_id );
				$file_path  = get_attached_file( $attachment_id );
				$metadata   = wp_get_attachment_metadata( $attachment_id );

				$results[ $attachment_id ] = array(
					'type'          => $this->get_type(),
					'attachment_id' => $attachment_id,
					'title'         => get_the_title( $attachment_id ),
					'filename'      => $file_path ? basename( $file_path ) : '',
					'mime_type'     => get_post_mime_type( $attachment_id ),
					'file_size'     => $file_path && file_exists( $file_path ) ? wp_filesize( $file_path ) : 0,
					'upload_date'   => $attachment->post_date,
					'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: '',
					'width'         => $metadata[ 'width' ] ?? 0,
					'height'        => $metadata[ 'height' ] ?? 0,
					'hash'          => $hash,
					'is_primary'    => $attachment_id === $primary_id,
					'group_ids'     => $group_ids,
					'group_count'   => count( $group_ids ),
				);
			}
		}

		return $results;
	}

	/**
	 * Get duplicate groups from the full set of scanned results.
	 *
	 * @param array<int, array<string, mixed>> $results All duplicate detection results.
	 * @return array<string, array<string, mixed>> Groups keyed by hash.
	 */
	public function get_groups( array $results ): array {
		$groups = array();

		foreach ( $results as $result ) {
			$hash = $result[ 'hash' ];

			if ( ! isset( $groups[ $hash ] ) ) {
				$groups[ $hash ] = array(
					'hash'    => $hash,
					'count'   => $result[ 'group_count' ],
					'members' => array(),
				);
			}

			$groups[ $hash ][ 'members' ][] = $result;
		}

		return array_values( $groups );
	}

	/**
	 * Determine the primary (original) attachment in a duplicate group.
	 *
	 * The primary is the attachment with the earliest upload date.
	 * If a user has explicitly assigned a primary via meta, that takes precedence.
	 *
	 * @param int[] $group_ids Attachment IDs in the duplicate group.
	 * @return int The primary attachment ID.
	 */
	private function determine_primary( array $group_ids ): int {
		// Check for user-assigned primary.
		foreach ( $group_ids as $id ) {
			$is_primary = get_post_meta( $id, '_vmfa_duplicate_primary', true );
			if ( $is_primary ) {
				return $id;
			}
		}

		// Default: oldest upload date.
		$oldest_id   = $group_ids[ 0 ];
		$oldest_date = get_post( $group_ids[ 0 ] )->post_date;

		foreach ( $group_ids as $id ) {
			$post = get_post( $id );
			if ( $post && $post->post_date < $oldest_date ) {
				$oldest_date = $post->post_date;
				$oldest_id   = $id;
			}
		}

		return $oldest_id;
	}
}
