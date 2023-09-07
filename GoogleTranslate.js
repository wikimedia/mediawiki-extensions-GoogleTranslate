window.GoogleTranslate = {

	/**
	 * Initialization script
	 */
	init: function () {
		$( '#ca-google-translate' ).on( 'click', GoogleTranslate.translate );

		// Check for automatic translations every 5 seconds
		if ( mw.config.get( 'wgGoogleTranslateSave' ) ) {
			GoogleTranslate.interval = setInterval( GoogleTranslate.checkForTranslation, 5000 );
		}
	},

	/**
	 * Translate the current page by loading a hidden Google Translate element and clicking it
	 */
	translate: function () {

		// If Google Translate is not loaded yet, load it and try again
		var $googleTranslateElement = $( '#google-translate-element' );
		if ( !$googleTranslateElement.length ) {
			$googleTranslateElement = $( '<div>' ).attr( 'id', 'google-translate-element' ).hide();
			$( 'body' ).after( $googleTranslateElement );
			$.getScript( '//translate.google.com/translate_a/element.js?cb=GoogleTranslate.translate' );
			return;
		}

		// Initialize the translate element
		google.translate.TranslateElement( {
			pageLanguage: mw.config.get( 'wgPageContentLanguage' ),
			layout: google.translate.TranslateElement.InlineLayout.SIMPLE
		}, 'google-translate-element' );

		// Wait for the element to load and then open the language list
		// @todo Wait for the relevant element rather than setTimeout
		setTimeout( function () {
			$( '#google-translate-element .goog-te-gadget-simple' ).trigger( 'click' );
		}, 1000 );

		// Make the language menu scrollable in small screens
		// @todo Wait for the relevant element rather than setTimeout
		setTimeout( function () {
			var $frames = $( '#goog-gt-tt' ).nextAll( 'iframe' );
			if ( $frames.length ) {
				$frames.attr( 'scrollable', true ).css( 'max-width', '100%' );
				$frames.contents().find( 'body' ).css( 'overflow', 'scroll' );
			}
		}, 1000 );
	},

	checkForTranslation: function () {

		// Only check for translations when viewing content
		var action = mw.config.get( 'wgAction' );
		if ( action !== 'view' ) {
			return;
		}

		// Only check for translations in configured namespaces
		var namespace = mw.config.get( 'wgNamespaceNumber' );
		var namespaces = mw.config.get( 'wgGoogleTranslateNamespaces' );
		if ( namespaces.indexOf( namespace ) === -1 ) {
			return;
		}

		// Check for <font> tags because Google Translate inserts MANY such tags
		var $content = $( '#mw-content-text > .mw-parser-output' ).clone();
		$content.find( '.mw-editsection' ).remove(); // Remove edit section links
		var translatedNodes = $content.find( 'font' ).length;
		if ( translatedNodes < 10 ) {
			return;
		}

		// Ignore translations of translations
		var title = mw.config.get( 'wgPageName' );
		if ( title.match( '/[a-z]{2,3}$' ) ) {
			return;
		}

		// Ignore rare and mysterious 'auto' language
		var translationLanguage = $( 'html' ).attr( 'lang' ).replace( /-.+/, '' ).trim();
		if ( translationLanguage === 'auto' ) {
			return;
		}

		// Ignore translations to the same language (including language variants like en-GB)
		var contentLanguage = mw.config.get( 'wgPageContentLanguage' );
		if ( contentLanguage === translationLanguage ) {
			return;
		}

		// Check if there's a saved translation already and record its length
		if ( GoogleTranslate.savedNodes === undefined ) {
			new mw.Api().get( {
				action: 'parse',
				formatversion: 2,
				page: title + '/' + translationLanguage
			} ).done( function ( data ) {
				var html = data.parse.text;
				var $html = $( html );
				var nodes = $html.find( 'font' ).length;
				GoogleTranslate.savedNodes = nodes;
			} ).fail( function () {
				GoogleTranslate.savedNodes = 0;
			} );
			return;
		}

		// Save the current translation but only if it's longer than the saved one
		if ( GoogleTranslate.savedNodes < translatedNodes ) {
			GoogleTranslate.savedNodes = translatedNodes;
			GoogleTranslate.saveTranslation();
		}
	},

	saveTranslation: function () {

		// Fix old Hebrew language code
		var translationLanguage = $( 'html' ).attr( 'lang' ).replace( /-.+/, '' );
		if ( translationLanguage === 'iw' ) {
			translationLanguage = 'he';
		}

		// If the user reverts to the original language
		var contentLanguage = mw.config.get( 'wgPageContentLanguage' );
		if ( contentLanguage === translationLanguage ) {
			return;
		}

		// Get the translated title
		var translatedTitle = $( '#firstHeading' ).text();

		// Get the translated text
		var $text = $( '#mw-content-text > .mw-parser-output' ).clone();
		$text.find( '.mw-editsection' ).remove().end(); // Remove edit section links
		var translatedText = $text.html();
		translatedText = translatedText.replace( /\n\s+|\n/g, '' ); // Minify

		// Send the raw translation for backend processing
		var page = mw.config.get( 'wgPageName' );
		new mw.Api().postWithEditToken( {
			action: 'googletranslatesave',
			page: page,
			language: translationLanguage,
			title: translatedTitle,
			text: translatedText
		} );
	}
};

mw.loader.using( 'mediawiki.api', GoogleTranslate.init );
