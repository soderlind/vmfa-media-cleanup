<?php
/**
 * Tests for ReferenceIndex.
 *
 * @package VmfaMediaCleanup\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use VmfaMediaCleanup\Services\ReferenceIndex;

beforeEach( function () {
	$this->index = new ReferenceIndex();

	// Set up a mock $wpdb global.
	$this->wpdb         = Mockery::mock( 'wpdb' );
	$this->wpdb->prefix = 'wp_';
	$this->wpdb->posts  = 'wp_posts';
	$GLOBALS['wpdb']    = $this->wpdb;
} );

afterEach( function () {
	unset( $GLOBALS['wpdb'] );
} );

it( 'is_referenced returns true when attachment has references', function () {
	$this->wpdb->shouldReceive( 'prepare' )
		->once()
		->andReturn( 'prepared_query' );

	$this->wpdb->shouldReceive( 'get_var' )
		->once()
		->with( 'prepared_query' )
		->andReturn( '3' );

	expect( $this->index->is_referenced( 42 ) )->toBeTrue();
} );

it( 'is_referenced returns false when no references exist', function () {
	$this->wpdb->shouldReceive( 'prepare' )
		->once()
		->andReturn( 'prepared_query' );

	$this->wpdb->shouldReceive( 'get_var' )
		->once()
		->with( 'prepared_query' )
		->andReturn( '0' );

	expect( $this->index->is_referenced( 99 ) )->toBeFalse();
} );

it( 'get_references returns array of source records', function () {
	$expected = array(
		array( 'source_type' => 'post_content', 'source_id' => '10' ),
		array( 'source_type' => 'featured_image', 'source_id' => '20' ),
	);

	$this->wpdb->shouldReceive( 'prepare' )
		->once()
		->andReturn( 'prepared_query' );

	$this->wpdb->shouldReceive( 'get_results' )
		->once()
		->with( 'prepared_query', ARRAY_A )
		->andReturn( $expected );

	$result = $this->index->get_references( 42 );

	expect( $result )->toBe( $expected );
	expect( $result )->toHaveCount( 2 );
} );

it( 'add_reference inserts a single record', function () {
	$inserted = false;
	$this->wpdb->shouldReceive( 'insert' )
		->once()
		->with(
			'wp_vmfa_media_references',
			array(
				'attachment_id' => 42,
				'source_type'   => 'post_content',
				'source_id'     => 10,
			),
			array( '%d', '%s', '%d' )
		)
		->andReturnUsing( function () use ( &$inserted ) {
			$inserted = true;
			return true;
		} );

	$this->index->add_reference( 42, 'post_content', 10 );

	expect( $inserted )->toBeTrue();
} );

it( 'add_references_batch does nothing for empty records', function () {
	// $wpdb->query should NOT be called.
	$this->wpdb->shouldNotReceive( 'query' );

	$this->index->add_references_batch( array() );

	expect( true )->toBeTrue(); // Assertion to avoid risky test.
} );

it( 'add_references_batch inserts records in bulk', function () {
	$records = array(
		array( 'attachment_id' => 42, 'source_type' => 'post_content', 'source_id' => 10 ),
		array( 'attachment_id' => 43, 'source_type' => 'featured_image', 'source_id' => 20 ),
	);

	$query_executed = false;

	$this->wpdb->shouldReceive( 'prepare' )
		->twice()
		->andReturn( "(42, 'post_content', 10)", "(43, 'featured_image', 20)" );

	$this->wpdb->shouldReceive( 'query' )
		->once()
		->andReturnUsing( function () use ( &$query_executed ) {
			$query_executed = true;
			return 2;
		} );

	$this->index->add_references_batch( $records );

	expect( $query_executed )->toBeTrue();
} );

it( 'clear truncates the reference table', function () {
	$truncated = false;
	$this->wpdb->shouldReceive( 'query' )
		->once()
		->with( 'TRUNCATE TABLE wp_vmfa_media_references' )
		->andReturnUsing( function () use ( &$truncated ) {
			$truncated = true;
			return true;
		} );

	$this->index->clear();

	expect( $truncated )->toBeTrue();
} );

it( 'build_global_references indexes site icon and custom logo', function () {
	$batch_inserted = false;

	Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
		return match ( $key ) {
			'site_icon'        => 100,
			'sidebars_widgets' => array(),
			default            => $default,
		};
	} );

	Functions\expect( 'get_theme_mod' )
		->with( 'custom_logo', 0 )
		->andReturn( 200 );

	// Expect batch insert with site icon + custom logo.
	$this->wpdb->shouldReceive( 'prepare' )
		->twice()
		->andReturn( "(100, 'site_icon', 0)", "(200, 'custom_logo', 0)" );

	$this->wpdb->shouldReceive( 'query' )
		->once()
		->andReturnUsing( function () use ( &$batch_inserted ) {
			$batch_inserted = true;
			return 2;
		} );

	$this->index->build_global_references();

	expect( $batch_inserted )->toBeTrue();
} );

it( 'build_global_references scans widgets for attachment references', function () {
	Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
		return match ( $key ) {
			'site_icon'        => 0,
			'sidebars_widgets' => array( 'sidebar-1' => array( 'media_image-2' ) ),
			'widget_media_image' => array(
				2 => array(
					'attachment_id' => 55,
					'url'           => 'http://example.com/wp-content/uploads/2024/01/photo.jpg',
				),
			),
			default => $default,
		};
	} );

	Functions\expect( 'get_theme_mod' )
		->with( 'custom_logo', 0 )
		->andReturn( 0 );

	Functions\expect( 'wp_json_encode' )
		->andReturnUsing( function ( $data ) {
			return json_encode( $data );
		} );

	Functions\expect( 'wp_get_upload_dir' )
		->andReturn(
			array(
				'baseurl' => 'http://example.com/wp-content/uploads',
			)
		);

	Functions\expect( 'attachment_url_to_postid' )
		->andReturn( 55 );

	// Expect batch insert.
	$batch_inserted = false;
	$this->wpdb->shouldReceive( 'prepare' )
		->andReturn( "(55, 'widget', 0)" );

	$this->wpdb->shouldReceive( 'query' )
		->once()
		->andReturnUsing( function () use ( &$batch_inserted ) {
			$batch_inserted = true;
			return 1;
		} );

	$this->index->build_global_references();

	expect( $batch_inserted )->toBeTrue();
} );

it( 'build_index_batch scans post content and featured images', function () {
	$post1            = Mockery::mock( 'WP_Post' );
	$post1->ID        = 10;
	$post1->post_content = '<!-- wp:image {"id":42} --><img class="wp-image-42" /><!-- /wp:image -->';

	$post2            = Mockery::mock( 'WP_Post' );
	$post2->ID        = 20;
	$post2->post_content = '';

	Functions\expect( 'get_post_types' )
		->with( array( 'public' => true ) )
		->andReturn( array( 'post', 'page' ) );

	Functions\expect( 'get_posts' )
		->once()
		->andReturn( array( $post1, $post2 ) );

	// Featured image for post1.
	Functions\expect( 'get_post_meta' )
		->with( 10, '_thumbnail_id', true )
		->andReturn( '99' );

	// No featured image for post2.
	Functions\expect( 'get_post_meta' )
		->with( 20, '_thumbnail_id', true )
		->andReturn( '0' );

	// Page builder meta: return empty for all.
	Functions\expect( 'get_post_meta' )
		->andReturn( '' );

	// Settings for extra meta keys.
	Functions\expect( 'get_option' )
		->with( 'vmfa_media_cleanup_settings', array() )
		->andReturn( array() );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_reference_meta_keys', Mockery::any() )
		->andReturnUsing( function ( $hook, $keys ) {
			return $keys;
		} );

	Functions\expect( 'apply_filters' )
		->with( 'vmfa_cleanup_reference_sources', array() )
		->andReturn( array() );

	// Block editor: get_post_type for gallery id extraction.
	Functions\expect( 'get_post_type' )
		->with( 42 )
		->andReturn( 'attachment' );

	// Batch insert.
	$this->wpdb->shouldReceive( 'prepare' )
		->andReturn( 'prepared_value' );

	$this->wpdb->shouldReceive( 'query' )
		->andReturn( true );

	$processed = $this->index->build_index_batch( 0, 50 );

	expect( $processed )->toBe( 2 );
} );

it( 'build_index_batch returns 0 for empty posts', function () {
	Functions\expect( 'get_post_types' )
		->with( array( 'public' => true ) )
		->andReturn( array( 'post' ) );

	Functions\expect( 'get_posts' )
		->once()
		->andReturn( array() );

	$processed = $this->index->build_index_batch( 100, 50 );

	expect( $processed )->toBe( 0 );
} );

it( 'get_total_posts queries public post types', function () {
	Functions\expect( 'get_post_types' )
		->with( array( 'public' => true ) )
		->andReturn( array( 'post', 'page' ) );

	$this->wpdb->shouldReceive( 'prepare' )
		->once()
		->andReturn( 'prepared_count_query' );

	$this->wpdb->shouldReceive( 'get_var' )
		->once()
		->with( 'prepared_count_query' )
		->andReturn( '150' );

	expect( $this->index->get_total_posts() )->toBe( 150 );
} );
