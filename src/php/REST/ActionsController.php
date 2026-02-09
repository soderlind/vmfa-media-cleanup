<?php
/**
 * Actions REST API controller.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for bulk actions (archive, trash, flag).
 */
class ActionsController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'vmfa-cleanup/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /actions/archive — Move to archive folder.
		register_rest_route(
			$this->namespace,
			'/actions/archive',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'archive_media' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_bulk_args(),
			)
		);

		// POST /actions/trash — Trash media.
		register_rest_route(
			$this->namespace,
			'/actions/trash',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'trash_media' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_bulk_args(),
			)
		);

		// POST /actions/flag — Flag for review.
		register_rest_route(
			$this->namespace,
			'/actions/flag',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'flag_media' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'ids' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		// POST /actions/unflag — Remove review flag.
		register_rest_route(
			$this->namespace,
			'/actions/unflag',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'unflag_media' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'ids' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		// POST /actions/set-primary — Set primary duplicate.
		register_rest_route(
			$this->namespace,
			'/actions/set-primary',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_primary' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id'        => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'group_ids' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		// POST /actions/restore — Restore trashed media.
		register_rest_route(
			$this->namespace,
			'/actions/restore',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'restore_media' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'ids' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		// POST /actions/delete — Permanently delete media.
		register_rest_route(
			$this->namespace,
			'/actions/delete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'delete_media' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_bulk_args(),
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
	 * Archive media by moving to the Archive virtual folder.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function archive_media( WP_REST_Request $request ) {
		if ( ! $request->get_param( 'confirm' ) ) {
			return new WP_Error(
				'not_confirmed',
				__( 'Action requires confirmation. Set confirm to true.', 'vmfa-media-cleanup' ),
				array( 'status' => 400 )
			);
		}

		$ids       = array_map( 'absint', $request->get_param( 'ids' ) );
		$folder_id = $this->get_or_create_archive_folder();

		if ( is_wp_error( $folder_id ) ) {
			return $folder_id;
		}

		/**
		 * Fires before a bulk action is processed.
		 *
		 * @param string $action The action being performed.
		 * @param int[]  $ids    The attachment IDs.
		 */
		do_action( 'vmfa_cleanup_before_bulk_action', 'archive', $ids );

		$success = 0;
		$failed  = 0;

		foreach ( array_chunk( $ids, 50 ) as $chunk ) {
			foreach ( $chunk as $attachment_id ) {
				$result = wp_set_object_terms( $attachment_id, $folder_id, 'vmfo_folder' );

				if ( is_wp_error( $result ) ) {
					++$failed;
				} else {
					++$success;

					/**
					 * Fires after a media item is archived.
					 *
					 * @param int $attachment_id The attachment ID.
					 * @param int $folder_id     The archive folder term ID.
					 */
					do_action( 'vmfa_cleanup_media_archived', $attachment_id, $folder_id );
				}
			}
		}

		return rest_ensure_response(
			array(
				'action'    => 'archive',
				'success'   => $success,
				'failed'    => $failed,
				'folder_id' => $folder_id,
			)
		);
	}

	/**
	 * Trash media (soft-delete, bypasses MEDIA_TRASH constant).
	 *
	 * WordPress core's wp_trash_post() permanently deletes attachments when
	 * MEDIA_TRASH is false. We manually set the trash status so our plugin
	 * manages its own trash view regardless of site configuration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function trash_media( WP_REST_Request $request ) {
		if ( ! $request->get_param( 'confirm' ) ) {
			return new WP_Error(
				'not_confirmed',
				__( 'Action requires confirmation. Set confirm to true.', 'vmfa-media-cleanup' ),
				array( 'status' => 400 )
			);
		}

		$ids = array_map( 'absint', $request->get_param( 'ids' ) );

		do_action( 'vmfa_cleanup_before_bulk_action', 'trash', $ids );

		$success = 0;
		$failed  = 0;

		foreach ( array_chunk( $ids, 50 ) as $chunk ) {
			foreach ( $chunk as $attachment_id ) {
				$attachment = get_post( $attachment_id );

				if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
					++$failed;
					continue;
				}

				// Store original status and trash time for restore.
				update_post_meta( $attachment_id, '_wp_trash_meta_status', $attachment->post_status );
				update_post_meta( $attachment_id, '_wp_trash_meta_time', time() );
				// Mark as trashed by this plugin (for filtering in Trash tab).
				update_post_meta( $attachment_id, '_vmfa_trashed', '1' );

				$result = wp_update_post(
					array(
						'ID'          => $attachment_id,
						'post_status' => 'trash',
					)
				);

				if ( $result && ! is_wp_error( $result ) ) {
					++$success;

					/**
					 * Fires after a media item is trashed.
					 *
					 * @param int $attachment_id The attachment ID.
					 */
					do_action( 'vmfa_cleanup_media_trashed', $attachment_id );
				} else {
					++$failed;
				}
			}
		}

		return rest_ensure_response(
			array(
				'action'  => 'trash',
				'success' => $success,
				'failed'  => $failed,
			)
		);
	}

	/**
	 * Restore trashed media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function restore_media( WP_REST_Request $request ): WP_REST_Response {
		$ids     = array_map( 'absint', $request->get_param( 'ids' ) );
		$success = 0;
		$failed  = 0;

		foreach ( $ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );

			if ( ! $attachment || 'attachment' !== $attachment->post_type || 'trash' !== $attachment->post_status ) {
				++$failed;
				continue;
			}

			$original_status = get_post_meta( $attachment_id, '_wp_trash_meta_status', true );
			$new_status      = $original_status ?: 'inherit';

			$result = wp_update_post(
				array(
					'ID'          => $attachment_id,
					'post_status' => $new_status,
				)
			);

			if ( $result && ! is_wp_error( $result ) ) {
				delete_post_meta( $attachment_id, '_wp_trash_meta_status' );
				delete_post_meta( $attachment_id, '_wp_trash_meta_time' );
				delete_post_meta( $attachment_id, '_vmfa_trashed' );
				++$success;

				/**
				 * Fires after a media item is restored from trash.
				 *
				 * @param int $attachment_id The attachment ID.
				 */
				do_action( 'vmfa_cleanup_media_restored', $attachment_id );
			} else {
				++$failed;
			}
		}

		return rest_ensure_response(
			array(
				'action'  => 'restore',
				'success' => $success,
				'failed'  => $failed,
			)
		);
	}

	/**
	 * Permanently delete media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_media( WP_REST_Request $request ) {
		if ( ! $request->get_param( 'confirm' ) ) {
			return new WP_Error(
				'not_confirmed',
				__( 'Action requires confirmation. Set confirm to true.', 'vmfa-media-cleanup' ),
				array( 'status' => 400 )
			);
		}

		$ids = array_map( 'absint', $request->get_param( 'ids' ) );

		do_action( 'vmfa_cleanup_before_bulk_action', 'delete', $ids );

		$success = 0;
		$failed  = 0;

		foreach ( array_chunk( $ids, 50 ) as $chunk ) {
			foreach ( $chunk as $attachment_id ) {
				$result = wp_delete_attachment( $attachment_id, true );

				if ( $result ) {
					++$success;

					/**
					 * Fires after a media item is permanently deleted.
					 *
					 * @param int $attachment_id The attachment ID.
					 */
					do_action( 'vmfa_cleanup_media_deleted', $attachment_id );
				} else {
					++$failed;
				}
			}
		}

		return rest_ensure_response(
			array(
				'action'  => 'delete',
				'success' => $success,
				'failed'  => $failed,
			)
		);
	}

	/**
	 * Flag media for review.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function flag_media( WP_REST_Request $request ): WP_REST_Response {
		$ids     = array_map( 'absint', $request->get_param( 'ids' ) );
		$success = 0;

		foreach ( $ids as $attachment_id ) {
			$timestamp = current_time( 'mysql', true );
			update_post_meta( $attachment_id, '_vmfa_flagged_for_review', $timestamp );
			++$success;

			/**
			 * Fires after a media item is flagged for review.
			 *
			 * @param int $attachment_id The attachment ID.
			 */
			do_action( 'vmfa_cleanup_media_flagged', $attachment_id );
		}

		return rest_ensure_response(
			array(
				'action'  => 'flag',
				'success' => $success,
			)
		);
	}

	/**
	 * Remove review flag from media.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function unflag_media( WP_REST_Request $request ): WP_REST_Response {
		$ids     = array_map( 'absint', $request->get_param( 'ids' ) );
		$success = 0;

		foreach ( $ids as $attachment_id ) {
			delete_post_meta( $attachment_id, '_vmfa_flagged_for_review' );
			++$success;
		}

		return rest_ensure_response(
			array(
				'action'  => 'unflag',
				'success' => $success,
			)
		);
	}

	/**
	 * Set an attachment as the primary in a duplicate group.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function set_primary( WP_REST_Request $request ): WP_REST_Response {
		$primary_id = absint( $request->get_param( 'id' ) );
		$group_ids  = array_map( 'absint', $request->get_param( 'group_ids' ) );

		// Clear primary flag from all group members.
		foreach ( $group_ids as $id ) {
			delete_post_meta( $id, '_vmfa_duplicate_primary' );
		}

		// Set the new primary.
		update_post_meta( $primary_id, '_vmfa_duplicate_primary', true );

		return rest_ensure_response(
			array(
				'action'     => 'set-primary',
				'primary_id' => $primary_id,
			)
		);
	}

	/**
	 * Get or create the Archive virtual folder.
	 *
	 * @return int|WP_Error The folder term ID, or WP_Error on failure.
	 */
	private function get_or_create_archive_folder() {
		$settings    = get_option( 'vmfa_media_cleanup_settings', array() );
		$folder_name = $settings[ 'archive_folder_name' ] ?? 'Archive';

		/**
		 * Filter the archive folder name.
		 *
		 * @param string $folder_name The archive folder name.
		 */
		$folder_name = apply_filters( 'vmfa_cleanup_archive_folder_name', $folder_name );

		// Check if folder already exists.
		$existing = get_term_by( 'name', $folder_name, 'vmfo_folder' );

		if ( $existing ) {
			return $existing->term_id;
		}

		// Create the folder.
		$result = wp_insert_term( $folder_name, 'vmfo_folder', array( 'parent' => 0 ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result[ 'term_id' ];
	}

	/**
	 * Get bulk action argument definitions.
	 *
	 * @return array
	 */
	private function get_bulk_args(): array {
		return array(
			'ids'     => array(
				'required' => true,
				'type'     => 'array',
				'items'    => array( 'type' => 'integer' ),
			),
			'confirm' => array(
				'required' => true,
				'type'     => 'boolean',
				'default'  => false,
			),
		);
	}
}
