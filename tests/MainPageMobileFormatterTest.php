<?php

namespace Tests\Wikimedia\MobileFormatter;

use PHPUnit_Framework_TestCase;
use Wikimedia\MobileFormatter\MainPageMobileFormatter;

class MainPageMobileFormatterTest extends PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider provideHtmlTransform
	 */
	public function testHtmlTransform( $input, $expectedOutput ) {
		$formatter = new MainPageMobileFormatter( $input );
		$formatter->filterContent();

		$this->assertEquals( $expectedOutput, $formatter->getText() );
	}

	public function provideHtmlTransform() {
		return array(
			array(
				'fooo
<div id="mp-itn">bar</div>
<div id="mf-custom" title="custom">blah</div>',
				'<div id="mainpage"><h2>'
					. wfMessage( 'mobile-frontend-news-items' )
					. '</h2><div id="mp-itn">bar</div>'
					. '<h2>custom</h2><div id="mf-custom">blah</div><br clear="all"></div>',
			),
			array(
				'<div id="foo">test</div>',
				'<div id="foo">test</div>',
			),
			array(
				'<div id="mf-foo" title="A &amp; B">test</div>',
				'<div id="mainpage"><h2>A &amp; B</h2><div id="mf-foo">test</div><br clear="all"></div>',
			),
			array(
				'<div id="foo">test</div><div id="central-auth-images">images</div>',
				'<div id="foo">test</div><div id="central-auth-images">images</div>',
			),
			array(
				'<div id="mf-foo" title="A &amp; B">test</div><div id="central-auth-images">images</div>',
				'<div id="mainpage"><h2>A &amp; B</h2><div id="mf-foo">test</div><br clear="all">'
					. '<div id="central-auth-images">images</div></div>',
			),
		);
	}
}
