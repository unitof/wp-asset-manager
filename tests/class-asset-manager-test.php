<?php

namespace Asset_Manager_Tests;

use Asset_Manager_Scripts;
use Asset_Manager_Styles;
use Asset_Manager_Preload;
use Mantle\Testing\Concerns\Refresh_Database;
use Mantle\Testkit\Test_Case;

abstract class Asset_Manager_Test extends Test_Case {
	use Refresh_Database;

	public $test_script = [
		'handle' => 'my-test-asset',
		'src'    => 'http://www.example.org/wp-content/themes/example/static/js/test.bundle.js',
	];

	public $test_script_two = [
		'handle' => 'test-asset-two',
		'src'    => 'http://www.example.org/wp-content/themes/example/static/js/test-two.bundle.js',
	];

	public $test_style = [
		'handle' => 'my-test-style',
		'src'    => 'http://www.example.org/wp-content/themes/example/static/css/test.css',
	];

	public $test_style_two = [
		'handle' => 'test-style-two',
		'src'    => 'http://www.example.org/wp-content/themes/example/static/css/test-two.css',
	];

	protected function setUp(): void {
		parent::setUp();

		// Add test conditions
		remove_all_filters( 'am_asset_conditions', 10 );
		add_filter(
			'am_asset_conditions',
			function() {
				return [
					'global'            => true,
					'article_post_type' => true,
					'single'            => true,
					'archive'           => false,
					'has_slideshow'     => false,
					'has_video'         => false,
				];
			}
		);
		add_filter(
			'am_inline_script_context',
			function() {
				return 'assetContext';
			}
		);

		$this->reset_assets();
		$this->acting_as( 'administrator' );
	}

	public function reset_assets() {
		Asset_Manager_Scripts::instance()->assets           = [];
		Asset_Manager_Scripts::instance()->assets_by_handle = [];
		Asset_Manager_Scripts::instance()->asset_handles    = [];
		Asset_Manager_Styles::instance()->assets            = [];
		Asset_Manager_Styles::instance()->assets_by_handle  = [];
		Asset_Manager_Styles::instance()->asset_handles     = [];
		Asset_Manager_Styles::instance()->loadcss_added     = false;
		Asset_Manager_Preload::instance()->assets           = [];
		Asset_Manager_Preload::instance()->assets_by_handle = [];
		Asset_Manager_Preload::instance()->asset_handles    = [];

		wp_deregister_script( 'my-test-asset' );
		wp_deregister_script( 'test-asset-two' );
	}
}
