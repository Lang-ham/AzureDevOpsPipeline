
/* global YT */
(function( window, settings ) {

	var NativeHandler, YouTubeHandler;

	/** @namespace wp */
	window.wp = window.wp || {};

	// Fail gracefully in unsupported browsers.
	if ( ! ( 'addEventListener' in window ) ) {
		return;
	}

	/**
	 * Trigger an event.
	 *
	 * @param {Element} target HTML element to dispatch the event on.
	 * @param {string} name Event name.
	 */
	function trigger( target, name ) {
		var evt;

		if ( 'function' === typeof window.Event ) {