<?php
/**
 * WordPress Taxonomy Administration API.
 *
 * @package WordPress
 * @subpackage Administration
 */

//
// Category
//

/**
 * Check whether a category exists.
 *
 * @since 2.0.0
 *
 * @see term_exists()
 *
 * @param int|string $cat_name Category name.
 * @param int        $parent   Optional. ID of parent term.
 * @return mixed
 */
function category_exists( $cat_name, $parent = null ) {
	$id = term_exists($cat_name, 'category', $parent);
	if ( is_array($id) )
		$id = $id['term_id'];
	return $id;
}

/**
 * Get category object for given ID and 'edit' filter context.
 *
 * @since 2.0.0
 *
 * @param int $id
 * @return object
 */
function get_category_to_edit( $id ) {
	$category = get_term( $id, 'category', OBJECT, 'edit' );
	_make_cat_compat( $category );
	return $category;
}

/**
 * Add a new category to the database if it does not already exist.
 *
 * @since 2.0.0
 *
 * @param int|string $cat_name
 * @param int        $parent
 * @return int|WP_Error
 */
function wp_create_category( $cat_name, $parent = 0 ) {
	if ( $id = category_exists($cat_name, $parent) )
		return $id;

	return wp_insert_category( array('cat_name' => $cat_name, 'category_parent' => $parent) );
}

/**
 * Create categories for the given post.
 *
 * @since 2.0.0
 *
 * @param array $categories List of categories to create.
 * @param int   $post_id    Optional. The post ID. Default empty.
 * @return array List of categories to create for the given post.
 */
function wp_create_categories( $categories, $post_id = '' ) {
	$cat_ids = array ();
	foreach ( $categories as $category ) {
		if ( $id = category_exists( $category ) ) {
			$cat_ids[] = $id;
		} elseif ( $id = wp_create_category( $category ) ) {
			$cat_ids[] = $id;
		}
	}

	if ( $post_id )
		wp_set_post_categories($post_id, $cat_ids);

	return $cat_ids;
}

/**
 * Updates an existing Category or cre