<?php

use Wikimedia\ParamValidator\ParamValidator;

/**
 * This class is used for saving translations sent by the client
 *
 * @todo Figure out how to make this a POST module
 */
class GoogleTranslateSave extends ApiBase {

	public function execute() {
		$config = $this->getConfig();

		// Make sure the feature is enabled
		if ( !$config->get( 'GoogleTranslateSave' ) ) {
			return;
		}

		// Get the parameters
		$page = $this->getParameter( 'page' );
		$language = $this->getParameter( 'language' );
		$translatedTitle = $this->getParameter( 'title' );
		$translatedText = $this->getParameter( 'text' );

		// Fix old Hebrew language code
		if ( $language === 'iw' ) {
			$language = 'he';
		}

		// Make sure that the page being translated actually exists
		$title = Title::newFromText( $page );
		if ( !$title->exists() ) {
			return;
		}

		// Make sure that we're editing a whitelisted namespace
		$namespace = $title->getNamespace();
		$namespaces = $config->get( 'GoogleTranslateNamespaces' );
		if ( !in_array( $namespace, $namespaces ) ) {
			return;
		}

		// Minimize <font> tags and line breaks
		$translatedText = str_replace( '<font style="vertical-align: inherit;">', '<font>', $translatedText );
		$translatedText = str_replace( '<font><font>', '<font>', $translatedText );
		$translatedText = str_replace( '</font><font>', '', $translatedText );
		$translatedText = str_replace( '<font></font>', '', $translatedText );
		$translatedText = str_replace( '</font></font>', '</font>', $translatedText );
		$translatedText = str_replace( '<font><b></font>', '<b>', $translatedText );
		$translatedText = str_replace( '<font></b></font>', '</b>', $translatedText );
		$translatedText = preg_replace( '/\n\s+|\n/', '', $translatedText );

		// Use xPath to do some further processing
		$DOM = new DOMDocument;
		$DOM->substituteEntities = false;
		$DOM->loadHTML( '<?xml encoding="utf-8" ?>' . $translatedText );
		$xPath = new DomXPath( $DOM );

		// Remove HTML comments
		foreach ( $xPath->query( '//comment()' ) as $comment ) {
			$comment->parentNode->removeChild( $comment );
		}

		// Remove edit section links
		foreach ( $xPath->query( '//span[contains(@class,"mw-editsection")]' ) as $editSection ) {
			$editSection->parentNode->removeChild( $editSection );
		}

		// Make sure that at least some % is translated
		$translatedNodes = $xPath->query( '//font' );
		$translatableNodes = $xPath->query( '//text()' );
		$translatedRatio = $translatedNodes->length / $translatableNodes->length;
		if ( $translatedRation < $config->get( 'GoogleTranslateSaveTreshold' ) ) {
			// return; @todo For some reason this is firing too often
		}

		// Get the processed text
		$translatedText = $xPath->query( '//body/div[contains(@class,"mw-parser-output")]' )->item( 0 );
		$translatedText = $DOM->saveHTML( $translatedText );

		// Build the wikitext
		$wikitext = "<html>$translatedText</html>";
		if ( $config->get( 'GoogleTranslateSaveTitle' ) ) {
			$wikitext = '{{DISPLAYTITLE:' . $translatedTitle . '}}' . $wikitext;
		}
		$notice = $config->get( 'GoogleTranslateSaveNotice' );
		if ( $notice ) {
			$wikitext = '{{' . $notice . '}}' . PHP_EOL . PHP_EOL . $wikitext;
		}
		if ( $config->get( 'GoogleTranslateSaveCategories' ) ) {
			$categories = array_keys( $title->getParentCategories() );
			if ( $categories ) {
				$wikitext .= PHP_EOL;
				foreach ( $categories as $category ) {
					$category = str_replace( '_', ' ', $category );
					$wikitext .= PHP_EOL . '[[' . $category . ']]';
				}
			}
		}

		// Create or edit the relevant subpage
		$subpage = $title->getSubpage( $language );
		$systemAccount = $config->get( 'GoogleTranslateSystemAccount' );
		$user = User::newSystemUser( $systemAccount );
		$wikipage = WikiPage::factory( $subpage );
		$content = ContentHandler::makeContent( $wikitext, $subpage );
		$summary = $this->msg( 'googletranslate-summary' )->inLanguage( $language )->plain();
		$comment = CommentStoreComment::newUnsavedComment( $summary );
		$updater = $wikipage->newPageUpdater( $user );
		$updater->setContent( 'main', $content );
		$updater->saveRevision( $comment, EDIT_FORCE_BOT );

		// Change the page language if allowed
		if ( $this->getConfig()->get( 'PageLanguageUseDB' ) ) {
			SpecialPageLanguage::changePageLanguage( $this, $subpage, $language );
		}
	}

	/**
	 * @return array Associative array describing the allowed parameters
	 */
	public function getAllowedParams() {
		return [
			'page' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'language' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'text' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
