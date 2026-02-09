<?php
/**
 * Scan REST API controller.
 *
 * @package VmfaMediaCleanup
 */

declare(strict_types=1);

namespace VmfaMediaCleanup\REST;

defined( 'ABSPATH' ) || exit;

use VmfaMediaCleanup\Services\ScanService;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for scan operations.
 */
class ScanController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'vmfa-cleanup/v1';

	/**
	 * Scan service.
	 *
	 * @var ScanService
	 */
	private ScanService $scan_service;

	/**
	 * Constructor.
	 *
	 * @param ScanService $scan_service Scan service instance.
	 */
	public function __construct( ScanService $scan_service ) {
		$this->scan_service = $scan_service;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// POST /scan — Start a scan.
		register_rest_route(
			$this->namespace,
			'/scan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_scan' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'types' => array(
						'type'    => 'array',
						'items'   => array(
							'type' => 'string',
							'enum' => array( 'unused', 'duplicate', 'oversized' ),
						),
						'default' => array(),
					),
				),
			)
		);

		// GET /scan/status — Get scan progress.
		register_rest_route(
			$this->namespace,
			'/scan/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /scan/cancel — Cancel a running scan.
		register_rest_route(
			$this->namespace,
			'/scan/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_scan' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// POST /scan/reset — Reset scan results.
		register_rest_route(
			$this->namespace,
			'/scan/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_scan' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// GET /stats — Dashboard statistics.
		register_rest_route(
			$this->namespace,
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'check_permission' ),
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
	 * Start a scan.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_scan( WP_REST_Request $request ) {
		$types   = $request->get_param( 'types' ) ?? array();
		$started = $this->scan_service->start_scan( $types );

		if ( ! $started ) {
			return new WP_Error(
				'scan_failed',
				__( 'Could not start scan. A scan may already be running, or Action Scheduler is not available.', 'vmfa-media-cleanup' ),
				array( 'status' => 409 )
			);
		}

		return rest_ensure_response(
			array(
				'started'  => true,
				'progress' => $this->scan_service->get_progress(),
			)
		);
	}

	/**
	 * Get scan status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->scan_service->get_progress() );
	}

	/**
	 * Cancel a running scan.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_scan( WP_REST_Request $request ) {
		$cancelled = $this->scan_service->cancel_scan();

		if ( ! $cancelled ) {
			return new WP_Error(
				'cancel_failed',
				__( 'Could not cancel scan.', 'vmfa-media-cleanup' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response( array( 'cancelled' => true ) );
	}

	/**
	 * Reset scan results.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function reset_scan( WP_REST_Request $request ): WP_REST_Response {
		$this->scan_service->reset_scan();

		return rest_ensure_response( array( 'reset' => true ) );
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_stats( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->scan_service->get_stats() );
	}
}
