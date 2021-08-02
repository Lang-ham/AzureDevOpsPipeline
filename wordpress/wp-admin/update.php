<?php
/**
 * Update/Install Plugin/Theme administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

if ( ! defined( 'IFRAME_REQUEST' ) && isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'update-selected', 'activate-plugin', 'update-selected-themes' ) ) )
	define( 'IFRAME_REQUEST', true );

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

include_once( 