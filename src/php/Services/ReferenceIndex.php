<?php
/**
 * Reference index service.
 *
 * Builds and queries a reverse-lookup index of where media attachments
 * are used across the site (post content, featured images, page builders, etc.).
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Media reference index for tracking attachment usage.
 */
class ReferenceIndex {

	/**
	 * Database table name (without prefix).
	 */
	private const TABLE_NAME = 'vmfa_media_references';

	/**
	 * Meta keys from popular page builders to scan for attachment references.
	 *
	 * @var string[]
	 */
	private const PAGE_BUILDER_META_KEYS = array(
		'_elementor_data',
		'_fl_builder_data',
		'panels_data',
		'_fusion_builder_data',
	);

	/**
	 * Create the reference index database table.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL,
			source_type varchar(50) NOT NULL,
			source_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY attachment_id (attachment_id),
			KEY source_type (source_type),
			KEY source_lookup (source_type, source_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop the reference index table.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Clear all entries from the index.
	 *
	 * @return void
	 */
	public function clear(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$wpdb->query( "TRUNCATE TABLE $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Check if an attachment is referenced anywhere.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return bool True if referenced.
	 */
	public function is_referenced( int $attachment_id ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table_name WHERE attachment_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$attachment_id
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get all references for an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return array<int, array<string, mixed>> Array of reference records.
	 */
	public function get_references( int $attachment_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source_type, source_id FROM $table_name WHERE attachment_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$attachment_id
			),
			ARRAY_A
		);
	}

	/**
	 * Add a reference record.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $source_type   The type of source (e.g. 'post_content', 'featured_image', 'page_builder').
	 * @param int    $source_id     The source post/object ID.
	 * @return void
	 */
	public function add_reference( int $attachment_id, string $source_type, int $source_id ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$wpdb->insert(
			$table_name,
			array(
				'attachment_id' => $attachment_id,
				'source_type'   => $source_type,
				'source_id'     => $source_id,
			),
			array( '%d', '%s', '%d' )
		);
	}

	/**
	 * Batch-insert references.
	 *
	 * @param array<int, array{attachment_id: int, source_type: string, source_id: int}> $records Array of reference records.
	 * @return void
	 */
	public function add_references_batch( array $records ): void {
		global $wpdb;

		if ( empty( $records ) ) {
			return;
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$values     = array();
		$format     = array();

		foreach ( $records as $record ) {
			$values[] = $wpdb->prepare( '(%d, %s, %d)', $record[ 'attachment_id' ], $record[ 'source_type' ], $record[ 'source_id' ] );
		}

		// Insert in chunks to avoid query size limits.
		$chunks = array_chunk( $values, 500 );
		foreach ( $chunks as $chunk ) {
			$sql = "INSERT INTO $table_name (attachment_id, source_type, source_id) VALUES " . implode( ', ', $chunk ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Build the reference index for a batch of posts.
	 *
	 * Scans post content, featured images, and page builder meta fields.
	 *
	 * @param int $offset         Offset for the post query.
	 * @param int $batch_size     Number of posts to process.
	 * @return int Number of posts processed.
	 */
	public function build_index_batch( int $offset, int $batch_size ): int {
		$post_types = get_post_types( array( 'public' => true ) );

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $posts ) ) {
			return 0;
		}

		$records = array();

		foreach ( $posts as $post ) {
			// 1. Scan post_content for attachment references.
			$content_ids = $this->extract_ids_from_content( $post->post_content );
			foreach ( $content_ids as $attachment_id ) {
				$records[] = array(
					'attachment_id' => $attachment_id,
					'source_type'   => 'post_content',
					'source_id'     => $post->ID,
				);
			}

			// 2. Featured image.
			$thumbnail_id = (int) get_post_meta( $post->ID, '_thumbnail_id', true );
			if ( $thumbnail_id > 0 ) {
				$records[] = array(
					'attachment_id' => $thumbnail_id,
					'source_type'   => 'featured_image',
					'source_id'     => $post->ID,
				);
			}

			// 3. Page builder meta keys.
			$meta_keys = $this->get_meta_keys_to_scan();
			foreach ( $meta_keys as $meta_key ) {
				$meta_value = get_post_meta( $post->ID, $meta_key, true );
				if ( empty( $meta_value ) ) {
					continue;
				}

				// Convert to string if array/object (e.g. serialized data).
				if ( ! is_string( $meta_value ) ) {
					$meta_value = wp_json_encode( $meta_value );
				}

				$meta_ids = $this->extract_ids_from_meta( $meta_value );
				foreach ( $meta_ids as $attachment_id ) {
					$records[] = array(
						'attachment_id' => $attachment_id,
						'source_type'   => 'page_builder',
						'source_id'     => $post->ID,
					);
				}
			}
		}

		// 4. Custom reference sources (third-party).
		$records = $this->add_custom_sources( $records, $posts );

		// Batch-insert all discovered records.
		$this->add_references_batch( $records );

		return count( $posts );
	}

	/**
	 * Build reference index for global items (site icon, custom logo, widgets).
	 *
	 * @return void
	 */
	public function build_global_references(): void {
		$records = array();

		// Site icon.
		$site_icon = (int) get_option( 'site_icon', 0 );
		if ( $site_icon > 0 ) {
			$records[] = array(
				'attachment_id' => $site_icon,
				'source_type'   => 'site_icon',
				'source_id'     => 0,
			);
		}

		// Custom logo.
		$custom_logo = (int) get_theme_mod( 'custom_logo', 0 );
		if ( $custom_logo > 0 ) {
			$records[] = array(
				'attachment_id' => $custom_logo,
				'source_type'   => 'custom_logo',
				'source_id'     => 0,
			);
		}

		// Widgets — scan all widget instances for attachment references.
		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( is_array( $sidebars ) ) {
			foreach ( $sidebars as $sidebar_id => $widgets ) {
				if ( ! is_array( $widgets ) || 'wp_inactive_widgets' === $sidebar_id ) {
					continue;
				}

				foreach ( $widgets as $widget_id ) {
					$widget_type = preg_replace( '/-\d+$/', '', $widget_id );
					$instances   = get_option( "widget_{$widget_type}", array() );

					if ( is_array( $instances ) ) {
						$content = wp_json_encode( $instances );
						$ids     = $this->extract_ids_from_meta( $content );

						foreach ( $ids as $attachment_id ) {
							$records[] = array(
								'attachment_id' => $attachment_id,
								'source_type'   => 'widget',
								'source_id'     => 0,
							);
						}
					}
				}
			}
		}

		$this->add_references_batch( $records );
	}

	/**
	 * Extract attachment IDs from post content.
	 *
	 * Looks for block editor patterns, classic editor patterns, and raw URLs.
	 *
	 * @param string $content The post content.
	 * @return int[] Array of attachment IDs found.
	 */
	private function extract_ids_from_content( string $content ): array {
		if ( empty( $content ) ) {
			return array();
		}

		$ids = array();

		// Block editor: wp-image-{id} class.
		if ( preg_match_all( '/wp-image-(\d+)/', $content, $matches ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $matches[ 1 ] ) );
		}

		// Block editor: {"id":{id}} in wp:image, wp:cover, wp:video, wp:audio.
		if ( preg_match_all( '/wp:(image|cover|video|audio|file)\s+\{[^}]*"id"\s*:\s*(\d+)/', $content, $matches ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $matches[ 2 ] ) );
		}

		// Block editor: wp:media-text {"mediaId":{id}}.
		if ( preg_match_all( '/wp:media-text\s+\{[^}]*"mediaId"\s*:\s*(\d+)/', $content, $matches ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $matches[ 1 ] ) );
		}

		// Block editor: wp:gallery inner images — {"id":{id}} inside gallery blocks.
		if ( preg_match_all( '/"id"\s*:\s*(\d+)/', $content, $matches ) ) {
			// Filter to only valid attachment IDs (avoid false positives from other {"id":...} patterns).
			foreach ( $matches[ 1 ] as $potential_id ) {
				$potential_id = (int) $potential_id;
				if ( $potential_id > 0 && 'attachment' === get_post_type( $potential_id ) ) {
					$ids[] = $potential_id;
				}
			}
		}

		// Classic editor: ?attachment_id={id}.
		if ( preg_match_all( '/\?attachment_id=(\d+)/', $content, $matches ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $matches[ 1 ] ) );
		}

		// URL-based references: resolve wp-content/uploads URLs to attachment IDs.
		if ( preg_match_all( '#(?:https?:)?//[^"\'>\s]+/wp-content/uploads/[^"\'>\s]+#', $content, $matches ) ) {
			foreach ( $matches[ 0 ] as $url ) {
				// Remove size suffixes like -300x200 to find the original.
				$clean_url     = preg_replace( '/-\d+x\d+(?=\.\w+$)/', '', $url );
				$attachment_id = attachment_url_to_postid( $clean_url );

				if ( $attachment_id > 0 ) {
					$ids[] = $attachment_id;
				}
			}
		}

		return array_unique( array_filter( $ids ) );
	}

	/**
	 * Extract attachment IDs from serialized/JSON meta values.
	 *
	 * @param string $meta_value The meta value as a string.
	 * @return int[] Array of attachment IDs found.
	 */
	private function extract_ids_from_meta( string $meta_value ): array {
		$ids = array();

		// Common patterns in page builder data.
		// Elementor: "id":"123" or "image":{"id":"123"}.
		if ( preg_match_all( '/"(?:id|image_id|attach_id|attachment_id)"\s*:\s*"?(\d+)"?/', $meta_value, $matches ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $matches[ 1 ] ) );
		}

		// URL-based references in meta.
		if ( preg_match_all( '#wp-content/uploads/[^"\'>\s\\\\]+#', $meta_value, $matches ) ) {
			foreach ( $matches[ 0 ] as $path ) {
				$upload_dir = wp_get_upload_dir();
				$url        = $upload_dir[ 'baseurl' ] . '/' . ltrim( $path, '/' );
				$clean_url  = preg_replace( '/-\d+x\d+(?=\.\w+$)/', '', $url );

				$attachment_id = attachment_url_to_postid( $clean_url );
				if ( $attachment_id > 0 ) {
					$ids[] = $attachment_id;
				}
			}
		}

		return array_unique( array_filter( $ids ) );
	}

	/**
	 * Get the list of meta keys to scan for attachment references.
	 *
	 * @return string[]
	 */
	private function get_meta_keys_to_scan(): array {
		$settings   = get_option( 'vmfa_media_cleanup_settings', array() );
		$extra_keys = $settings[ 'extra_meta_keys' ] ?? array();

		$meta_keys = array_merge( self::PAGE_BUILDER_META_KEYS, $extra_keys );

		/**
		 * Filter the meta keys scanned for attachment references.
		 *
		 * @param string[] $meta_keys Array of meta key names.
		 */
		return apply_filters( 'vmfa_cleanup_reference_meta_keys', $meta_keys );
	}

	/**
	 * Add custom reference sources from third-party plugins.
	 *
	 * @param array                $records Existing reference records.
	 * @param array<int, \WP_Post> $posts   The posts being processed.
	 * @return array Updated records.
	 */
	private function add_custom_sources( array $records, array $posts ): array {
		/**
		 * Filter to register custom reference source callbacks.
		 *
		 * Each source is an array with:
		 * - 'type'     (string)   Source type identifier.
		 * - 'callback' (callable) Function receiving post ID, returning array of attachment IDs.
		 *
		 * @param array $sources Array of custom source definitions.
		 */
		$sources = apply_filters( 'vmfa_cleanup_reference_sources', array() );

		if ( empty( $sources ) ) {
			return $records;
		}

		foreach ( $posts as $post ) {
			foreach ( $sources as $source ) {
				if ( ! isset( $source[ 'callback' ] ) || ! is_callable( $source[ 'callback' ] ) ) {
					continue;
				}

				$source_type    = $source[ 'type' ] ?? 'custom';
				$attachment_ids = call_user_func( $source[ 'callback' ], $post->ID );

				if ( ! is_array( $attachment_ids ) ) {
					continue;
				}

				foreach ( $attachment_ids as $attachment_id ) {
					$records[] = array(
						'attachment_id' => (int) $attachment_id,
						'source_type'   => sanitize_key( $source_type ),
						'source_id'     => $post->ID,
					);
				}
			}
		}

		return $records;
	}

	/**
	 * Get the total number of posts to index.
	 *
	 * @return int Total post count.
	 */
	public function get_total_posts(): int {
		global $wpdb;

		$post_types    = get_post_types( array( 'public' => true ) );
		$placeholders  = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$post_statuses = array( 'publish', 'draft', 'private', 'pending' );
		$status_ph     = implode( ', ', array_fill( 0, count( $post_statuses ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ($placeholders) AND post_status IN ($status_ph)",
				...array_values( array_merge( $post_types, $post_statuses ) )
			)
		);

		return (int) $count;
	}
}
