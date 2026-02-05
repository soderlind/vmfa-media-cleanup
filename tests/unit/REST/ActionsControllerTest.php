<?php
/**
 * Tests for REST ActionsController.
 *
 * @package VmfaMediaCleanup\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use VmfaMediaCleanup\REST\ActionsController;

beforeEach( function () {
	$this->controller = new ActionsController();
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

	expect( $result )->toBeInstanceOf( WP_Error::class );
} );

it( 'trash_media requires confirmation', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'confirm' )->andReturn( false );

	Functions\expect( '__' )
		->andReturnUsing( function ( $text ) {
			return $text;
		} );

	$result = $this->controller->trash_media( $request );

	expect( $result )->toBeInstanceOf( WP_Error::class );
} );

it( 'trash_media trashes confirmed items', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'confirm' )->andReturn( true );
	$request->shouldReceive( 'get_param' )->with( 'ids' )->andReturn( array( 42, 43 ) );

	Functions\expect( 'absint' )->andReturnUsing( function ( $v ) {
		return (int) $v;
	} );

	Functions\expect( 'do_action' )->andReturn( null );

	Functions\when( 'wp_trash_post' )->alias( function ( $id ) {
		return $id === 42 ? (object) array( 'ID' => 42 ) : false;
	} );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->trash_media( $request );

	expect( $result->data['action'] )->toBe( 'trash' );
	expect( $result->data['success'] )->toBe( 1 );
	expect( $result->data['failed'] )->toBe( 1 );
} );

it( 'archive_media requires confirmation', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'confirm' )->andReturn( false );

	Functions\expect( '__' )
		->andReturnUsing( function ( $text ) {
			return $text;
		} );

	$result = $this->controller->archive_media( $request );

	expect( $result )->toBeInstanceOf( WP_Error::class );
} );

it( 'flag_media sets meta and returns count', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'ids' )->andReturn( array( 42, 43 ) );

	Functions\expect( 'absint' )->andReturnUsing( function ( $v ) {
		return (int) $v;
	} );

	Functions\expect( 'current_time' )
		->with( 'mysql', true )
		->andReturn( '2024-01-01 00:00:00' );

	Functions\expect( 'update_post_meta' )->times( 2 )->andReturn( true );
	Functions\expect( 'do_action' )->andReturn( null );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->flag_media( $request );

	expect( $result->data['action'] )->toBe( 'flag' );
	expect( $result->data['success'] )->toBe( 2 );
} );

it( 'unflag_media deletes meta and returns count', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'ids' )->andReturn( array( 42 ) );

	Functions\expect( 'absint' )->andReturnUsing( function ( $v ) {
		return (int) $v;
	} );

	Functions\expect( 'delete_post_meta' )
		->with( 42, '_vmfa_flagged_for_review' )
		->once()
		->andReturn( true );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->unflag_media( $request );

	expect( $result->data['action'] )->toBe( 'unflag' );
	expect( $result->data['success'] )->toBe( 1 );
} );

it( 'set_primary clears old and sets new primary', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'id' )->andReturn( 42 );
	$request->shouldReceive( 'get_param' )->with( 'group_ids' )->andReturn( array( 42, 43, 44 ) );

	Functions\expect( 'absint' )->andReturnUsing( function ( $v ) {
		return (int) $v;
	} );

	// Clear primary from all 3.
	Functions\expect( 'delete_post_meta' )->times( 3 )->andReturn( true );

	// Set the new primary.
	Functions\expect( 'update_post_meta' )
		->with( 42, '_vmfa_duplicate_primary', true )
		->once()
		->andReturn( true );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->set_primary( $request );

	expect( $result->data['action'] )->toBe( 'set-primary' );
	expect( $result->data['primary_id'] )->toBe( 42 );
} );
