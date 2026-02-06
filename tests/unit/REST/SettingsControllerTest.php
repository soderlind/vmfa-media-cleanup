<?php
/**
 * Tests for REST SettingsController.
 *
 * @package VmfaMediaCleanup\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use VmfaMediaCleanup\REST\SettingsController;

beforeEach( function () {
	$this->controller = new SettingsController();
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

it( 'get_settings returns defaults when none stored', function () {
	$request = Mockery::mock( 'WP_REST_Request' );

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array() );

	Functions\expect( 'wp_parse_args' )
		->andReturnUsing( function ( $args, $defaults ) {
			return array_merge( $defaults, $args );
		} );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->get_settings( $request );

	expect( $result->data )->toHaveKey( 'archive_folder_name' );
	expect( $result->data[ 'archive_folder_name' ] )->toBe( 'Archive' );
	expect( $result->data[ 'auto_scan_on_upload' ] )->toBeFalse();
} );

it( 'get_settings merges stored with defaults', function () {
	$request = Mockery::mock( 'WP_REST_Request' );

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array( 'archive_folder_name' => 'My Archive' ) );

	Functions\expect( 'wp_parse_args' )
		->andReturnUsing( function ( $args, $defaults ) {
			return array_merge( $defaults, $args );
		} );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->get_settings( $request );

	expect( $result->data[ 'archive_folder_name' ] )->toBe( 'My Archive' );
} );

it( 'update_settings sanitizes and saves values', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_params' )->andReturn(
		array(
			'archive_folder_name'       => 'Custom Archive',
			'oversized_threshold_image' => 5242880,
			'auto_scan_on_upload'       => true,
		)
	);

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array() );

	Functions\expect( 'wp_parse_args' )
		->andReturnUsing( function ( $args, $defaults ) {
			return array_merge( $defaults, $args );
		} );

	Functions\expect( 'absint' )->andReturnUsing( function ( $v ) {
		return (int) $v;
	} );

	Functions\expect( 'sanitize_text_field' )->andReturnUsing( function ( $v ) {
		return $v;
	} );

	Functions\expect( 'update_option' )
		->once()
		->andReturn( true );

	Functions\expect( 'do_action' )->andReturn( null );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->update_settings( $request );

	expect( $result->data[ 'archive_folder_name' ] )->toBe( 'Custom Archive' );
	expect( $result->data[ 'oversized_threshold_image' ] )->toBe( 5242880 );
	expect( $result->data[ 'auto_scan_on_upload' ] )->toBeTrue();
} );

it( 'update_settings rejects invalid content_scan_depth', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_params' )->andReturn(
		array(
			'content_scan_depth' => 'invalid_value',
		)
	);

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array() );

	Functions\expect( 'wp_parse_args' )
		->andReturnUsing( function ( $args, $defaults ) {
			return array_merge( $defaults, $args );
		} );

	Functions\expect( 'absint' )->andReturnUsing( function ( $v ) {
		return (int) $v;
	} );

	Functions\expect( 'sanitize_text_field' )->andReturnUsing( function ( $v ) {
		return $v;
	} );

	Functions\expect( 'update_option' )->once()->andReturn( true );
	Functions\expect( 'do_action' )->andReturn( null );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->update_settings( $request );

	// Should keep the default 'full' instead of 'invalid_value'.
	expect( $result->data[ 'content_scan_depth' ] )->toBe( 'full' );
} );
