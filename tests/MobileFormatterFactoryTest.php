<?php

namespace Tests\Wikimedia\MobileFormatter;

use PHPUnit_Framework_TestCase;
use Title;
use Wikimedia\MobileFormatter\MobileFormatterFactory;
use Wikimedia\MobileFormatter\MobileFormatter;
use Wikimedia\MobileFormatter\MainPageMobileFormatter;

class MobileFormatterFactoryTest extends PHPUnit_Framework_TestCase {

	protected function setUp() {
		$this->html = '<h1>Maybeshewill</h1>';
		$this->mainPageTitle = Title::newMainPage();
	}

	public function test_it_should_return_a_main_page_formatter_when_the_title_is_the_main_page() {
		$formatter = MobileFormatterFactory::factory( $this->html, $this->mainPageTitle );

		$this->assertTrue( $formatter instanceof MainPageMobileFormatter );
	}

	public function test_it_should_return_a_formatter_when_the_title_isnt_the_main_page() {
		$title = Title::makeTitle( NS_MAIN, 'Maybeshewill' );
		$formatter = MobileFormatterFactory::factory( $this->html, $title );

		$this->assertEquals( 'Wikimedia\\MobileFormatter\\MobileFormatter', get_class( $formatter ) );
	}

	public function test_it_should_return_a_formatter_when_the_main_page_isnt_a_special_case() {
		$formatter = MobileFormatterFactory::factory(
			$this->html,
			$this->mainPageTitle,
			false
		);

		$this->assertEquals( 'Wikimedia\\MobileFormatter\\MobileFormatter', get_class( $formatter ) );
	}
}
