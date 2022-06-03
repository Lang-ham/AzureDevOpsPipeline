<?php
/**
 * Deprecated functions from past WordPress versions. You shouldn't use these
 * functions and look for the alternatives instead. The functions will be
 * removed in a later version.
 *
 * @package WordPress
 * @subpackage Deprecated
 */

/*
 * Deprecated functions come here to die.
 */

/**
 * Retrieves all post data for a given post.
 *
 * @since 0.71
 * @deprecated 1.5.1 Use get_post()
 * @see get_post()
 *
 * @param int $postid Post ID.
 * @return array Post data.
 */
function get_postdata($postid) {
	_deprecated_function( __FUNCTION__, '1.5.1', 'get_post()' );

	$post = get_post($postid);

	$postdata = array (
		'ID' => $post->ID,
		'Author_ID' => $post->post_author,
		'Date' => $post->post_date,
		'Content' => $post->post_content,
		'Excerpt' => $post->post_excerpt,
		'Title' => $post->post_title,
		'Category' => $post->post_category,
		'post_status' => $post->post_status,
		'comment_status' => $post->comment_status,
		'ping_status' => $post->ping_status,
		'post_password' => $post->post_password,
		'to_ping' => $post->to_ping,
		'pinged' => $post->pinged,
		'post_type' => $post->post_type,
		'post_name' => $post->post_name
	);

	return $postdata;
}

/**
 * Sets up the WordPress Loop.
 *
 * Use The Loop instead.
 *
 * @link https://codex.wordpress.org/The_Loop
 *
 * @since 1.0.1
 * @deprecated 1.5.0
 */
function start_wp() {
	global $wp_query;

	_deprecated_function( __FUNCTION__, '1.5.0', __('new WordPress Loop') );

	// Since the old style loop is being used, advance the query iterator here.
	$wp_query->next_post();

	setup_postdata( get_post() );
}

/**
 * Returns or prints a category ID.
 *
 * @since 0.71
 * @deprecated 0.71 Use get_the_category()
 * @see get_the_category()
 *
 * @param bool $echo Optional. Whether to echo the output. Default true.
 * @return int Category ID.
 */
function the_category_ID($echo = true) {
	_deprecated_function( __FUNCTION__, '0.71', 'get_the_category()' );

	// Grab the first cat in the list.
	$categories = get_the_category();
	$cat = $categories[0]->term_id;

	if ( $echo )
		echo $cat;

	return $cat;
}

/**
 * Prints a category with optional text before and after.
 *
 * @since 0.71
 * @deprecated 0.71 Use get_the_category_by_ID()
 * @see get_the_category_by_ID()
 *
 * @param string $before Optional. Text to display before the category. Default empty.
 * @param string $after  Optional. Text to display after the category. Default empty.
 */
function the_category_head( $before = '', $after = '' ) {
	global $currentcat, $previouscat;

	_deprecated_function( __FUNCTION__, '0.71', 'get_the_category_by_ID()' );

	// Grab the first cat in the list.
	$categories = get_the_category();
	$currentcat = $categories[0]->category_id;
	if ( $currentcat != $previouscat ) {
		echo $before;
		echo get_the_category_by_ID($currentcat);
		echo $after;
		$previouscat = $currentcat;
	}
}

/**
 * Prints a link to the previous post.
 *
 * @since 1.5.0
 * @deprecated 2.0.0 Use previous_post_link()
 * @see previous_post_link()
 *
 * @param string $format
 * @param string $previous
 * @param string $title
 * @param string $in_same_cat
 * @param int    $limitprev
 * @param string $excluded_categories
 */
function previous_post($format='%', $previous='previous post: ', $title='yes', $in_same_cat='no', $limitprev=1, $excluded_categories='') {

	_deprecated_function( __FUNCTION__, '2.0.0', 'previous_post_link()' );

	if ( empty($in_same_cat) || 'no' == $in_same_cat )
		$in_same_cat = false;
	else
		$in_same_cat = true;

	$post = get_previous_post($in_same_cat, $excluded_categories);

	if ( !$post )
		return;

	$string = '<a href="'.get_permalink($post->ID).'">'.$previous;
	if ( 'yes' == $title )
		$string .= apply_filters('the_title', $post->post_title, $post->ID);
	$string .= '</a>';
	$format = str_replace('%', $string, $format);
	echo $format;
}

/**
 * Prints link to the next post.
 *
 * @since 0.71
 * @deprecated 2.0.0 Use next_post_link()
 * @see next_post_link()
 *
 * @param string $format
 * @param string $next
 * @param string $title
 * @param string $in_same_cat
 * @param int $limitnext
 * @param string $excluded_categories
 */
function next_post($format='%', $next='next post: ', $title='yes', $in_same_cat='no', $limitnext=1, $excluded_categories='') {
	_deprecated_function( __FUNCTION__, '2.0.0', 'next_post_link()' );

	if ( empty($in_same_cat) || 'no' == $in_same_cat )
		$in_same_cat = false;
	else
		$in_same_cat = true;

	$post = get_next_post($in_same_cat, $excluded_categories);

	if ( !$post	)
		return;

	$string = '<a href="'.get_permalink($post->ID).'">'.$next;
	if ( 'yes' == $title )
		$string .= apply_filters('the_title', $post->post_title, $post->ID);
	$string .= '</a>';
	$format = str_replace('%', $string, $format);
	echo $format;
}

/**
 * Whether user can create a post.
 *
 * @since 1.5.0
 * @deprecated 2.0.0 Use current_user_can()
 * @see current_user_can()
 *
 * @param int $user_id
 * @param int $blog_id Not Used
 * @param int $category_id Not Used
 * @return bool
 */
function user_can_create_post($user_id, $blog_id = 1, $category_id = 'None') {
	_deprecated_function( __FUNCTION__, '2.0.0', 'current_user_can()' );

	$author_data = get_userdata($user_id);
	return ($author_data->user_level > 1);
}

/**
 * Whether user can create a post.
 *
 * @since 1.5.0
 * @deprecated 2.0.0 Use current_user_can()
 * @see current_user_can()
 *
 * @param int $user_id
 * @param int $blog_id Not Used
 * @param int $category_id Not Used
 * @return bool
 */
function user_can_create_draft($user_id, $blog_id = 1, $category_id = 'None') {
	_deprecated_function( __FUNCTION__, '2.0.0', 'current_user_can()' );

	$author_data = get_userdata($user_id);
	return ($author_data->user_level >= 1);
}

/**
 * Whether user can edit a post.
 *
 * @since 1.5.0
 * @deprecated 2.0.0 Use current_user_can()
 * @see current_user_can()
 *
 * @param int $user_id
 * @param int $post_id
 * @param int $blog_id Not Used
 * @return bool
 */
function user_can_edit_post($user_id, $post_id, $blog_id = 1) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'current_user_can()' );

	$author_data = get_userdata($user_id);
	$post = get_post($post_id);
	$post_author_data = get_userdata($post->post_author);

	if ( (($user_id == $post_author_data->ID) && !($post->post_status == 'publish' && $author_data->user_level < 2))
			 || ($author_data->user_level > $post_author_data->user_level)
			 || ($author_data->user_level >= 10) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Whether user can delete a post.
 *
 * @since 1.5.0
 * @deprecated 2.0.0 Use current_user_can()
 * @see current_user_can()
 *
 * @param int $user_id
 * @param int $post_id
 * @param int $blog_id Not Used
 * @return bool
 */
function user_can_delete_post($user_id, $post_id, $blog_id = 1) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'current_user_can()' );

	// right now if one can edit, one can delete
	return user_can_edit_post($user_id, $post_id, $blog_id);
}

/**
 * Whether user can set new posts' dates.
 *
 * @since 1.5.0
 * @deprecated 2.0.0 Use current_user_can()
 * @see current_user_can()
 *
 * @param int $user_id
 * @param int $blog_id Not Used
 * @param int $category_id Not Used
 * @return bool
 */
function user_can_set_post_date($user_id, $blog_id = 1, $category_id = 'None') {
	_deprecated_function( __FUNCTION__, '2.0.0', 'current_user_can()' );

	$author_data = get_userdata($user_id);
	return (($author_data->user_level > 4) && user_can_create_post($user_id, $blog_id, $category_id));
}

/**
 * Whether user can delete a post.
 *
 * @since 1.5.0
 * @deprecated 2.0.0 Use current_user_can()
 * @see current_user_can()
 *
 * @param int $user_id
 * @param int $post_id
 * @param int $blog_id Not Used
 * @return bool returns true if $user_id can edit $post_id's date
 */
function user_can_edit_post_date($user_id, $post_id, $blog_id = 1) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'current_user_can()' );

	$author_data = get_userdata($user_id);
	return (($author_data->user_level > 4) && user_can_edit_post($user_id, $post_id, $blog_id));
}

/**
 * Whether user can delete a post.
 *
 * @since 1.5.0
 * @deprecated 2.0.0 Use current_user_can()
 * @see current_user_can()
 *
 * @param int $user_id
 * @param int $post_id
 * @param int $blog_id Not Used
 * @return bool returns true if $user_id can edit $post_id's comments
 */
function user_can_edit_post_comments($user_id, $post_id, $blog_id = 1) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'current_user_can()' );

	// right now if one can edit a post, one can edit comments made on it
	return user_can_edit_post($user_id, $post_id, $blog_id);
}

/**
 * Whether user can delete a post.
 *
 * @since 1.5.0
 * @deprecated 2.0.0 Use current_user_can()
 * @see current_user_can()
 *
 * @param int $user_id
 * @param int $post_id
 * @param int $blog_id Not Used
 * @return bool returns true if $user_id can delete $post_id's comments
 */
function user_can_delete_post_comments($user_id, $post_id, $blog_id = 1) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'current_user_can()' );

	// right now if one can edit comments, one can delete comments
	return user_can_edit_post_comments($user_id, $post_id, $blog_id);
}

/**
 * Can user can edit other user.
 *
 * @since 1.5.0
 * @deprecated 2.0.0 Use current_user_can()
 * @see current_user_can()
 *
 * @param int $user_id
 * @param int $other_user
 * @return bool
 */
function user_can_edit_user($user_id, $other_user) {
	_deprecated_function( __FUNCTION__, '2.0.0', 'current_user_can()' );

	$user  = get_userdata($user_id);
	$other = get_userdata($other_user);
	if ( $user->user_level > $other->user_level || $user->user_level > 8 || $user->ID == $other->ID )
		return true;
	else
		return false;
}

/**
 * Gets the links associated with category $cat_name.
 *
 * @since 0.71
 * @deprecated 2.1.0 Use get_bookmarks()
 * @see get_bookmarks()
 *
 * @param string $cat_name Optional. The category name to use. If no match is found uses all.
 * @param string $before Optional. The html to output before the link.
 * @param string $after Optional. The html to output after the link.
 * @param string $between Optional. The html to output between the link/image and its description. Not used if no image or $show_images is true.
 * @param bool $show_images Optional. Whether to show images (if defined).
 * @param string $orderby Optional. The order to output the links. E.g. 'id', 'name', 'url', 'description' or 'rating'. Or maybe owner.
 *		If you start the name with an underscore the order will be reversed. You can also specify 'rand' as the order which will return links in a
 *		random order.
 * @param bool $show_description Optional. Whether to show the description if show_images=false/not defined.
 * @param bool $show_rating Optional. Show rating stars/chars.
 * @param int $limit		Optional. Limit to X entries. If not specified, all entries are shown.
 * @param int $show_updated Optional. Whether to show last updated timestamp
 */
function get_linksbyname($cat_name = "noname", $before = '', $after = '<br />', $between = " ", $show_images = true, $orderby = 'id',
						 $show_description = true, $show_rating = false,
						 $limit = -1, $show_updated = 0) {
	_deprecated_function( __FUNCTION__, '2.1.0', 'get_bookmarks()' );

	$cat_id = -1;
	$cat = get_term_by('name', $cat_name, 'link_category');
	if ( $cat )
		$cat_id = $cat->term_id;

	get_links($cat_id, $before, $after, $between, $show_images, $orderby, $show_description, $show_rating, $limit, $show_updated);
}

/**
 * Gets the links associated with the named category.
 *
 * @since 1.0.1
 * @deprecated 2.1.0 Use wp_list_bookmarks()
 * @see wp_list_bookmarks()
 *
 * @param string $category The category to use.
 * @param string $args
 * @return string|null
 */
function wp_get_linksbyname($category, $args = '') {
	_deprecated_function(__FUNCTION__, '2.1.0', 'wp_list_bookmarks()');

	$defaults = array(
		'after' => '<br />',
		'before' => '',
		'categorize' => 0,
		'category_after' => '',
		'category_before' => '',
		'category_name' => $category,
		'show_description' => 1,
		'title_li' => '',
	);

	$r = wp_parse_args( $args, $defaults );

	return wp_list_bookmarks($r);
}

/**
 * Gets an array of link objects associated with category $cat_name.
 *
 *     $links = get_linkobjectsbyname( 'fred' );
 *     foreach ( $links as $link ) {
 *      	echo '<li>' . $link->link_name . '</li>';
 *     }
 *
 * @since 1.0.1
 * @deprecated 2.1.0 Use get_bookmarks()
 * @see get_bookmarks()
 *
 * @param string $cat_name The category name to use. If no match is found uses all.
 * @param string $orderby The order to output the links. E.g. 'id', 'name', 'url', 'description', or 'rating'.
 *		Or maybe owner. If you start the name with an underscore the order will be reversed. You can also
 *		specify 'rand' as the order which will return links in a random order.
 * @param int $limit Limit to X entries. If not specified, all entries are shown.
 * @return array
 */
function get_linkobjectsbyname($cat_name = "noname" , $orderby = 'name', $limit = -1) {
	_deprecated_function( __FUNCTION__, '2.1.0', 'get_bookmarks()' );

	$cat_id = -1;
	$cat = get_term_by('name', $cat_name, 'link_category');
	if ( $cat )
		$cat_id = $cat->term_id;

	return get_linkobjects($cat_id, $orderby, $limit);
}

/**
 * Gets an array of link objects associated with category n.
 *
 * Usage:
 *
 *     $links = get_linkobjects(1);
 *     if ($links) {
 *     	for