<?php

namespace Tests\Wikimedia\MobileFormatter;

use PHPUnit_Framework_TestCase;
use Wikimedia\MobileFormatter\MobileFormatter;

class MobileFormatterTest extends PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider provideHtmlTransform
	 */
	public function testHtmlTransform( $input, $expected, $callback = false ) {
		$input = str_replace( "\r", '', $input ); // "yay" to Windows!
		$mf = new MobileFormatter( $input );
		if ( $callback ) {
			$callback( $mf );
		}
		$mf->filterContent();
		$html = $mf->getText();
		$this->assertEquals( str_replace( "\n", '', $expected ), str_replace( "\n", '', $html ) );
	}

	public function provideHtmlTransform() {
		$enableSections = function ( MobileFormatter $mf ) {
			$mf->enableExpandableSections();
		};
		$longLine = "\n" . str_repeat( 'A', 5000 );
		$longLine = "\nA";
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
				'<h2><span class="mw-headline" id="Forty-niners">Forty-niners</span>'
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
				'<h3><span>h3</span></h3>'
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
				'<h6><span>h6</span></h6>'
					. '<div>' . $longLine . '</div>',
				$enableSections
			),
			// Bug 36670
			array(
				'<h2><span class="mw-headline" id="History"><span id="Overview"></span>'
					. 'History</span><a class="edit-page" href="#editor/2">Edit</a></h2>'
					. $longLine,
				'<h2><span class="mw-headline" id="History"><span id="Overview"></span>'
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
	 * @dataProvider provideTopHeadingTagsThrows
	 * @expectedException InvalidArgumentException
	 */
	public function testSetTopHeadingTagsThrows( $topHeadingTags ) {
		$formatter = new MobileFormatter( '<h1>Foo</h1>' );
		$formatter->setTopHeadingTags( $topHeadingTags );
	}

	public function provideTopHeadingTagsThrows() {
		return array(
			array( array() ),
			array( array( 'div' ) ),
			array( array( 'h1', 'div' ) ),
			array( array( 'h1', 'div', 'h2' ) ),
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
		return array(

			// The "in-block" class is added to a subheading.
			array(
				array( 'h1', 'h2' ),
				'<h1>Foo</h1><h2>Bar</h2>',
				'<h1>Foo</h1><div><h2 class="in-block">Bar</h2></div>',
			),

			// The "in-block" class is added to a subheading
			// without overwriting the existing attribute.
			array(
				array( 'h1', 'h2' ),
				'<h1>Foo</h1><h2 class="baz">Bar</h2>',
				'<h1>Foo</h1><div><h2 class="baz in-block">Bar</h2></div>',
			),

			// The "in-block" class is added to all subheadings.
			array(
				array( 'h1', 'h2', 'h3' ),
				'<h1>Foo</h1><h2>Bar</h2><h3>Qux</h3>',
				'<h1>Foo</h1><div><h2 class="in-block">Bar</h2><h3 class="in-block">Qux</h3></div>',
			),

			// The first heading found is the highest ranked
			// subheading.
			array(
				array( 'h1', 'h2', 'h3' ),
				'<h2>Bar</h2><h3>Qux</h3>',
				'<h2>Bar</h2><div><h3 class="in-block">Qux</h3></div>',
			),

			// Unenclosed text is appended to the expandable container.
			array(
				array( 'h1', 'h2' ),
				'<h1>Foo</h1><h2>Bar</h2>A',
				'<h1>Foo</h1><div><h2 class="in-block">Bar</h2>A</div>',
			),

			// Unencloded text that appears before the first
			// heading is appended to a container.
			//
			// FIXME: This behaviour was included for backwards
			// compatibility but mightn't be necessary.
			array(
				array( 'h1', 'h2' ),
				'A<h1>Foo</h1><h2>Bar</h2>',
				'<div>A</div><h1>Foo</h1><div><h2 class="in-block">Bar</h2></div>',
			),

			// Multiple headings are handled identically.
			array(
				array( 'h1', 'h2' ),
				'<h1>Foo</h1><h2>Bar</h2>Baz<h1>Qux</h1>Quux',
				'<h1>Foo</h1><div><h2 class="in-block">Bar</h2>Baz</div><h1>Qux</h1><div>Quux</div>',
			),
		);
	}

	/**
	 * @dataProvider provideRemovableClasses
	 */
	public function testRemovableClasses( $removableClasses, $expectedRemovableClasses ) {
		$formatter = new MobileFormatter( '' );
		$formatter->setRemovableClasses( $removableClasses );

		$this->assertEquals( $expectedRemovableClasses, $formatter->getRemovableClasses() );
	}

	public function provideRemovableClasses() {
		return array(

			// Passing an empty map shouldn't have any effect.
			array(
				array(),
				array(
					'base' => array(),
					'HTML' => array(),
				),
			),

			// One or both of the "base" or "HTML" entries in the
			// map should have an effect.
			array(
				array( 'base' => array( '.foo' ) ),
				array(
					'base' => array( '.foo' ),
					'HTML' => array(),
				),
			),
			array(
				array(
					'base' => array( '.foo' ),
					'HTML' => array( '#bar' ),
				),
				array(
					'base' => array( '.foo' ),
					'HTML' => array( '#bar' ),
				),
			),
		);
	}
}
