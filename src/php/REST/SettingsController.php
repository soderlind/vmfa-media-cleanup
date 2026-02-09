<?php
/**
 * Settings REST API controller.
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
 * REST API controller for plugin settings.
 */
class SettingsController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'vmfa-cleanup/v1';

	/**
	 * Option name for settings.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'vmfa_media_cleanup_settings';

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private const DEFAULTS = array(
		'archive_folder_name'          => 'Archive',
		'scan_batch_size'              => 100,
		'content_scan_depth'           => 'full',
		'auto_scan_on_upload'          => false,
		'protected_attachment_ids'     => array(),
	);

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /settings — Retrieve settings.
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /settings — Update settings.
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => $this->get_settings_args(),
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
	 * Get current settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = wp_parse_args(
			get_option( self::OPTION_NAME, array() ),
			self::DEFAULTS
		);

		return rest_ensure_response( $settings );
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$current = wp_parse_args(
			get_option( self::OPTION_NAME, array() ),
			self::DEFAULTS
		);
		$params  = $request->get_params();
		$updated = array();

		// Sanitize and validate each field.
		$allowed_keys = array_keys( self::DEFAULTS );

		foreach ( $allowed_keys as $key ) {
			if ( ! isset( $params[ $key ] ) ) {
				$updated[ $key ] = $current[ $key ];
				continue;
			}

			$updated[ $key ] = match ( $key ) {
				'scan_batch_size'          => absint( $params[ $key ] ),

				'archive_folder_name'      => sanitize_text_field( $params[ $key ] ),

				'content_scan_depth'       => in_array( $params[ $key ], array( 'full', 'featured_only', 'none' ), true )
				? $params[ $key ]
				: $current[ $key ],

				'auto_scan_on_upload'      => (bool) $params[ $key ],

				'protected_attachment_ids' => array_map( 'absint', (array) $params[ $key ] ),

				default                    => $current[ $key ],
			};
		}

		update_option( self::OPTION_NAME, $updated );

		/**
		 * Fires after cleanup settings are updated.
		 *
		 * @param array $updated  The new settings.
		 * @param array $current  The previous settings.
		 */
		do_action( 'vmfa_cleanup_settings_updated', $updated, $current );

		return rest_ensure_response( $updated );
	}

	/**
	 * Get argument definitions for settings.
	 *
	 * @return array
	 */
	private function get_settings_args(): array {
		return array(
			'archive_folder_name'          => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'scan_batch_size'              => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'content_scan_depth'           => array(
				'type' => 'string',
				'enum' => array( 'full', 'featured_only', 'none' ),
			),
			'auto_scan_on_upload'          => array(
				'type' => 'boolean',
			),
			'protected_attachment_ids'     => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
		);
	}
}
