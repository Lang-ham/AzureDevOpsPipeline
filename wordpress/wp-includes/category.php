<?php
/**
 * Taxonomy API: Core category-specific functionality
 *
 * @package WordPress
 * @subpackage Taxonomy
 */

/**
 * Retrieve list of category objects.
 *
 * If you change the type to 'link' in the arguments, then the link categories
 * will be returned instead. Also all categories will be updated to be backward
 * compatible with pre-2.3 plugins and themes.
 *
 * @since 2.1.0
 * @see get_terms() Type of arguments that can be changed.
 *
 * @param string|array $args {
 *     Optional. Arguments to retrieve categories. See get_terms() for additional options.
 *
 *     @type string $taxonomy Taxonomy to retrieve terms for. In this case, default 'category'.
 * }
 * @return array List of categories.
 */
function get_categories( $args = '' ) {
	$defaults = array( 'taxonomy' => 'category' );
	$args = wp_parse_args( $args, $defaults );

	$taxonomy = $args['taxonomy'];

	/**
	 * Filters the taxonomy used to retrieve terms when calling get_categories().
	 *
	 * @since 2.7.0
	 *
	 * @param string $taxonomy Taxonomy to retrieve terms from.
	 * @param array  $args     An array of arguments. See get_terms().
	 */
	$taxonomy = apply_filters( 'get_categories_taxonomy', $taxonomy, $args );

	// Back compat
	if ( isset($args['type']) && 'link' == $args['type'] ) {
		_deprecated_argument( __FUNCTION__, '3.0.0',
			/* translators: 1: "type => link", 2: "taxonomy => link_category" */
			sprintf( __( '%1$s is deprecated. Use %2$s instead.' ),
				'<code>type => link</code>',
				'<code>taxonomy => link_category</code>'
			)
		);
		$taxonomy = $args['taxonomy'] = 'link_category';
	}

	$categories = get_terms( $taxonomy, $args );

	if ( is_wp_error( $categories ) ) {
		$categories = array();
	} else {
		$categories = (array) $categories;
		foreach ( array_keys( $categories ) as $k ) {
			_make_cat_compat( $categories[ $k ] );
		}
	}

	return $categories;
}

/**
 * Retrieves category data given a category ID or category object.
 *
 * If you pass the $category parameter an object, which is assumed to be the
 * category row object retrieved the database. It will cache the category data.
 *
 * If you pass $category an integer of the category ID, then that category will
 * be retrieved from the database, if it isn't already cached, and pass it back.
 *
 * If you look at get_term(), then both types will be passed through several
 * filters and finally sanitized based on the $filter parameter value.
 *
 * The category will converted to maintain backward compatibility.
 *
 * @since 1.5.1
 *
 * @param int|object $category Category ID or Category row object
 * @param string $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to a
 *                       WP_Term object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @param string $filter Optional. Default is raw or no WordPress defined filter will applied.
 * @return object|array|WP_Error|null Category data in type defined by $output parameter.
 *                                    WP_Error if $category is empty, null if it does not exist.
 */
function get_category( $category, $output = OBJECT, $filter = 'raw' ) {
	$category = get_term( $category, 'category', $output, $filter );

	if ( is_wp_error( $category ) )
		return $category;

	_make_cat_compat( $category );

	return $category;
}

/**
 * Retrieve category based on URL containing the category slug.
 *
 * Breaks the $category_path parameter up to get the category slug.
 *
 * Tries to find the child path and will return it. If it doesn't find a
 * match, then it will return the first category matching slug, if $full_match,
 * is set to false. If it does not, then it will return null.
 *
 * It is also possible that it will return a WP_Error object on failure. Check
 * for it when using this function.
 *
 * @since 2.1.0
 *
 * @param string $category_path URL containing category slugs.
 * @param bool   $full_match    Optional. Whether full path should be matched.
 * @param string $output        Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                              a WP_Term object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @return WP_Term|array|WP_Error|null Type is based on $output value.
 */
function get_category_by_path( $category_path, $full_match = true, $output = OBJECT ) {
	$category_path = rawurlencode( urldecode( $category_path ) );
	$category_path = str_replace( '%2F', '/', $category_path );
	$category_path = str_replace( '%20', ' ', $category_path );
	$category_paths = '/' . trim( $category_path, '/' );
	$leaf_path  = sanitize_title( basename( $category_paths ) );
	$category_paths = explode( '/', $category_paths );
	$full_path = '';
	foreach ( (array) $category_paths as $pathdir ) {
		$full_path .= ( $pathdir != '' ? '/' : '' ) . sanitize_title( $pathdir );
	}
	$categories = get_terms( 'category', array('get' => 'all', 'slug' => $leaf_path) );

	if ( empty( $categories ) ) {
		return;
	}

	foreach ( $categories as $category ) {
		$path = '/' . $leaf_path;
		$curcategory = $category;
		while ( ( $curcategory->parent != 0 ) && ( $curcategory->parent != $curcategory->term_id ) ) {
			$curcategory = get_term( $curcategory->parent, 'category' );
			if ( is_wp_error( $curcategory ) ) {
				return $curcategory;
			}
			$path = '/' . $curcategory->slug . $path;
		}

		if ( $path == $full_path ) {
			$category = get_term( $category->term_id, 'category', $output );
			_make_cat_compat( $category );
			return $category;
		}
	}

	// If full matching is not required, return the first cat that matches the leaf.
	if ( ! $full_match ) {
		$category = get_term( reset( $categories )->term_id, 'category', $output );
		_make_cat_compat( $category );
		return $category;
	}
}

/**
 * Retrieve category object by category slug.
 *
 * @since 2.3.0
 *
 * @param string $slug The category slug.
 * @return object Category data object
 */
function get_category_by_slug( $slug  ) {
	$category = get_term_by( 'slug', $slug, 'category' );
	if ( $category )
		_make_cat_compat( $category );

	return $category;
}

/**
 * Retrieve the ID of a category from its name.
 *
 * @since 1.0.0
 *
 * @param string $cat_name Category name.
 * @return int 0, if failure and ID of category on success.
 */
function get_cat_ID( $cat_name ) {
	$cat = get_term_by( 'name', $cat_name, 'category' );
	if ( $cat )
		return $cat->term_id;
	return 0;
}

/**
 * Retrieve the name of a category from its ID.
 *
 * @since 1.0.0
 *
 * @param int $cat_id Category ID
 * @return string Category name, or an empty string if category doesn't exist.
 */
function get_cat_name( $cat_id ) {
	$cat_id = (int) $cat_id;
	$category = get_term( $cat_id, 'category' );
	if ( ! $category || is_wp_error( $category ) )
		return '';
	return $category->name;
}

/**
 * Check if a category is an ancestor of another category.
 *
 * You can use either an id or the category object for both parameters. If you
 * use an integer the category will be retrieved.
 *
 * @since 2.1.0
 *
 * @param int|object $cat1 ID or object to check if this is the parent category.
 * @param int|object $cat2 The child category.
 * @return bool Whether $cat2 is child of $cat1
 */
function cat_is_ancestor_of( $cat1, $cat2 ) {
	return term_is_ancestor_of( $cat1, $cat2, 'category' );
}

/**
 * Sanitizes category data based on context.
 *
 * @since 2.3.0
 *
 * @param object|array $category Category data
 * @param string $context Optional. Default is 'display'.
 * @return object|array Same type as $category with sanitized data for safe use.
 */
function sanitize_category( $category, $context = 'display' ) {
	return sanitize_term( $category, 'category', $context );
}

/**
 * Sanitizes data in single category key field.
 *
 * @since 2.3.0
 *
 * @param string $field Category key to sanitize
 * @param mixed $value Category value to sanitize
 * @param int $cat_id Category ID
 * @param string $context What filter to use, 'raw', 'display', etc.
 * @return mixed Same type as $value after $value has been sanitized.
 */
function sanitize_category_field( $field, $value, $cat_id, $context ) {
	return sanitize_term_field( $field, $value, 