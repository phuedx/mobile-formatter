<?php

namespace Tests\Wikimedia\MobileFormatter;

use PHPUnit_Framework_TestCase;
use Wikimedia\MobileFormatter\MobileFormatter;

/**
 * @group MobileFrontend
 */
class MobileFormatterTest extends PHPUnit_Framework_TestCase {
	/**
	 * @dataProvider getHtmlData
	 *
	 * @param $input
	 * @param $expected
	 * @param callable|bool $callback
	 */
	public function testHtmlTransform( $input, $expected, $callback = false ) {
		$input = str_replace( "\r", '', $input ); // "yay" to Windows!
		$mf = new MobileFormatter( MobileFormatter::wrapHTML( $input ) );
		if ( $callback ) {
			$callback( $mf );
		}
		$mf->filterContent();
		$html = $mf->getText();
		$this->assertEquals( str_replace( "\n", '', $expected ), str_replace( "\n", '', $html ) );
	}

	public function getHtmlData() {
		$enableSections = function ( MobileFormatter $mf ) {
			$mf->enableExpandableSections();
		};
		$longLine = "\n" . str_repeat( 'A', 5000 );
		$removeImages = function( MobileFormatter $f ) {
			$f->setRemoveMedia();
		};

		return array(
			array(
				'<img src="/foo/bar.jpg">Blah</img>',
				'<span class="mw-mf-image-replacement">['
					. htmlspecialchars( wfMessage( 'mobile-frontend-missing-image' ) ) .']</span>Blah',
				$removeImages,
			),
			array(
				'<img src="/foo/bar.jpg" alt="Blah"/>',
				'<span class="mw-mf-image-replacement">[Blah]</span>',
				$removeImages,
			),
			// \n</h2> in headers
			array(
				'<h2><span class="mw-headline" id="Forty-niners">Forty-niners</span>'
					. '<a class="edit-page" href="#editor/2">Edit</a></h2>'
					. $longLine,
				'<div></div>'
					. '<h2><span class="mw-headline" id="Forty-niners">Forty-niners</span>'
					. '<a class="edit-page" href="#editor/2">Edit</a></h2>'
					. '<div>' . $longLine . '</div>',
				$enableSections
			),
			// \n</h3> in headers
			array(
				'<h3><span>h3</span></h3>'
					. $longLine
					. '<h4><span>h4</span></h4>'
					. 'h4 text.',
				'<div></div>'
					. '<h3><span>h3</span></h3>'
					. '<div>'
					. $longLine
					. '<h4 class="in-block"><span>h4</span></h4>'
					. 'h4 text.'
					. '</div>',
				$enableSections
			),
			// \n</h6> in headers
			array(
				'<h6><span>h6</span></h6>'
					. $longLine,
				'<div></div>'
					. '<h6><span>h6</span></h6>'
					. '<div>' . $longLine . '</div>',
				$enableSections
			),
			// Bug 36670
			array(
				'<h2><span class="mw-headline" id="History"><span id="Overview"></span>'
					. 'History</span><a class="edit-page" href="#editor/2">Edit</a></h2>'
					. $longLine,
				'<div></div><h2><span class="mw-headline" id="History"><span id="Overview"></span>'
					. 'History</span><a class="edit-page" href="#editor/2">Edit</a></h2><div>'
					. $longLine . '</div>',
				$enableSections
			),
			array(
				'<img alt="picture of kitty" src="kitty.jpg">',
				'<span class="mw-mf-image-replacement">[picture of kitty]</span>',
				$removeImages,
			),
			array(
				'<img src="kitty.jpg">',
				'<span class="mw-mf-image-replacement">[' .
					htmlspecialchars( wfMessage( 'mobile-frontend-missing-image' ) ) . ']</span>',
				$removeImages,
			),
			array(
				'<img alt src="kitty.jpg">',
				'<span class="mw-mf-image-replacement">[' .
					htmlspecialchars( wfMessage( 'mobile-frontend-missing-image' ) ) . ']</span>',
				$removeImages,
			),
			array(
				'<img alt src="kitty.jpg">look at the cute kitty!' .
					'<img alt="picture of angry dog" src="dog.jpg">',
				'<span class="mw-mf-image-replacement">[' .
					htmlspecialchars( wfMessage( 'mobile-frontend-missing-image' ) ) .
					']</span>look at the cute kitty!' .
					'<span class="mw-mf-image-replacement">[picture of angry dog]</span>',
				$removeImages,
			),
		);
	}

	/**
	 * @dataProvider provideHeadingTransform
	 */
	public function testHeadingTransform( array $topHeadingTags, $input, $expectedOutput ) {
		$formatter = new MobileFormatter( $input );

		// If MobileFormatter#enableExpandableSections isn't called, then headings
		// won't be transformed.
		$formatter->enableExpandableSections( true );

		$formatter->setTopHeadingTags( $topHeadingTags );
		$formatter->filterContent();

		$this->assertEquals( $expectedOutput, $formatter->getText() );
	}

	public function provideHeadingTransform() {
		$input =  '<h1>Foo</h1><h2>Bar</h2>';

		return array(
			array(
				array( 'h1', 'h2' ),
				$input,
				'<div></div><h1>Foo</h1><div><h2 class="in-block">Bar</h2></div>',
			),

			// FIXME: If none of the top heading tags are in the document, then all of
			// the headings are transformed.
			array(
				array( 'h3' ),
				$input,
				'<div><h1 class="in-block">Foo</h1><h2 class="in-block">Bar</h2></div>',
			),

			// FIXME: If there are no top heading tags specified, then all of the
			// headings are transformed.
			array(
				array(),
				$input,
				'<div><h1 class="in-block">Foo</h1><h2 class="in-block">Bar</h2></div>',
			),

			// FIXME: Note the extraneous `div` at the end of this example and at the
			// beginning of the first example.
			array(
				array( 'h2', 'h1' ),
				$input,
				'<div><h1 class="in-block">Foo</h1></div><h2>Bar</h2><div></div>',
			),
		);
	}
}
