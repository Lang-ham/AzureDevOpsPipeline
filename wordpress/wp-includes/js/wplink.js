var wpLink;

( function( $, wpLinkL10n, wp ) {
	var editor, searchTimer, River, Query, correctedURL, linkNode,
		emailRegexp = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,63}$/i,
		urlRegexp = /^(https?|ftp):\/\/[A-Z0-9.-]+\.[A-Z]{2,63}[^ "]*$/i,
		inputs = {},
		rivers = {},
		isTouch = ( 'ontouchend' in document );

	function getLink() {
		return linkNode || editor.dom.getParent( editor.selection.getNode(), 'a[href]' );
	}

	wpLink = {
		timeToTriggerRiver: 150,
		minRiverAJAXDuration: 200,
		riverBottomThreshold: 5,
		keySensitivity: 100,
		lastSearch: '',
		textarea: '',
		modalOpen: false,

		init: function() {
			inputs.wrap = $('#wp-link-wrap');
			inputs.dialog = $( '#wp-link' );
			inputs.backdrop = $( '#wp-link-backdrop' );
			inputs.submit = $( '#wp-link-submit' );
			inputs.close = $( '#wp-link-close' );

			// Input
			inputs.text = $( '#wp-link-text' );
			inputs.url = $( '#wp-link-url' );
			inputs.nonce = $( '#_ajax_linking_nonce' );
			inputs.openInNewTab = $( '#wp-link-target' );
			inputs.search = $( '#wp-link-search' );

			// Build Rivers
			rivers.search = new River( $( '#search-results' ) );
			rivers.recent = new River( $( '#most-recent-results' ) );
			rivers.elements = inputs.dialog.find( '.query-results' );

			// Get search notice text
			inputs.queryNotice = $( '#query-notice-message' );
			inputs.queryNoticeTextDefault = inputs.queryNotice.find( '.query-notice-default' );
			inputs.queryNoticeTextHint = inputs.queryNotice.find( '.query-notice-hint' );

			// Bind event handlers
			inputs.dialog.keydown( wpLink.keydown );
			inputs.dialog.keyup( wpLink.keyup );
			inputs.submit.click( function( event ) {
				event.preventDefault();
				wpLink.update();
			});

			inputs.close.add( inputs.backdrop ).add( '#wp-link-cancel button' ).click( function( event ) {
				event.preventDefault();
				wpLink.close();
			});

			rivers.elements.on( 'river-select', wpLink.updateFields );

			// Display 'hint' message when search field or 'query-results' box are focused
			inputs.search.on( 'focus.wplink', function() {
				inputs.queryNoticeTextDefault.hide();
				inputs.queryNoticeTextHint.removeClass( 'screen-reader-text' ).show();
			} ).on( 'blur.wplink', function() {
				inputs.queryNoticeTextDefault.show();
				inputs.queryNoticeTextHint.addClass( 'screen-reader-text' ).hide();
			} );

			inputs.search.on( 'keyup input', function() {
				window.clearTimeout( searchTimer );
				searchTimer = window.setTimeout( function() {
					wpLink.searchInternalLinks();
				}, 500 );
			});

			inputs.url.on( 'paste', function() {
				setTimeout( wpLink.correctURL, 0 );
			} );

			inputs.url.on( 'blur', wpLink.correctURL );
		},

		// If URL wasn't corrected last time and doesn't start with http:, https:, ? # or /, prepend http://
		correctURL: function () {
			var url = $.trim( inputs.url.val() );

			if ( url && correctedURL !== url && ! /^(?:[a-z]+:|#|\?|\.|\/)/.test( url ) ) {
				inputs.url.val( 'http://' + url );
				correctedURL = url;
			}
		},

		open: function( editorId, url, text, 