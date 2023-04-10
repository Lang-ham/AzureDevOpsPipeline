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
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	do_action( "deleted_{$meta_type}_meta", $meta_ids, $object_id, $meta_key, $_meta_value );

	// Old-style action.
	if ( 'post' == $meta_type ) {
		/**
		 * Fires immediately after deleting metadata for a post.
		 *
		 * @since 2.9.0
		 *
		 * @param array $meta_ids An array of deleted post metadata entry IDs.
		 */
		do_action( 'deleted_postmeta', $meta_ids );
	}

	return true;
}

/**
 * Retrieve metadata for the specified object.
 *
 * @since 2.9.0
 *
 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
 * @param int    $object_id ID of the object metadata is for
 * @param string $meta_key  Optional. Metadata key. If not specified, retrieve all metadata for
 * 		                    the specified object.
 * @param bool   $single    Optional, default is false.
 *                          If true, return only the first value of the specified meta_key.
 *                          This parameter has no effect if meta_key is not specified.
 * @return mixed Single metadata value, or array of values
 */
function get_metadata($meta_type, $object_id, $meta_key = '', $single = false) {
	if ( ! $meta_type || ! is_numeric( $object_id ) ) {
		return false;
	}

	$object_id = absint( $object_id );
	if ( ! $object_id ) {
		return false;
	}

	/**
	 * Filters whether to retrieve metadata of a specific type.
	 *
	 * The dynamic portion of the hook, `$meta_type`, refers to the meta
	 * object type (comment, post, or user). Returning a non-null value
	 * will effectively short-circuit the function.
	 *
	 * @since 3.1.0
	 *
	 * @param null|array|string $value     The value get_metadata() should return - a single metadata value,
	 *                                     or an array of values.
	 * @param int               $object_id Object ID.
	 * @param string            $meta_key  Meta key.
	 * @param bool              $single    Whether to return only the first value of the specified $meta_key.
	 */
	$check = apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, $single );
	if ( null !== $check ) {
		if ( $single && is_array( $check ) )
			return $check[0];
		else
			return $check;
	}

	$meta_cache = wp_cache_get($object_id, $meta_type . '_meta');

	if ( !$meta_cache ) {
		$meta_cache = update_meta_cache( $meta_type, array( $object_id ) );
		$meta_cache = $meta_cache[$object_id];
	}

	if ( ! $meta_key ) {
		return $meta_cache;
	}

	if ( isset($meta_cache[$meta_key]) ) {
		if ( $single )
			return maybe_unserialize( $meta_cache[$meta_key][0] );
		else
			return array_map('maybe_unserialize', $meta_cache[$meta_key]);
	}

	if ($single)
		return '';
	else
		return array();
}

/**
 * Determine if a meta key is set for a given object
 *
 * @since 3.3.0
 *
 * @param string $meta_type Type of object metadata is for (e.g., comment, post, or user)
 * @param int    $object_id ID of the object metadata is for
 * @param string $meta_key  Metadata key.
 * @return bool True of the key is set, false if not.
 */
function metadata_exists( $meta_type, $object_id, $meta_key ) {
	if ( ! $meta_type || ! is_numeric( $object_id ) ) {
		return false;
	}

	$object_id = absint( $object_id );
	if ( ! $object_id ) {
		return false;
	}

	/** This filter is documented in wp-includes/meta.php */
	$check = apply_filters( "get_{$meta_type}_metadata", null, $object_id, $meta_key, true );
	if ( null !== $check )
		return (bool) $check;

	$meta_cache = wp_cache_get( $object_id, $meta_type . '_meta' );

	if ( !$meta_cache ) {
		$meta_cache = update_meta_cache( $meta_type, array( $object_id ) );
		$meta_cache = $meta_cache[$object_id];
	}

	if ( isset( $meta_cache[ $meta_key ] ) )
		return true;

	return false;
}

/**
 * Get meta data by meta ID
 *
 * @since 3.3.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $meta_type Type of object metadata is for (e.g., comment, post, term, or user).
 * @param int    $meta_id   ID for a specific meta row
 * @return object|false Meta object or false.
 */
function get_metadata_by_mid( $meta_type, $meta_id ) {
	global $wpdb;

	if ( ! $meta_type || ! is_numeric( $meta_id ) || floor( $meta_id ) != $meta_id ) {
		return false;
	}

	$meta_id = intval( $meta_id );
	if ( $meta_id <= 0 ) {
		return false;
	}

	$table = _get_meta_table( $meta_type );
	if ( ! $table ) {
		return false;
	}

	$id_column = ( 'user' == $meta_type ) ? 'umeta_id' : 'meta_id';

	$meta = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE $id_column = %d", $meta_id ) );

	if ( empty( $meta ) )
		return false;

	if ( isset( $meta->meta_value ) )
		$meta->meta_value = maybe_unserialize( $meta->meta_value );

	return $meta;
}

/**
 * Update meta data by meta ID
 *
 * @since 3.3.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $meta_type  Type of object metadata is for (e.g., comment, post, or user)
 * @param int    $meta_id    ID for a specific meta row
 * @param string $meta_value Metadata value
 * @param string $meta_key   Optional, you can provide a meta key to update it
 * @return bool True on successful update, false on failure.
 */
function update_metadata_by_mid( $meta_type, $meta_id, $meta_value, $meta_key = false ) {
	global $wpdb;

	// Make sure everything is valid.
	if ( ! $meta_type || ! is_numeric( $meta_id ) || floor( $meta_id ) != $meta_id ) {
		return false;
	}

	$meta_id = intval( $meta_id );
	if ( $meta_id <= 0 ) {
		return false;
	}

	$table = _get_meta_table( $meta_type );
	if ( ! $table ) {
		return false;
	}

	$column = sanitize_key($meta_type . '_id');
	$id_column = 'user' == $meta_type ? 'umeta_id' : 'meta_id';

	// Fetch the meta and go on if it's found.
	if ( $meta = get_metadata_by_mid( $meta_type, $meta_id ) ) {
		$original_key = $meta->meta_key;
		$object_id = $meta->{$column};

		// If a new meta_key (last parameter) was specified, change the meta key,
		// otherwise use the original key in the update statement.
		if ( false === $meta_key ) {
			$meta_key = $original_key;
		} elseif ( ! is_string( $meta_key ) ) {
			return false;
		}

		// Sanitize the meta
		$_meta_value = $meta_value;
		$meta_value = sanitize_meta( $meta_key, $meta_value, $meta_type );
		$meta_value = maybe_serialize( $meta_value );

		// Format the data query arguments.
		$data = array(
			'meta_key' => $meta_key,
			'meta_value' => $meta_value
		);

		// Format the where query arguments.
		$where = array();
		$where[$id_column] = $meta_id;

		/** This action is documented in wp-includes/meta.php */
		do_action( "update_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );

		if ( 'post' == $meta_type ) {
			/** This action is documented in wp-includes/meta.php */
			do_action( 'update_postmeta', $meta_id, $object_id, $meta_key, $meta_value );
		}

		// Run the update query, all fields in $data are %s, $where is a %d.
		$result = $wpdb->update( $table, $data, $where, '%s', '%d' );
		if ( ! $result )
			return false;

		// Clear the caches.
		wp_cache_delete($object_id, $meta_type . '_meta');

		/** This action is documented in wp-includes/meta.php */
		do_action( "updated_{$meta_type}_meta", $meta_id, $object_id, $meta_key, $_meta_value );

		if ( 'post' == $meta_type ) {
			/** This action is documented in wp-includes/meta.php */
			do_action( 'updated_postmeta', $meta_id, $object_id, $meta_key, $meta_value );
		}

		return true;
	}

	// And if the meta was not found.
	return false;
}

/**
 * Delete meta data by meta ID
 *
 * @since 3.3.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $meta_type Type of object metadata is for (e.g., comment, post, term, or user).
 * @param int    $meta_id   ID for a specific meta row
 * @return bool True on successful delete, false on failure.
 */
function delete_metadata_by_mid( $meta_type, $meta_id ) {
	global $wpdb;

	// Make sure everything is valid.
	if ( ! $meta_type || ! is_numeric( $meta_id ) || floor( $meta_id ) != $meta_id ) {
		return false;
	}

	$meta_id = intval( $meta_id );
	if ( $meta_id <= 0 ) {
		return false;
	}

	$table = _get_meta_table( $meta_type );
	if ( ! $table ) {
		return false;
	}

	// object and id columns
	$column = sanitize_key($meta_type . '_id');
	$id_column = 'user' == $meta_type ? 'umeta_id' : 'meta_id';

	// Fetch the meta and go on if it's found.
	if ( $meta = get_metadata_by_mid( $meta_type, $meta_id ) ) {
		$object_id = $meta->{$column};

		/** This action is documented in wp-includes/meta.php */
		do_action( "delete_{$meta_type}_meta", (array) $meta_id, $object_id, $meta->meta_key, $meta->meta_value );

		// Old-style action.
		if ( 'post' == $meta_type || 'comment' == $meta_type ) {
			/**
			 * Fires immediately before deleting post or comment metadata of a specific type.
			 *
			 * The dynamic portion of the hook, `$meta_type`, refers to the meta
			 * object type (post or comment).
			 *
			 * @since 3.4.0
			 *
			 * @param int $meta_id ID of the metadata entry to delete.
			 */
			do_action( "delete_{$meta_type}meta", $meta_id );
		}

		// Run the query, will return true if deleted, false otherwise
		$result = (bool) $wpdb->delete( $table, array( $id_column => $meta_id ) );

		// Clear the caches.
		wp_cache_delete($object_id, $meta_type . '_meta');

		/** This action is documented in wp-includes/meta.php */
		do_action( "deleted_{$meta_type}_meta", (array) $meta_id, $object_id, $meta->meta_key, $meta->meta_value );

		// Old-style action.
		if ( 'post' == $meta_type || 'comment' == $meta_type ) {
			/**
			 * Fires immediately after deleting post or comment metadata of a specific type.
			 *
			 * The dynamic portion of the hook, `$meta_type`, refers to the meta
			 * object type (post or comment).
			 *
			 * @since 3.4.0
			 *
			 * @param int $meta_ids Deleted metadata entry ID.
			 */
			do_action( "deleted_{$meta_type}meta", $meta_id );
		}

		return $result;

	}

	// Meta id was not found.
	return false;
}

/**
 * Update the metadata cache for the specified objects.
 *
 * @since 2.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string    $meta_type  Type of object metadata is for (e.g., comment, post, or user)
 * @param int|array $object_ids Array or comma delimited list of object IDs to update cache for
 * @return array|false Metadata cache for the specified objects, or false on failure.
 */
function update_meta_cache($meta_type, $object_ids) {
	global $wpdb;

	if ( ! $meta_type || ! $object_ids ) {
		return false;
	}

	$table = _get_meta_table( $meta_type );
	if ( ! $table ) {
		return false