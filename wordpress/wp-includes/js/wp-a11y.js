/** @namespace wp */
window.wp = window.wp || {};

( function ( wp, $ ) {
	'use strict';

	var $containerPolite,
		$containerAssertive,
		previousMessage = '';

	/**
	 * Update the ARIA live notification area text node.
	 *
	 * @since 4.2.0
	 * @since 4.3.0 Introduced the 'ariaLive' argument.
	 *
	 * @param {String} message    The message to be announced by Assistive Technologies.
	 * @param {String} [ariaLive] The politeness level for aria-live. Possible values:
	 *                            polite or assertive. Default polite.
	 * @returns {void}
	 */
	function speak( message, ariaLive ) {
		// Clear previous messages to allow repeated strings being read out.
		clear();

		// Ensure only text is sent to screen readers.
		message = $