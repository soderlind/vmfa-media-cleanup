<?php
/**
 * Oversized media detector.
 *
 * Flags media files that exceed configurable size thresholds per MIME type.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\Detectors;

/**
 * Detects oversized media files above configurable thresholds.
 */
class OversizedDetector implements DetectorInterface {

	/**
	 * Get the detector type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'oversized';
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Oversized', 'vmfa-media-cleanup' );
	}

	/**
	 * Detect oversized media in the given batch.
	 *
	 * @param int[] $attachment_ids Array of attachment IDs to check.
	 * @return array<int, array<string, mixed>> Results keyed by attachment ID.
	 */
	public function detect( array $attachment_ids ): array {
		$thresholds = $this->get_thresholds();
		$results    = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				continue;
			}

			$file_size = wp_filesize( $file_path );
			$mime_type = get_post_mime_type( $attachment_id );
			$threshold = $this->get_threshold_for_mime( $mime_type, $thresholds );

			if ( $file_size <= $threshold ) {
				continue;
			}

			$attachment = get_post( $attachment_id );
			$metadata   = wp_get_attachment_metadata( $attachment_id );

			$results[ $attachment_id ] = array(
				'type'          => $this->get_type(),
				'attachment_id' => $attachment_id,
				'title'         => get_the_title( $attachment_id ),
				'filename'      => basename( $file_path ),
				'mime_type'     => $mime_type,
				'file_size'     => $file_size,
				'threshold'     => $threshold,
				'over_by'       => $file_size - $threshold,
				'upload_date'   => $attachment->post_date,
				'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: '',
				'width'         => $metadata[ 'width' ] ?? 0,
				'height'        => $metadata[ 'height' ] ?? 0,
			);
		}

		return $results;
	}

	/**
	 * Get size thresholds per MIME type category.
	 *
	 * @return array<string, int> Thresholds in bytes.
	 */
	private function get_thresholds(): array {
		$settings = get_option( 'vmfa_media_cleanup_settings', array() );

		$thresholds = array(
			'image'    => $settings[ 'oversized_threshold_image' ] ?? 2097152,
			'video'    => $settings[ 'oversized_threshold_video' ] ?? 104857600,
			'audio'    => $settings[ 'oversized_threshold_audio' ] ?? 20971520,
			'document' => $settings[ 'oversized_threshold_document' ] ?? 10485760,
		);

		/**
		 * Filter oversized detection thresholds.
		 *
		 * @param array<string, int> $thresholds Thresholds in bytes keyed by category.
		 */
		return apply_filters( 'vmfa_cleanup_oversized_thresholds', $thresholds );
	}

	/**
	 * Get the applicable threshold for a given MIME type.
	 *
	 * @param string             $mime_type  The MIME type (e.g. image/jpeg).
	 * @param array<string, int> $thresholds Thresholds by category.
	 * @return int Threshold in bytes.
	 */
	private function get_threshold_for_mime( string $mime_type, array $thresholds ): int {
		$type = explode( '/', $mime_type )[ 0 ] ?? '';

		return match ( $type ) {
			'image' => $thresholds[ 'image' ],
			'video' => $thresholds[ 'video' ],
			'audio' => $thresholds[ 'audio' ],
			default => $thresholds[ 'document' ],
		};
	}
}
