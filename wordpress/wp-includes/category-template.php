<?php
/**
 * Taxonomy API: Core category-specific template tags
 *
 * @package WordPress
 * @subpackage Template
 * @since 1.2.0
 */

/**
 * Retrieve category link URL.
 *
 * @since 1.0.0
 * @see get_term_link()
 *
 * @param int|object $category Category ID or object.
 * @return string Link on success, empty string if category does not exist.
 */
function get_category_link( $category ) {
	if ( ! is_object( $category ) )
		$category = (int) $category;

	$category = get_term_link( $category );

	if ( is_wp_error( $category ) )
		return '';

	return $category;
}

/**
 * Retrieve category parents with separator.
 *
 * @since 1.2.0
 * @since 4.8.0 The `$visited` parameter was deprecated and renamed to `$deprecated`.
 *
 * @param int $id Category ID.
 * @param bool $link Optional, default is false. Whether to format with link.
 * @param string $separator Optional, default is '/'. How to separate categories.
 * @param bool $nicename Optional, default is false. Whether to use nice name for display.
 * @param array $deprecated Not used.
 * @return string|WP_Error A list of category parents on success, WP_Error on failure.
 */
function get_category_parents( $id, $link = false, $separator = '/', $nicename = false, $deprecated = array() ) {

	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '4.8.0' );
	}

	$format = $nicename ? 'slug' : 'name';

	$args = array(
		'separator' => $separator,
		'link'      => $link,
		'format'    => $format,
	);

	return get_term_parents_list( $id, 'category', $args );
}

/**
 * Retrieve post categories.
 *
 * This tag may be used outside The Loop by passing a post id as the parameter.
 *
 * Note: This function only returns results from the default "category" taxonomy.
 * For custom taxonomies use get_the_terms().
 *
 * @since 0.71
 *
 * @param int $id Optional, default to current post ID. The post ID.
 * @return array Array of WP_Term objects, one for each category assigned to the post.
 */
function get_the_category( $id = false ) {
	$categories = get_the_terms( $id, 'category' );
	if ( ! $categories || is_wp_error( $categories ) )
		$categories = array();

	$categories = array_values( $categories );

	foreach ( array_keys( $categories ) as $key ) {
		_make_cat_compat( $categories[$key] );
	}

	/**
	 * Filters the array of categories to return for a post.
	 *
	 * @since 3.1.0
	 * @since 4.4.0 Added `$id` parameter.
	 *
	 * @param array $categories An array of categories to return for the post.
	 * @param int   $id         ID of the post.
	 */
	return apply_filters( 'get_the_categories', $categories, $id );
}

/**
 * Retrieve category name based on category ID.
 *
 * @since 0.71
 *
 * @param int $cat_ID Category ID.
 * @return string|WP_Error Category name on success, WP_Error on failure.
 */
function get_the_category_by_ID( $cat_ID ) {
	$cat_ID = (int) $cat_ID;
	$category = get_term( $cat_ID );

	if ( is_wp_error( $category ) )
		return $category;

	return ( $category ) ? $category->name : '';
}

/**
 * Retrieve category list for a post in either HTML list or custom format.
 *
 * @since 1.5.1
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param string $separator Optional. Separator between the categories. By default, the links are placed
 *                          in an unordered list. An empty string will result in the default behavior.
 * @param string $parents Optional. How to display the parents.
 * @param int $post_id Optional. Post ID to retrieve categories.
 * @return string
 */
function get_the_category_list( $separator = '', $parents = '', $post_id = false ) {
	global $wp_rewrite;
	if ( ! is_object_in_taxonomy( get_post_type( $post_id ), 'category' ) ) {
		/** This filter is documented in wp-includes/category-template.php */
		return apply_filters( 'the_category', '', $separator, $parents );
	}

	/**
	 * Filters the categories before building the category list.
	 *
	 * @since 4.4.0
	 *
	 * @param array    $categories An array of the post's categories.
	 * @param int|bool $post_id    ID of the post we're retrieving categories for. When `false`, we assume the
	 *                             current post in the loop.
	 */
	$categories = apply_filters( 'the_category_list', get_the_category( $post_id ), $post_id );

	if ( empty( $categories ) ) {
		/** This filter is documented in wp-includes/category-template.php */
		return apply_filters( 'the_category', __( 'Uncategorized' ), $separator, $parents );
	}

	$rel = ( is_object( $wp_rewrite ) && $wp_rewrite->using_permalinks() ) ? 'rel="category tag"' : 'rel="category"';

	$thelist = '';
	if ( '' == $separator ) {
		$thelist .= '<ul class="post-categories">';
		foreach ( $categories as $category ) {
			$thelist .= "\n\t<li>";
			switch ( strtolower( $parents ) ) {
				case 'multiple':
					if ( $category->parent )
						$thelist .= get_category_parents( $category->parent, true, $separator );
					$thelist .= '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '" ' . $rel . '>' . $category->name.'</a></li>';
					break;
				case 'single':
					$thelist .= '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '"  ' . $rel . '>';
					if ( $category->parent )
						$thelist .= get_category_parents( $category->parent, false, $separator );
					$thelist .= $category->name.'</a></li>';
					break;
				case '':
				default:
					$thelist .= '<a href="' . esc_url( get_category_link( $category->term_id ) ) . '" ' . $rel . '>' . $category->name.'</a></li>';
			}
		}
		$thelist .= '</ul>';
	} else {
		$i = 0;
		foreach ( $categories as $category ) {
			if ( 0 < $i )
				$thelist .= $separator;
			switch ( strtolower( $parents ) ) {
				case 'multiple':
					if ( $category-