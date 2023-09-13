<?php

use MediaWiki\MainConfigNames;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * This class is used for saving translations sent by the client
 *
 * @todo Figure out how to make this a POST module
 */
class GoogleTranslateSave extends ApiBase {

	public function execute() {
		global $wgGoogleTranslateSave,
			$wgGoogleTranslateNamespaces,
			$wgGoogleTranslateSaveTitle,
			$wgGoogleTranslateSaveNotice,
			$wgGoogleTranslateSaveCategories,
			$wgGoogleTranslateSystemAccount;

		// Make sure the feature is enabled
		if ( !$wgGoogleTranslateSave ) {
			return;
		}

		// Get the parameters
		$page = $this->getParameter( 'page' );
		$language = $this->getParameter( 'language' );
		$title = $this->getParameter( 'title' );
		$text = $this->getParameter( 'text' );

		// Make sure that the page being translated actually exists
		$title = Title::newFromText( $page );
		if ( ! $title->exists() ) {
			return;
		}

		// Make sure that we're editing a whitelisted namespace
		$namespace = $title->getNamespace();
		if ( ! in_array( $namespace, $wgGoogleTranslateNamespaces ) ) {
			return;
		}

		// Build the wikitext
		$wikitext = "<html>$text</html>" ;
		if ( $wgGoogleTranslateSaveTitle ) {
			$wikitext = '{{DISPLAYTITLE:' . $title . '}}' . $wikitext;
		}
		if ( $wgGoogleTranslateSaveNotice ) {
			$wikitext = '{{' . $wgGoogleTranslateSaveNotice . '}}'. PHP_EOL . PHP_EOL . $wikitext;
		}
		if ( $wgGoogleTranslateSaveCategories ) {
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
		$user = User::newSystemUser( $wgGoogleTranslateSystemAccount );
		$wikipage = WikiPage::factory( $subpage );
		$content = ContentHandler::makeContent( $wikitext, $subpage );
		$summary = $this->msg( 'googletranslate-summary' )->inLanguage( $language )->plain();
		$comment = CommentStoreComment::newUnsavedComment( $summary );
		$updater = $wikipage->newPageUpdater( $user );
		$updater->setContent( 'main', $content );
		$updater->saveRevision( $comment, EDIT_FORCE_BOT );

		// Change the page language
		if ( $this->getConfig()->get( MainConfigNames::PageLanguageUseDB ) ) {
			SpecialPageLanguage::changePageLanguage( $this, $subpage, $language );
		}
	}

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