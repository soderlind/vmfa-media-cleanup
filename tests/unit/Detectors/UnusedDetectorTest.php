<?php
/**
 * Tests for UnusedDetector.
 *
 * @package VmfaMediaCleanup\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use VmfaMediaCleanup\Detectors\UnusedDetector;
use VmfaMediaCleanup\Services\ReferenceIndex;

beforeEach( function () {
	$this->reference_index = Mockery::mock( ReferenceIndex::class);
	$this->detector        = new UnusedDetector( $this->reference_index );
} );

it( 'returns the correct type identifier', function () {
	expect( $this->detector->get_type() )->toBe( 'unused' );
} );

it( 'returns the correct label', function () {
	Functions\when( '__' )->returnArg();

	expect( $this->detector->get_label() )->toBe( 'Unused' );
} );

it( 'detects unused attachments that are not referenced', function () {
	$attachment_id = 42;

	// Not a protected ID.
	Functions\expect( 'get_option' )
		->with( 'site_icon', 0 )
		->andReturn( 0 );

	Functions\expect( 'get_theme_mod' )
		->with( 'custom_logo', 0 )
		->andReturn( 0 );

	// Not referenced.
	$this->reference_index
		->shouldReceive( 'is_referenced' )
		->with( $attachment_id )
		->andReturn( false );

	// Filter keeps it unused.
	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_is_unused', true, $attachment_id )
		->andReturn( true );

	// Mock post data.
	$post            = new stdClass();
	$post->post_date = '2025-01-01 00:00:00';

	Functions\expect( 'get_post' )
		->with( $attachment_id )
		->andReturn( $post );

	Functions\expect( 'get_attached_file' )
		->with( $attachment_id )
		->andReturn( '/tmp/test-image.jpg' );

	Functions\expect( 'wp_get_attachment_metadata' )
		->with( $attachment_id )
		->andReturn( array( 'width' => 800, 'height' => 600 ) );

	Functions\expect( 'get_the_title' )
		->with( $attachment_id )
		->andReturn( 'Test Image' );

	Functions\expect( 'get_post_mime_type' )
		->with( $attachment_id )
		->andReturn( 'image/jpeg' );

	Functions\expect( 'wp_filesize' )
		->andReturn( 123456 );

	Functions\expect( 'wp_get_attachment_image_url' )
		->with( $attachment_id, 'thumbnail' )
		->andReturn( 'https://example.com/thumb.jpg' );

	$results = $this->detector->detect( array( $attachment_id ) );

	expect( $results )->toHaveKey( $attachment_id );
	expect( $results[ $attachment_id ][ 'type' ] )->toBe( 'unused' );
	expect( $results[ $attachment_id ][ 'attachment_id' ] )->toBe( $attachment_id );
	expect( $results[ $attachment_id ][ 'title' ] )->toBe( 'Test Image' );
	expect( $results[ $attachment_id ][ 'width' ] )->toBe( 800 );
	expect( $results[ $attachment_id ][ 'height' ] )->toBe( 600 );
} );

it( 'skips referenced attachments', function () {
	$attachment_id = 42;

	Functions\expect( 'get_option' )
		->with( 'site_icon', 0 )
		->andReturn( 0 );

	Functions\expect( 'get_theme_mod' )
		->with( 'custom_logo', 0 )
		->andReturn( 0 );

	$this->reference_index
		->shouldReceive( 'is_referenced' )
		->with( $attachment_id )
		->andReturn( true );

	$results = $this->detector->detect( array( $attachment_id ) );

	expect( $results )->toBeEmpty();
} );

it( 'skips site icon attachment', function () {
	$site_icon_id = 99;

	Functions\expect( 'get_option' )
		->with( 'site_icon', 0 )
		->andReturn( $site_icon_id );

	Functions\expect( 'get_theme_mod' )
		->with( 'custom_logo', 0 )
		->andReturn( 0 );

	$results = $this->detector->detect( array( $site_icon_id ) );

	expect( $results )->toBeEmpty();
} );

it( 'skips custom logo attachment', function () {
	$logo_id = 88;

	Functions\expect( 'get_option' )
		->with( 'site_icon', 0 )
		->andReturn( 0 );

	Functions\expect( 'get_theme_mod' )
		->with( 'custom_logo', 0 )
		->andReturn( $logo_id );

	$results = $this->detector->detect( array( $logo_id ) );

	expect( $results )->toBeEmpty();
} );

it( 'respects the vmfa_cleanup_is_unused filter', function () {
	$attachment_id = 42;

	Functions\expect( 'get_option' )
		->with( 'site_icon', 0 )
		->andReturn( 0 );

	Functions\expect( 'get_theme_mod' )
		->with( 'custom_logo', 0 )
		->andReturn( 0 );

	$this->reference_index
		->shouldReceive( 'is_referenced' )
		->with( $attachment_id )
		->andReturn( false );

	// Filter overrides — mark as NOT unused.
	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_is_unused', true, $attachment_id )
		->andReturn( false );

	$results = $this->detector->detect( array( $attachment_id ) );

	expect( $results )->toBeEmpty();
} );

it( 'handles multiple attachments with mixed states', function () {
	$used_id   = 10;
	$unused_id = 20;

	Functions\expect( 'get_option' )
		->with( 'site_icon', 0 )
		->andReturn( 0 );

	Functions\expect( 'get_theme_mod' )
		->with( 'custom_logo', 0 )
		->andReturn( 0 );

	$this->reference_index
		->shouldReceive( 'is_referenced' )
		->with( $used_id )
		->andReturn( true );

	$this->reference_index
		->shouldReceive( 'is_referenced' )
		->with( $unused_id )
		->andReturn( false );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_is_unused', true, $unused_id )
		->andReturn( true );

	$post            = new stdClass();
	$post->post_date = '2025-06-15 10:00:00';

	Functions\expect( 'get_post' )
		->with( $unused_id )
		->andReturn( $post );

	Functions\expect( 'get_attached_file' )
		->with( $unused_id )
		->andReturn( '/tmp/file.png' );

	Functions\expect( 'wp_get_attachment_metadata' )
		->with( $unused_id )
		->andReturn( array() );

	Functions\expect( 'get_the_title' )
		->with( $unused_id )
		->andReturn( 'Unused File' );

	Functions\expect( 'get_post_mime_type' )
		->with( $unused_id )
		->andReturn( 'image/png' );

	Functions\expect( 'wp_filesize' )
		->andReturn( 5000 );

	Functions\expect( 'wp_get_attachment_image_url' )
		->with( $unused_id, 'thumbnail' )
		->andReturn( '' );

	$results = $this->detector->detect( array( $used_id, $unused_id ) );

	expect( $results )->toHaveCount( 1 );
	expect( $results )->toHaveKey( $unused_id );
	expect( $results )->not->toHaveKey( $used_id );
} );
