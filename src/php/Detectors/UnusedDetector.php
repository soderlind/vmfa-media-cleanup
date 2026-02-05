<?php
/**
 * Unused media detector.
 *
 * Finds media items not referenced in any post content, featured images,
 * site icon, custom logo, widgets, or known page builder meta fields.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\Detectors;

use VmfaMediaCleanup\Services\ReferenceIndex;

/**
 * Detects unused media attachments.
 */
class UnusedDetector implements DetectorInterface {

	/**
	 * Reference index service.
	 *
	 * @var ReferenceIndex
	 */
	private ReferenceIndex $reference_index;

	/**
	 * Constructor.
	 *
	 * @param ReferenceIndex $reference_index Reference index service.
	 */
	public function __construct( ReferenceIndex $reference_index ) {
		$this->reference_index = $reference_index;
	}

	/**
	 * Get the detector type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return 'unused';
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Unused', 'vmfa-media-cleanup' );
	}

	/**
	 * Detect unused media in the given batch.
	 *
	 * @param int[] $attachment_ids Array of attachment IDs to check.
	 * @return array<int, array<string, mixed>> Results keyed by attachment ID.
	 */
	public function detect( array $attachment_ids ): array {
		$results = array();

		// Get globally protected attachment IDs.
		$protected = $this->get_protected_ids();

		foreach ( $attachment_ids as $attachment_id ) {
			// Skip globally protected items.
			if ( in_array( $attachment_id, $protected, true ) ) {
				continue;
			}

			// Check the reference index.
			if ( $this->reference_index->is_referenced( $attachment_id ) ) {
				continue;
			}

			/**
			 * Filter whether an attachment is considered unused.
			 *
			 * Allows third-party plugins to exclude specific attachments from
			 * being reported as unused (e.g. items used by WooCommerce galleries,
			 * ACF fields, etc.).
			 *
			 * @param bool $is_unused     Whether the attachment is unused.
			 * @param int  $attachment_id The attachment ID.
			 */
			$is_unused = apply_filters( 'vmfa_cleanup_is_unused', true, $attachment_id );

			if ( ! $is_unused ) {
				continue;
			}

			$attachment = get_post( $attachment_id );
			if ( ! $attachment ) {
				continue;
			}

			$file_path = get_attached_file( $attachment_id );
			$metadata  = wp_get_attachment_metadata( $attachment_id );

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
			);
		}

		return $results;
	}

	/**
	 * Get attachment IDs that are globally protected (site icon, custom logo, etc.).
	 *
	 * @return int[]
	 */
	private function get_protected_ids(): array {
		$protected = array();

		// Site icon.
		$site_icon = (int) get_option( 'site_icon', 0 );
		if ( $site_icon > 0 ) {
			$protected[] = $site_icon;
		}

		// Custom logo.
		$custom_logo = (int) get_theme_mod( 'custom_logo', 0 );
		if ( $custom_logo > 0 ) {
			$protected[] = $custom_logo;
		}

		return array_unique( $protected );
	}
}
