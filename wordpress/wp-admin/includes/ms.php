<?php
/**
 * Multisite administration functions.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

/**
 * Determine if uploaded file exceeds space quota.
 *
 * @since 3.0.0
 *
 * @param array $file $_FILES array for a given file.
 * @return array $_FILES array with 'error' key set if file exceeds quota. 'error' is empty otherwise.
 */
function check_upload_size( $file ) {
	if ( get_site_option( 'upload_space_check_disabled' ) )
		return $file;

	if ( $file['error'] != '0' ) // there's already an error
		return $file;

	if ( defined( 'WP_IMPORTING' ) )
		return $file;

	$space_left = get_upload_space_available();

	$file_size = filesize( $file['tmp_name'] );
	if ( $space_left < $file_size ) {
		/* translators: 1: Required disk space in kilobytes */
		$file['error'] = sprintf( __( 'Not enough space to upload. %1$s KB needed.' ), number_format( ( $file_size - $space_left ) / KB_IN_BYTES ) );
	}

	if ( $file_size > ( KB_IN_BYTES * get_site_option( 'fileupload_maxk', 1500 ) ) ) {
		/* translators: 1: Maximum allowed file size in kilobytes */
		$file['error'] = sprintf( __( 'This file is too big. Files must be less than %1$s KB in size.' ), get_site_option( 'fileupload_maxk', 1500 ) );
	}

	if ( upload_is_user_over_quota( false ) ) {
		$file['error'] = __( 'You have used your space quota. Please delete files before uploading.' );
	}

	if ( $file['error'] != '0' && ! isset( $_POST['html-upload'] ) && ! wp_doing_ajax() ) {
		wp_die( $file['error'] . ' <a href="javascript:history.go(-1)">' . __( 'Back' ) . '</a>' );
	}

	return $file;
}

/**
 * Delete a site.
 *
 * @since 3.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int  $blog_id Site ID.
 * @param bool $drop    True if site's database tables should be dropped. Default is false.
 */
function wpmu_delete_blog( $blog_id, $drop = false ) {
	global $wpdb;

	$switch = false;
	if ( get_current_blog_id() != $blog_id ) {
		$switch = true;
		switch_to_blog( $blog_id );
	}

	$blog = get_site( $blog_id );
	/**
	 * Fires before a site is deleted.
	 *
	 * @since MU (3.0.0)
	 *
	 * @param int  $blog_id The site ID.
	 * @param bool $drop    True if site's table should be dropped. Default is false.
	 */
	do_action( 'delete_blog', $blog_id, $drop );

	$users = get_users( array( 'blog_id' => $blog_id, 'fields' => 'ids' ) );

	// Remove users from this blog.
	if ( ! empty( $users ) ) {
		foreach ( $users as $user_id ) {
			remove_user_from_blog( $user_id, $blog_id );
		}
	}

	update_blog_status( $blog_id, 'deleted', 1 );

	$current_network = get_network();

	// If a full blog object is not available, do not destroy anything.
	if ( $drop && ! $blog ) {
		$drop = false;
	}

	// Don't destroy the initial, main, or root blog.
	if ( $drop && ( 1 == $blog_id || is_main_site( $blog_id ) || ( $blog->path == $current_network->path && $blog->domain == $current_network->domain ) ) ) {
		$drop = false;
	}

	$upload_path = trim( get_option( 'upload_path' ) );

	// If ms_files_rewriting is enabled and upload_path is empty, wp_upload_dir is not reliable.
	if ( $drop && get_site_option( 'ms_files_rewriting' ) && empty( $upload_path ) ) {
		$drop = false;
	}

	if ( $drop ) {
		$uploads = wp_get_uploa