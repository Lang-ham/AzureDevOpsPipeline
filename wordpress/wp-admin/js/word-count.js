/**
 * Word or character counting functionality. Count words or characters in a provided text string.
 *
 * @summary   Count words or characters in a text.
 *
 * @namespace wp.utils
 * @since     2.6.0
 */

( function() {
	/**
	 * Word counting utility
	 *
	 * @namespace wp.utils.wordcounter
	 * @memberof  wp.utils
	 *
	 * @class
	 *
	 * @param {Object} settings                                   Optional. Key-value object containing overrides for
	 *                                                            settings.
	 * @param {RegExp} settings.HTMLRegExp                        Optional. Regular expression to find HTML elements.
	 * @param {RegExp} settings.HTMLcommentRegExp                 Optional. Regular expression to find HTML comments.
	 * @param {RegExp} settings.spaceRegExp                       Optional. Regular expression to find irregular space
	 *                                                            characters.
	 * @param {RegExp} settings.HTMLEntityRegExp                  Optional. Regular expression to find HTML entities.
	 * @param {RegExp} settings.connectorRegExp                   Optional. Regular expression to find connectors that
	 *                                                            split words.
	 * @param {RegExp} settings.removeRegExp                      Optional. Regular expression to find remove unwanted
	 *                                                            characters to reduce false-positives.
	 * @param {RegExp} settings.astralRegExp                      Optional. Regular expression to find unwanted
	 *                                                            characters when searching for non-words.
	 * @param {RegExp} settings.wordsRegExp                       Optional. Regular expression to find words by spaces.
	 * @param {RegExp} settings.characters_excluding_spacesRegExp Optional. Regular expression to find characters which
	 *                                                            are non-spaces.
	 * @param {RegExp} settings.characters_including_spacesRegExp Optional. Regular expression to find characters
	 *                                                            including spaces.
	 * @param {RegExp} settings.shortcodesRegExp                  Optional. Regular expression to find shortcodes.
	 * @param {Object} settings.l10n                              Optional. Localization object containing specific
	 *                                                            configuration for the current localization.
	 * @param {String} settings.l10n.type                         Optional. Method of finding words to count.
	 * @param {Array}  settings.l10n.shortcodes                   Optional. Array of shortcodes that should be removed
	 *                                                            from the text.
	 *
	 * @return void
	 */
	function WordCounter( settings ) {
		var key,
			shortcodes;

		// Apply provided settings to object settings.
		if ( settings ) {
			for ( key in settings ) {

				// Only apply valid settings.
				if ( settings.hasOwnProperty( key ) ) {
					this.settings[ key ] = settings[ key ];
				}
			}
		}

		shortcodes = this.settings.l10n.shortcodes;

		// If there are any localization shortcodes, add this as type in the settings.
		if ( shortcodes && shortcodes.length ) {
			this.settings.shortcodesRegExp = new RegExp( '\\[\\/?(?:' + shortcodes.join( '|' ) + ')[^\\]]*?\\]', 'g' );
		}
	}

	// Default settings.
	WordCounter.prototype.settings = {
		HTMLRegExp: /<\/?[a-z][^>]*?>/gi,
		HTMLcommentRegExp: /<!--[\s\S]*?-->/g,
		spaceRegExp: /&nbsp;|&#160;/gi,
		HTMLEntityRegExp: /&\S+?;/g,

		// \u2014 = em-dash
		connectorRegExp: /--|\u2014/g,

		// Characters to be removed from input text.
		removeRegExp: new RegExp( [
			'[',

				// Basic Latin (extract)
				'\u0021-\u0040\u005B-\u0060\u007B-\u007E',

				// Latin-1 Supplement (extract)
				'\u0080-\u00BF\u00D7\u00F7',

				/*
				 * The following range consists of:
				 * General Punctuation
	