<?php
/**
 * WordPress Rewrite API
 *
 * @package WordPress
 * @subpackage Rewrite
 */

/**
 * Endpoint Mask for default, which is nothing.
 *
 * @since 2.1.0
 */
define('EP_NONE', 0);

/**
 * Endpoint Mask for Permalink.
 *
 * @since 2.1.0
 */
define('EP_PERMALINK', 1);

/**
 * Endpoint Mask for Attachment.
 *
 * @since 2.1.0
 */
define('EP_ATTACHMENT', 2);

/**
 * Endpoint Mask for date.
 *
 * @since 2.1.0
 */
define('EP_DATE', 4);

/**
 * Endpoint Mask for year
 *
 * @since 2.1.0
 */
define('EP_YEAR', 8);

/**
 * Endpoint Mask for month.
 *
 * @since 2.1.0
 */
define('EP_MONTH', 16);

/**
 * Endpoint Mask for day.
 *
 * @since 2.1.0
 */
define('EP_DAY', 32);

/**
 * Endpoint Mask for root.
 *
 * @since 2.1.0
 */
define('EP_ROOT', 64);

/**
 * Endpoint Mask for comments.
 *
 * @since 2.1.0
 */
define('EP_COMMENTS', 128);

/**
 * Endpoint Mask for searches.
 *
 * @since 2.1.0
 */
define('EP_SEARCH', 256);

/**
 * Endpoint Mask for categories.
 *
 * @since 2.1.0
 */
define('EP_CATEGORIES', 512);

/**
 * Endpoint Mask for tags.
 *
 * @since 2.3.0
 */
define('EP_TAGS', 1024);

/**
 * Endpoint Mask for authors.
 *
 * @since 2.1.0
 */
define('EP_AUTHORS', 2048);

/**
 * Endpoint Mask for pages.
 *
 * @since 2.1.0
 */
define('EP_PAGES', 4096);

/**
 * Endpoint Mask for all archive views.
 *
 * @since 3.7.0
 */
define( 'EP_ALL_ARCHIVES', EP_DATE | EP_YEAR | EP_MONTH | EP_DAY | EP_CATEGORIES | EP_TAGS | EP_AUTHORS );

/**
 * Endpoint Mask for everything.
 *
 * @since 2.1.0
 */
define( 'EP_ALL', EP_PERMALINK | EP_ATTACHMENT | EP_ROOT | EP_COMMENTS | EP_SEARCH | EP_PAGES | EP_ALL_ARCHIVES );

/**
 * Adds a rewrite rule that transforms a URL structure to a set of query vars.
 *
 * Any value in the $after parameter that isn't 'bottom' will result in the rule
 * being placed at the top of the rewrite rules.
 *
 * @since 2.1.0
 * @since 4.4.0 Array support was added to the `$query` parameter.
 *
 * @global WP_Rewrite $wp_rewrite WordPress Rewrite Component.
 *
 * @param string       $regex Regular expression to match request against.
 * @param string|array $query The corresponding query vars for this rewrite rule.
 * @param string       $after Optional. Priority of the new rule. Accepts 'top'
 *                            or 'bottom'. Default 'bottom'.
 */
function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {
	global $wp_rewrite;

	$wp_rewrite->add_rule( $regex, $query, $after );
}

/**
 * Add a new rewrite tag (like %postname%).
 *
 * The $query parameter is optional. If it is omitted you must ensure that
 * you call this on, or before, the {@see 'init'} hook. This is because $query defaults
 * to "$tag=", and for this to work a new query var has to be added.
 *
 * @since 2.1.0
 *
 * @global WP_Rewrite $wp_rewrite
 * @global WP         $wp
 *
 * @param string $tag   Name of the new rewrite tag.
 * @param string $regex Regular expression to substitute the tag for in rewrite rules.
 * @param string $query Optional. String to append to the rewritten query. Must end in '='. Default empty.
 */
function add_rewrite_tag( $tag, $regex, $query = '' ) {
	// validate the tag's name
	if ( strlen( $tag ) < 3 || $tag[0] != '%' || $tag[ strlen($tag) - 1 ] != '%' )
		return;

	global $wp_rewrite, $wp;

	if ( empty( $query ) ) {
		$qv = trim( $tag, '%' );
		$wp->add_query_var( $qv );
		$query = $qv . '=';
	}

	$wp_rewrite->add_rewrite_tag( $tag, $regex, $query );
}

/**
 * Removes an existing rewrite tag (like %postname%).
 *
 * @since 4.5.0
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @param string $tag Name of the rewrite tag.
 */
function remove_rewrite_tag( $tag ) {
	global $wp_rewrite;
	$wp_rewrite->remove_rewrite_tag( $tag );
}

/**
 * Add permalink structure.
 *
 * @since 3.0.0
 *
 * @see WP_Rewrite::add_permastruct()
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @param string $name   Name for permalink structure.
 * @param string $struct Permalink structure.
 * @param array  $args   Optional. Arguments for building the rules from the permalink structure,
 *                       see WP_Rewrite::add_permastruct() for full details. Default empty array.
 */
function add_permastruct( $name, $struct, $args = array() ) {
	global $wp_rewrite;

	// Back-compat for the old parameters: $with_front and $ep_mask.
	if ( ! is_array( $args ) )
		$args = array( 'with_front' => $args );
	if ( func_num_args() == 4 )
		$args['ep_mask'] = func_get_arg( 3 );

	$wp_rewrite->add_permastruct( $name, $struct, $args );
}

/**
 * Removes a permalink structure.
 *
 * Can only be used to remove permastructs that were added using add_permastruct().
 * Built-in permastructs cannot be removed.
 *
 * @since 4.5.0
 *
 * @see WP_Rewrite::remove_permastruct()
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 *
 * @param string $name Name for permalink structure.
 */
function remove_permastruct( $name ) {
	global $wp_rewrite;

	$wp_rewrite->remove_permastruct( $name );
}

/**
 * Add a new feed type like /atom1/.
 *
 * @since 2.1.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string   $feedname Feed name.
 * @param callable $function Callback to run on feed display.
 * @return string Feed action name.
 */
function add_feed( $feedname, $function ) {
	global $wp_rewrite;

	if ( ! in_array( $feedname, $wp_rewrite->feeds ) ) {
		$wp_rewrite->feeds[] = $feedname;
	}

	$hook = 'do_feed_' . $feedname;

	// Remove default function hook
	remove_action( $hook, $hook );

	add_action( $hook, $function, 10, 2 );

	return $hook;
}

/**
 * Remove rewrite rules and then recreate rewrite rules.
 *
 * @since 3.0.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param bool $hard Whether to update .htaccess (hard flush) or just update
 * 	                 rewrite_rules transient (soft flush). Default is true (hard).
 */
function flush_rewrite_rules( $hard = true ) {
	global $wp_rewrite;
	$wp_rewrite->flush_rules( $hard );
}

/**
 * Add an endpoint, like /trackback/.
 *
 * Adding an endpoint creates extra rewrite rules for each of the matching
 * places specified by the provided bitmask. For example:
 *
 *     add_rewrite_endpoint( 'json', EP_PERMALINK | EP_PAGES );
 *
 * will add a new rewrite rule ending with "json(/(.*))?/?$" for every permastruct
 * that describes a permalink (post) or page. This is rewritten to "json=$match"
 * where $match is the part of the URL matched by the endpoint regex (e.g. "foo" in
 * "[permalink]/json/foo/").
 *
 * A new query var with the same name as the endpoint will also be created.
 *
 * When specifying $places ensure that you are using the EP_* constants (or a
 * combination of them using the bitwise OR operator) as their values are not
 * guaranteed to remain static (especially `EP_ALL`).
 *
 * Be sure to flush the rewrite rules - see flush_rewrite_rules() - when your plugin gets
 * activated and deactivated.
 *
 * @since 2.1.0
 * @since 4.3.0 Added support for skipping query var registration by passing `false` to `$query_var`.
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string      $name      Name of the endpoint.
 * @param int         $places    Endpoint mask describing the places the endpoint should be added.
 * @param string|bool $query_var Name of the corresponding query variable. Pass `false` to skip registering a query_var
 *                               for this endpoint. Defaults to the value of `$name`.
 */
function add_rewrite_endpoint( $name, $places, $query_var = true ) {
	global $wp_rewrite;
	$wp_rewrite->add_endpoint( $name, $places, $query_var );
}

/**
 * Filters the URL base for taxonomies.
 *
 * To remove any manually prepended /index.php/.
 *
 * @access private
 * @since 2.6.0
 *
 * @param string $base The taxonomy base that we're going to filter
 * @return string
 */
function _wp_filter_taxonomy_base( $base ) {
	if ( !empty( $base ) ) {
		$base = preg_replace( '|^/index\.php/|', '', $base );
		$base = trim( $base, '/' );
	}
	return $base;
}


/**
 * Resolve numeric slugs that collide with date permalinks.
 *
 * Permalinks of posts with numeric slugs can sometimes look to WP_Query::parse_query()
 * like a date archive, as when your permalink structure is `/%year%/%postname%/` and
 * a post with post_name '05' has the URL `/2015/05/`.
 *
 * This function detects conflicts of this type and resolves them in favor of the
 * post permalink.
 *
 * Note that, since 4.3.0, wp_unique_post_slug() prevents the creation of post slugs
 * that would result in a date archive conflict. The resolution performed in this
 * function is primarily for legacy content, as well as cases when the admin has changed
 * the site's permalink structure in a way that introduces URL conflicts.
 *
 * @since 4.3.0
 *
 * @param array $query_vars Optional. Query variables for setting up the loop, as determined in
 *                          WP::parse_request(). Default empty array.
 * @return array Returns the original array of query vars, with date/post conflicts resolved.
 */
function wp_resolve_numeric_slug_conflicts( $query_vars = array() ) {
	if ( ! isset( $query_vars['year'] ) && ! isset( $query_vars['monthnum'] ) && ! isset( $query_vars['day'] ) ) {
		return $query_vars;
	}

	// Identify the 'postname' position in the permastruct array.
	$permastructs   = array_values( array_filter( explode( '/', get_option( 'permalink_structure' ) ) ) );
	$postname_index = array_search( '%postname%', $permastructs );

	if ( false === $postname_index ) {
		return $query_vars;
	}

	/*
	 * A numeric slug could be confused with a year, month, or day, depending on position. To account for
	 * the possibility of post pagination (eg 2015/2 for the second page of a post called '2015'), our
	 * `is_*` checks are generous: check for year-slug clashes when `is_year` *or* `is_month`, and check
	 * for month-slug clashes when `is_month` *or* `is_day`.
	 */
	$compare = '';
	if ( 0 === $postname_index && ( isset( $query_vars['year'] ) || isset( $query_vars['monthnum'] ) ) ) {
		$compare = 'year';
	} elseif ( '%year%' === $permastructs[ $postname_index - 1 ] && ( isset( $query_vars['monthnum'] ) || isset( $query_vars['day'] ) ) ) {
		$compare = 'monthnum';
	} elseif ( '%monthnum%' === $permastructs[ $postname_index - 1 ] && isset( $query_vars['day'] ) ) {
		$compare = 'day';
	}

	if ( ! $compare ) {
		return $query_vars;
	}

	// This is the potentially clashing slug.
	$value = $query_vars[ $compare ];

	$post = get_page_by_path( $value, OBJECT, 'post' );
	if ( ! ( $post instanceof WP_Post ) ) {
		return $query_vars;
	}

	// If the date of the post doesn't match the date specified in the URL, resolve to the date archive.
	if ( preg_match( '/^([0-9]{4})\-([0-9]{2})/', $post->post_date, $matches ) && isset( $query_vars['year'] ) && ( 'monthnum' === $compare || 'day' === $compare ) ) {
		// $matches[1] is the year the post was published.
		if ( intval( $query_vars['year'] ) !== intval( $matches[1] ) ) {
			return $query_vars;
		}

		// $matches[2] is the month the post was published.
		if ( 'day' === $compare && isset( $query_vars['monthnum'] ) && intval( $query_vars['monthnum'] ) !== intval( $matches[2] ) ) {
			return $query_vars;
		}
	}

	/*
	 * If the located post contains nextpage pagination, then the URL chunk following postname may be
	 * intended as the page number. Verify that it's a valid page before resolving to it.
	 */
	$maybe_page = '';
	if ( 'year' === $compare && isset( $query_vars['monthnum'] ) ) {
		$maybe_page = $query_vars['monthnum'];
	} elseif ( 'monthnum' === $compare && isset( $query_vars['day'] ) ) {
		$maybe_page = $query_vars['day'];
	}
	// Bug found in #11694 - 'page' was returning '/4'
	$maybe_page = (int) trim( $maybe_page, '/' );

	$post_page_count = substr_count( $post->post_content, '<!--nextpage-->' ) + 1;

	// If the post doesn't have multiple pages, but a 'page' candidate is found, resolve to the date archive.
	if ( 1 === $post_page_count && $maybe_page ) {
		return $query_vars;
	}

	// If the post has multiple pages and the 'page' number isn't valid, resolve to the date archive.
	if ( $post_page_count > 1 && $maybe_page > $post_page_count ) {
		return $query_vars;
	}

	// If we've gotten to this point, we have a slug/date clash. First, adjust for nextpage.
	if ( '' !== $maybe_page ) {
		$query_vars['page'] = intval( $maybe_page );
	}

	// Next, unset autodetected date-related query vars.
	unset( $query_vars['year'] );
	unset( $query_vars['monthn