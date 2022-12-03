/* global tinymce */
/**
 * Included for back-compat.
 * The default WindowManager in TinyMCE 4.0 supports three types of dialogs:
 *	- With HTML created from JS.
 *	- With inline HTML (like WPWindowManager).
 *	- Old type iframe based dialogs.
 * For examples see the default plugins: https://github.com/tinymce/tinymce/tree/master/js/tinymce/plugins
 */
tinymce.WPWindowManager = tinymce.InlineWindowManager = function( editor ) {
	if ( this.wp ) {
		return this;
	}

	this.wp = {};
	this.parent = editor.windowManager;
	this.editor = editor;

	tinymce.extend( this, this.parent );

	this.open = function( args, params ) {
		var $element,
			self = this,
			wp = this.wp;

		if ( ! args.wpDialog ) {
			ret