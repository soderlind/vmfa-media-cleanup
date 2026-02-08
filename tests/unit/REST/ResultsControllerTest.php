<?php
/**
 * Tests for REST ResultsController.
 *
 * @package VmfaMediaCleanup\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use VmfaMediaCleanup\REST\ResultsController;

beforeEach( function () {
	$this->controller = new ResultsController();
} );

it( 'check_permission returns true for admins', function () {
	Functions\expect( 'current_user_can' )
		->with( 'manage_options' )
		->andReturn( true );

	expect( $this->controller->check_permission() )->toBeTrue();
} );

it( 'get_results returns paginated results', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'type' )->andReturn( 'unused' );
	$request->shouldReceive( 'get_param' )->with( 'page' )->andReturn( 1 );
	$request->shouldReceive( 'get_param' )->with( 'per_page' )->andReturn( 2 );
	$request->shouldReceive( 'get_param' )->with( 'orderby' )->andReturn( 'file_size' );
	$request->shouldReceive( 'get_param' )->with( 'order' )->andReturn( 'desc' );

	Functions\expect( 'get_option' )
		->with( 'vmfa_cleanup_results', array() )
		->andReturn(
			array(
				'unused' => array(
					42 => array( 'title' => 'A', 'file_size' => 100 ),
					43 => array( 'title' => 'B', 'file_size' => 500 ),
					44 => array( 'title' => 'C', 'file_size' => 200 ),
				),
			)
		);

	// Mock post existence filtering.
	$mock_post            = new stdClass();
	$mock_post->post_type = 'attachment';
	Functions\when( 'clean_post_cache' )->justReturn();
	Functions\when( 'get_post' )->justReturn( $mock_post );
	Functions\when( 'get_post_field' )->justReturn( 'inherit' );
	Functions\when( 'get_post_meta' )->justReturn( '' );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->get_results( $request );

	expect( $result->data[ 'total' ] )->toBe( 3 );
	expect( $result->data[ 'page' ] )->toBe( 1 );
	expect( $result->data[ 'per_page' ] )->toBe( 2 );
	expect( $result->data[ 'total_pages' ] )->toBe( 2 );
	expect( $result->data[ 'items' ] )->toHaveCount( 2 );
} );

it( 'get_results returns empty for unknown type', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'type' )->andReturn( 'oversized' );
	$request->shouldReceive( 'get_param' )->with( 'page' )->andReturn( 1 );
	$request->shouldReceive( 'get_param' )->with( 'per_page' )->andReturn( 20 );
	$request->shouldReceive( 'get_param' )->with( 'orderby' )->andReturn( 'file_size' );
	$request->shouldReceive( 'get_param' )->with( 'order' )->andReturn( 'desc' );

	Functions\expect( 'get_option' )
		->with( 'vmfa_cleanup_results', array() )
		->andReturn( array() );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->get_results( $request );

	expect( $result->data[ 'total' ] )->toBe( 0 );
	expect( $result->data[ 'items' ] )->toBeEmpty();
} );

it( 'get_result_detail returns 404 for missing attachment', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'id' )->andReturn( 999 );

	Functions\expect( 'get_post' )
		->with( 999 )
		->andReturn( null );

	Functions\expect( '__' )
		->andReturnUsing( function ( $text ) {
			return $text;
		} );

	$result = $this->controller->get_result_detail( $request );

	expect( $result )->toBeInstanceOf( WP_Error::class);
} );

it( 'get_result_detail returns attachment info', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'id' )->andReturn( 42 );

	$attachment            = Mockery::mock( 'WP_Post' );
	$attachment->post_type = 'attachment';
	$attachment->post_date = '2024-01-01 00:00:00';
	$attachment->ID        = 42;

	Functions\expect( 'get_post' )
		->with( 42 )
		->andReturn( $attachment );

	Functions\expect( 'get_attached_file' )
		->with( 42 )
		->andReturn( '/srv/uploads/photo.jpg' );

	Functions\expect( 'wp_get_attachment_metadata' )
		->with( 42 )
		->andReturn( array( 'width' => 1920, 'height' => 1080 ) );

	Functions\expect( 'get_option' )
		->with( 'vmfa_cleanup_results', array() )
		->andReturn(
			array(
				'unused' => array( 42 => array( 'type' => 'unused' ) ),
			)
		);

	Functions\expect( 'get_post_meta' )
		->with( 42, '_vmfa_flagged_for_review', true )
		->andReturn( '' );

	Functions\expect( 'get_the_title' )
		->with( 42 )
		->andReturn( 'Photo' );

	Functions\expect( 'get_post_mime_type' )
		->with( 42 )
		->andReturn( 'image/jpeg' );

	Functions\expect( 'wp_get_attachment_image_url' )
		->with( 42, 'thumbnail' )
		->andReturn( 'http://example.com/thumb.jpg' );

	Functions\expect( 'get_edit_post_link' )
		->andReturn( 'http://example.com/wp-admin/post.php?post=42&action=edit' );

	Functions\expect( 'wp_filesize' )
		->andReturn( 1024000 );

	// ReferenceIndex mock via global $wpdb.
	$wpdb         = Mockery::mock( 'wpdb' );
	$wpdb->prefix = 'wp_';
	$wpdb->shouldReceive( 'prepare' )->andReturn( 'query' );
	$wpdb->shouldReceive( 'get_results' )->andReturn( array() );
	$GLOBALS[ 'wpdb' ] = $wpdb;

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->get_result_detail( $request );

	expect( $result->data[ 'attachment_id' ] )->toBe( 42 );
	expect( $result->data[ 'title' ] )->toBe( 'Photo' );
	expect( $result->data[ 'width' ] )->toBe( 1920 );
	expect( $result->data[ 'status' ] )->toContain( 'unused' );

	unset( $GLOBALS[ 'wpdb' ] );
} );

it( 'get_duplicate_groups returns empty when no duplicates', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'page' )->andReturn( 1 );
	$request->shouldReceive( 'get_param' )->with( 'per_page' )->andReturn( 10 );

	Functions\expect( 'get_option' )
		->with( 'vmfa_cleanup_results', array() )
		->andReturn( array() );

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->get_duplicate_groups( $request );

	expect( $result->data[ 'groups' ] )->toBeEmpty();
	expect( $result->data[ 'total' ] )->toBe( 0 );
} );

it( 'get_results returns trash items for trash type', function () {
	$request = Mockery::mock( 'WP_REST_Request' );
	$request->shouldReceive( 'get_param' )->with( 'type' )->andReturn( 'trash' );
	$request->shouldReceive( 'get_param' )->with( 'page' )->andReturn( 1 );
	$request->shouldReceive( 'get_param' )->with( 'per_page' )->andReturn( 20 );
	$request->shouldReceive( 'get_param' )->with( 'orderby' )->andReturn( 'file_size' );
	$request->shouldReceive( 'get_param' )->with( 'order' )->andReturn( 'desc' );

	$mock_post = (object) array(
		'ID'        => 42,
		'post_date' => '2024-01-01 00:00:00',
	);

	\WP_Query::$test_fixture = array(
		'posts'         => array( $mock_post ),
		'found_posts'   => 1,
		'max_num_pages' => 1,
	);

	Functions\expect( 'get_the_title' )->with( 42 )->andReturn( 'Trashed Image' );
	Functions\expect( 'get_attached_file' )->with( 42 )->andReturn( '/uploads/image.jpg' );
	Functions\expect( 'wp_get_attachment_metadata' )->with( 42 )->andReturn( array( 'width' => 800, 'height' => 600 ) );
	Functions\expect( 'get_post_mime_type' )->with( 42 )->andReturn( 'image/jpeg' );
	Functions\expect( 'wp_filesize' )->andReturn( 1024 );
	Functions\expect( 'wp_get_attachment_image_url' )->andReturn( '' );
	Functions\expect( 'get_post_meta' )->with( 42, '_wp_trash_meta_time', true )->andReturn( '1704067200' );

	Functions\when( 'wp_suspend_cache_addition' )->justReturn();

	Functions\expect( 'rest_ensure_response' )
		->andReturnUsing( function ( $data ) {
			return new \WP_REST_Response( $data );
		} );

	$result = $this->controller->get_results( $request );

	expect( $result->data[ 'total' ] )->toBe( 1 );
	expect( $result->data[ 'items' ] )->toHaveCount( 1 );
	expect( $result->data[ 'items' ][ 0 ][ 'type' ] )->toBe( 'trash' );
	expect( $result->data[ 'items' ][ 0 ][ 'title' ] )->toBe( 'Trashed Image' );

	\WP_Query::$test_fixture = null;
} );
