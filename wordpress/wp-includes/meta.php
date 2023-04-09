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
	 * object type (comment, p