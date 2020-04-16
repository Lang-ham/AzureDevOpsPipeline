<?php
/**
 * WordPress Generic Request (POST/GET) Handler
 *
 * Intended for form submission handling in themes and plugins.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** We are located in WordPress Administration Screens */
if ( ! defined( 'WP_ADMIN' ) ) {
	define( 'WP_ADMIN', true );
}

if ( defined('ABSPATH') )
	require_once(ABSPATH . 'wp-load.php');
else
	require_once( dirname( dirname( __FILE__ ) ) . '/wp-load.php' );

/** Allow for cross-domain requests (from the front end). */
send_origin_headers();

require_once(ABSPATH . 'wp-admin/includes/admin.php');

nocache_headers();

/** This action is documented in wp-admin/admin.php */
do_action( 'admin_init' );

$action = empty( $_REQUEST['action'] ) ? '' : $_REQUEST['action'];

if ( ! wp_validate_auth_cookie() ) {
	if ( empty( $action ) ) {
		/**
		 * Fires on a non-authenticated admin post request where no action was supplied.
		 *