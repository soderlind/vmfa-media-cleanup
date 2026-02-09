<?php
/**
 * Results REST API controller.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\REST;

defined( 'ABSPATH' ) || exit;

use VmfaMediaCleanup\Detectors\DuplicateDetector;
use VmfaMediaCleanup\Services\ReferenceIndex;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for scan results.
 */
class ResultsController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'vmfa-cleanup/v1';

	/**
	 * Constructor - add filter to prevent caching of REST responses.
	 */
	public function __construct() {
		add_filter( 'rest_post_dispatch', array( $this, 'add_no_cache_headers' ), 10, 3 );
	}

	/**
	 * Add no-cache headers to our REST responses.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_REST_Server   $server   The REST server.
	 * @param WP_REST_Request  $request  The request object.
	 * @return WP_REST_Response
	 */
	public function add_no_cache_headers( $response, $server, $request ): WP_REST_Response {
		$route = $request->get_route();

		// Only add headers to our plugin's routes.
		if ( str_starts_with( $route, '/' . $this->namespace ) ) {
			$response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'Expires', '0' );
		}

		return $response;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /results — List results (paginated, filterable).
		register_rest_route(
			$this->namespace,
			'/results',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_results' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'type'     => array(
						'type'              => 'string',
						'enum'              => array( 'unused', 'duplicate', 'flagged', 'trash' ),
						'default'           => 'unused',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					),
					'orderby'  => array(
						'type'              => 'string',
						'default'           => 'file_size',
						'enum'              => array( 'file_size', 'upload_date', 'title' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'order'    => array(
						'type'    => 'string',
						'default' => 'desc',
						'enum'    => array( 'asc', 'desc' ),
					),
				),
			)
		);

		// GET /results/{id} — Single result detail.
		register_rest_route(
			$this->namespace,
			'/results/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_result_detail' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// GET /duplicates — List duplicate groups.
		register_rest_route(
			$this->namespace,
			'/duplicates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_duplicate_groups' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 10,
						'minimum' => 1,
						'maximum' => 50,
					),
				),
			)
		);
	}

	/**
	 * Check if user has permission.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'vmfa-media-cleanup' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Get results.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_results( WP_REST_Request $request ): WP_REST_Response {
		$type     = $request->get_param( 'type' );
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$orderby  = $request->get_param( 'orderby' );
		$order    = $request->get_param( 'order' );

		// Handle flagged type separately (from post meta, not scan results).
		if ( 'flagged' === $type ) {
			return $this->get_flagged_results( $page, $per_page );
		}

		// Handle trash type (attachments with post_status = trash).
		if ( 'trash' === $type ) {
			return $this->get_trash_results( $page, $per_page );
		}

		$all_results = get_option( 'vmfa_cleanup_results', array() );
		$items       = $all_results[ $type ] ?? array();

		// Filter out deleted items and verify attachments still exist.
		$items = array_filter( $items, function ( $item, $key ) {
			$att_id = (int) ( $item[ 'attachment_id' ] ?? $item[ 'id' ] ?? $key );
			if ( $att_id <= 0 ) {
				return false;
			}
			// Clear cache and check if post exists.
			clean_post_cache( $att_id );
			$post = get_post( $att_id );
			return $post && 'attachment' === $post->post_type;
		}, ARRAY_FILTER_USE_BOTH );

		// Sort.
		usort( $items, function ( $a, $b ) use ( $orderby, $order ) {
			$val_a = $a[ $orderby ] ?? '';
			$val_b = $b[ $orderby ] ?? '';

			$cmp = $val_a <=> $val_b;
			return 'desc' === $order ? -$cmp : $cmp;
		} );

		// Paginate.
		$total = count( $items );
		$items = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );

		// Enrich items with flagged and trashed state (fresh from DB).
		foreach ( $items as $key => &$item ) {
			$att_id               = (int) ( $item[ 'attachment_id' ] ?? $item[ 'id' ] ?? $key );
			$post_status          = get_post_field( 'post_status', $att_id );
			$item[ 'is_flagged' ] = $att_id > 0 && (bool) get_post_meta( $att_id, '_vmfa_flagged_for_review', true );
			$item[ 'is_trashed' ] = 'trash' === $post_status;
		}
		unset( $item );

		return rest_ensure_response(
			array(
				'items'       => array_values( $items ),
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Get detail for a single result item.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_result_detail( WP_REST_Request $request ) {
		$attachment_id = $request->get_param( 'id' );
		$attachment    = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'not_found',
				__( 'Attachment not found.', 'vmfa-media-cleanup' ),
				array( 'status' => 404 )
			);
		}

		$file_path = get_attached_file( $attachment_id );
		$metadata  = wp_get_attachment_metadata( $attachment_id );

		// Get references from the index.
		$reference_index = new ReferenceIndex();
		$references      = $reference_index->get_references( $attachment_id );

		// Enrich references with source titles.
		foreach ( $references as &$ref ) {
			if ( $ref[ 'source_id' ] > 0 ) {
				$source_post           = get_post( (int) $ref[ 'source_id' ] );
				$ref[ 'source_title' ] = $source_post ? get_the_title( $source_post ) : '';
				$ref[ 'source_url' ]   = $source_post ? get_edit_post_link( $source_post->ID, 'raw' ) : '';
			}
		}

		// Check scan results for this attachment.
		$all_results = get_option( 'vmfa_cleanup_results', array() );
		$status      = array();

		foreach ( array( 'unused', 'duplicate' ) as $type ) {
			if ( isset( $all_results[ $type ][ $attachment_id ] ) ) {
				$status[] = $type;
			}
		}

		$is_flagged = (bool) get_post_meta( $attachment_id, '_vmfa_flagged_for_review', true );
		if ( $is_flagged ) {
			$status[] = 'flagged';
		}

		return rest_ensure_response(
			array(
				'attachment_id' => $attachment_id,
				'title'         => get_the_title( $attachment_id ),
				'filename'      => $file_path ? basename( $file_path ) : '',
				'mime_type'     => get_post_mime_type( $attachment_id ),
				'file_size'     => $file_path && file_exists( $file_path ) ? wp_filesize( $file_path ) : 0,
				'upload_date'   => $attachment->post_date,
				'thumbnail_url' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: '',
				'width'         => $metadata[ 'width' ] ?? 0,
				'height'        => $metadata[ 'height' ] ?? 0,
				'status'        => $status,
				'references'    => $references,
				'edit_url'      => get_edit_post_link( $attachment_id, 'raw' ),
			)
		);
	}

	/**
	 * Get duplicate groups.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_duplicate_groups( WP_REST_Request $request ): WP_REST_Response {
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$all_results       = get_option( 'vmfa_cleanup_results', array() );
		$duplicate_results = $all_results[ 'duplicate' ] ?? array();

		if ( empty( $duplicate_results ) ) {
			return rest_ensure_response(
				array(
					'groups'      => array(),
					'total'       => 0,
					'page'        => $page,
					'per_page'    => $per_page,
					'total_pages' => 0,
				)
			);
		}

		$detector = new DuplicateDetector( new \VmfaMediaCleanup\Services\HashService() );
		$groups   = $detector->get_groups( $duplicate_results );

		// Enrich members with reference counts and trashed state (clear cache for fresh data).
		$reference_index = new ReferenceIndex();
		foreach ( $groups as &$group ) {
			// Filter out deleted members.
			$group[ 'members' ] = array_filter( $group[ 'members' ], function ( $member ) {
				$att_id = (int) $member[ 'attachment_id' ];
				clean_post_cache( $att_id );
				$post = get_post( $att_id );
				return $post && 'attachment' === $post->post_type;
			} );

			foreach ( $group[ 'members' ] as &$member ) {
				$att_id                      = (int) $member[ 'attachment_id' ];
				$refs                        = $reference_index->get_references( $att_id );
				$member[ 'reference_count' ] = count( $refs );
				$member[ 'is_trashed' ]      = 'trash' === get_post_field( 'post_status', $att_id );
			}

			// Re-index members array.
			$group[ 'members' ] = array_values( $group[ 'members' ] );
		}
		unset( $group, $member );

		// Filter out groups with less than 2 members (no longer duplicates).
		$groups = array_filter( $groups, function ( $group ) {
			return count( $group[ 'members' ] ) >= 2;
		} );
		$groups = array_values( $groups );

		// Paginate groups.
		$total  = count( $groups );
		$groups = array_slice( $groups, ( $page - 1 ) * $per_page, $per_page );

		return rest_ensure_response(
			array(
				'groups'      => $groups,
				'total'       => $total,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Get trashed attachment results.
	 *
	 * @param int $page     Current page.
	 * @param int $per_page Items per page.
	 * @return WP_REST_Response
	 */
	private function get_trash_results( int $page, int $per_page ): WP_REST_Response {
		// Disable object cache for fresh results.
		wp_suspend_cache_addition( true );

		// Only show items trashed by this plugin (have _vmfa_trashed meta).
		$trash_query = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'trash',
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'suppress_filters'       => false,
				'cache_results'          => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_vmfa_trashed',
						'value'   => '1',
						'compare' => '=',
					),
				),
			)
		);

		wp_suspend_cache_addition( false );

		$items = array();
		foreach ( $trash_query->posts as $attachment ) {
			$file_path = get_attached_file( $attachment->ID );
			$metadata  = wp_get_attachment_metadata( $attachment->ID );

			$items[] = array(
				'type'          => 'trash',
				'attachment_id' => $attachment->ID,
				'title'         => get_the_title( $attachment->ID ),
				'filename'      => $file_path ? basename( $file_path ) : '',
				'mime_type'     => get_post_mime_type( $attachment->ID ),
				'file_size'     => $file_path && file_exists( $file_path ) ? wp_filesize( $file_path ) : 0,
				'upload_date'   => $attachment->post_date,
				'thumbnail_url' => wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ) ?: '',
				'width'         => $metadata[ 'width' ] ?? 0,
				'height'        => $metadata[ 'height' ] ?? 0,
				'trashed_at'    => get_post_meta( $attachment->ID, '_wp_trash_meta_time', true ),
			);
		}

		return rest_ensure_response(
			array(
				'items'       => $items,
				'total'       => $trash_query->found_posts,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) $trash_query->max_num_pages,
			)
		);
	}

	/**
	 * Get flagged-for-review results.
	 *
	 * @param int $page     Current page.
	 * @param int $per_page Items per page.
	 * @return WP_REST_Response
	 */
	private function get_flagged_results( int $page, int $per_page ): WP_REST_Response {
		$flagged_query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'meta_key'       => '_vmfa_flagged_for_review',
				'meta_compare'   => 'EXISTS',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$items = array();
		foreach ( $flagged_query->posts as $attachment ) {
			$file_path = get_attached_file( $attachment->ID );
			$metadata  = wp_get_attachment_metadata( $attachment->ID );

			$items[] = array(
				'type'          => 'flagged',
				'attachment_id' => $attachment->ID,
				'title'         => get_the_title( $attachment->ID ),
				'filename'      => $file_path ? basename( $file_path ) : '',
				'mime_type'     => get_post_mime_type( $attachment->ID ),
				'file_size'     => $file_path && file_exists( $file_path ) ? wp_filesize( $file_path ) : 0,
				'upload_date'   => $attachment->post_date,
				'thumbnail_url' => wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ) ?: '',
				'width'         => $metadata[ 'width' ] ?? 0,
				'height'        => $metadata[ 'height' ] ?? 0,
				'flagged_at'    => get_post_meta( $attachment->ID, '_vmfa_flagged_for_review', true ),
				'is_flagged'    => true,
			);
		}

		return rest_ensure_response(
			array(
				'items'       => $items,
				'total'       => $flagged_query->found_posts,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => (int) $flagged_query->max_num_pages,
			)
		);
	}
}
