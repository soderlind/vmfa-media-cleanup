<?php
/**
 * Hash service.
 *
 * Computes and caches file content hashes for duplicate detection.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\Services;

defined( 'ABSPATH' ) || exit;

/**
 * File hash computation and caching service.
 */
class HashService {

	/**
	 * Meta key for storing the file hash.
	 */
	private const HASH_META_KEY = '_vmfa_file_hash';

	/**
	 * Meta key for storing the hash algorithm used.
	 */
	private const ALGO_META_KEY = '_vmfa_hash_algo';

	/**
	 * Get or compute the hash for an attachment file.
	 *
	 * Returns cached hash from post meta if available and algorithm matches;
	 * otherwise computes, stores, and returns the hash.
	 *
	 * @param int  $attachment_id The attachment ID.
	 * @param bool $force         Force recomputation even if cached.
	 * @return string The file hash, or empty string on failure.
	 */
	public function get_hash( int $attachment_id, bool $force = false ): string {
		$algorithm = $this->get_algorithm();

		if ( ! $force ) {
			$cached_hash = get_post_meta( $attachment_id, self::HASH_META_KEY, true );
			$cached_algo = get_post_meta( $attachment_id, self::ALGO_META_KEY, true );

			if ( $cached_hash && $cached_algo === $algorithm ) {
				return $cached_hash;
			}
		}

		$hash = $this->compute_hash( $attachment_id, $algorithm );

		if ( $hash ) {
			update_post_meta( $attachment_id, self::HASH_META_KEY, $hash );
			update_post_meta( $attachment_id, self::ALGO_META_KEY, $algorithm );
		}

		return $hash;
	}

	/**
	 * Compute file hash for an attachment.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $algorithm     The hash algorithm.
	 * @return string The file hash, or empty string on failure.
	 */
	public function compute_hash( int $attachment_id, string $algorithm = '' ): string {
		if ( empty( $algorithm ) ) {
			$algorithm = $this->get_algorithm();
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return '';
		}

		$hash = hash_file( $algorithm, $file_path );

		return $hash ?: '';
	}

	/**
	 * Compute and store hashes for a batch of attachments.
	 *
	 * @param int[] $attachment_ids Array of attachment IDs.
	 * @param bool  $force          Force recomputation.
	 * @return int Number of hashes computed.
	 */
	public function hash_batch( array $attachment_ids, bool $force = false ): int {
		$count = 0;

		foreach ( $attachment_ids as $attachment_id ) {
			$hash = $this->get_hash( $attachment_id, $force );
			if ( $hash ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Clear cached hash for an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return void
	 */
	public function clear_hash( int $attachment_id ): void {
		delete_post_meta( $attachment_id, self::HASH_META_KEY );
		delete_post_meta( $attachment_id, self::ALGO_META_KEY );
	}

	/**
	 * Get the configured hash algorithm.
	 *
	 * @return string Hash algorithm name.
	 */
	private function get_algorithm(): string {
		$settings  = get_option( 'vmfa_media_cleanup_settings', array() );
		$algorithm = $settings[ 'hash_algorithm' ] ?? 'sha256';

		/**
		 * Filter the hash algorithm used for duplicate detection.
		 *
		 * @param string $algorithm The hash algorithm (default: sha256).
		 */
		return apply_filters( 'vmfa_cleanup_hash_algorithm', $algorithm );
	}
}
