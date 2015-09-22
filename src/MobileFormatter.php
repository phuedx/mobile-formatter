<?php
/**
 * MobileFormatter.php
 */

namespace Wikimedia\MobileFormatter;

use HtmlFormatter;
use DOMDocument;
use DOMXPath;

/**
 * Converts HTML into a mobile-friendly version
 */
class MobileFormatter extends HtmlFormatter {
	/** @var string $pageTransformStart String prefixes to be
		applied at start and end of output from Parser */
	protected $pageTransformStart = '<div>';
	/** @var string $pageTransformEnd String prefixes to be
		applied at start and end of output from Parser */
	protected $pageTransformEnd = '</div>';
	/** @var string $headingTransformStart String prefixes to be
		applied before and after section content. */
	protected $headingTransformStart = '</div>';
	/** @var string $headingTransformEnd String prefixes to be
		applied before and after section content. */
	protected $headingTransformEnd = '<div>';
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
	 * Change mainPage (is this the main page) to $value (standard: true)
	 * @param boolean $value
	 */
	public function setIsMainPage( $value = true ) {
	}

	/**
	 * Sets the possible heading tags
	 *
	 * As sections are being marked as expandable, their associated headings
	 * are found and transformed. For details of the transformation see
	 * {@link MobileFormatter::headingTransform}.  The "top heading tags"
	 * property property defines the set of tags that will be considered
	 * headings and the order in which they will be searched for in the
	 * document.  The first heading tag found will be ignored but all others
	 * will be transformed.
	 *
	 * Example:
	 * <code>
	 * <?php
	 *
	 * $input = '<h1>Foo</h1><div><h2>Bar</h2></div>
	 * $formatter = new \Wikimedia\MobileFormatter\MobileFormatter( $input );
	 * $formatter->enableExpandableSections();
	 *
	 * // Note well the order of the tags.
	 * $formatter->setTopHeadingTags( array( 'h2', 'h1' ) );
	 *
	 * $formatter->getText();
	 * // => "<div><h1 class="in-block">Foo</h1></div><h2>Bar</h2><div></div>"
	 * </code>
	 *
	 * By default, the rank of the HTML heading elements is respected, i.e.
	 * the default value is <code>['h1', 'h2', 'h3', 'h4', 'h5',
	 * 'h6']</code>.
	 *
	 * @param array $topHeadingTags
	 */
	public function setTopHeadingTags( array $topHeadingTags ) {
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
	 * Performs final transformations to mobile format and returns resulting HTML
	 *
	 * @param DOMElement|string|null $element ID of element to get HTML from or
	 *   false to get it from the whole tree
	 * @return string Processed HTML
	 */
	public function getText( $element = null ) {
		$html = parent::getText( $element );

		return $html;
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
	 * Transforms heading for toggling and editing
	 *
	 * - Add CSS classes to all heading tags _inside_ a section to enable
	 *   editing of these sections. Doesn't add this class to the first
	 *   heading (<code>$tagName</code>)
	 *   {@see MobileFormatter::markSubHeadingsAsEditable}
	 * - Wraps section-content inside a div to enable toggling
	 *
	 * @param string $s
	 * @param string $tagName
	 * @return string
	 */
	protected function headingTransform( $s, $tagName = 'h2' ) {
		$s = $this->markSubHeadingsAsEditable( $s, $tagName );

		// Makes sections expandable
		$tagRegEx = '<' . $tagName . '.*</' . $tagName . '>';
		$s = $this->pageTransformStart .
			preg_replace(
				'%(' . $tagRegEx . ')%sU', $this->headingTransformStart . '\1' . $this->headingTransformEnd,
				$s
			) .
			$this->pageTransformEnd;

		return $s;
	}

	/**
	 * Marks sub-headings in a section as editable by adding the
	 * <code>in-block</code> class.
	 *
	 * @param string $html
	 * @param string $sectionHeading The tag name of the section's heading
	 *  element
	 * @return string
	 */
	protected function markSubHeadingsAsEditable( $html, $sectionHeading ) {
		// add in-block class to all headings included in this section (except the first one)
		return preg_replace_callback(
			'/<(h[1-6])>/si',
			function ( $match ) use ( $sectionHeading ) {
				$tag = $match[1];
				$cssClass = '';
				if ( $tag !== $sectionHeading ) {
					$cssClass = ' class="in-block"';
				}
				return '<' . $tag . $cssClass . '>';
			},
			$html
		);
	}

	/**
	 * Finds the first heading in the page and uses that to determine top level sections.
	 * When a page contains no headings returns h6.
	 *
	 * @param string $html
	 * @return string the tag name for the top level headings
	 */
	protected function findTopHeading( $html ) {
		$tags = $this->topHeadingTags;
		if ( !is_array( $tags ) ) {
			throw new UnexpectedValueException( 'Possible top headings needs to be an array of strings, ' .
				gettype( $tags ) . ' given.' );
		}
		foreach ( $tags as $tag ) {
			if ( strpos( $html, '<' . $tag ) !== false ) {
				return $tag;
			}
		}
		return 'h6';
	}

	/**
	 * Call headingTransform if needed
	 *
	 * @param string $html
	 * @return string
	 */
	protected function onHtmlReady( $html ) {
		if ( $this->expandableSections ) {
			$tagName = $this->findTopHeading( $html );
			$html = $this->headingTransform( $html, $tagName );
		}

		return $html;
	}
}
