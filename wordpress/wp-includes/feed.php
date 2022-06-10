<?php
/**
 * WordPress Feed API
 *
 * Many of the functions used in here belong in The Loop, or The Loop for the
 * Feeds.
 *
 * @package WordPress
 * @subpackage Feed
 * @since 2.1.0
 */

/**
 * RSS container for the bloginfo function.
 *
 * You can retrieve anything that you can using the get_bloginfo() function.
 * Everything will be stripped of tags and characters converted, when the values
 * are retrieved for use in the feeds.
 *
 * @since 1.5.1
 * @see get_bloginfo() For the list of possible values to display.
 *
 * @param string $show See get_bloginfo() for possible values.
 * @return string
 */
function get_bloginfo_rss($show = '') {
	$info = strip_tags(get_bloginfo($show));
	/**
	 * Filters the bloginfo for use in RSS feeds.
	 *
	 * @since 2.2.0
	 *
	 * @see convert_chars()
	 * @see get_bloginfo()
	 *
	 * @param string $info Converted string value of the blog information.
	 * @param string $show The type of blog information to retrieve.
	 */
	return apply_filters( 'get_bloginfo_rss', convert_chars( $info ), $show );
}

/**
 * Display RSS container for the bloginfo function.
 *
 * You can retrieve anything that you can using the get_bloginfo() function.
 * Everything will be stripped of tags and characters converted, when the values
 * are retrieved for use in the feeds.
 *
 * @since 0.71
 * @see get_bloginfo() For the list of possible values to display.
 *
 * @param string $show See get_bloginfo() for possible values.
 */
function bloginfo_rss($show = '') {
	/**
	 * Filters the bloginfo for display in RSS feeds.
	 *
	 * @since 2.1.0
	 *
	 * @see get_bloginfo()
	 *
	 * @param string $rss_container RSS container for the blog information.
	 * @param string $show          The type of blog information to retrieve.
	 */
	echo apply_filters( 'bloginfo_rss', get_bloginfo_rss( $show ), $show );
}

/**
 * Retrieve the default feed.
 *
 * The default feed is 'rss2', unless a plugin changes it through the
 * {@see 'default_feed'} filter.
 *
 * @since 2.5.0
 *
 * @return string Default feed, or for example 'rss2', 'atom', etc.
 */
function get_default_feed() {
	/**
	 * Filters the default feed type.
	 *
	 * @since 2.5.0
	 *
	 * @param string $feed_type Type of default feed. Possible values include 'rss2', 'atom'.
	 *                          Default 'rss2'.
	 */
	$default_feed = apply_filters( 'default_feed', 'rss2' );
	return 'rss' == $default_feed ? 'rss2' : $default_feed;
}

/**
 * Retrieve the blog title for the feed title.
 *
 * @since 2.2.0
 * @since 4.4.0 The optional `$sep` parameter was deprecated and renamed to `$deprecated`.
 *
 * @param string $deprecated Unused..
 * @return string The document title.
 */
function get_wp_title_rss( $deprecated = '&#8211;' ) {
	if ( '&#8211;' !== $deprecated ) {
		/* translators: %s: 'document_title_separator' filter name */
		_deprecated_argument( __FUNCTION__, '4.4.0', sprintf( __( 'Use the %s filter instead.' ), '<code>document_title_separator</code>' ) );
	}

	/**
	 * Filters the blog title for use as the feed title.
	 *
	 * @since 2.2.0
	 * @since 4.4.0 The `$sep` parameter was deprecated and renamed to `$deprecated`.
	 *
	 * @param string $title      The current blog title.
	 * @param string $deprecated Unused.
	 */
	return apply_filters( 'get_wp_title_rss', wp_get_document_title(), $deprecated );
}

/**
 * Display the blog title for display of the feed title.
 *
 * @since 2.2.0
 * @since 4.4.0 The optional `$sep` parameter was deprecated and renamed to `$deprecated`.
 *
 * @param string $deprecated Unused.
 */
function wp_title_rss( $deprecated = '&#8211;' ) {
	if ( '&#8211;' !== $deprecated ) {
		/* translators: %s: 'document_title_separator' filter name */
		_deprecated_argument( __FUNCTION__, '4.4.0', sprintf( __( 'Use the %s filter instead.' ), '<code>document_title_separator</code>' ) );
	}

	/**
	 * Filters the blog title for display of the feed title.
	 *
	 * @since 2.2.0
	 * @since 4.4.0 The `$sep` parameter was deprecated and renamed to `$deprecated`.
	 *
	 * @see get_wp_title_rss()
	 *
	 * @param string $wp_title_rss The current blog title.
	 * @param string $deprecated   Unused.
	 */
	echo apply_filters( 'wp_title_rss', get_wp_title_rss(), $deprecated );
}

/**
 * Retrieve the current post title for the feed.
 *
 * @since 2.0.0
 *
 * @return string Current post title.
 */
function get_the_title_rss() {
	$title = get_the_title();

	/**
	 * Filters the post title for use in a feed.
	 *
	 * @since 1.2.0
	 *
	 * @param string $title The current post title.
	 */
	$title = apply_filters( 'the_title_rss', $title );
	return $title;
}

/**
 * Display the post title in the feed.
 *
 * @since 0.71
 */
function the_title_rss() {
	echo get_the_title_rss();
}

/**
 * Retrieve the post content for feeds.
 *
 * @since 2.9.0
 * @see get_the_content()
 *
 * @param string $feed_type The type of feed. rss2 | atom | rss | rdf
 * @return string The filtered content.
 */
function get_the_content_feed($feed_type = null) {
	if ( !$feed_type )
		$feed_type = get_default_feed();

	/** This filter is documented in wp-includes/post-template.php */
	$content = apply_filters( 'the_content', get_the_content() );
	$content = str_replace(']]>', ']]&gt;', $content);
	/**
	 * Filters the post content for use in feeds.
	 *
	 * @since 2.9.0
	 *
	 * @para