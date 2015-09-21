<?php

namespace Wikimedia\MobileFormatter;

use Title;

/**
 * Initializes an instance of a {@see MobileFormatter} class given some
 * additional context, i.e. whether or not the page is the main page.
 */
class MobileFormatterFactory {

	/**
	 * Initializes an instance of the {@see MobileFormatter} class, which
	 * will format the given HTML.
	 *
	 * If the title is the main page and the main page isn't considered a
	 * special case, then an instance of the {@see MainPageMobileFormatter}
	 * is returned. Otherwise an instance of the {@see MobileFormatter} is
	 * returned.
	 *
	 * The <code>$specialCaseMainPage</code> parameter and associated
	 * behaviour are included for compatibility with the current
	 * configuration options of the {@link MobileFrontend
	 * https://github.com/wikimedia/mediawiki-extensions-MobileFrontend}
	 * extension.
	 *
	 * @param string $html
	 * @param Title $title
	 * @param boolean $specialCaseMainPage
	 * @return
	 */
	public static function factory( $html, Title $title, $specialCaseMainPage = true ) {
		if ( $specialCaseMainPage && $title->isMainPage() ) {
			return new MainPageMobileFormatter( $html );
		}

		return new MobileFormatter( $html );
	}
}
