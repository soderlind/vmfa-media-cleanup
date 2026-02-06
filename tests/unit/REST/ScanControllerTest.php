<?php
/**
 * Tests for REST ScanController.
 *
 * @package VmfaMediaCleanup\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use VmfaMediaCleanup\REST\ScanController;
use VmfaMediaCleanup\Services\ScanService;

beforeEach( function () {
	$this->scan_service = Mockery::mock( ScanService::class);
	$this->controller   = new ScanController( $this->scan_service );
} );

it( 'check_permission returns true for admins', function () {
	Functions\expect( 'current_user_can' )
		->with( 'manage_options' )
		->andReturn( true );

	expect( $this->controller->check_permission() )->toBeTrue();
} );

it( 'check_permission returns WP_Error for non-admins', function () {
	Functions\expect( 'current_user_can' )
		->with( 'manage_options' )
		->andReturn( false );

	Functions\expect( '__' )
		->andReturnUsing( function ( $text ) {
			return $text;
		} );

	$result = $this->controller->check_permission();

	expect( $result )->toBeInstanceOf( WP_Error::class);
} );

it( 'start_scan returns error when scan fails', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'types' )->andReturn( array() );

	$this->scan_service->shouldReceive( 'start_scan' )
		->with( array() )
		->andReturn( false );

	Functions\expect( '__' )
		->andReturnUsing( function ( $text ) {
			return $text;
		} );

	$result = $this->controller->start_scan( $request );

	expect( $result )->toBeInstanceOf( WP_Error::class);
} );

it( 'start_scan returns success response', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'types' )->andReturn( array( 'unused' ) );

	$this->scan_service->shouldReceive( 'start_scan' )
		->with( array( 'unused' ) )
		->andReturn( true );

	$progress = array( 'status' => 'running', 'phase' => 'indexing' );
	$this->scan_service->shouldReceive( 'get_progress' )
		->andReturn( $progress );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->start_scan( $request );

	expect( $result->data[ 'started' ] )->toBeTrue();
	expect( $result->data[ 'progress' ][ 'status' ] )->toBe( 'running' );
} );

it( 'get_status returns scan progress', function () {
	$request = Mockery::mock( 'WP_REST_Request' );

	$progress = array( 'status' => 'running', 'phase' => 'hashing', 'processed' => 50 );
	$this->scan_service->shouldReceive( 'get_progress' )
		->andReturn( $progress );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->get_status( $request );

	expect( $result->data[ 'status' ] )->toBe( 'running' );
	expect( $result->data[ 'phase' ] )->toBe( 'hashing' );
} );

it( 'cancel_scan returns success when cancelled', function () {
	$request = Mockery::mock( 'WP_REST_Request' );

	$this->scan_service->shouldReceive( 'cancel_scan' )
		->andReturn( true );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->cancel_scan( $request );

	expect( $result->data[ 'cancelled' ] )->toBeTrue();
} );

it( 'cancel_scan returns error when cannot cancel', function () {
	$request = Mockery::mock( 'WP_REST_Request' );

	$this->scan_service->shouldReceive( 'cancel_scan' )
		->andReturn( false );

	Functions\expect( '__' )
		->andReturnUsing( function ( $text ) {
			return $text;
		} );

	$result = $this->controller->cancel_scan( $request );

	expect( $result )->toBeInstanceOf( WP_Error::class);
} );

it( 'reset_scan returns success', function () {
	$request = Mockery::mock( 'WP_REST_Request' );

	$this->scan_service->shouldReceive( 'reset_scan' )
		->once();

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->reset_scan( $request );

	expect( $result->data[ 'reset' ] )->toBeTrue();
} );

it( 'get_stats returns statistics', function () {
	$request = Mockery::mock( 'WP_REST_Request' );

	$stats = array(
		'total_media'      => 100,
		'unused_count'     => 10,
		'duplicate_count'  => 5,
		'duplicate_groups' => 2,
		'oversized_count'  => 3,
		'flagged_count'    => 1,
	);

	$this->scan_service->shouldReceive( 'get_stats' )
		->andReturn( $stats );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->get_stats( $request );

	expect( $result->data[ 'total_media' ] )->toBe( 100 );
	expect( $result->data[ 'unused_count' ] )->toBe( 10 );
} );
