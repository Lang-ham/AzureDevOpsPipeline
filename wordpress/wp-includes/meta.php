<?php
/**
 * Core Metadata API
 *
 * Functions for retrieving and manipulating metadata of various WordPress object types. Metadata
 * for an object is a represented by a simple key-value pair. Objects may contain multiple
 * metadata entries that share the same key and differ only in their value.
 *
 * @package WordPress
 * @subpackage Meta
 */

/**
 * Add metadata for the specified object.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $meta_type  Type of object metadata is for (e.g., comment, post, or user)
 * @param int    $object_id  ID of the object metadata is for
 * @param string $meta_key   Metadata key
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @param bool   $unique     Optional, default is false.
 *                           Whether the specified metadata key should be unique for the object.
 *                           If true, and the object already has a value for the specified metadata key,
 *                           no change will be made.
 * @return int|false The meta ID on success, false on failure.
 */
function add_metadata($meta_type, $object_id, $meta_key, $meta_value, $unique = false) {
	global $wpdb;

	if ( ! $meta_type || ! $meta_key || ! is_numeric( $object_id ) ) {
		return false;
	}

	$object_id = absint( $object_id );
	if ( ! $object_id ) {
		return false;
	}

	$table = _get_meta_table( $meta_type );
	if ( ! $table ) {
		return false;
	}

	$column = sanitize_key($meta_type . '_id');

	// expected_slashed ($meta_key)
	$meta_key = wp_unslash($meta_key);
	$meta_value = wp_unslash($meta_value);
	$meta_value = sanitize_meta( $meta_key, $meta_value, $meta_type );

	/**
	 * Filters whether to add metadata of a specific type.
	 *
	 * The dynamic portion of the hook, `$meta_type`, refers to the meta
	 * object type (comment, post, or user). Returning a non-null value
	 * will effectively short-circuit the function.
	 *
	 * @since 3.1.0
	 *
	 * @param null|bool $check      Whether to allow adding metadata for the given type.
	 * @param int       $object_id  Object ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
	 * @param bool      $unique     Whether the specified meta key should be unique
	 *                              for the object. Optional. Default false.
	 */
	$check = apply_filters( "add_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $unique );
	if ( null !== $check )
		return $check;

	if ( $unique && $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM $table WHERE meta_key = %s AND $column = %d",
		$meta_key, $object_id ) ) )
		return false;

	$_meta_value = $meta_value;
	$meta_value = maybe_serialize( $meta_value );

	/**
	 * Fires immediately before meta of a specific type is added.
	 *
	 * The dynamic portion of the hook, `$meta_type`, refers to the meta
	 * object type (comment, post, or user).
	 *
	 * @since 3.1.0
	 *
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	do_action( "add_{$meta_type}_meta", $object_id, $meta_key, $_meta_value );

	$result = $wpdb->insert( $table, array(
		$column => $object_id,
		'meta_key' => $meta_key,
		'meta_value' => $meta_value
	) );

	if ( ! $result )
		return false;

	$mid = (int) $wpdb->insert_id;

	wp_cache_delete($object_id, $meta_type . '_meta');

	/**
	 * Fires immediately after meta of a specific type is added.
	 *
	 * The dynamic portion of the hook, `$meta_type`, refers to the meta
	 * object type (comment, post, or user).
	 *
	 * @since 2.9.0
	 *
	 * @param int    $mid        The meta ID after successful update.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	do_action( "added_{$meta_type}_meta", $mid, $object_id, $meta_key, $_meta_value );

	return $mid;
}

/**
 * Update metadata for the specified object. If no value already exists for the specified object
 * ID and metadata key, the metadata will be added.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $meta_type  Type of object metadata is for (e.g., comment, post, or user)
 * @param int    $object_id  ID of the object metadata is for
 * @param string $meta_key   Metadata key
 * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
 * @param mixed  $prev_value Optional. If specified, only update existing metadata entries with
 * 		                     the specified value. Otherwise, update all entries.
 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
 */
function update_metadata($meta_type, $object_id, $meta_key, $meta_value, $prev_value = '') {
	global $wpdb;

	if ( ! $meta_type || ! $meta_key || ! is_numeric( $object_id ) ) {
		return false;
	}

	$object_id = absint( $object_id );
	if ( ! $object_id ) {
		return false;
	}

	$table = _get_meta_table( $meta_type );
	if ( ! $table ) {
		return false;
	}

	$column = sanitize_key($meta_type . '_id');
	$id_column = 'user' == $meta_type ? 'umeta_id' : 'meta_id';

	// expected_slashed ($meta_key)
	$raw_meta_key = $meta_key;
	$meta_key = wp_unslash($meta_key);
	$passed_value = $meta_value;
	$meta_value = wp_unslash($meta_value);
	$meta_value = sanitize_meta( $meta_key, $meta_value, $meta_type );

	/**
	 * Filters whether to update metadata of a specific type.
	 *
	 * The dynamic portion of the hook, `$meta_type`, refers to the meta
	 * object type (comment, post, or user). Returning a non-null value
	 * will effectively short-circuit the function.
	 *
	 * @since 3.1.0
	 *
	 * @param null|bool $check      Whether to allow updating metadata for the given type.
	 * @param int       $object_id  Object ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
	 * @param mixed     $prev_value Optional. If specified, only update existing
	 *                              metadata entries with the specified value.
	 *                              Otherwise, update all entries.
	 */
	$check = apply_filters( "update_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $prev_value );
	if ( null !== $check )
		return (bool) $check;

	// Compare existing value to new value if no prev value given and the key exists only once.
	if ( empty($prev_value) ) {
		$old_value = get_metadata($meta_type, $object_id, $meta_key);
		if ( count($old_value) == 1 ) {
			if ( $old_value[0] === $meta_value )
				return false;
		}
	}

	$meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $id_column FROM $table WHERE meta_key = %s AND $column = %d", $meta_key, $object_id ) );
	if ( empty( $meta_ids ) ) {
		return add_metadata( $meta_type, $object_id, $raw_meta_key, $passed_value );
	}

	$_meta_value = $meta_value;
	$meta_value = maybe_serialize( $meta_value );

	$data  = compact( 'meta_value' );
	$where = array( $column => $object_id, 'meta_key' => $meta_key );

	if ( !empty( $prev_value ) ) {
		$prev_value = maybe_serialize($prev_value);
		$where['meta_value'] = $prev_value;
	}

	foreach ( $meta_ids as $meta_id ) {
		/**
		 * Fires immediately before updating metadata of a specific type.
		 *
		 * The dynamic portion of the hook, `$meta_type`, refers to the meta
		 * object type (comment, post, or user).
		 *
		 * @since 2.9.0
		 *
		 * @param int    $meta_id    ID of the metadata entry to update.
		 * @param int    $object_id  Object ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 */
		do_action( "update_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );

		if ( 'post' == $meta_type ) {
			/**
			 * Fires immediately before updating a post's metadata.
			 *
			 * @since 2.9.0
			 *
			 * @param int    $meta_id    ID of metadata entry to update.
			 * @param int    $object_id  Object ID.
			 * @param string $meta_key   Meta key.
			 * @param mixed  $meta_value Meta value.
			 */
			do_action( 'update_postmeta', $meta_id, $object_id, $meta_key, $meta_value );
		}
	}

	$result = $wpdb->update( $table, $data, $where );
	if ( ! $result )
		return false;

	wp_cache_delete($object_id, $meta_type . '_meta');

	foreach ( $meta_ids as $meta_id ) {
		/**
		 * Fires immediately after updating metadata of a specific type.
		 *
		 * The dynamic portion of the hook, `$meta_type`, refers to the meta
		 * object type (comment, post, or user).
		 *
		 * @since 2.9.0
		 *
		 * @param int    $meta_id    ID of updated metadata entry.
		 * @param int    $object_id  Object ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 */
		do_action( "updated_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );

		if ( 'post' == $meta_type ) {
			/**
			 * Fires immediately after updating a post's metadata.
			 *
			 * @since 2.9.0
			 *
			 * @param int    $meta_id    ID of updated metadata entry.
			 * @param int    $object_id  Object ID.
			 * @param string $meta_key   Meta key.
			 * @param mixed  $meta_value Meta value.
			 */
			do_action( 'updated_postmeta', $meta_id, $object_id, $meta_key, $meta_value );
		}
	}

	return true;
}

/**
 * Delete metadata for the specified object.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $meta_type  Type of object metadata is for (e.g., comment, post, or user)
 * @param int    $object_id  ID of the object metadata is for
 * @param string $meta_key   Metadata key
 * @param mixed  $meta_value Optional. Metadata value. Must be serializable if non-scalar. If specified, only delete
 *                           metadata entries with this value. Otherwise, delete all entries with the specified meta_key.
 *                           Pass `null, `false`, or an empty string to skip this check. (For backward compatibility,
 *                           it is not possible to pass an empty string to delete those entries with an empty string
 *                           for a value.)
 * @param bool   $delete_all Optional, default is false. If true, delete matching metadata entries for all objects,
 *                           ignoring the specified object_id. Otherwise, only delete matching metadata entries for
 *                           the specified object_id.
 * @return bool True on successful delete, false on failure.
 */
function delete_metadata($meta_type, $object_id, $meta_key, $meta_value = '', $delete_all = false) {
	global $wpdb;

	if ( ! $meta_type || ! $meta_key || ! is_numeric( $object_id ) && ! $delete_all ) {
		return false;
	}

	$object_id = absint( $object_id );
	if ( ! $object_id && ! $delete_all ) {
		return false;
	}

	$table = _get_meta_table( $meta_type );
	if ( ! $table ) {
		return false;
	}

	$type_column = sanitize_key($meta_type . '_id');
	$id_column = 'user' == $meta_type ? 'umeta_id' : 'meta_id';
	// expected_slashed ($meta_key)
	$meta_key = wp_unslash($meta_key);
	$meta_value = wp_unslash($meta_value);

	/**
	 * Filters whether to delete metadata of a specific type.
	 *
	 * The dynamic portion of the hook, `$meta_type`, refers to the meta
	 * object type (comment, post, or user). Returning a non-null value
	 * will effectively short-circuit the function.
	 *
	 * @since 3.1.0
	 *
	 * @param null|bool $delete     Whether to allow metadata deletion of the given type.
	 * @param int       $object_id  Object ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
	 * @param bool      $delete_all Whether to delete the matching metadata entries
	 *                              for all objects, ignoring the specified $object_id.
	 *                              Default false.
	 */
	$check = apply_filters( "delete_{$meta_type}_metadata", null, $object_id, $meta_key, $meta_value, $delete_all );
	if ( null !== $check )
		return (bool) $check;

	$_meta_value = $meta_value;
	$meta_value = maybe_serialize( $meta_value );

	$query = $wpdb->prepare( "SELECT $id_column FROM $table WHERE meta_key = %s", $meta_key );

	if ( !$delete_all )
		$query .= $wpdb->prepare(" AND $type_column = %d", $object_id );

	if ( '' !== $meta_value && null !== $meta_value && false !== $meta_value )
		$query .= $wpdb->prepare(" AND meta_value = %s", $meta_value );

	$meta_ids = $wpdb->get_col( $query );
	if ( !count( $meta_ids ) )
		return false;

	if ( $delete_all ) {
		if ( '' !== $meta_value && null !== $meta_value && false !== $meta_value ) {
			$object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $type_column FROM $table WHERE meta_key = %s AND meta_value = %s", $meta_key, $meta_value ) );
		} else {
			$object_ids = $wpdb->get_col( $wpdb->prepare( "SELECT $type_column FROM $table WHERE meta_key = %s", $meta_key ) );
		}
	}

	/**
	 * Fires immediately before deleting metadata of a specific type.
	 *
	 * The dynamic portion of the hook, `$meta_type`, refers to the meta
	 * object type (comment, post, or user).
	 *
	 * @since 3.1.0
	 *
	 * @param array  $meta_ids   An array of metadata entry IDs to delete.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	do_action( "delete_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value );

	// Old-style action.
	if ( 'post' == $meta_type ) {
		/**
		 * Fires immediately before deleting metadata for a post.
		 *
		 * @since 2.9.0
		 *
		 * @param array $meta_ids An array of post metadata entry IDs to delete.
		 */
		do_action( 'delete_postmeta', $meta_ids );
	}

	$query = "DELETE FROM $table WHERE $id_column IN( " . implode( ',', $meta_ids ) . " )";

	$count = $wpdb->query($query);

	if ( !$count )
		return false;

	if ( $delete_all ) {
		foreach ( (array) $object_ids as $o_id ) {
			wp_cache_delete($o_id, $meta_type . '_meta');
		}
	} else {
		wp_cache_delete($object_id, $meta_type . '_meta');
	}

	/**
	 * Fires immediately after deleting metadata of a specific type.
	 *
	 * The dynamic portion of the hook name, `$meta_type`, refers to the meta
	 * object type (comment, post, or user).
	 *
	 * @since 2.9.0
	 *
	 * @param array  $meta_ids   An array of deleted metadata entry IDs.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key