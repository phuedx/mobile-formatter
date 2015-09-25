<?php

namespace Wikimedia\MobileFormatter;

use DOMDocument;
use DOMXPath;

class MainPageMobileFormatter extends MobileFormatter
{
	public function getText( $element = null ) {
		$document = $this->getDoc();
		$mainPageElement = $this->parseMainPage( $document );

		return parent::getText( $mainPageElement );
	}

	/**
	 * The marking of subheadings as editable on the main page is disabled.
	 *
	 * @link https://phabricator.wikimedia.org/T89559
	 */
	protected function makeHeadingsEditable( array $headings ) {
	}

	/**
	 * Performs transformations specific to main page
	 * @param DOMDocument $mainPage Tree to process
	 * @return DOMElement|null
	 */
	protected function parseMainPage( DOMDocument $mainPage ) {
		$featuredArticle = $mainPage->getElementById( 'mp-tfa' );
		$newsItems = $mainPage->getElementById( 'mp-itn' );
		$centralAuthImages = $mainPage->getElementById( 'central-auth-images' );

		// Collect all the Main Page DOM elements that have an id starting with 'mf-'
		$xpath = new DOMXPath( $mainPage );
		$elements = $xpath->query( '//*[starts-with(@id, "mf-")]' );

		// These elements will be handled specially
		$commonAttributes = array( 'mp-tfa', 'mp-itn' );

		// Start building the new Main Page content in the $content var
		$content = $mainPage->createElement( 'div' );
		$content->setAttribute( 'id', 'mainpage' );

		// If there is a featured article section, add it to the new Main Page content
		if ( $featuredArticle ) {
			$h2FeaturedArticle = $mainPage->createElement(
				'h2',
				$this->msg( 'mobile-frontend-featured-article' )
			);
			$content->appendChild( $h2FeaturedArticle );
			$content->appendChild( $featuredArticle );
		}

		// If there is a news section, add it to the new Main Page content
		if ( $newsItems ) {
			$h2NewsItems = $mainPage->createElement( 'h2', $this->msg( 'mobile-frontend-news-items' ) );
			$content->appendChild( $h2NewsItems );
			$content->appendChild( $newsItems );
		}

		// Go through all the collected Main Page DOM elements and format them for mobile
		/** @var $element DOMElement */
		foreach ( $elements as $element ) {
			if ( $element->hasAttribute( 'id' ) ) {
				$id = $element->getAttribute( 'id' );
				// Filter out elements processed specially
				if ( !in_array( $id, $commonAttributes ) ) {
					// Convert title attributes into h2 headers
					$sectionTitle = $element->hasAttribute( 'title' ) ? $element->getAttribute( 'title' ) : '';
					if ( $sectionTitle !== '' ) {
						$element->removeAttribute( 'title' );
						$h2UnknownMobileSection =
							$mainPage->createElement( 'h2', htmlspecialchars( $sectionTitle ) );
						$content->appendChild( $h2UnknownMobileSection );
					}
					$br = $mainPage->createElement( 'br' );
					$br->setAttribute( 'clear', 'all' );
					$content->appendChild( $element );
					$content->appendChild( $br );
				}
			}
		}

		// If no mobile-appropriate content has been assembled at this point, return null.
		// This will cause HtmlFormatter to fall back to using the entire page.
		if ( $content->childNodes->length == 0 ) {
			return null;
		}

		// If there are CentralAuth 1x1 images, preserve them unmodified
		if ( $centralAuthImages ) {
			$content->appendChild( $centralAuthImages );
		}

		return $content;
	}
}
