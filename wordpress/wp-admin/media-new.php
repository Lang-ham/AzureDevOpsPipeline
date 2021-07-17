<?php
/**
 * Manage media uploaded file.
 *
 * There are many filters in here for media. Plugins can extend functionality
 * by hooking into the filters.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** Load WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

if (!current_user_can('upload_files'))
	wp_die(__('Sorry, you are not allowed to upload files.'));

wp_enqueue_script('plupload-handlers');

$post_id = 0;
if ( isset( $_REQUEST['post_id'] ) ) {
	