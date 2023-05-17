<?php
/**
 * WordPress scripts and styles default loader.
 *
 * Several constants are used to manage the loading, concatenating and compression of scripts and CSS:
 * define('SCRIPT_DEBUG', true); loads the development (non-minified) versions of all scripts and CSS, and disables compression and concatenation,
 * define('CONCATENATE_SCRIPTS', false); disables compression and concatenation of scripts and CSS,
 * define('COMPRESS_SCRIPTS', false); disables compression of scripts,
 * define('COMPRESS_CSS', false); disables compression of CSS,
 * define('ENFORCE_GZIP', true); forces gzip for compression (default is deflate).
 *
 * The globals $concatenate_scripts, $compress_scripts and $compress_css can be set by plugins
 * to temporarily override the above settings. Also a compression test is run once and the result is saved
 * as option 'can_compress_scripts' (0/1). The test will run again if that option is deleted.
 *
 * @package WordPress
 */

/** WordPress Dependency Class */
require( ABSPATH . WPINC . '/class-wp-dependency.php' );

/** WordPress Dependencies Class */
require( ABSPATH . WPINC . '/class.wp-dependencies.php' );

/** WordPress Scripts Class */
require( ABSPATH . WPINC . '/class.wp-scripts.php' );

/** WordPress Scripts Functions */
require( ABSPATH . WPINC . '/functions.wp-scripts.php' );

/** WordPress Styles Class */
require( ABSPATH . WPINC . '/class.wp-styles.php' );

/** WordPress Styles Functions */
require( ABSPATH . WPINC . '/functions.wp-styles.php' );

/**
 * Register all WordPress scripts.
 *
 * Localizes some of them.
 * args order: `$scripts->add( 'handle', 'url', 'dependencies', 'query-string', 1 );`
 * when last arg === 1 queues the script for the footer
 *
 * @since 2.6.0
 *
 * @param WP_Scripts $scripts WP_Scripts object.
 */
function wp_default_scripts( &$scripts ) {
	include( ABSPATH . WPINC . '/version.php' ); // include an unmodified $wp_version

	$develop_src = false !== strpos( $wp_version, '-src' );

	if ( ! defined( 'SCRIPT_DEBUG' ) ) {
		define( 'SCRIPT_DEBUG', $develop_src );
	}

	if ( ! $guessurl = site_url() ) {
		$guessed_url = true;
		$guessurl = wp_guess_url();
	}

	$scripts->base_url = $guessurl;
	$scripts->content_url = defined('WP_CONTENT_URL')? WP_CONTENT_URL : '';
	$scripts->default_version = get_bloginfo( 'version' );
	$scripts->default_dirs = array('/wp-admin/js/', '/wp-includes/js/');

	$suffix = SCRIPT_DEBUG ? '' : '.min';
	$dev_suffix = $develop_src ? '' : '.min';

	$scripts->add( 'utils', "/wp-includes/js/utils$suffix.js" );
	did_action( 'init' ) && $scripts->localize( 'utils', 'userSettings', array(
		'url' => (string) SITECOOKIEPATH,
		'uid' => (string) get_current_user_id(),
		'time' => (string) time(),
		'secure' => (string) ( 'https' === parse_url( site_url(), PHP_URL_SCHEME ) ),
	) );

	$scripts->add( 'common', "/wp-admin/js/common$suffix.js", array('jquery', 'hoverIntent', 'utils'), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'common', 'commonL10n', array(
		'warnDelete'   => __( "You are about to permanently delete these items from your site.\nThis action cannot be undone.\n 'Cancel' to stop, 'OK' to delete." ),
		'dismiss'      => __( 'Dismiss this notice.' ),
		'collapseMenu' => __( 'Collapse Main menu' ),
		'expandMenu'   => __( 'Expand Main menu' ),
	) );

	$scripts->add( 'wp-a11y', "/wp-includes/js/wp-a11y$suffix.js", array( 'jquery' ), false, 1 );

	$scripts->add( 'sack', "/wp-includes/js/tw-sack$suffix.js", array(), '1.6.1', 1 );

	$scripts->add( 'quicktags', "/wp-includes/js/quicktags$suffix.js", array(), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'quicktags', 'quicktagsL10n', array(
		'closeAllOpenTags'      => __( 'Close all open tags' ),
		'closeTags'             => __( 'close tags' ),
		'enterURL'              => __( 'Enter the URL' ),
		'enterImageURL'         => __( 'Enter the URL of the image' ),
		'enterImageDescription' => __( 'Enter a description of the image' ),
		'textdirection'         => __( 'text direction' ),
		'toggleTextdirection'   => __( 'Toggle Editor Text Direction' ),
		'dfw'                   => __( 'Distraction-free writing mode' ),
		'strong'          => __( 'Bold' ),
		'strongClose'     => __( 'Close bold tag' ),
		'em'              => __( 'Italic' ),
		'emClose'         => __( 'Close italic tag' ),
		'link'            => __( 'Insert link' ),
		'blockquote'      => __( 'Blockquote' ),
		'blockquoteClose' => __( 'Close blockquote tag' ),
		'del'             => __( 'Deleted text (strikethrough)' ),
		'delClose'        => __( 'Close deleted text tag' ),
		'ins'             => __( 'Inserted text' ),
		'insClose'        => __( 'Close inserted text tag' ),
		'image'           => __( 'Insert image' ),
		'ul'              => __( 'Bulleted list' ),
		'ulClose'         => __( 'Close bulleted list tag' ),
		'ol'              => __( 'Numbered list' ),
		'olClose'         => __( 'Close numbered list tag' ),
		'li'              => __( 'List item' ),
		'liClose'         => __( 'Close list item tag' ),
		'code'            => __( 'Code' ),
		'codeClose'       => __( 'Close code tag' ),
		'more'            => __( 'Insert Read More tag' ),
	) );

	$scripts->add( 'colorpicker', "/wp-includes/js/colorpicker$suffix.js", array('prototype'), '3517m' );

	$scripts->add( 'editor', "/wp-admin/js/editor$suffix.js", array('utils','jquery'), false, 1 );

	// Back-compat for old DFW. To-do: remove at the end of 2016.
	$scripts->add( 'wp-fullscreen-stub', "/wp-admin/js/wp-fullscreen-stub$suffix.js", array(), false, 1 );

	$scripts->add( 'wp-ajax-response', "/wp-includes/js/wp-ajax-response$suffix.js", array('jquery'), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'wp-ajax-response', 'wpAjax', array(
		'noPerm' => __('Sorry, you are not allowed to do that.'),
		'broken' => __('An unidentified error has occurred.')
	) );

	$scripts->add( 'wp-api-request', "/wp-includes/js/api-request$suffix.js", array( 'jquery' ), false, 1 );
	// `wpApiSettings` is also used by `wp-api`, which depends on this script.
	did_action( 'init' ) && $scripts->localize( 'wp-api-request', 'wpApiSettings', array(
		'root'          => esc_url_raw( get_rest_url() ),
		'nonce'         => ( wp_installing() && ! is_multisite() ) ? '' : wp_create_nonce( 'wp_rest' ),
		'versionString' => 'wp/v2/',
	) );

	$scripts->add( 'wp-pointer', "/wp-includes/js/wp-pointer$suffix.js", array( 'jquery-ui-widget', 'jquery-ui-position' ), '20111129a', 1 );
	did_action( 'init' ) && $scripts->localize( 'wp-pointer', 'wpPointerL10n', array(
		'dismiss' => __('Dismiss'),
	) );

	$scripts->add( 'autosave', "/wp-includes/js/autosave$suffix.js", array('heartbeat'), false, 1 );

	$scripts->add( 'heartbeat', "/wp-includes/js/heartbeat$suffix.js", array('jquery'), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'heartbeat', 'heartbeatSettings',
		/**
		 * Filters the Heartbeat settings.
		 *
		 * @since 3.6.0
		 *
		 * @param array $settings Heartbeat settings array.
		 */
		apply_filters( 'heartbeat_settings', array() )
	);

	$scripts->add( 'wp-auth-check', "/wp-includes/js/wp-auth-check$suffix.js", array('heartbeat'), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'wp-auth-check', 'authcheckL10n', array(
		'beforeunload' => __('Your session has expired. You can log in again from this page or go to the login page.'),

		/**
		 * Filters the authentication check interval.
		 *
		 * @since 3.6.0
		 *
		 * @param int $interval The interval in which to check a user's authentication.
		 *                      Default 3 minutes in seconds, or 180.
		 */
		'interval' => apply_filters( 'wp_auth_check_interval', 3 * MINUTE_IN_SECONDS ),
	) );

	$scripts->add( 'wp-lists', "/wp-includes/js/wp-lists$suffix.js", array( 'wp-ajax-response', 'jquery-color' ), false, 1 );

	// WordPress no longer uses or bundles Prototype or script.aculo.us. These are now pulled from an external source.
	$scripts->add( 'prototype', 'https://ajax.googleapis.com/ajax/libs/prototype/1.7.1.0/prototype.js', array(), '1.7.1');
	$scripts->add( 'scriptaculous-root', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/scriptaculous.js', array('prototype'), '1.9.0');
	$scripts->add( 'scriptaculous-builder', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/builder.js', array('scriptaculous-root'), '1.9.0');
	$scripts->add( 'scriptaculous-dragdrop', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/dragdrop.js', array('scriptaculous-builder', 'scriptaculous-effects'), '1.9.0');
	$scripts->add( 'scriptaculous-effects', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/effects.js', array('scriptaculous-root'), '1.9.0');
	$scripts->add( 'scriptaculous-slider', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/slider.js', array('scriptaculous-effects'), '1.9.0');
	$scripts->add( 'scriptaculous-sound', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/sound.js', array( 'scriptaculous-root' ), '1.9.0' );
	$scripts->add( 'scriptaculous-controls', 'https://ajax.googleapis.com/ajax/libs/scriptaculous/1.9.0/controls.js', array('scriptaculous-root'), '1.9.0');
	$scripts->add( 'scriptaculous', false, array('scriptaculous-dragdrop', 'scriptaculous-slider', 'scriptaculous-controls') );

	// not used in core, replaced by Jcrop.js
	$scripts->add( 'cropper', '/wp-includes/js/crop/cropper.js', array('scriptaculous-dragdrop') );

	// jQuery
	$scripts->add( 'jquery', false, array( 'jquery-core', 'jquery-migrate' ), '1.12.4' );
	$scripts->add( 'jquery-core', '/wp-includes/js/jquery/jquery.js', array(), '1.12.4' );
	$scripts->add( 'jquery-migrate', "/wp-includes/js/jquery/jquery-migrate$suffix.js", array(), '1.4.1' );

	// full jQuery UI
	$scripts->add( 'jquery-ui-core', "/wp-includes/js/jquery/ui/core$dev_suffix.js", array('jquery'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-core', "/wp-includes/js/jquery/ui/effect$dev_suffix.js", array('jquery'), '1.11.4', 1 );

	$scripts->add( 'jquery-effects-blind', "/wp-includes/js/jquery/ui/effect-blind$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-bounce', "/wp-includes/js/jquery/ui/effect-bounce$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-clip', "/wp-includes/js/jquery/ui/effect-clip$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-drop', "/wp-includes/js/jquery/ui/effect-drop$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-explode', "/wp-includes/js/jquery/ui/effect-explode$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-fade', "/wp-includes/js/jquery/ui/effect-fade$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-fold', "/wp-includes/js/jquery/ui/effect-fold$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-highlight', "/wp-includes/js/jquery/ui/effect-highlight$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-puff', "/wp-includes/js/jquery/ui/effect-puff$dev_suffix.js", array('jquery-effects-core', 'jquery-effects-scale'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-pulsate', "/wp-includes/js/jquery/ui/effect-pulsate$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-scale', "/wp-includes/js/jquery/ui/effect-scale$dev_suffix.js", array('jquery-effects-core', 'jquery-effects-size'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-shake', "/wp-includes/js/jquery/ui/effect-shake$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-size', "/wp-includes/js/jquery/ui/effect-size$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-slide', "/wp-includes/js/jquery/ui/effect-slide$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-effects-transfer', "/wp-includes/js/jquery/ui/effect-transfer$dev_suffix.js", array('jquery-effects-core'), '1.11.4', 1 );

	$scripts->add( 'jquery-ui-accordion', "/wp-includes/js/jquery/ui/accordion$dev_suffix.js", array('jquery-ui-core', 'jquery-ui-widget'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-autocomplete', "/wp-includes/js/jquery/ui/autocomplete$dev_suffix.js", array( 'jquery-ui-menu', 'wp-a11y' ), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-button', "/wp-includes/js/jquery/ui/button$dev_suffix.js", array('jquery-ui-core', 'jquery-ui-widget'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-datepicker', "/wp-includes/js/jquery/ui/datepicker$dev_suffix.js", array('jquery-ui-core'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-dialog', "/wp-includes/js/jquery/ui/dialog$dev_suffix.js", array('jquery-ui-resizable', 'jquery-ui-draggable', 'jquery-ui-button', 'jquery-ui-position'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-draggable', "/wp-includes/js/jquery/ui/draggable$dev_suffix.js", array('jquery-ui-mouse'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-droppable', "/wp-includes/js/jquery/ui/droppable$dev_suffix.js", array('jquery-ui-draggable'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-menu', "/wp-includes/js/jquery/ui/menu$dev_suffix.js", array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position' ), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-mouse', "/wp-includes/js/jquery/ui/mouse$dev_suffix.js", array( 'jquery-ui-core', 'jquery-ui-widget' ), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-position', "/wp-includes/js/jquery/ui/position$dev_suffix.js", array('jquery'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-progressbar', "/wp-includes/js/jquery/ui/progressbar$dev_suffix.js", array('jquery-ui-core', 'jquery-ui-widget'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-resizable', "/wp-includes/js/jquery/ui/resizable$dev_suffix.js", array('jquery-ui-mouse'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-selectable', "/wp-includes/js/jquery/ui/selectable$dev_suffix.js", array('jquery-ui-mouse'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-selectmenu', "/wp-includes/js/jquery/ui/selectmenu$dev_suffix.js", array('jquery-ui-menu'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-slider', "/wp-includes/js/jquery/ui/slider$dev_suffix.js", array('jquery-ui-mouse'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-sortable', "/wp-includes/js/jquery/ui/sortable$dev_suffix.js", array('jquery-ui-mouse'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-spinner', "/wp-includes/js/jquery/ui/spinner$dev_suffix.js", array( 'jquery-ui-button' ), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-tabs', "/wp-includes/js/jquery/ui/tabs$dev_suffix.js", array('jquery-ui-core', 'jquery-ui-widget'), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-tooltip', "/wp-includes/js/jquery/ui/tooltip$dev_suffix.js", array( 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-position' ), '1.11.4', 1 );
	$scripts->add( 'jquery-ui-widget', "/wp-includes/js/jquery/ui/widget$dev_suffix.js", array('jquery'), '1.11.4', 1 );

	// Strings for 'jquery-ui-autocomplete' live region messages
	did_action( 'init' ) && $scripts->localize( 'jquery-ui-autocomplete', 'uiAutocompleteL10n', array(
		'noResults' => __( 'No results found.' ),
		/* translators: Number of results found when using jQuery UI Autocomplete */
		'oneResult' => __( '1 result found. Use up and down arrow keys to navigate.' ),
		/* translators: %d: Number of results found when using jQuery UI Autocomplete */
		'manyResults' => __( '%d results found. Use up and down arrow keys to navigate.' ),
		'itemSelected' => __( 'Item selected.' ),
	) );

	// deprecated, not used in core, most functionality is included in jQuery 1.3
	$scripts->add( 'jquery-form', "/wp-includes/js/jquery/jquery.form$suffix.js", array('jquery'), '4.2.1', 1 );

	// jQuery plugins
	$scripts->add( 'jquery-color', "/wp-includes/js/jquery/jquery.color.min.js", array('jquery'), '2.1.1', 1 );
	$scripts->add( 'schedule', '/wp-includes/js/jquery/jquery.schedule.js', array('jquery'), '20m', 1 );
	$scripts->add( 'jquery-query', "/wp-includes/js/jquery/jquery.query.js", array('jquery'), '2.1.7', 1 );
	$scripts->add( 'jquery-serialize-object', "/wp-includes/js/jquery/jquery.serialize-object.js", array('jquery'), '0.2', 1 );
	$scripts->add( 'jquery-hotkeys', "/wp-includes/js/jquery/jquery.hotkeys$suffix.js", array('jquery'), '0.0.2m', 1 );
	$scripts->add( 'jquery-table-hotkeys', "/wp-includes/js/jquery/jquery.table-hotkeys$suffix.js", array('jquery', 'jquery-hotkeys'), false, 1 );
	$scripts->add( 'jquery-touch-punch', "/wp-includes/js/jquery/jquery.ui.touch-punch.js", array('jquery-ui-widget', 'jquery-ui-mouse'), '0.2.2', 1 );

	// Not used any more, registered for backwards compatibility.
	$scripts->add( 'suggest', "/wp-includes/js/jquery/suggest$suffix.js", array('jquery'), '1.1-20110113', 1 );

	// Masonry v2 depended on jQuery. v3 does not. The older jquery-masonry handle is a shiv.
	// It sets jQuery as a dependency, as the theme may have been implicitly loading it this way.
	$scripts->add( 'imagesloaded', "/wp-includes/js/imagesloaded.min.js", array(), '3.2.0', 1 );
	$scripts->add( 'masonry', "/wp-includes/js/masonry.min.js", array( 'imagesloaded' ), '3.3.2', 1 );
	$scripts->add( 'jquery-masonry', "/wp-includes/js/jquery/jquery.masonry$dev_suffix.js", array( 'jquery', 'masonry' ), '3.1.2b', 1 );

	$scripts->add( 'thickbox', "/wp-includes/js/thickbox/thickbox.js", array('jquery'), '3.1-20121105', 1 );
	did_action( 'init' ) && $scripts->localize( 'thickbox', 'thickboxL10n', array(
		'next' => __('Next &gt;'),
		'prev' => __('&lt; Prev'),
		'image' => __('Image'),
		'of' => __('of'),
		'close' => __('Close'),
		'noiframes' => __('This feature requires inline frames. You have iframes disabled or your browser does not support them.'),
		'loadingAnimation' => includes_url('js/thickbox/loadingAnimation.gif'),
	) );

	$scripts->add( 'jcrop', "/wp-includes/js/jcrop/jquery.Jcrop.min.js", array('jquery'), '0.9.12');

	$scripts->add( 'swfobject', "/wp-includes/js/swfobject.js", array(), '2.2-20120417');

	// Error messages for Plupload.
	$uploader_l10n = array(
		'queue_limit_exceeded' => __('You have attempted to queue too many files.'),
		'file_exceeds_size_limit' => __('%s exceeds the maximum upload size for this site.'),
		'zero_byte_file' => __('This file is empty. Please try another.'),
		'invalid_filetype' => __('Sorry, this file type is not permitted for security reasons.'),
		'not_an_image' => __('This file is not an image. Please try another.'),
		'image_memory_exceeded' => __('Memory exceeded. Please try another smaller file.'),
		'image_dimensions_exceeded' => __('This is larger than the maximum size. Please try another.'),
		'default_error' => __('An error occurred in the upload. Please try again later.'),
		'missing_upload_url' => __('There was a configuration error. Please contact the server administrator.'),
		'upload_limit_exceeded' => __('You may only upload 1 file.'),
		'http_error' => __('HTTP error.'),
		'upload_failed' => __('Upload failed.'),
		/* translators: 1: Opening link tag, 2: Closing link tag */
		'big_upload_failed' => __('Please try uploading this file with the %1$sbrowser uploader%2$s.'),
		'big_upload_queued' => __('%s exceeds the maximum upload size for the multi-file uploader when used in your browser.'),
		'io_error' => __('IO error.'),
		'security_error' => __('Security error.'),
		'file_cancelled' => __('File canceled.'),
		'upload_stopped' => __('Upload stopped.'),
		'dismiss' => __('Dismiss'),
		'crunching' => __('Crunching&hellip;'),
		'deleted' => __('moved to the trash.'),
		'error_uploading' => __('&#8220;%s&#8221; has failed to upload.')
	);

	$scripts->add( 'moxiejs', "/wp-includes/js/plupload/moxie$suffix.js", array(), '1.3.5' );
	$scripts->add( 'plupload', "/wp-includes/js/plupload/plupload$suffix.js", array( 'moxiejs' ), '2.1.9' );
	// Back compat handles:
	foreach ( array( 'all', 'html5', 'flash', 'silverlight', 'html4' ) as $handle ) {
		$scripts->add( "plupload-$handle", false, array( 'plupload' ), '2.1.1' );
	}

	$scripts->add( 'plupload-handlers', "/wp-includes/js/plupload/handlers$suffix.js", array( 'plupload', 'jquery' ) );
	did_action( 'init' ) && $scripts->localize( 'plupload-handlers', 'pluploadL10n', $uploader_l10n );

	$scripts->add( 'wp-plupload', "/wp-includes/js/plupload/wp-plupload$suffix.js", array( 'plupload', 'jquery', 'json2', 'media-models' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'wp-plupload', 'pluploadL10n', $uploader_l10n );

	// keep 'swfupload' for back-compat.
	$scripts->add( 'swfupload', '/wp-includes/js/swfupload/swfupload.js', array(), '2201-20110113');
	$scripts->add( 'swfupload-all', false, array( 'swfupload' ), '2201' );
	$scripts->add( 'swfupload-handlers', "/wp-includes/js/swfupload/handlers$suffix.js", array('swfupload-all', 'jquery'), '2201-20110524');
	did_action( 'init' ) && $scripts->localize( 'swfupload-handlers', 'swfuploadL10n', $uploader_l10n );

	$scripts->add( 'comment-reply', "/wp-includes/js/comment-reply$suffix.js", array(), false, 1 );

	$scripts->add( 'json2', "/wp-includes/js/json2$suffix.js", array(), '2015-05-03' );
	did_action( 'init' ) && $scripts->add_data( 'json2', 'conditional', 'lt IE 8' );

	$scripts->add( 'underscore', "/wp-includes/js/underscore$dev_suffix.js", array(), '1.8.3', 1 );
	$scripts->add( 'backbone', "/wp-includes/js/backbone$dev_suffix.js", array( 'underscore','jquery' ), '1.2.3', 1 );

	$scripts->add( 'wp-util', "/wp-includes/js/wp-util$suffix.js", array('underscore', 'jquery'), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'wp-util', '_wpUtilSettings', array(
		'ajax' => array(
			'url' => admin_url( 'admin-ajax.php', 'relative' ),
		),
	) );

	$scripts->add( 'wp-sanitize', "/wp-includes/js/wp-sanitize$suffix.js", array('jquery'), false, 1 );

	$scripts->add( 'wp-backbone', "/wp-includes/js/wp-backbone$suffix.js", array('backbone', 'wp-util'), false, 1 );

	$scripts->add( 'revisions', "/wp-admin/js/revisions$suffix.js", array( 'wp-backbone', 'jquery-ui-slider', 'hoverIntent' ), false, 1 );

	$scripts->add( 'imgareaselect', "/wp-includes/js/imgareaselect/jquery.imgareaselect$suffix.js", array('jquery'), false, 1 );

	$scripts->add( 'mediaelement', false, array( 'jquery', 'mediaelement-core', 'mediaelement-migrate' ), '4.2.6-78496d1' );
	$scripts->add( 'mediaelement-core', "/wp-includes/js/mediaelement/mediaelement-and-player$suffix.js", array(), '4.2.6-78496d1', 1 );
	$scripts->add( 'mediaelement-migrate', "/wp-includes/js/mediaelement/mediaelement-migrate$suffix.js", array(), false, 1);

	did_action( 'init' ) && $scripts->add_inline_script( 'mediaelement-core', sprintf( 'var mejsL10n = %s;', wp_json_encode( array(
		'language' => strtolower( strtok( is_admin() ? get_user_locale() : get_locale(), '_-' ) ),
		'strings'  => array(
			'mejs.install-flash'       => __( 'You are using a browser that does not have Flash player enabled or installed. Please turn on your Flash player plugin or download the latest version from https://get.adobe.com/flashplayer/' ),
			'mejs.fullscreen-off'      => __( 'Turn off Fullscreen' ),
			'mejs.fullscreen-on'       => __( 'Go Fullscreen' ),
			'mejs.download-video'      => __( 'Download Video' ),
			'mejs.fullscreen'          => __( 'Fullscreen' ),
			'mejs.time-jump-forward'   => array( __( 'Jump forward 1 second' ), __( 'Jump forward %1 seconds' ) ),
			'mejs.loop'                => __( 'Toggle Loop' ),
			'mejs.play'                => __( 'Play' ),
			'mejs.pause'               => __( 'Pause' ),
			'mejs.close'               => __( 'Close' ),
			'mejs.time-slider'         => __( 'Time Slider' ),
			'mejs.time-help-text'      => __( 'Use Left/Right Arrow keys to advance one second, Up/Down arrows to advance ten seconds.' ),
			'mejs.time-skip-back'      => array( __( 'Skip back 1 second' ), __( 'Skip back %1 seconds' ) ),
			'mejs.captions-subtitles'  => __( 'Captions/Subtitles' ),
			'mejs.captions-chapters'   => __( 'Chapters' ),
			'mejs.none'                => __( 'None' ),
			'mejs.mute-toggle'         => __( 'Mute Toggle' ),
			'mejs.volume-help-text'    => __( 'Use Up/Down Arrow keys to increase or decrease volume.' ),
			'mejs.unmute'              => __( 'Unmute' ),
			'mejs.mute'                => __( 'Mute' ),
			'mejs.volume-slider'       => __( 'Volume Slider' ),
			'mejs.video-player'        => __( 'Video Player' ),
			'mejs.audio-player'        => __( 'Audio Player' ),
			'mejs.ad-skip'             => __( 'Skip ad' ),
			'mejs.ad-skip-info'        => array( __( 'Skip in 1 second' ), __( 'Skip in %1 seconds' ) ),
			'mejs.source-chooser'      => __( 'Source Chooser' ),
			'mejs.stop'                => __( 'Stop' ),
			'mejs.speed-rate'          => __( 'Speed Rate' ),
			'mejs.live-broadcast'      => __( 'Live Broadcast' ),
			'mejs.afrikaans'           => __( 'Afrikaans' ),
			'mejs.albanian'            => __( 'Albanian' ),
			'mejs.arabic'              => __( 'Arabic' ),
			'mejs.belarusian'          => __( 'Belarusian' ),
			'mejs.bulgarian'           => __( 'Bulgarian' ),
			'mejs.catalan'             => __( 'Catalan' ),
			'mejs.chinese'             => __( 'Chinese' ),
			'mejs.chinese-simplified'  => __( 'Chinese (Simplified)' ),
			'mejs.chinese-traditional' => __( 'Chinese (Traditional)' ),
			'mejs.croatian'            => __( 'Croatian' ),
			'mejs.czech'               => __( 'Czech' ),
			'mejs.danish'              => __( 'Danish' ),
			'mejs.dutch'               => __( 'Dutch' ),
			'mejs.english'             => __( 'English' ),
			'mejs.estonian'            => __( 'Estonian' ),
			'mejs.filipino'            => __( 'Filipino' ),
			'mejs.finnish'             => __( 'Finnish' ),
			'mejs.french'              => __( 'French' ),
			'mejs.galician'            => __( 'Galician' ),
			'mejs.german'              => __( 'German' ),
			'mejs.greek'               => __( 'Greek' ),
			'mejs.haitian-creole'      => __( 'Haitian Creole' ),
			'mejs.hebrew'              => __( 'Hebrew' ),
			'mejs.hindi'               => __( 'Hindi' ),
			'mejs.hungarian'           => __( 'Hungarian' ),
			'mejs.icelandic'           => __( 'Icelandic' ),
			'mejs.indonesian'          => __( 'Indonesian' ),
			'mejs.irish'               => __( 'Irish' ),
			'mejs.italian'             => __( 'Italian' ),
			'mejs.japanese'            => __( 'Japanese' ),
			'mejs.korean'              => __( 'Korean' ),
			'mejs.latvian'             => __( 'Latvian' ),
			'mejs.lithuanian'          => __( 'Lithuanian' ),
			'mejs.macedonian'          => __( 'Macedonian' ),
			'mejs.malay'               => __( 'Malay' ),
			'mejs.maltese'             => __( 'Maltese' ),
			'mejs.norwegian'           => __( 'Norwegian' ),
			'mejs.persian'             => __( 'Persian' ),
			'mejs.polish'              => __( 'Polish' ),
			'mejs.portuguese'          => __( 'Portuguese' ),
			'mejs.romanian'            => __( 'Romanian' ),
			'mejs.russian'             => __( 'Russian' ),
			'mejs.serbian'             => __( 'Serbian' ),
			'mejs.slovak'              => __( 'Slovak' ),
			'mejs.slovenian'           => __( 'Slovenian' ),
			'mejs.spanish'             => __( 'Spanish' ),
			'mejs.swahili'             => __( 'Swahili' ),
			'mejs.swedish'             => __( 'Swedish' ),
			'mejs.tagalog'             => __( 'Tagalog' ),
			'mejs.thai'                => __( 'Thai' ),
			'mejs.turkish'             => __( 'Turkish' ),
			'mejs.ukrainian'           => __( 'Ukrainian' ),
			'mejs.vietnamese'          => __( 'Vietnamese' ),
			'mejs.welsh'               => __( 'Welsh' ),
			'mejs.yiddish'             => __( 'Yiddish' ),
			),
		) ) ), 'before' );


	$scripts->add( 'mediaelement-vimeo', "/wp-includes/js/mediaelement/renderers/vimeo.min.js", array('mediaelement'), '4.2.6-78496d1', 1 );
	$scripts->add( 'wp-mediaelement', "/wp-includes/js/mediaelement/wp-mediaelement$suffix.js", array('mediaelement'), false, 1 );
	$mejs_settings = array(
		'pluginPath'    => includes_url( 'js/mediaelement/', 'relative' ),
		'classPrefix'   => 'mejs-',
		'stretching'    => 'responsive',
	);
	did_action( 'init' ) && $scripts->localize( 'mediaelement', '_wpmejsSettings',
		/**
		 * Filters the MediaElement configuration settings.
		 *
		 * @since 4.4.0
		 *
		 * @param array $mejs_settings MediaElement settings array.
		 */
		apply_filters( 'mejs_settings', $mejs_settings )
	);

	$scripts->add( 'wp-codemirror', '/wp-includes/js/codemirror/codemirror.min.js', array(), '5.29.1-alpha-ee20357' );
	$scripts->add( 'csslint', '/wp-includes/js/codemirror/csslint.js', array(), '1.0.5' );
	$scripts->add( 'jshint', '/wp-includes/js/codemirror/jshint.js', array(), '2.9.5.999' );
	$scripts->add( 'jsonlint', '/wp-includes/js/codemirror/jsonlint.js', array(), '1.6.2' );
	$scripts->add( 'htmlhint', '/wp-includes/js/codemirror/htmlhint.js', array(), '0.9.14-xwp' );
	$scripts->add( 'htmlhint-kses', '/wp-includes/js/codemirror/htmlhint-kses.js', array( 'htmlhint' ) );
	$scripts->add( 'code-editor', "/wp-admin/js/code-editor$suffix.js", array( 'jquery', 'wp-codemirror', 'underscore' ) );
	$scripts->add( 'wp-theme-plugin-editor', "/wp-admin/js/theme-plugin-editor$suffix.js", array( 'wp-util', 'wp-sanitize', 'jquery', 'jquery-ui-core', 'wp-a11y', 'underscore' ) );
	did_action( 'init' ) && $scripts->add_inline_script( 'wp-theme-plugin-editor', sprintf( 'wp.themePluginEditor.l10n = %s;', wp_json_encode( array(
		'saveAlert' => __( 'The changes you made will be lost if you navigate away from this page.' ),
		'saveError' => __( 'Something went wrong. Your change may not have been saved. Please try again. There is also a chance that you may need to manually fix and upload the file over FTP.' ),
		'lintError' => array(
			/* translators: %d: error count */
			'singular' => _n( 'There is %d error which must be fixed before you can update this file.', 'There are %d errors which must be fixed before you can update this file.', 1 ),
			/* translators: %d: error count */
			'plural' => _n( 'There is %d error which must be fixed before you can update this file.', 'There are %d errors which must be fixed before you can update this file.', 2 ), // @todo This is lacking, as some languages have a dedicated dual form. For proper handling of plurals in JS, see #20491.
		),
	) ) ) );

	$scripts->add( 'wp-playlist', "/wp-includes/js/mediaelement/wp-playlist$suffix.js", array( 'wp-util', 'backbone', 'mediaelement' ), false, 1 );

	$scripts->add( 'zxcvbn-async', "/wp-includes/js/zxcvbn-async$suffix.js", array(), '1.0' );
	did_action( 'init' ) && $scripts->localize( 'zxcvbn-async', '_zxcvbnSettings', array(
		'src' => empty( $guessed_url ) ? includes_url( '/js/zxcvbn.min.js' ) : $scripts->base_url . '/wp-includes/js/zxcvbn.min.js',
	) );

	$scripts->add( 'password-strength-meter', "/wp-admin/js/password-strength-meter$suffix.js", array( 'jquery', 'zxcvbn-async' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'password-strength-meter', 'pwsL10n', array(
		'unknown'  => _x( 'Password strength unknown', 'password strength' ),
		'short'    => _x( 'Very weak', 'password strength' ),
		'bad'      => _x( 'Weak', 'password strength' ),
		'good'     => _x( 'Medium', 'password strength' ),
		'strong'   => _x( 'Strong', 'password strength' ),
		'mismatch' => _x( 'Mismatch', 'password mismatch' ),
	) );

	$scripts->add( 'user-profile', "/wp-admin/js/user-profile$suffix.js", array( 'jquery', 'password-strength-meter', 'wp-util' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'user-profile', 'userProfileL10n', array(
		'warn'     => __( 'Your new password has not been saved.' ),
		'warnWeak' => __( 'Confirm use of weak password' ),
		'show'     => __( 'Show' ),
		'hide'     => __( 'Hide' ),
		'cancel'   => __( 'Cancel' ),
		'ariaShow' => esc_attr__( 'Show password' ),
		'ariaHide' => esc_attr__( 'Hide password' ),
	) );

	$scripts->add( 'language-chooser', "/wp-admin/js/language-chooser$suffix.js", array( 'jquery' ), false, 1 );

	$scripts->add( 'user-suggest', "/wp-admin/js/user-suggest$suffix.js", array( 'jquery-ui-autocomplete' ), false, 1 );

	$scripts->add( 'admin-bar', "/wp-includes/js/admin-bar$suffix.js", array(), false, 1 );

	$scripts->add( 'wplink', "/wp-includes/js/wplink$suffix.js", array( 'jquery', 'wp-a11y' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'wplink', 'wpLinkL10n', array(
		'title' => __('Insert/edit link'),
		'update' => __('Update'),
		'save' => __('Add Link'),
		'noTitle' => __('(no title)'),
		'noMatchesFound' => __('No results found.'),
		'linkSelected' => __( 'Link selected.' ),
		'linkInserted' => __( 'Link inserted.' ),
	) );

	$scripts->add( 'wpdialogs', "/wp-includes/js/wpdialog$suffix.js", array( 'jquery-ui-dialog' ), false, 1 );

	$scripts->add( 'word-count', "/wp-admin/js/word-count$suffix.js", array(), false, 1 );

	$scripts->add( 'media-upload', "/wp-admin/js/media-upload$suffix.js", array( 'thickbox', 'shortcode' ), false, 1 );

	$scripts->add( 'hoverIntent', "/wp-includes/js/hoverIntent$suffix.js", array('jquery'), '1.8.1', 1 );

	$scripts->add( 'customize-base',     "/wp-includes/js/customize-base$suffix.js",     array( 'jquery', 'json2', 'underscore' ), false, 1 );
	$scripts->add( 'customize-loader',   "/wp-includes/js/customize-loader$suffix.js",   array( 'customize-base' ), false, 1 );
	$scripts->add( 'customize-preview',  "/wp-includes/js/customize-preview$suffix.js",  array( 'wp-a11y', 'customize-base' ), false, 1 );
	$scripts->add( 'customize-models',   "/wp-includes/js/customize-models.js", array( 'underscore', 'backbone' ), false, 1 );
	$scripts->add( 'customize-views',    "/wp-includes/js/customize-views.js",  array( 'jquery', 'underscore', 'imgareaselect', 'customize-models', 'media-editor', 'media-views' ), false, 1 );
	$scripts->add( 'customize-controls', "/wp-admin/js/customize-controls$suffix.js", array( 'customize-base', 'wp-a11y', 'wp-util', 'jquery-ui-core' ), false, 1 );
	did_action( 'init' ) && $scripts->localize( 'customize-controls', '_wpCustomizeControlsL10n', array(
		'activate'           => __( 'Activate &amp; Publish' ),
		'save'               => __( 'Save &amp; Publish' ), // @todo Remove as not required.
		'publish'            => __( 'Publish' ),
		'published'          => __( 'Published' ),
		'saveDraft'          => __( 'Save Draft' ),
		'draftSaved'         => __( 'Draft Saved' ),
		'updating'           => __( 'Updating' ),
		'schedule'           => _x( 'Schedule', 'customizer changeset action/button label' ),
		'scheduled'          => _x( 'Scheduled', 'customizer changeset status' ),
		'invalid'            => __( 'Invalid' ),
		'saveBeforeShare'    => __( 'Please save your changes in order to share the preview.' ),
		'futureDateError'    => __( 'You must supply a future date to schedule.' ),
		'saveAlert'          => __( 'The changes you made will be lost if you navigate away from this page.' ),
		'saved'              => __( 'Saved' ),
		'cancel'             => __( 'Cancel' ),
		'close'              => __( 'Close' ),
		'action'             => __( 'Action' ),
		'discardChanges'     => __( 'Discard changes' ),
		'cheatin'            => __( 'Cheatin&#8217; uh?' ),
		'notAllowed'         => __( 'Sorry, you are not allowed to customize this site.' ),
		'previewIframeTitle' => __( 'Site Preview' ),
		'loginIframeTitle'   => __( 'Session expired' ),
		'collapseSidebar'    => _x( 'Hide Controls', 'label for hide controls button without length constraints' ),
		'expandSidebar'      => _x( 'Show Controls', 'label for hide controls button without length constraints' ),
		'untitledBlogName'   => __( '(Untitled)' ),
		'unknownRequestFail' => __( 'Looks like something&#8217;s gone wrong. Wait a couple seconds, and then try again.' ),
		'themeDownloading'   => __( 'Downloading your new theme&hellip;' ),
		'themePreviewWait'   => __( 'Setting up your live preview. This may take a bit.' ),
		'revertingChanges'   => __( 'Reverting unpublished changes&hellip;' ),
		'trashConfirm'       => __( 'Are you sure you&#8217;d like to discard your unpublished changes?' ),
		/* translators: %s: Display name of the user who has taken over the changeset in customizer. */
		'takenOverMessage'   => __( '%s has taken over and is currently customizing.' ),
		/* translators: %s: URL to the Customizer to load the autosaved version */
		'autosaveNotice'     => __( 'There is a more recent autosave of your changes than the one you are previewing. <a href="%s">Restore the autosave</a>' ),
		'videoHeaderNotice'  => __( 'This theme doesn&#8217;t support video headers on this page. Navigate to the front page or another page that supports video headers.' ),
		// Used for overriding the file types allowed in plupload.
		'allowedFiles'       => __( '