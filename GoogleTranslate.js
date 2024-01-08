window.GoogleTranslate = {

	/**
	 * Initialization script
	 */
	init: function () {
		$( '#ca-google-translate' ).on( 'click', GoogleTranslate.translate );

		// Check for automatic translations every 5 seconds
		if ( mw.config.get( 'wgGoogleTranslateSave' ) ) {
			GoogleTranslate.interval = setInterval( GoogleTranslate.checkTranslation, 5000 );
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

	/**
	 * Check for a valid translation
	 */
	checkTranslation: function () {

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

		// Check for <font> tags because Google Translate inserts a bunch of those
		var translatedNodes = $( '#mw-content-text' ).find( 'font' ).length;
		if ( translatedNodes < 10 ) {
			return;
		}

		// Ignore translations of translations
		// @todo Make more robust, sometimes matches pages with short subpage titles
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

		// Ignore partial translations
		var translatedRatio = GoogleTranslate.getTranslatedRatio();
		if ( translatedRatio < mw.config.get( 'wgGoogleTranslateSaveTreshold' ) ) {
			return;
		}

		// Prepare the data for submission
		var translatedTitle = $( '#firstHeading' ).text();
		var $translatedText = $( '#mw-content-text' ).clone();
		$translatedText.find( '.mw-editsection' ).remove().end(); // Remove edit section links
		var translatedText = $translatedText.html(); // Remove outer .mw-parser-output
		translatedText = translatedText.replace( /\n\s+|\n/g, '' ); // Remove extra spacing
		translatedText = translatedText.replace( /<!--(.*?)-->/g, '' ); // Remove HTML comments
		if ( translationLanguage === 'iw' ) {
			translationLanguage = 'he'; // Fix old Hebrew language code
		}

		// Send the translation for backend processing
		new mw.Api().postWithEditToken( {
			action: 'googletranslatesave',
			page: mw.config.get( 'wgPageName' ),
			language: translationLanguage,
			title: translatedTitle,
			text: translatedText
		} );

		// Don't save more than once
		clearInterval( GoogleTranslate.interval );
	},

	/**
	 * Helper method to estimate the % of the HTML translated
	 *
	 * @return {number} Translated ratio, for example 0.42
	 */
	getTranslatedRatio: function () {
		var $translation = $( '#mw-content-text' );

		// Get the nodes that can be translated
		var $translatableNodes = $translation.find( '*' ).contents().filter( function () {
			if ( this.nodeType !== Node.TEXT_NODE ) {
				return false;
			}
			var value = this.nodeValue.trim();
			if ( !value ) {
				return false;
			}
			if ( value.length < 5 ) {
				return false;
			}
			if ( !isNaN( value ) ) {
				return false;
			}
			if ( GoogleTranslate.isValidUrl( value ) ) {
				return false;
			}
			return true;
		} );

		// Get the nodes that are actually translated
		var $translatedNodes = $translatableNodes.parent( 'font' );

		// Return the ratio
		return $translatedNodes.length / $translatableNodes.length;
	},

	/**
	 * Helper method to determine if the given string is a valid URL
	 *
	 * @param {string} string
	 * @return {boolean}
	 */
	isValidUrl: function ( string ) {
		var url;
		try {
			url = new URL( string );
		} catch ( error ) {
			return false;
		}
		return url.protocol === 'http:' || url.protocol === 'https:';
	}
};

mw.loader.using( 'mediawiki.api', GoogleTranslate.init );
