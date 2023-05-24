<?php
/**
 * Core Taxonomy API
 *
 * @package WordPress
 * @subpackage Taxonomy
 */

//
// Taxonomy Registration
//

/**
 * Creates the initial taxonomies.
 *
 * This function fires twice: in wp-settings.php before plugins are loaded (for
 * backward compatibility reasons), and again on the {@see 'init'} action. We must
 * avoid registering rewrite rules before the {@see 'init'} action.
 *
 * @since 2.8.0
 *
 * @global WP_Rewrite $wp_rewrite The WordPress rewrite class.
 */
function create_initial_taxonomies() {
	global $wp_rewrite;

	if ( ! did_action( 'init' ) ) {
		$rewrite = array( 'category' => false, 'post_tag' => false, 'post_format' => false );
	} else {

		/**
		 * Filters the post formats rewrite base.
		 *
		 * @since 3.1.0
		 *
		 * @param string $context Context of the rewrite base. Default 'type'.
		 */
		$post_format_base = apply_filters( 'post_format_rewrite_base', 'type' );
		$rewrite = array(
			'category' => array(
				'hierarchical' => true,
				'slug' => get_option('category_base') ? get_option('category_base') : 'category',
				'with_front' => ! get_option('category_base') || $wp_rewrite->using_index_permalinks(),
				'ep_mask' => EP_CATEGORIES,
			),
			'post_tag' => array(
				'hierarchical' => false,
				'slug' => get_option('tag_base') ? get_option('tag_base') : 'tag',
				'with_front' => ! get_option('tag_base') || $wp_rewrite->using_index_permalinks(),
				'ep_mask' => EP_TAGS,
			),
			'post_format' => $post_format_base ? array( 'slug' => $post_format_base ) : false,
		);
	}

	register_taxonomy( 'category', 'post', array(
		'hierarchical' => true,
		'query_var' => 'category_name',
		'rewrite' => $rewrite['category'],
		'public' => true,
		'show_ui' => true,
		'show_admin_column' => true,
		'_builtin' => true,
		'capabilities' => array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'edit_categories',
			'delete_terms' => 'delete_categories',
			'assign_terms' => 'assign_categories',
		),
		'show_in_rest' => true,
		'rest_base' => 'categories',
		'rest_controller_class' => 'WP_REST_Terms_Controller',
	) );

	register_taxonomy( 'post_tag', 'post', array(
	 	'hierarchical' => false,
		'query_var' => 'tag',
		'rewrite' => $rewrite['post_tag'],
		'public' => true,
		'show_ui' => true,
		'show_admin_column' => true,
		'_builtin' => true,
		'capabilities' => array(
			'manage_terms' => 'manage_post_tags',
			'edit_terms'   => 'edit_post_tags',
			'delete_terms' => 'delete_post_tags',
			'assign_terms' => 'assign_post_tags',
		),
		'show_in_rest' => true,
		'rest_base' => 'tags',
		'rest_controller_class' => 'WP_REST_Terms_Controller',
	) );

	register_taxonomy( 'nav_menu', 'nav_menu_item', array(
		'public' => false,
		'hierarchical' => false,
		'labels' => array(
			'name' => __( 'Navigation Menus' ),
			'singular_name' => __( 'Navigation Menu' ),
		),
		'query_var' => false,
		'rewrite' => false,
		'show_ui' => false,
		'_builtin' => true,
		'show_in_nav_menus' => false,
	) );

	register_taxonomy( 'link_category', 'link', array(
		'hierarchical' => false,
		'labels' => array(
			'name' => __( 'Link Categories' ),
			'singular_name' => __( 'Link Category' ),
			'search_items' => __( 'Search Link Categories' ),
			'popular_items' => null,
			'all_items' => __( 'All Link Categories' ),
			'edit_item' => __( 'Edit Link Category' ),
			'update_item' => __( 'Update Link Category' ),
			'add_new_item' => __( 'Add New Link Category' ),
			'new_item_name' => __( 'New Link Category Name' ),
			'separate_items_with_commas' => null,
			'add_or_remove_items' => null,
			'choose_from_most_used' => null,
			'back_to_items' => __( '&larr; Back to Link Categories' ),
		),
		'capabilities' => array(
			'manage_terms' => 'manage_links',
			'edit_terms'   => 'manage_links',
			'delete_terms' => 'manage_links',
			'assign_terms' => 'manage_links',
		),
		'query_var' => false,
		'rewrite' => false,
		'public' => false,
		'show_ui' => true,
		'_builtin' => true,
	) );

	register_taxonomy( 'post_format', 'post', array(
		'public' => true,
		'hierarchical' => false,
		'labels' => array(
			'name' => _x( 'Format', 'post format' ),
			'singular_name' => _x( 'Format', 'post format' ),
		),
		'query_var' => true,
		'rewrite' => $rewrite['post_format'],
		'show_ui' => false,
		'_builtin' => true,
		'show_in_nav_menus' => current_theme_supports( 'post-formats' ),
	) );
}

/**
 * Retrieves a list of registered taxonomy names or objects.
 *
 * @since 3.0.0
 *
 * @global array $wp_taxonomies The registered taxonomies.
 *
 * @param array  $args     Optional. An array of `key => value` arguments to match against the taxonomy objects.
 *                         Default empty array.
 * @param string $output   Optional. The type of output to return in the array. Accepts either taxonomy 'names'
 *                         or 'objects'. Default 'names'.
 * @param string $operator Optional. The logical operation to perform. Accepts 'and' or 'or'. 'or' means only
 *                         one element from the array needs to match; 'and' means all elements must match.
 *                         Default 'and'.
 * @return array A list of taxonomy names or objects.
 */
function get_taxonomies( $args = array(), $output = 'names', $operator = 'and' ) {
	global $wp_taxonomies;

	$field = ('names' == $output) ? 'name' : false;

	return wp_filter_object_list($wp_taxonomies, $args, $operator, $field);
}

/**
 * Return the names or objects of the taxonomies which are registered for the requested object or object type, such as
 * a post object or post type name.
 *
 * Example:
 *
 *     $taxonomies = get_object_taxonomies( 'post' );
 *
 * This results in:
 *
 *     Array( 'category', 'post_tag' )
 *
 * @since 2.3.0
 *
 * @global array $wp_taxonomies The registered taxonomies.
 *
 * @param array|string|WP_Post $object Name of the type of taxonomy object, or an object (row from posts)
 * @param string               $output Optional. The type of output to return in the array. Accepts either
 *                                     taxonomy 'names' or 'objects'. Default 'names'.
 * @return array The names of all taxonomy of $object_type.
 */
function get_object_taxonomies( $object, $output = 'names' ) {
	global $wp_taxonomies;

	if ( is_object($object) ) {
		if ( $object->post_type == 'attachment' )
			return get_attachment_taxonomies( $object, $output );
		$object = $object->post_type;
	}

	$object = (array) $object;

	$taxonomies = array();
	foreach ( (array) $wp_taxonomies as $tax_name => $tax_obj ) {
		if ( array_intersect($object, (array) $tax_obj->object_type) ) {
			if ( 'names' == $output )
				$taxonomies[] = $tax_name;
			else
				$taxonomies[ $tax_name ] = $tax_obj;
		}
	}

	return $taxonomies;
}

/**
 * Retrieves the taxonomy object of $taxonomy.
 *
 * The get_taxonomy function will first check that the parameter string given
 * is a taxonomy object and if it is, it will return it.
 *
 * @since 2.3.0
 *
 * @global array $wp_taxonomies The registered taxonomies.
 *
 * @param string $taxonomy Name of taxonomy object to return.
 * @return WP_Taxonomy|false The Taxonomy Object or false if $taxonomy doesn't exist.
 */
function get_taxonomy( $taxonomy ) {
	global $wp_taxonomies;

	if ( ! taxonomy_exists( $taxonomy ) )
		return false;

	return $wp_taxonomies[$taxonomy];
}

/**
 * Checks that the taxonomy name exists.
 *
 * Formerly is_taxonomy(), introduced in 2.3.0.
 *
 * @since 3.0.0
 *
 * @global array $wp_taxonomies The registered taxonomies.
 *
 * @param string $taxonomy Name of taxonomy object.
 * @return bool Whether the taxonomy exists.
 */
function taxonomy_exists( $taxonomy ) {
	global $wp_taxonomies;

	return isset( $wp_taxonomies[$taxonomy] );
}

/**
 * Whether the taxonomy object is hierarchical.
 *
 * Checks to make sure that the taxonomy is an object first. Then Gets the
 * object, and finally returns the hierarchical value in the object.
 *
 * A false return value might also mean that the taxonomy does not exist.
 *
 * @since 2.3.0
 *
 * @param string $taxonomy Name of taxonomy object.
 * @return bool Whether the taxonomy is hierarchical.
 */
function is_taxonomy_hierarchical($taxonomy) {
	if ( ! taxonomy_exists($taxonomy) )
		return false;

	$taxonomy = get_taxonomy($taxonomy);
	return $taxonomy->hierarchical;
}

/**
 * Creates or modifies a taxonomy object.
 *
 * Note: Do not use before the {@see 'init'} hook.
 *
 * A simple function for creating or modifying a taxonomy object based on
 * the parameters given. If modifying an existing taxonomy object, note
 * that the `$object_type` value from the original registration will be
 * overwritten.
 *
 * @since 2.3.0
 * @since 4.2.0 Introduced `show_in_quick_edit` argument.
 * @since 4.4.0 The `show_ui` argument is now enforced on the term editing screen.
 * @since 4.4.0 The `public` argument now controls whether the taxonomy can be queried on the front end.
 * @since 4.5.0 Introduced `publicly_queryable` argument.
 * @since 4.7.0 Introduced `show_in_rest`, 'rest_base' and 'rest_controller_class'
 *              arguments to register the Taxonomy in REST API.
 *
 * @global array $wp_taxonomies Registered taxonomies.
 *
 * @param string       $taxonomy    Taxonomy key, must not exceed 32 characters.
 * @param array|string $object_type Object type or array of object types with which the taxonomy should be associated.
 * @param array|string $args        {
 *     Optional. Array or query string of arguments for registering a taxonomy.
 *
 *     @type array         $labels                An array of labels for this taxonomy. By default, Tag labels are
 *                                                used for non-hierarchical taxonomies, and Category labels are used
 *                                                for hierarchical taxonomies. See accepted values in
 *                                                get_taxonomy_labels(). Default empty array.
 *     @type string        $description           A short descriptive summary of what the taxonomy is for. Default empty.
 *     @type bool          $public                Whether a taxonomy is intended for use publicly either via
 *                                                the admin interface or by front-end users. The default settings
 *                                                of `$publicly_queryable`, `$show_ui`, and `$show_in_nav_menus`
 *                                                are inherited from `$public`.
 *     @type bool          $publicly_queryable    Whether the taxonomy is publicly queryable.
 *                                                If not set, the default is inherited from `$public`
 *     @type bool          $hierarchical          Whether the taxonomy is hierarchical. Default false.
 *     @type bool          $show_ui               Whether to generate and allow a UI for managing terms in this taxonomy in
 *                                                the admin. If not set, the default is inherited from `$public`
 *                                                (default true).
 *     @type bool          $show_in_menu          Whether to show the taxonomy in the admin menu. If true, the taxonomy is
 *                                                shown as a submenu of the object type menu. If false, no menu is shown.
 *                                                `$show_ui` must be true. If not set, default is inherited from `$show_ui`
 *                                                (default true).
 *     @type bool          $show_in_nav_menus     Makes this taxonomy available for selection in navigation menus. If not
 *                                                set, the default is inherited from `$public` (default true).
 *     @type bool          $show_in_rest          Whether to include the taxonomy in the REST API.
 *     @type string        $rest_base             To change the base url of REST API route. Default is $taxonomy.
 *     @type string        $rest_controller_class REST API Controller class name. Default is 'WP_REST_Terms_Controller'.
 *     @type bool          $show_tagcloud         Whether to list the taxonomy in the Tag Cloud Widget controls. If not set,
 *                                                the default is inherited from `$show_ui` (default true).
 *     @type bool          $show_in_quick_edit    Whether to show the taxonomy in the quick/bulk edit panel. It not set,
 *                                                the default is inherited from `$show_ui` (default true).
 *     @type bool          $show_admin_column     Whether to display a column for the taxonomy on its post type listing
 *                                                screens. Default false.
 *     @type bool|callable $meta_box_cb           Provide a callback function for the meta box display. If not set,
 *                                                post_categories_meta_box() is used for hierarchical taxonomies, and
 *                                                post_tags_meta_box() is used for non-hierarchical. If false, no meta
 *                                                box is shown.
 *     @type array         $capabilities {
 *         Array of capabilities for this taxonomy.
 *
 *         @type string $manage_terms Default 'manage_categories'.
 *         @type string $edit_terms   Default 'manage_categories'.
 *         @type string $delete_terms Default 'manage_categories'.
 *         @type string $assign_terms Default 'edit_posts'.
 *     }
 *     @type bool|array    $rewrite {
 *         Triggers the handling of rewrites for this taxonomy. Default true, using $taxonomy as slug. To prevent
 *         rewrite, set to false. To specify rewrite rules, an array can be passed with any of these keys:
 *
 *         @type string $slug         Customize the permastruct slug. Default `$taxonomy` key.
 *         @type bool   $with_front   Should the permastruct be prepended with WP_Rewrite::$front. Default true.
 *         @type bool   $hierarchical Either hierarchical rewrite tag or not. Default false.
 *         @type int    $ep_mask      Assign an endpoint mask. Default `EP_NONE`.
 *     }
 *     @type string        $query_var             Sets the query var key for this taxonomy. Default `$taxonomy` key. If
 *                                                false, a taxonomy cannot be loaded at `?{query_var}={term_slug}`. If a
 *                                                string, the query `?{query_var}={term_slug}` will be valid.
 *     @type callable      $update_count_callback Works much like a hook, in that it will be called when the count is
 *                                                updated. Default _update_post_term_count() for taxonomies attached
 *                                                to post types, which confirms that the objects are published before
 *                                                counting them. Default _update_generic_term_count() for taxonomies
 *                                                attached to other object types, such as users.
 *     @type bool          $_builtin              This taxonomy is a "built-in" taxonomy. INTERNAL USE ONLY!
 *                                                Default false.
 * }
 * @return WP_Error|void WP_Error, if errors.
 */
function register_taxonomy( $taxonomy, $object_type, $args = array() ) {
	global $wp_taxonomies;

	if ( ! is_array( $wp_taxonomies ) )
		$wp_taxonomies = array();

	$args = wp_parse_args( $args );

	if ( empty( $taxonomy ) || strlen( $taxonomy ) > 32 ) {
		_doing_it_wrong( __FUNCTION__, __( 'Taxonomy names must be between 1 and 32 characters in length.' ), '4.2.0' );
		return new WP_Error( 'taxonomy_length_invalid', __( 'Taxonomy names must be between 1 and 32 characters in length.' ) );
	}

	$taxonomy_object = new WP_Taxonomy( $taxonomy, $object_type, $args );
	$taxonomy_object->add_rewrite_rules();

	$wp_taxonomies[ $taxonomy ] = $taxonomy_object;

	$taxonomy_object->add_hooks();


	/**
	 * Fires after a taxonomy is registered.
	 *
	 * @since 3.3.0
	 *
	 * @param string       $taxonomy    Taxonomy slug.
	 * @param array|string $object_type Object type or array of object types.
	 * @param array        $args        Array of taxonomy registration arguments.
	 */
	do_action( 'registered_taxonomy', $taxonomy, $object_type, (array) $taxonomy_object );
}

/**
 * Unregisters a taxonomy.
 *
 * Can not be used to unregister built-in taxonomies.
 *
 * @since 4.5.0
 *
 * @global WP    $wp            Current WordPress environment instance.
 * @global array $wp_taxonomies List of taxonomies.
 *
 * @param string $taxonomy Taxonomy name.
 * @return bool|WP_Error True on success, WP_Error on failure or if the taxonomy doesn't exist.
 */
function unregister_taxonomy( $taxonomy ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
	}

	$taxonomy_object = get_taxonomy( $taxonomy );

	// Do not allow unregistering internal taxonomies.
	if ( $taxonomy_object->_builtin ) {
		return new WP_Error( 'invalid_taxonomy', __( 'Unregistering a built-in taxonomy is not allowed.' ) );
	}

	global $wp_taxonomies;

	$taxonomy_object->remove_rewrite_rules();
	$taxonomy_object->remove_hooks();

	// Remove the taxonomy.
	unset( $wp_taxonomies[ $taxonomy ] );

	/**
	 * Fires after a taxonomy is unregistered.
	 *
	 * @since 4.5.0
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	do_action( 'unregistered_taxonomy', $taxonomy );

	return true;
}

/**
 * Builds an object with all taxonomy labels out of a taxonomy object.
 *
 * @since 3.0.0
 * @since 4.3.0 Added the `no_terms` label.
 * @since 4.4.0 Added the `items_list_navigation` and `items_list` labels.
 * @since 4.9.0 Added the `most_used` and `back_to_items` labels.
 *
 * @param WP_Taxonomy $tax Taxonomy object.
 * @return object {
 *     Taxonomy labels object. The first default value is for non-hierarchical taxonomies
 *     (like tags) and the second one is for hierarchical taxonomies (like categories).
 *
 *     @type string $name                       General name for the taxonomy, usually plural. The same
 *                                              as and overridden by `$tax->label`. Default 'Tags'/'Categories'.
 *     @type string $singular_name              Name for one object of this taxonomy. Default 'Tag'/'Category'.
 *     @type string $search_items               Default 'Search Tags'/'Search Categories'.
 *     @type string $popular_items              This label is only used for non-hierarchical taxonomies.
 *                                              Default 'Popular Tags'.
 *     @type string $all_items                  Default 'All Tags'/'All Categories'.
 *     @type string $parent_item                This label is only used for hierarchical taxonomies. Default
 *                                              'Parent Category'.
 *     @type string $parent_item_colon          The same as `parent_item`, but with colon `:` in the end.
 *     @type string $edit_item                  Default 'Edit Tag'/'Edit Category'.
 *     @type string $view_item                  Default 'View Tag'/'View Category'.
 *     @type string $update_item                Default 'Update Tag'/'Update Category'.
 *     @type string $add_new_item               Default 'Add New Tag'/'Add New Category'.
 *     @type string $new_item_name              Default 'New Tag Name'/'New Category Name'.
 *     @type string $separate_items_with_commas This label is only used for non-hierarchical taxonomies. Default
 *                                              'Separate tags with commas', used in the meta box.
 *     @type string $add_or_remove_items        This label is only used for non-hierarchical taxonomies. Default
 *                                              'Add or remove tags', used in the meta box when JavaScript
 *                                              is disabled.
 *     @type string $choose_from_most_used      This label is only used on non-hierarchical taxonomies. Default
 *                                              'Choose from the most used tags', used in the meta box.
 *     @type string $not_found                  Default 'No tags found'/'No categories found', used in
 *                                              the meta box and taxonomy list table.
 *     @type string $no_terms                   Default 'No tags'/'No categories', used in the posts and media
 *                                              list tables.
 *     @type string $items_list_navigation      Label for the table pagination hidden heading.
 *     @type string $items_list                 Label for the table hidden heading.
 *     @type string $most_used                  Title for the Most Used tab. Default 'Most Used'.
 *     @type string $back_to_items              Label displayed after a term has been updated.
 * }
 */
function get_taxonomy_labels( $tax ) {
	$tax->labels = (array) $tax->labels;

	if ( isset( $tax->helps ) && empty( $tax->labels['separate_items_with_commas'] ) )
		$tax->labels['separate_items_with_commas'] = $tax->helps;

	if ( isset( $tax->no_tagcloud ) && empty( $tax->labels['not_found'] ) )
		$tax->labels['not_found'] = $tax->no_tagcloud;

	$nohier_vs_hier_defaults = array(
		'name' => array( _x( 'Tags', 'taxonomy general name' ), _x( 'Categories', 'taxonomy general name' ) ),
		'singular_name' => array( _x( 'Tag', 'taxonomy singular name' ), _x( 'Category', 'taxonomy singular name' ) ),
		'search_items' => array( __( 'Search Tags' ), __( 'Search Categories' ) ),
		'popular_items' => array( __( 'Popular Tags' ), null ),
		'all_items' => array( __( 'All Tags' ), __( 'All Categories' ) ),
		'parent_item' => array( null, __( 'Parent Category' ) ),
		'parent_item_colon' => array( null, __( 'Parent Category:' ) ),
		'edit_item' => array( __( 'Edit Tag' ), __( 'Edit Category' ) ),
		'view_item' => array( __( 'View Tag' ), __( 'View Category' ) ),
		'update_item' => array( __( 'Update Tag' ), __( 'Update Category' ) ),
		'add_new_item' => array( __( 'Add New Tag' ), __( 'Add New Category' ) ),
		'new_item_name' => array( __( 'New Tag Name' ), __( 'New Category Name' ) ),
		'separate_items_with_commas' => array( __( 'Separate tags with commas' ), null ),
		'add_or_remove_items' => array( __( 'Add or remove tags' ), null ),
		'choose_from_most_used' => array( __( 'Choose from the most used tags' ), null ),
		'not_found' => array( __( 'No tags found.' ), __( 'No categories found.' ) ),
		'no_terms' => array( __( 'No tags' ), __( 'No categories' ) ),
		'items_list_navigation' => array( __( 'Tags list navigation' ), __( 'Categories list navigation' ) ),
		'items_list' => array( __( 'Tags list' ), __( 'Categories list' ) ),
		/* translators: Tab heading when selecting from the most used terms */
		'most_used' => array( _x( 'Most Used', 'tags' ), _x( 'Most Used', 'categories' ) ),
		'back_to_items' => array( __( '&larr; Back to Tags' ), __( '&larr; Back to Categories' ) ),
	);
	$nohier_vs_hier_defaults['menu_name'] = $nohier_vs_hier_defaults['name'];

	$labels = _get_custom_object_labels( $tax, $nohier_vs_hier_defaults );

	$taxonomy = $tax->name;

	$default_labels = clone $labels;

	/**
	 * Filters the labels of a specific taxonomy.
	 *
	 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
	 *
	 * @since 4.4.0
	 *
	 * @see get_taxonomy_labels() for the full list of taxonomy labels.
	 *
	 * @param object $labels Object with labels for the taxonomy as member variables.
	 */
	$labels = apply_filters( "taxonomy_labels_{$taxonomy}", $labels );

	// Ensure that the filtered labels contain all required default values.
	$labels = (object) array_merge( (array) $default_labels, (array) $labels );

	return $labels;
}

/**
 * Add an already registered taxonomy to an object type.
 *
 * @since 3.0.0
 *
 * @global array $wp_taxonomies The registered taxonomies.
 *
 * @param string $taxonomy    Name of taxonomy object.
 * @param string $object_type Name of the object type.
 * @return bool True if successful, false if not.
 */
function register_taxonomy_for_object_type( $taxonomy, $object_type) {
	global $wp_taxonomies;

	if ( !isset($wp_taxonomies[$taxonomy]) )
		return false;

	if ( ! get_post_type_object($object_type) )
		return false;

	if ( ! in_array( $object_type, $wp_taxonomies[$taxonomy]->object_type ) )
		$wp_taxonomies[$taxonomy]->object_type[] = $object_type;

	// Filter out empties.
	$wp_taxonomies[ $taxonomy ]->object_type = array_filter( $wp_taxonomies[ $taxonomy ]->object_type );

	return true;
}

/**
 * Remove an already registered taxonomy from an object type.
 *
 * @since 3.7.0
 *
 * @global array $wp_taxonomies The registered taxonomies.
 *
 * @param string $taxonomy    Name of taxonomy object.
 * @param string $object_type Name of the object type.
 * @return bool True if successful, false if not.
 */
function unregister_taxonomy_for_object_type( $taxonomy, $object_type ) {
	global $wp_taxonomies;

	if ( ! isset( $wp_taxonomies[ $taxonomy ] ) )
		return false;

	if ( ! get_post_type_object( $object_type ) )
		return false;

	$key = array_search( $object_type, $wp_taxonomies[ $taxonomy ]->object_type, true );
	if ( false === $key )
		return false;

	unset( $wp_taxonomies[ $taxonomy ]->object_type[ $key ] );
	return true;
}

//
// Term API
//

/**
 * Retrieve object_ids of valid taxonomy and term.
 *
 * The strings of $taxonomies must exist before this function will continue. On
 * failure of finding a valid taxonomy, it will return an WP_Error class, kind
 * of like Exceptions in PHP 5, except you can't catch them. Even so, you can
 * still test for the WP_Error class and get the error message.
 *
 * The $terms aren't checked the same as $taxonomies, but still need to exist
 * for $object_ids to be returned.
 *
 * It is possible to change the order that object_ids is returned by either
 * using PHP sort family functions or using the database by using $args with
 * either ASC or DESC array. The value should be in the key named 'order'.
 *
 * @since 2.3.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int|array    $term_ids   Term id or array of term ids of terms that will be used.
 * @param string|array $taxonomies String of taxonomy name or Array of string values of taxonomy names.
 * @param array|string $args       Change the order of the object_ids, either ASC or DESC.
 * @return WP_Error|array If the taxonomy does not exist, then WP_Error will be returned. On success.
 *	the array can be empty meaning that there are no $object_ids found or it will return the $object_ids found.
 */
function get_objects_in_term( $term_ids, $taxonomies, $args = array() ) {
	global $wpdb;

	if ( ! is_array( $term_ids ) ) {
		$term_ids = array( $term_ids );
	}
	if ( ! is_array( $taxonomies ) ) {
		$taxonomies = array( $taxonomies );
	}
	foreach ( (array) $taxonomies as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
		}
	}

	$defaults = array( 'order' => 'ASC' );
	$args = wp_parse_args( $args, $defaults );

	$order = ( 'desc' == strtolower( $args['order'] ) ) ? 'DESC' : 'ASC';

	$term_ids = array_map('intval', $term_ids );

	$taxonomies = "'" . implode( "', '", array_map( 'esc_sql', $taxonomies ) ) . "'";
	$term_ids = "'" . implode( "', '", $term_ids ) . "'";

	$sql = "SELECT tr.object_id FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy IN ($taxonomies) AND tt.term_id IN ($term_ids) ORDER BY tr.object_id $order";

	$last_changed = wp_cache_get_last_changed( 'terms' );
	$cache_key = 'get_objects_in_term:' . md5( $sql ) . ":$last_changed";
	$cache = wp_cache_get( $cache_key, 'terms' );
	if ( false === $cache ) {
		$object_ids = $wpdb->get_col( $sql );
		wp_cache_set( $cache_key, $object_ids, 'terms' );
	} else {
		$object_ids = (array) $cache;
	}

	if ( ! $object_ids ){
		return array();
	}
	return $object_ids;
}

/**
 * Given a taxonomy query, generates SQL to be appended to a main query.
 *
 * @since 3.1.0
 *
 * @see WP_Tax_Query
 *
 * @param array  $tax_query         A compact tax query
 * @param string $primary_table
 * @param string $primary_id_column
 * @return array
 */
function get_tax_sql( $tax_query, $primary_table, $primary_id_column ) {
	$tax_query_obj = new WP_Tax_Query( $tax_query );
	return $tax_query_obj->get_sql( $primary_table, $primary_id_column );
}

/**
 * Get all Term data from database by Term ID.
 *
 * The usage of the get_term function is to apply filters to a term object. It
 * is possible to get a term object from the database before applying the
 * filters.
 *
 * $term ID must be part of $taxonomy, to get from the database. Failure, might
 * be able to be captured by the hooks. Failure would be the same value as $wpdb
 * returns for the get_row method.
 *
 * There are two hooks, one is specifically for each term, named 'get_term', and
 * the second is for the taxonomy name, 'term_$taxonomy'. Both hooks gets the
 * term object, and the taxonomy name as parameters. Both hooks are expected to
 * return a Term object.
 *
 * {@see 'get_term'} hook - Takes two parameters the term Object and the taxonomy name.
 * Must return term object. Used in get_term() as a catch-all filter for every
 * $term.
 *
 * {@see 'get_$taxonomy'} hook - Takes two parameters the term Object and the taxonomy
 * name. Must return term object. $taxonomy will be the taxonomy name, so for
 * example, if 'category', it would be 'get_category' as the filter name. Useful
 * for custom taxonomies or plugging into default taxonomies.
 *
 * @todo Better formatting for DocBlock
 *
 * @since 2.3.0
 * @since 4.4.0 Converted to return a WP_Term object if `$output` is `OBJECT`.
 *              The `$taxonomy` parameter was made optional.
 *
 * @see sanitize_term_field() The $context param lists the available values for get_term_by() $filter param.
 *
 * @param int|WP_Term|object $term If integer, term data will be fetched from the database, or from the cache if
 *                                 available. If stdClass object (as in the results of a database query), will apply
 *                                 filters and return a `WP_Term` object corresponding to the `$term` data. If `WP_Term`,
 *                                 will return `$term`.
 * @param string     $taxonomy Optional. Taxonomy name that $term is part of.
 * @param string     $output   Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                             a WP_Term object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @param string     $filter   Optional, default is raw or no WordPress defined filter will applied.
 * @return array|WP_Term|WP_Error|null Object of the type specified by `$output` on success. When `$output` is 'OBJECT',
 *                                     a WP_Term instance is returned. If taxonomy does not exist, a WP_Error is
 *                                     returned. Returns null for miscellaneous failure.
 */
function get_term( $term, $taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
	if ( empty( $term ) ) {
		return new WP_Error( 'invalid_term', __( 'Empty Term.' ) );
	}

	if ( $taxonomy && ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
	}

	if ( $term instanceof WP_Term ) {
		$_term = $term;
	} elseif ( is_object( $term ) ) {
		if ( empty( $term->filter ) || 'raw' === $term->filter ) {
			$_term = sanitize_term( $term, $taxonomy, 'raw' );
			$_term = new WP_Term( $_term );
		} else {
			$_term = WP_Term::get_instance( $term->term_id );
		}
	} else {
		$_term = WP_Term::get_instance( $term, $taxonomy );
	}

	if ( is_wp_error( $_term ) ) {
		return $_term;
	} elseif ( ! $_term ) {
		return null;
	}

	/**
	 * Filters a term.
	 *
	 * @since 2.3.0
	 * @since 4.4.0 `$_term` can now also be a WP_Term object.
	 *
	 * @param int|WP_Term $_term    Term object or ID.
	 * @param string      $taxonomy The taxonomy slug.
	 */
	$_term = apply_filters( 'get_term', $_term, $taxonomy );

	/**
	 * Filters a taxonomy.
	 *
	 * The dynamic portion of the filter name, `$taxonomy`, refers
	 * to the taxonomy slug.
	 *
	 * @since 2.3.0
	 * @since 4.4.0 `$_term` can now also be a WP_Term object.
	 *
	 * @param int|WP_Term $_term    Term object or ID.
	 * @param string      $taxonomy The taxonomy slug.
	 */
	$_term = apply_filters( "get_{$taxonomy}", $_term, $taxonomy );

	// Bail if a filter callback has changed the type of the `$_term` object.
	if ( ! ( $_term instanceof WP_Term ) ) {
		return $_term;
	}

	// Sanitize term, according to the specified filter.
	$_term->filter( $filter );

	if ( $output == ARRAY_A ) {
		return $_term->to_array();
	} elseif ( $output == ARRAY_N ) {
		return array_values( $_term->to_array() );
	}

	return $_term;
}

/**
 * Get all Term data from database by Term field and data.
 *
 * Warning: $value is not escaped for 'name' $field. You must do it yourself, if
 * required.
 *
 * The default $field is 'id', therefore it is possible to also use null for
 * field, but not recommended that you do so.
 *
 * If $value does not exist, the return value will be false. If $taxonomy exists
 * and $field and $value combinations exist, the Term will be returned.
 *
 * This function will always return the first term that matches the `$field`-
 * `$value`-`$taxonomy` combination specified in the parameters. If your query
 * is likely to match more than one term (as is likely to be the case when
 * `$field` is 'name', for example), consider using get_terms() instead; that
 * way, you will get all matching terms, and can provide your own logic for
 * deciding which one was intended.
 *
 * @todo Better formatting for DocBlock.
 *
 * @since 2.3.0
 * @since 4.4.0 `$taxonomy` is optional if `$field` is 'term_taxonomy_id'. Converted to return
 *              a WP_Term object if `$output` is `OBJECT`.
 *
 * @see sanitize_term_field() The $context param lists the available values for get_term_by() $filter param.
 *
 * @param string     $field    Either 'slug', 'name', 'id' (term_id), or 'term_taxonomy_id'
 * @param string|int $value    Search for this term value
 * @param string     $taxonomy Taxonomy name. Optional, if `$field` is 'term_taxonomy_id'.
 * @param string     $output   Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
 *                             a WP_Term object, an associative array, or a numeric array, respectively. Default OBJECT.
 * @param string     $filter   Optional, default is raw or no WordPress defined filter will applied.
 * @return WP_Term|array|false WP_Term instance (or array) on success. Will return false if `$taxonomy` does not exist
 *                             or `$term` was not found.
 */
function get_term_by( $field, $value, $taxonomy = '', $output = OBJECT, $filter = 'raw' ) {

	// 'term_taxonomy_id' lookups don't require taxonomy checks.
	if ( 'term_taxonomy_id' !== $field && ! taxonomy_exists( $taxonomy ) ) {
		return false;
	}

	// No need to perform a query for empty 'slug' or 'name'.
	if ( 'slug' === $field || 'name' === $field ) {
		$value = (string) $value;

		if ( 0 === strlen( $value ) ) {
			return false;
		}
	}

	if ( 'id' === $field || 'term_id' === $field ) {
		$term = get_term( (int) $value, $taxonomy, $output, $filter );
		if ( is_wp_error( $term ) || null === $term ) {
			$term = false;
		}
		return $term;
	}

	$args = array(
		'get'                    => 'all',
		'number'                 => 1,
		'taxonomy'               => $taxonomy,
		'update_term_meta_cache' => false,
		'orderby'                => 'none',
		'suppress_filter'        => true,
	);

	switch ( $field ) {
		case 'slug' :
			$args['slug'] = $value;
			break;
		case 'name' :
			$args['name'] = $value;
			break;
		case 'term_taxonomy_id' :
			$args['term_taxonomy_id'] = $value;
			unset( $args[ 'taxonomy' ] );
			break;
		default :
			return false;
	}

	$terms = get_terms( $args );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return false;
	}

	$term = array_shift( $terms );

	// In the case of 'term_taxonomy_id', override the provided `$taxonomy` with whatever we find in the db.
	if ( 'term_taxonomy_id' === $field ) {
		$taxonomy = $term->taxonomy;
	}

	return get_term( $term, $taxonomy, $output, $filter );
}

/**
 * Merge all term children into a single array of their IDs.
 *
 * This recursive function will merge all of the children of $term into the same
 * array of term IDs. Only useful for taxonomies which are hierarchical.
 *
 * Will return an empty array if $term does not exist in $taxonomy.
 *
 * @since 2.3.0
 *
 * @param int    $term_id  ID of Term to get children.
 * @param string $taxonomy Taxonomy Name.
 * @return array|WP_Error List of Term IDs. WP_Error returned if `$taxonomy` does not exist.
 */
function get_term_children( $term_id, $taxonomy ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'invalid_taxonomy', __( 'Invalid taxonomy.' ) );
	}

	$term_id = intval( $term_id );

	$terms = _get_term_hierarchy($taxonomy);

	if ( ! isset($terms[$term_id]) )
		return array();

	$children = $terms[$term_id];

	foreach ( (array) $terms[$term_id] as $child ) {
		if ( $term_id == $child ) {
			continue;
		}

		if ( isset($terms[$child]) )
			$children = array_merge($children, get_term_children($child, $taxonomy));
	}

	return $children;
}

/**
 * Get sanitized Term field.
 *
 * The function is for contextual reasons and for simplicity of usage.
 *
 * @since 2.3.0
 * @since 4.4.0 The `$taxonomy` parameter was made optional. `$term` can also now accept a WP_Term object.
 *
 * @see sanitize_term_field()
 *
 * @param string      $field    Term field to fetch.
 * @param int|WP_Term $term     Term ID or object.
 * @param string      $taxonomy Optional. Taxonomy Name. Default empty.
 * @param string      $context  Optional, default is display. Look at sanitize_term_field() for available options.
 * @return string|int|null|WP_Error Will return an empty string if $term is not an object or if $field is not set in $term.
 */
function get_term_field( $field, $term, $taxonomy = '', $context = 'display' ) {
	$term = get_term( $term, $taxonomy );
	if ( is_wp_error($term) )
		return $term;

	if ( !is_object($term) )
		return '';

	if ( !isset($term->$field) )
		return '';

	return sanitize_term_field( $field, $term->$field, $term->term_id, $term->taxonomy, $context );
}

/**
 * Sanitizes Term for editing.
 *
 * Return value is sanitize_term() and usage is for sanitizing the term for
 * editing. Function is for contextual and simplicity.
 *
 * @since 2.3.0
 *
 * @param int|object $id       Term ID or object.
 * @param string     $taxonomy Taxonomy name.
 * @return string|int|null|WP_Error Will return empty string if $term is not an object.
 */
function get_term_to_edit( $id, $taxonomy ) {
	$term = get_term( $id, $taxonomy );

	if ( is_wp_error($term) )
		return $term;

	if ( !is_object($term) )
		return '';

	return sanitize_term($term, $taxonomy, 'edit');
}

/**
 * Retrieve the terms in a given taxonomy or list of taxonomies.
 *
 * You can fully inject any customizations to the query before it is sent, as
 * well as control the output with a filter.
 *
 * The {@see 'get_terms'} filter will be called when the cache has the term and will
 * pass the found term along with the array of $taxonomies and array of $args.
 * This filter is also called before the array of terms is passed and will pass
 * the array of terms, along with the $taxonomies and $args.
 *
 * The {@see 'list_terms_exclusions'} filter passes the compiled exclusions along with
 * the $args.
 *
 * The {@see 'get_terms_orderby'} filter passes the `ORDER BY` clause for the query
 * along with the $args array.
 *
 * Prior to 4.5.0, the first parameter of `get_terms()` was a taxonomy or list of taxonomies:
 *
 *     $terms = get_terms( 'post_tag', array(
 *         'hide_empty' => false,
 *     ) );
 *
 * Since 4.5.0, taxonomies should be passed via the 'taxonomy' argument in the `$args` array:
 *
 *     $terms = get_terms( array(
 *         'taxonomy' => 'post_tag',
 *         'hide_empty' => false,
 *     ) );
 *
 * @since 2.3.0
 * @since 4.2.0 Introduced 'name' and 'childless' parameters.
 * @since 4.4.0 Introduced the ability to pass 'term_id' as an alias of 'id' for the `orderby` parameter.
 *              Introduced the 'meta_query' and 'update_term_meta_cache' parameters. Converted to return
 *              a list of WP_Term objects.
 * @since 4.5.0 Changed the function signature so that the `$args` array can be provided as the first parameter.
 *              Introduced 'meta_key' and 'meta_value' parameters. Introduced the ability to order results by metadata.
 * @since 4.8.0 Introduced 'suppress_filter' parameter.
 *
 * @internal The `$deprecated` parameter is parsed for backward compatibility only.
 *
 * @param string|array $args       Optional. Array or string of arguments. See WP_Term_Query::__construct()
 *                                 for information on accepted arguments. Default empty.
 * @param array        $deprecated Argument array, when using the legacy function parameter format. If present, this
 *                                 parameter will be interpreted as `$args`, and the first function parameter will
 *                                 be parsed as a taxonomy or array of taxonomies.
 * @return array|int|WP_Error List of WP_Term instances and their children. Will return WP_Error, if any of $taxonomies
 *                            do not exist.
 */
function get_terms( $args = array(), $deprecated = '' ) {
	$term_query = new WP_Term_Query();

	$defaults = array(
		'suppress_filter' => false,
	);

	/*
	 * Legacy argument format ($taxonomy, $args) takes precedence.
	 *
	 * We detect legacy argument format by checking if
	 * (a) a second non-empty parameter is passed, or
	 * (b) the first parameter shares no keys with the default array 