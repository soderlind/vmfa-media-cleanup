<?php
/**
 * Detector interface.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\Detectors;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for media detection strategies.
 */
interface DetectorInterface {

	/**
	 * Run detection on a batch of attachment IDs.
	 *
	 * @param int[] $attachment_ids Array of attachment IDs to check.
	 * @return array<int, array<string, mixed>> Results keyed by attachment ID.
	 */
	public function detect( array $attachment_ids ): array;

	/**
	 * Get the detector type identifier.
	 *
	 * @return string
	 */
	public function get_type(): string;

	/**
	 * Get the human-readable label.
	 *
	 * @return string
	 */
	public function get_label(): string;
}
