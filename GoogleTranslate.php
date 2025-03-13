<?php

use MediaWiki\MediaWikiServices;

class GoogleTranslate {

	/**
	 * Add the JavaScript module and check for Extension:HTMLPurifier dependecy
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$config = $skin->getConfig();

		// If the feature to save translations is enabled, check for the Extension:HTMLPurifier dependecy
		if ( $config->get( 'GoogleTranslateSave' ) && !ExtensionRegistry::getInstance()->isLoaded( 'HTMLPurifier' ) ) {
			$error = $out->msg( 'googletranslate-htmlpurifier-error' )->plain();
			throw new MWException( $error );
		}

		$out->addModules( 'ext.GoogleTranslate' );
	}

	/**
	 * Pass some of the config to JavaScript
	 *
	 * @param array &$vars
	 * @param string $skin
	 * @param Config $config
	 */
	public static function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ) {
		$vars['wgGoogleTranslateSave'] = $config->get( 'GoogleTranslateSave' );
		$vars['wgGoogleTranslateSaveTreshold'] = $config->get( 'GoogleTranslateSaveTreshold' );
		$vars['wgGoogleTranslateNamespaces'] = $config->get( 'GoogleTranslateNamespaces' );
	}

	/**
	 * Add the Translate button
	 *
	 * @param SkinTemplate $skinTemplate
	 * @param array &$links
	 */
	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skinTemplate, array &$links ) {
		$config = $skinTemplate->getConfig();

		// Don't add the button if the page doesn't exist
		$skin = $skinTemplate->getSkin();
		$title = $skin->getTitle();
		if ( !$title->exists() ) {
			return;
		}

		// Don't add the button in redirects
		if ( $title->isRedirect() ) {
			return;
		}

		// Only add the button when viewing pages
		$context = $skin->getContext();
		$action = Action::getActionName( $context );
		if ( $action !== 'view' ) {
			return;
		}

		// Only add the button in whitelisted namespaces
		$namespace = $title->getNamespace();
		$namespaces = $config->get( 'GoogleTranslateNamespaces' );
		if ( !in_array( $namespace, $namespaces ) ) {
			return;
		}

		// Define the button
		$readAloud = [
			'id' => 'ca-google-translate',
			'href' => '#',
			'text' => wfMessage( 'googletranslate-translate' )->plain()
		];

		// Add the button
		$location = $config->get( 'GoogleTranslateNearEdit' ) ? 'views' : 'actions';
		$links[ $location ]['google-translate'] = $readAloud;
	}

	/**
	 * Change the page language based on the subpage name
	 *
	 * @param MediaWiki\Title\Title $title
	 * @param mixed &$pageLang
	 */
	public static function onPageContentLanguage( MediaWiki\Title\Title $title, &$pageLang ) {
		global $wgGoogleTranslateSubpageLanguage, $wgNamespacesWithSubpages;

		// Check that this option is enabled
		if ( !$wgGoogleTranslateSubpageLanguage ) {
			return;
		}

		// Check that subpages for this namespace are enabled
		$namespace = $title->getNamespace();
		$namespaces = array_keys( $wgNamespacesWithSubpages );
		if ( !in_array( $namespace, $namespaces ) ) {
			return;
		}

		// Check that this is actualy a subpage
		if ( !$title->isSubpage() ) {
			return;
		}

		// Check if the subpage is a supported language code
		$subpage = $title->getSubpageText();
		$mediawikiServices = MediaWikiServices::getInstance();
		$languageNameUtils = $mediawikiServices->getLanguageNameUtils();
		if ( !$languageNameUtils->isSupportedLanguage( $subpage ) ) {
			return;
		}

		// Set the page language to the subpage name
		$languageFactory = $mediawikiServices->getLanguageFactory();
		$pageLang = $languageFactory->getLanguage( $subpage );
	}
}
