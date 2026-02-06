<?php
/**
 * Tests for DuplicateDetector.
 *
 * @package VmfaMediaCleanup\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use VmfaMediaCleanup\Detectors\DuplicateDetector;
use VmfaMediaCleanup\Services\HashService;

beforeEach( function () {
	$this->hash_service = Mockery::mock( HashService::class);
	$this->detector     = new DuplicateDetector( $this->hash_service );
} );

it( 'returns the correct type identifier', function () {
	expect( $this->detector->get_type() )->toBe( 'duplicate' );
} );

it( 'returns the correct label', function () {
	Functions\when( '__' )->returnArg();

	expect( $this->detector->get_label() )->toBe( 'Duplicate' );
} );

it( 'detects duplicates when two attachments share a hash', function () {
	$id_a = 10;
	$id_b = 20;

	$post_a              = new stdClass();
	$post_a->post_status = 'inherit';
	$post_a->post_date   = '2025-01-01 00:00:00';

	$post_b              = new stdClass();
	$post_b->post_status = 'inherit';
	$post_b->post_date   = '2025-06-01 00:00:00';

	Functions\expect( 'get_post' )
		->with( $id_a )
		->andReturn( $post_a );

	Functions\expect( 'get_post' )
		->with( $id_b )
		->andReturn( $post_b );

	$this->hash_service
		->shouldReceive( 'get_hash' )
		->with( $id_a )
		->andReturn( 'abc123hash' );

	$this->hash_service
		->shouldReceive( 'get_hash' )
		->with( $id_b )
		->andReturn( 'abc123hash' );

	Functions\expect( 'get_post_meta' )
		->with( $id_a, '_vmfa_duplicate_primary', true )
		->andReturn( '' );

	Functions\expect( 'get_post_meta' )
		->with( $id_b, '_vmfa_duplicate_primary', true )
		->andReturn( '' );

	Functions\expect( 'get_attached_file' )
		->andReturn( '/tmp/file.jpg' );

	Functions\expect( 'wp_get_attachment_metadata' )
		->andReturn( array( 'width' => 100, 'height' => 100 ) );

	Functions\expect( 'get_the_title' )
		->andReturnUsing( fn( $id ) => "Title $id" );

	Functions\expect( 'get_post_mime_type' )
		->andReturn( 'image/jpeg' );

	Functions\expect( 'wp_filesize' )
		->andReturn( 10000 );

	Functions\expect( 'wp_get_attachment_image_url' )
		->andReturn( 'https://example.com/thumb.jpg' );

	$results = $this->detector->detect( array( $id_a, $id_b ) );

	expect( $results )->toHaveCount( 2 );
	expect( $results[ $id_a ][ 'hash' ] )->toBe( 'abc123hash' );
	expect( $results[ $id_b ][ 'hash' ] )->toBe( 'abc123hash' );
	// Oldest is primary.
	expect( $results[ $id_a ][ 'is_primary' ] )->toBeTrue();
	expect( $results[ $id_b ][ 'is_primary' ] )->toBeFalse();
} );

it( 'returns empty when no duplicates exist', function () {
	$id_a = 10;
	$id_b = 20;

	$post              = new stdClass();
	$post->post_status = 'inherit';

	Functions\expect( 'get_post' )
		->andReturn( $post );

	$this->hash_service
		->shouldReceive( 'get_hash' )
		->with( $id_a )
		->andReturn( 'hash_unique_a' );

	$this->hash_service
		->shouldReceive( 'get_hash' )
		->with( $id_b )
		->andReturn( 'hash_unique_b' );

	$results = $this->detector->detect( array( $id_a, $id_b ) );

	expect( $results )->toBeEmpty();
} );

it( 'skips trashed attachments', function () {
	$id_a = 10;
	$id_b = 20;

	$post_a              = new stdClass();
	$post_a->post_status = 'trash';

	$post_b              = new stdClass();
	$post_b->post_status = 'inherit';

	Functions\expect( 'get_post' )
		->with( $id_a )
		->andReturn( $post_a );

	Functions\expect( 'get_post' )
		->with( $id_b )
		->andReturn( $post_b );

	$this->hash_service
		->shouldReceive( 'get_hash' )
		->with( $id_b )
		->andReturn( 'some_hash' );

	$results = $this->detector->detect( array( $id_a, $id_b ) );

	expect( $results )->toBeEmpty();
} );

it( 'skips attachments with empty hash', function () {
	$post              = new stdClass();
	$post->post_status = 'inherit';

	Functions\expect( 'get_post' )
		->andReturn( $post );

	$this->hash_service
		->shouldReceive( 'get_hash' )
		->with( 10 )
		->andReturn( '' );

	$results = $this->detector->detect( array( 10 ) );

	expect( $results )->toBeEmpty();
} );

it( 'respects user-assigned primary via meta', function () {
	$id_a = 10; // older
	$id_b = 20; // newer, but user set as primary

	$post_a              = new stdClass();
	$post_a->post_status = 'inherit';
	$post_a->post_date   = '2024-01-01 00:00:00';

	$post_b              = new stdClass();
	$post_b->post_status = 'inherit';
	$post_b->post_date   = '2025-06-01 00:00:00';

	Functions\expect( 'get_post' )
		->with( $id_a )
		->andReturn( $post_a );

	Functions\expect( 'get_post' )
		->with( $id_b )
		->andReturn( $post_b );

	$this->hash_service
		->shouldReceive( 'get_hash' )
		->with( $id_a )
		->andReturn( 'same_hash' );

	$this->hash_service
		->shouldReceive( 'get_hash' )
		->with( $id_b )
		->andReturn( 'same_hash' );

	// User assigned id_b as primary.
	Functions\when( 'get_post_meta' )->alias( function ( $id, $key, $single ) use ( $id_b ) {
		if ( '_vmfa_duplicate_primary' === $key ) {
			return $id === $id_b ? '1' : '';
		}
		return '';
	} );

	Functions\expect( 'get_attached_file' )->andReturn( '/tmp/file.jpg' );
	Functions\expect( 'wp_get_attachment_metadata' )->andReturn( array() );
	Functions\expect( 'get_the_title' )->andReturn( 'Title' );
	Functions\expect( 'get_post_mime_type' )->andReturn( 'image/jpeg' );
	Functions\expect( 'wp_filesize' )->andReturn( 5000 );
	Functions\expect( 'wp_get_attachment_image_url' )->andReturn( '' );

	$results = $this->detector->detect( array( $id_a, $id_b ) );

	expect( $results[ $id_b ][ 'is_primary' ] )->toBeTrue();
	expect( $results[ $id_a ][ 'is_primary' ] )->toBeFalse();
} );

it( 'groups duplicates correctly via get_groups', function () {
	$results = array(
		10 => array(
			'attachment_id' => 10,
			'hash'          => 'hash_abc',
			'group_count'   => 2,
			'is_primary'    => true,
		),
		20 => array(
			'attachment_id' => 20,
			'hash'          => 'hash_abc',
			'group_count'   => 2,
			'is_primary'    => false,
		),
		30 => array(
			'attachment_id' => 30,
			'hash'          => 'hash_xyz',
			'group_count'   => 2,
			'is_primary'    => true,
		),
		40 => array(
			'attachment_id' => 40,
			'hash'          => 'hash_xyz',
			'group_count'   => 2,
			'is_primary'    => false,
		),
	);

	$groups = $this->detector->get_groups( $results );

	expect( $groups )->toHaveCount( 2 );
	expect( $groups[ 0 ][ 'hash' ] )->toBe( 'hash_abc' );
	expect( $groups[ 0 ][ 'members' ] )->toHaveCount( 2 );
	expect( $groups[ 1 ][ 'hash' ] )->toBe( 'hash_xyz' );
	expect( $groups[ 1 ][ 'members' ] )->toHaveCount( 2 );
} );
