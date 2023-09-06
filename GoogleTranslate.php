<?php

class GoogleTranslate {

	/**
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		global $wgGoogleTranslateSave;

		// If the feature to save translations is enabled, check for the Extension:HTMLPurifier dependecy
		if ( $wgGoogleTranslateSave && ! ExtensionRegistry::getInstance()->isLoaded( 'HTMLPurifier' ) ) {
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
		$vars['wgGoogleTranslateNamespaces'] = $config->get( 'GoogleTranslateNamespaces' );
	}

	/**
	 * @param SkinTemplate $skinTemplate
	 * @param array &$links
	 */
	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skinTemplate, array &$links ) {
		global $wgGoogleTranslateNamespaces, $wgGoogleTranslateNearEdit;

		// Don't add the button if the page doesn't exist
		$skin = $skinTemplate->getSkin();
		$title = $skin->getTitle();
		if ( ! $title->exists() ) {
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
		if ( ! in_array( $namespace, $wgGoogleTranslateNamespaces ) ) {
			return;
		}

		// Define the button
		$readAloud = [
			'id' => 'ca-google-translate',
			'href' => '#',
			'text' => wfMessage( 'googletranslate-translate' )->plain()
		];

		// Add the button
		$location = $wgGoogleTranslateNearEdit ? 'views' : 'actions';
		$links[ $location ]['google-translate'] = $readAloud;
	}
}
