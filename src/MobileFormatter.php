<?php
/**
 * MobileFormatter.php
 */

namespace Wikimedia\MobileFormatter;

use HtmlFormatter;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

/**
 * Converts HTML into a mobile-friendly version
 */
class MobileFormatter extends HtmlFormatter {
	/** @var array $topHeadingTags Array of strings with possible tags,
		can be recognized as top headings. */
	public $topHeadingTags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

	/**
	 * Are sections expandable?
	 * @var boolean $expandableSections
	 */
	protected $expandableSections = false;

	/**
	 * Initializes a new instance of the class.
	 *
	 * Note well that the HTML is always wrapped using
	 * {@see MobileFormatter::wrapHTML} so that it forms a complete
	 * document.
	 *
	 * @param string $html
	 */
	public function __construct( $html ) {
		parent::__construct( self::wrapHTML( $html ) );
	}

	/**
	 * Set support of page for expandable sections to $flag (standard: true)
	 * @todo kill with fire when there will be minimum of pre-1.1 app users remaining
	 * @param bool $flag
	 */
	public function enableExpandableSections( $flag = true ) {
		$this->expandableSections = $flag;
	}

	/**
	 * Sets the possible heading tags
	 *
	 * As sections are being marked as expandable, their associated headings
	 * are found and transformed. For details of the transformation see
	 * {@see MobileFormatter::getText}. The "top heading tags" property
	 * property defines the set of tags that will be considered headings
	 * and the order in which they will be searched for in the document.
	 * The first heading tag found will be ignored but all others will be
	 * transformed.
	 *
	 * Example:
	 * <code><pre>
	 * <?php
	 *
	 * $input = '<h1>Foo</h1><div><h2>Bar</h2>Baz<h3>Quux</h3></div>'
	 * $formatter = new \Wikimedia\MobileFormatter\MobileFormatter( $input );
	 * $formatter->enableExpandableSections();
	 * $formatter->setTopHeadingTags( array( 'h2', 'h3' ) );
	 *
	 * $formatter->getText();
	 * // => "<div><h1>Foo</h1></div><h2>Bar</h2>Baz<h3 class="in-block">Quux</h3>"
	 * </pre></code>
	 *
	 * By default, the rank of the HTML heading elements is respected, i.e.
	 * the default value is
	 * <code>['h1', 'h2', 'h3', 'h4', 'h5', 'h6']</code>.
	 *
	 * @param array $topHeadingTags
	 * @throws InvalidArgumentException
	 */
	public function setTopHeadingTags( array $topHeadingTags ) {
		$validHeadingTags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

		$topHeadingTags = (array)$topHeadingTags;

		if ( !$topHeadingTags || array_diff( $topHeadingTags, $validHeadingTags ) ) {
			throw new InvalidArgumentException();
		}

		$this->topHeadingTags = $topHeadingTags;
	}

	/**
	 * Removes content inappropriate for mobile devices
	 * @param bool $removeDefaults Whether default settings at $wgMFRemovableClasses should be used
	 * @return array
	 */
	public function filterContent( $removeDefaults = true ) {
		$mfRemovableClasses = array(
			'base' => array(),
			'HTML' => array(),
		);

		if ( $removeDefaults ) {
			$this->remove( $mfRemovableClasses['base'] );
			$this->remove( $mfRemovableClasses['HTML'] ); // @todo: Migrate this variable
		}

		if ( $this->removeMedia ) {
			$this->doRemoveImages();
		}
		return parent::filterContent();
	}

	/**
	 * Replaces images with [annotations from alt]
	 */
	private function doRemoveImages() {
		$doc = $this->getDoc();
		$domElemsToReplace = array();
		foreach ( $doc->getElementsByTagName( 'img' ) as $element ) {
			$domElemsToReplace[] = $element;
		}
		/** @var $element DOMElement */
		foreach ( $domElemsToReplace as $element ) {
			$alt = $element->getAttribute( 'alt' );
			if ( $alt === '' ) {
				$alt = '[' . wfMessage( 'mobile-frontend-missing-image' )->inContentLanguage() . ']';
			} else {
				$alt = '[' . $alt . ']';
			}
			$replacement = $doc->createElement( 'span', htmlspecialchars( $alt ) );
			$replacement->setAttribute( 'class', 'mw-mf-image-replacement' );
			$element->parentNode->replaceChild( $replacement, $element );
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * In this case, there are two transformations applied to the entirety
	 * of the document:
	 *
	 * <ol>
	 *   <li>
	 *     the sections demarcated by the highest rank heading are made
	 *     expandable ({@see MobileFormatter::makeSectionsExpandable})
	 *   </li>
	 *   <li>
	 *     all subheadings are marked as editable
	 *     ({@see MobileFormatter::makeHeadingsEditable})
	 *   </li>
	 * </ol>
	 *
	 * @param DOMElement|string|null $element
	 * @return string
	 */
	public function getText( $element = null ) {
		$this->transformHeadings();

		return parent::getText( $element );
	}

	/**
	 * Returns interface message text
	 * @param string $key Message key
	 * @return string Wiki text
	 */
	protected function msg( $key ) {
		return wfMessage( $key )->text();
	}

	/**
	 * See the documentation for {@see MobileFormatter::getText}.
	 */
	protected function transformHeadings() {
		$doc = $this->getDoc();
		list( $headings, $subheadings ) = $this->getHeadings( $doc );

		$this->makeSectionsExpandable( $doc, $headings );
		$this->makeHeadingsEditable( $subheadings );
	}

	/**
	 * Gets all headings in the document in rank order.
	 *
	 * Note well that the rank order is defined by the
	 * <code>MobileFormatter#topHeadingTags</code> property, which can be
	 * set with {@see MobileFormatter::setTopHeadingTags}.
	 *
	 * @param DOMDocument $doc
	 * @return array A two-element array where the first is the highest
	 *  rank headings and the second is all other headings
	 */
	private function getHeadings( DOMDocument $doc ) {
		$result = array();
		$headings = $subheadings = array();

		foreach ( $this->topHeadingTags as $tagName ) {
			$elements = $doc->getElementsByTagName( $tagName );

			if ( !$elements->length ) {
				continue;
			}

			$elements = iterator_to_array( $elements );

			if ( !$headings ) {
				$headings = $elements;
			} else {
				$subheadings = array_merge( $subheadings, $elements );
			}
		}

		return array( $headings, $subheadings );
	}

	/**
	 * Marks the headings as editable by adding the <code>in-block</code>
	 * class to each of them, if it hasn't already been added.
	 *
	 * FIXME: <code>in-block</code> isn't semantic in that it isn't
	 * obviously connected to being editable.
	 *
	 * @param [DOMElement] $headings
	 */
	protected function makeHeadingsEditable( array $headings ) {
		foreach ( $headings as $heading ) {
			$class = $heading->getAttribute( 'class' );

			if ( strpos( $class, 'in-block' ) === false ) {
				$heading->setAttribute(
					'class',
					ltrim( $class . ' in-block' )
				);
			}
		}
	}

	/**
	 * Splits the body of the document into sections demarcated by the
	 * <code>$headings</code> elements.
	 *
	 * All member elements of the sections are added to a
	 * <code><div></code> so that the sections can be made "expandable" by
	 * the client.
	 *
	 * Example:
	 * <code><pre>
	 * <?php
	 *
	 * $input = '<h1>Foo</h1><div><h2>Bar</h2><p>Baz</p></div>'
	 * $formatter = new \Wikimedia\MobileFormatter\MobileFormatter( $input );
	 * $formatter->enableExpandableSections();
	 *
	 * $formatter->getText();
	 * // => "<h1>Foo</h1><div><h2 class="in-block">Bar</h2><p>Baz</p></div>"
	 * </pre></code>
	 *
	 * @param DOMDocument $doc
	 * @param [DOMElement] $headings The headings returned by
	 *  {@see MobileFormatter::getHeadings}
	 */
	protected function makeSectionsExpandable( DOMDocument $doc, array $headings ) {
		if ( !$this->expandableSections || !$headings ) {
			return;
		}

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		$sibling = $body->firstChild;

		// TODO: Under HHVM 3.6.6, `iterator_to_array` returns a
		// one-indexed array rather than a zero-indexed array (see
		// https://travis-ci.org/phuedx/mobile-formatter).  Create a
		// minimal test case and raise a bug.
		$firstHeading = reset( $headings );
		$div = $doc->createElement( 'div' );

		while ( $sibling ) {
			$node = $sibling;
			$sibling = $sibling->nextSibling;

			// Note well the use of DOMNode#nodeName here. Only
			// DOMElement defines DOMElement#tagName.  So, if
			// there's trailing text - represented by DOMText -
			// then accessing #tagName will trigger an error.
			if ( $node->nodeName === $firstHeading->nodeName ) {
				if ( $div->hasChildNodes() ) {
					$body->insertBefore( $div, $node );

					$div = $doc->createElement( 'div' );
				}

				continue;
			}

			$div->appendChild( $node );
		}

		$body->appendChild( $div );
	}
}
