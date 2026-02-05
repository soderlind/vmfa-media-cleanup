<?php
/**
 * Tests for HashService.
 *
 * @package VmfaMediaCleanup\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use VmfaMediaCleanup\Services\HashService;

beforeEach( function () {
	$this->service = new HashService();
} );

it( 'returns cached hash when available and algorithm matches', function () {
	$attachment_id = 42;

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array( 'hash_algorithm' => 'sha256' ) );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_hash_algorithm', 'sha256' )
		->andReturn( 'sha256' );

	Functions\when( 'get_post_meta' )->alias( function ( $id, $key, $single ) {
		if ( '_vmfa_file_hash' === $key ) {
			return 'cached_hash_value';
		}
		if ( '_vmfa_hash_algo' === $key ) {
			return 'sha256';
		}
		return '';
	} );

	$result = $this->service->get_hash( $attachment_id );

	expect( $result )->toBe( 'cached_hash_value' );
} );

it( 'recomputes hash when algorithm changes', function () {
	$attachment_id = 42;
	$file_path     = tempnam( sys_get_temp_dir(), 'vmfa_test_' );
	file_put_contents( $file_path, 'test file content for hashing' );

	$expected_hash = hash_file( 'sha512', $file_path );

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array( 'hash_algorithm' => 'sha512' ) );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_hash_algorithm', 'sha512' )
		->andReturn( 'sha512' );

	Functions\expect( 'get_post_meta' )
		->with( $attachment_id, '_vmfa_file_hash', true )
		->andReturn( 'old_sha256_hash' );

	Functions\expect( 'get_post_meta' )
		->with( $attachment_id, '_vmfa_hash_algo', true )
		->andReturn( 'sha256' ); // Different from current sha512.

	Functions\expect( 'get_attached_file' )
		->with( $attachment_id )
		->andReturn( $file_path );

	Functions\expect( 'update_post_meta' )
		->with( $attachment_id, '_vmfa_file_hash', $expected_hash )
		->andReturn( true );

	Functions\expect( 'update_post_meta' )
		->with( $attachment_id, '_vmfa_hash_algo', 'sha512' )
		->andReturn( true );

	$result = $this->service->get_hash( $attachment_id );

	expect( $result )->toBe( $expected_hash );

	unlink( $file_path );
} );

it( 'computes hash and stores in meta', function () {
	$attachment_id = 42;
	$file_path     = tempnam( sys_get_temp_dir(), 'vmfa_test_' );
	file_put_contents( $file_path, 'some binary content' );

	$expected_hash = hash_file( 'sha256', $file_path );

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array() );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_hash_algorithm', 'sha256' )
		->andReturn( 'sha256' );

	Functions\expect( 'get_post_meta' )
		->with( $attachment_id, '_vmfa_file_hash', true )
		->andReturn( '' ); // No cached hash.

	Functions\expect( 'get_post_meta' )
		->with( $attachment_id, '_vmfa_hash_algo', true )
		->andReturn( '' );

	Functions\expect( 'get_attached_file' )
		->with( $attachment_id )
		->andReturn( $file_path );

	Functions\expect( 'update_post_meta' )
		->with( $attachment_id, '_vmfa_file_hash', $expected_hash )
		->andReturn( true );

	Functions\expect( 'update_post_meta' )
		->with( $attachment_id, '_vmfa_hash_algo', 'sha256' )
		->andReturn( true );

	$result = $this->service->get_hash( $attachment_id );

	expect( $result )->toBe( $expected_hash );

	unlink( $file_path );
} );

it( 'returns empty string for missing file', function () {
	$attachment_id = 42;

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array() );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_hash_algorithm', 'sha256' )
		->andReturn( 'sha256' );

	Functions\expect( 'get_post_meta' )
		->with( $attachment_id, '_vmfa_file_hash', true )
		->andReturn( '' );

	Functions\expect( 'get_post_meta' )
		->with( $attachment_id, '_vmfa_hash_algo', true )
		->andReturn( '' );

	Functions\expect( 'get_attached_file' )
		->with( $attachment_id )
		->andReturn( '/nonexistent/path/file.jpg' );

	$result = $this->service->get_hash( $attachment_id );

	expect( $result )->toBe( '' );
} );

it( 'force flag bypasses cache', function () {
	$attachment_id = 42;
	$file_path     = tempnam( sys_get_temp_dir(), 'vmfa_test_' );
	file_put_contents( $file_path, 'force hash content' );

	$expected_hash = hash_file( 'sha256', $file_path );

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array() );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_hash_algorithm', 'sha256' )
		->andReturn( 'sha256' );

	// get_post_meta should NOT be called for cache check when force=true.

	Functions\expect( 'get_attached_file' )
		->with( $attachment_id )
		->andReturn( $file_path );

	Functions\expect( 'update_post_meta' )
		->with( $attachment_id, '_vmfa_file_hash', $expected_hash )
		->andReturn( true );

	Functions\expect( 'update_post_meta' )
		->with( $attachment_id, '_vmfa_hash_algo', 'sha256' )
		->andReturn( true );

	$result = $this->service->get_hash( $attachment_id, true );

	expect( $result )->toBe( $expected_hash );

	unlink( $file_path );
} );

it( 'hash_batch processes multiple attachments', function () {
	$ids       = array( 10, 20, 30 );
	$file_path = tempnam( sys_get_temp_dir(), 'vmfa_test_' );
	file_put_contents( $file_path, 'batch content' );

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array() );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_hash_algorithm', 'sha256' )
		->andReturn( 'sha256' );

	Functions\expect( 'get_post_meta' )
		->andReturn( '' );

	Functions\expect( 'get_attached_file' )
		->andReturn( $file_path );

	Functions\expect( 'update_post_meta' )
		->andReturn( true );

	$count = $this->service->hash_batch( $ids );

	expect( $count )->toBe( 3 );

	unlink( $file_path );
} );

it( 'clear_hash deletes meta keys', function () {
	$attachment_id = 42;

	$deleted_keys = array();

	Functions\when( 'delete_post_meta' )->alias( function ( $id, $key ) use ( &$deleted_keys ) {
		$deleted_keys[] = $key;
		return true;
	} );

	$this->service->clear_hash( $attachment_id );

	expect( $deleted_keys )->toContain( '_vmfa_file_hash' );
	expect( $deleted_keys )->toContain( '_vmfa_hash_algo' );
} );
