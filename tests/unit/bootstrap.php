<?php
/**
 * PHPUnit bootstrap file for Brain Monkey integration.
 *
 * @package VmfaMediaCleanup\Tests
 */

declare(strict_types=1);

// Composer autoloader.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Define WordPress constants used by the plugin.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

// Minimal WP_Error stub for unit tests.
if ( ! class_exists( 'WP_Error' ) ) {
	// phpcs:ignore
	class WP_Error {
		public $errors = array();
		public $error_data = array();
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( $code ) {
				$this->errors[ $code ][] = $message;
				if ( $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}
		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return $codes[0] ?? '';
		}
		public function get_error_message( $code = '' ) {
			if ( ! $code ) {
				$code = $this->get_error_code();
			}
			return $this->errors[ $code ][0] ?? '';
		}
	}
}

// Minimal WP_REST_Controller stub.
if ( ! class_exists( 'WP_REST_Controller' ) ) {
	// phpcs:ignore
	class WP_REST_Controller {
		protected $namespace;
		protected $rest_base;
	}
}

// Minimal WP_REST_Server stub.
if ( ! class_exists( 'WP_REST_Server' ) ) {
	// phpcs:ignore
	class WP_REST_Server {
		const READABLE  = 'GET';
		const CREATABLE = 'POST';
		const EDITABLE  = 'POST, PUT, PATCH';
		const DELETABLE = 'DELETE';
	}
}

// Minimal WP_REST_Request stub.
if ( ! class_exists( 'WP_REST_Request' ) ) {
	// phpcs:ignore
	class WP_REST_Request {
		private $params = array();
		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}
		public function get_params() {
			return $this->params;
		}
		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}
	}
}

// Minimal WP_REST_Response stub.
if ( ! class_exists( 'WP_REST_Response' ) ) {
	// phpcs:ignore
	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
		public function get_data() {
			return $this->data;
		}
	}
}

if ( ! defined( 'VMFA_MEDIA_CLEANUP_VERSION' ) ) {
	define( 'VMFA_MEDIA_CLEANUP_VERSION', '1.0.0-test' );
}

if ( ! defined( 'VMFA_MEDIA_CLEANUP_FILE' ) ) {
	define( 'VMFA_MEDIA_CLEANUP_FILE', dirname( __DIR__, 2 ) . '/vmfa-media-cleanup.php' );
}

if ( ! defined( 'VMFA_MEDIA_CLEANUP_PATH' ) ) {
	define( 'VMFA_MEDIA_CLEANUP_PATH', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'VMFA_MEDIA_CLEANUP_URL' ) ) {
	define( 'VMFA_MEDIA_CLEANUP_URL', 'https://example.com/wp-content/plugins/vmfa-media-cleanup/' );
}

if ( ! defined( 'VMFA_MEDIA_CLEANUP_BASENAME' ) ) {
	define( 'VMFA_MEDIA_CLEANUP_BASENAME', 'vmfa-media-cleanup/vmfa-media-cleanup.php' );
}
