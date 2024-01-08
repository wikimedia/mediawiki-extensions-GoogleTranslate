<?php

use Wikimedia\ParamValidator\ParamValidator;

/**
 * This class is used for saving translations sent by the client
 *
 * @todo Figure out how to make this a proper POST module
 */
class GoogleTranslateSave extends ApiBase {

	public function execute() {
		$config = $this->getConfig();

		// Make sure the feature is enabled
		if ( !$config->get( 'GoogleTranslateSave' ) ) {
			return;
		}

		// Get the data
		$page = $this->getParameter( 'page' );
		$language = $this->getParameter( 'language' );
		$translatedTitle = $this->getParameter( 'title' );
		$translatedText = $this->getParameter( 'text' );

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
			// @todo Use WikiPage::getCategories
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
