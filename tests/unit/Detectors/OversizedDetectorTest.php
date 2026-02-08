<?php
/**
 * Tests for OversizedDetector.
 *
 * @package VmfaMediaCleanup\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use VmfaMediaCleanup\Detectors\OversizedDetector;

beforeEach( function () {
	$this->detector = new OversizedDetector();
} );

it( 'returns the correct type identifier', function () {
	expect( $this->detector->get_type() )->toBe( 'oversized' );
} );

it( 'returns the correct label', function () {
	Functions\when( '__' )->returnArg();

	expect( $this->detector->get_label() )->toBe( 'Oversized' );
} );

it( 'detects an image exceeding the threshold', function () {
	$attachment_id = 42;
	$file_path     = tempnam( sys_get_temp_dir(), 'vmfa_oversized_' );
	file_put_contents( $file_path, str_repeat( 'x', 100 ) ); // Real file for file_exists.
	$file_size = 5 * 1024 * 1024; // 5 MB (reported by wp_filesize mock).
	$threshold = 2 * 1024 * 1024; // 2 MB.

	// Settings with image threshold.
	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array( 'image_size_threshold' => $threshold ) );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_oversized_thresholds', Mockery::type( 'array' ) )
		->andReturnUsing( fn( $hook, $thresholds ) => $thresholds );

	Functions\expect( 'get_attached_file' )
		->with( $attachment_id )
		->andReturn( $file_path );

	Functions\expect( 'wp_filesize' )
		->with( $file_path )
		->andReturn( $file_size );

	Functions\expect( 'get_post_mime_type' )
		->with( $attachment_id )
		->andReturn( 'image/jpeg' );

	$post            = new stdClass();
	$post->post_date = '2025-03-15 12:00:00';

	Functions\expect( 'get_post' )
		->with( $attachment_id )
		->andReturn( $post );

	Functions\expect( 'wp_get_attachment_metadata' )
		->with( $attachment_id )
		->andReturn( array( 'width' => 4000, 'height' => 3000 ) );

	Functions\expect( 'get_the_title' )
		->with( $attachment_id )
		->andReturn( 'Large Image' );

	Functions\expect( 'wp_get_attachment_image_url' )
		->with( $attachment_id, 'thumbnail' )
		->andReturn( 'https://example.com/thumb.jpg' );

	$results = $this->detector->detect( array( $attachment_id ) );

	expect( $results )->toHaveCount( 1 );
	expect( $results[ $attachment_id ][ 'type' ] )->toBe( 'oversized' );
	expect( $results[ $attachment_id ][ 'file_size' ] )->toBe( $file_size );
	expect( $results[ $attachment_id ][ 'threshold' ] )->toBe( $threshold );
	expect( $results[ $attachment_id ][ 'over_by' ] )->toBe( $file_size - $threshold );

	unlink( $file_path );
} );

it( 'does not flag files under the threshold', function () {
	$attachment_id = 42;
	$file_path     = '/tmp/small-image.jpg';
	$file_size     = 500000; // 500 KB.
	$threshold     = 2097152; // 2 MB.

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array( 'image_size_threshold' => $threshold ) );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_oversized_thresholds', Mockery::type( 'array' ) )
		->andReturnUsing( fn( $hook, $thresholds ) => $thresholds );

	Functions\expect( 'get_attached_file' )
		->with( $attachment_id )
		->andReturn( $file_path );

	Functions\expect( 'wp_filesize' )
		->with( $file_path )
		->andReturn( $file_size );

	Functions\expect( 'get_post_mime_type' )
		->with( $attachment_id )
		->andReturn( 'image/jpeg' );

	$results = $this->detector->detect( array( $attachment_id ) );

	expect( $results )->toBeEmpty();
} );

it( 'skips attachments with no file path', function () {
	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array() );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_oversized_thresholds', Mockery::type( 'array' ) )
		->andReturnUsing( fn( $hook, $thresholds ) => $thresholds );

	Functions\expect( 'get_attached_file' )
		->with( 42 )
		->andReturn( false );

	$results = $this->detector->detect( array( 42 ) );

	expect( $results )->toBeEmpty();
} );

it( 'uses different thresholds per mime type', function () {
	// Image under threshold, video over threshold.
	$image_id = 10;
	$video_id = 20;

	$image_size   = 1 * 1024 * 1024; // 1 MB (under 2 MB threshold).
	$video_size   = 60 * 1024 * 1024; // 60 MB (over 50 MB threshold).
	$image_thresh = 2 * 1024 * 1024;
	$video_thresh = 50 * 1024 * 1024;

	// Create real temp files so file_exists() returns true.
	$image_path = tempnam( sys_get_temp_dir(), 'vmfa_img_' );
	$video_path = tempnam( sys_get_temp_dir(), 'vmfa_vid_' );
	file_put_contents( $image_path, 'x' );
	file_put_contents( $video_path, 'x' );

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn(
			array(
				'oversized_threshold_image' => $image_thresh,
				'oversized_threshold_video' => $video_thresh,
			)
		);

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_oversized_thresholds', Mockery::type( 'array' ) )
		->andReturnUsing( fn( $hook, $thresholds ) => $thresholds );

	// Use aliases so multiple calls with different args work correctly.
	Functions\when( 'get_attached_file' )->alias( function ( $id ) use ( $image_id, $video_id, $image_path, $video_path ) {
		return match ( $id ) {
			$image_id => $image_path,
			$video_id => $video_path,
			default   => false,
		};
	} );

	Functions\when( 'wp_filesize' )->alias( function ( $path ) use ( $image_path, $video_path, $image_size, $video_size ) {
		return match ( $path ) {
			$image_path => $image_size,
			$video_path => $video_size,
			default     => 0,
		};
	} );

	Functions\when( 'get_post_mime_type' )->alias( function ( $id ) use ( $image_id, $video_id ) {
		return match ( $id ) {
			$image_id => 'image/jpeg',
			$video_id => 'video/mp4',
			default   => '',
		};
	} );

	$post            = new stdClass();
	$post->post_date = '2025-01-01 00:00:00';

	Functions\expect( 'get_post' )
		->with( $video_id )
		->andReturn( $post );

	Functions\expect( 'wp_get_attachment_metadata' )
		->with( $video_id )
		->andReturn( array() );

	Functions\expect( 'get_the_title' )
		->with( $video_id )
		->andReturn( 'Big Video' );

	Functions\expect( 'wp_get_attachment_image_url' )
		->with( $video_id, 'thumbnail' )
		->andReturn( '' );

	$results = $this->detector->detect( array( $image_id, $video_id ) );

	// Only video should be flagged.
	expect( $results )->toHaveCount( 1 );
	expect( $results )->toHaveKey( $video_id );
	expect( $results )->not->toHaveKey( $image_id );

	unlink( $image_path );
	unlink( $video_path );
} );

it( 'uses document threshold for unknown mime types', function () {
	$attachment_id = 42;
	$file_path     = tempnam( sys_get_temp_dir(), 'vmfa_zip_' );
	file_put_contents( $file_path, 'x' ); // Real file for file_exists.
	$file_size  = 15 * 1024 * 1024; // 15 MB.
	$doc_thresh = 10 * 1024 * 1024; // 10 MB.

	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array( 'oversized_threshold_document' => $doc_thresh ) );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_oversized_thresholds', Mockery::type( 'array' ) )
		->andReturnUsing( fn( $hook, $thresholds ) => $thresholds );

	Functions\expect( 'get_attached_file' )
		->with( $attachment_id )
		->andReturn( $file_path );

	Functions\expect( 'wp_filesize' )
		->with( $file_path )
		->andReturn( $file_size );

	Functions\expect( 'get_post_mime_type' )
		->with( $attachment_id )
		->andReturn( 'application/zip' );

	$post            = new stdClass();
	$post->post_date = '2025-01-01 00:00:00';

	Functions\expect( 'get_post' )
		->with( $attachment_id )
		->andReturn( $post );

	Functions\expect( 'wp_get_attachment_metadata' )
		->with( $attachment_id )
		->andReturn( array() );

	Functions\expect( 'get_the_title' )
		->with( $attachment_id )
		->andReturn( 'Large ZIP' );

	Functions\expect( 'wp_get_attachment_image_url' )
		->with( $attachment_id, 'thumbnail' )
		->andReturn( '' );

	$results = $this->detector->detect( array( $attachment_id ) );

	expect( $results )->toHaveCount( 1 );
	expect( $results[ $attachment_id ][ 'threshold' ] )->toBe( $doc_thresh );

	unlink( $file_path );
} );
