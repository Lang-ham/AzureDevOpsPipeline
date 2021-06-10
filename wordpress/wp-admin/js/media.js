/* global ajaxurl, attachMediaBoxL10n, _wpMediaGridSettings, showNotice */

/**
 * @summary Creates a dialog containing posts that can have a particular media attached to it.
 *
 * @since 2.7.0
 *
 * @global
 * @namespace
 *
 * @requires jQuery
 */
var findPosts;

( function( $ ){
	findPosts = {
		/**
		 * @summary Opens a dialog to attach media to a post.
		 *
		 * Adds an overlay prior to retrieving a list of posts to attach the media to.
		 *
		 * @since 2.7.0
		 *
		 * @memberOf findPosts
		 *
		 * @param {string} af_name The name of the affected element.
		 * @param {string} af_val The value of the affected post element.
		 *
		 * @returns {boolean} Always returns false.
		 */
		open: function( af_name, af_val ) {
			var overlay = $( '.ui-find-overlay' );

			if ( overlay.length === 0 ) {
				$( 'body' ).append( '<div class="ui-find-overlay"></div>' );
				findPosts.overlay();
			}

			overlay.show();

			if ( af_name && af_val ) {
				// #affected is a hidden input field in the dialog that keeps track of which media should be attached.
				$( '#affected' ).attr( 'name', af_name ).val( af_val );
			}

			$( '#find-posts' ).show();

			// Close the dialog when the escape key is pressed.
			$('#find-posts-input').focus().keyup( function( event ){
				if ( event.which == 27 ) {
					findPosts.close();
				}
			});

			// Retrieves a list of applicable posts for media attachment and shows them.
			findPosts.send();

			return false;
		},

		/**
		 * @summary Clears the found posts lists before hiding the attach media dialog.
		 *
		 * @since 2.7.0
		 *
		 * @memberOf findPosts
		 *
		 * @returns {void}
		 */
		close: function() {
			$('#find-posts-response').empty();
			$('#find-posts').hide();
			$( '.ui-find-overlay' ).hide();
		},

		/**
		 * @summary Binds a click event listener to the overlay which closes the attach media dialog.
		 *
		 * @since 3.5.0
		 *
		 * @memberOf findPosts
		 *
		 * @returns {void}
		 */
		overlay: function() {
			$( '.ui-find-overlay' ).on( 'click', function () {
				findPosts.close();
			});
		},

		/**
		 * @summary Retrieves and displays posts based on the search term.
		 *
		 * Sends a post request to the admin_ajax.php, requesting posts based on the search term provided by the user.
		 * Defaults to all posts if no search term is provided.
		 *
		 * @since 2.7.0
		 *
		 * @memberOf findPosts
		 *
		 * @returns {void}
		 */
		send: function() {
			var post = {
					p