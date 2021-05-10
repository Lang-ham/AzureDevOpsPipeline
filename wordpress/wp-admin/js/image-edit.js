/* global imageEditL10n, ajaxurl, confirm */
/**
 * @summary   The functions necessary for editing images.
 *
 * @since     2.9.0
 */

(function($) {

	/**
	 * Contains all the methods to initialise and control the image editor.
	 *
	 * @namespace imageEdit
	 */
	var imageEdit = window.imageEdit = {
	iasapi : {},
	hold : {},
	postid : '',
	_view : false,

	/**
	 * @summary Converts a value to an integer.
	 *
	 * @memberof imageEdit
	 * @since    2.9.0
	 *
	 * @param {number} f The float value that should be converted.
	 *
	 * @return {number} The integer representation from the float value.
	 */
	intval : function(f) {
		/*
		 * Bitwise OR operator: one of the obscure ways to truncate floating point figures,
		 * worth reminding JavaScript doesn't have a distinct "integer" type.
		 */
		return f | 0;
	},

	/**
	 * @summary Adds the disabled attribute and class to a single form element
	 *          or a field set.
	 *
	 * @memberof imageEdit
	 * @since    2.9.0
	 *
	 * @param {jQuery}         el The element that should be modified.
	 * @param {bool|number}    s  The state for the element. If set to true
	 *                            the element is disabled,
	 *                            otherwise the element is enabled.
	 *                            The function is sometimes called with a 0 or 1
	 *                            instead of true or false.
	 *
	 * @returns {void}
	 */
	setDisabled : function( el, s ) {
		/*
		 * `el` can be a single form element or a fieldset. Before #28864, the disabled state on
		 * some text fields  was handled targeting $('input', el). Now we need to handle the
		 * disabled state on buttons too so we can just target `el` regardless if it's a single
		 * element or a fieldset because when a fieldset is disabled, its descendants are disabled too.
		 */
		if ( s ) {
			el.removeClass( 'disabled' ).prop( 'disabled', false );
		} else {
			el.addClass( 'disabled' ).prop( 'disabled', true );
		}
	},

	/**
	 * @summary Initializes the image editor.
	 *
	 * @memberof imageEdit
	 * @since    2.9.0
	 *
	 * @param {number} postid The post id.
	 *
	 * @returns {void}
	 */
	init : function(postid) {
		var t = this, old = $('#image-editor-' + t.postid),
			x = t.intval( $('#imgedit-x-' + postid).val() ),
			y = t.intval( $('#imgedit-y-' + postid).val() );

		if ( t.postid !== postid && old.length ) {
			t.close(t.postid);
		}

		t.hold.w = t.hold.ow = x;
		t.hold.h = t.hold.oh = y;
		t.hold.xy_ratio = x / y;
		t.hold.sizer = parseFloat( $('#imgedit-sizer-' + postid).val() );
		t.postid = postid;
		$('#imgedit-response-' + postid).empty();

		$('input[type="text"]', '#imgedit-panel-' + postid).keypress(function(e) {
			var k = e.keyCode;

			// Key codes 37 thru 40 are the arrow keys.
			if ( 36 < k && k < 41 ) {
				$(this).blur();
			}

			// The key code 13 is the enter key.
			if ( 13 === k ) {
				e.preventDefault();
				e.stopPropagation();
				return false;
			}
		});
	},

	/**
	 * @summary Toggles the wait/load icon in the editor.
	 *
	 * @memberof imageEdit
	 * @since    2.9.0
	 *
	 * @param {number} postid The post id.
	 * @param {number} toggle Is 0 or 1, fades the icon in then 1 and out when 0.
	 *
	 * @returns {void}
	 */
	toggleEditor : function(postid, toggle) {
		var wait = $('#imgedit-wait-' + postid);

		if ( toggle ) {
			wait.fadeIn( 'fast' );
		} else {
			wait.fadeOut('fast');
		}
	},

	/**
	 * @summary Shows or hides the image edit help box.
	 *
	 * @memberof imageEdit
	 * @since    2.9.0
	 *
	 * @param {HTMLElement} el The element to create the help window in.
	 *
	 * @returns {boolean} Always returns false.
	 */
	toggleHelp : function(el) {
		var $el = $( el );
		$el
			.attr( 'aria-expanded', 'false' === $el.attr( 'aria-expanded' ) ? 'true' : 'false' )
			.parents( '.imgedit-group-top' ).toggleClass( 'imgedit-help-toggled' ).find( '.imgedit-help' ).slideToggle( 'fast' );

		return false;
	},

	/**
	 * @summary Gets the value from the image edit target.
	 *
	 * The image edit target contains the image sizes where the (possible) changes
	 * have to be applied to.
	 *
	 * @memberof imageEdit
	 * @since    2.9.0
	 *
	 * @param {number} postid The post id.
	 *
	 * @returns {string} The value from the imagedit-save-target input field when available,
	 *                   or 'full' when not available.
	 */
	getTarget : function(postid) {
		return $('input[name="imgedit-target-' + postid + '"]:checked', '#imgedit-save-target-' + postid).val() || 'full';
	},

	/**
	 * @summary Recalculates the height or width a