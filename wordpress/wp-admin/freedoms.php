<?php
/**
 * Your Rights administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

$title = __( 'Freedoms' );

list( $display_version ) = explode( '-', get_bloginfo( 'version' ) );

include( ABSPATH . 'wp-admin/admin-header.php' );
?>
<div class="wrap about-wrap full-width-layout">

<h1><?php printf( __( 'Welcome to WordPress %s' ), $display_version ); ?></h1>

<p class="about-text"><?php printf( __( 'Thank you for updating to the latest version! WordPress %s will smooth your design workflow and keep you safe from coding errors.' ), $display_version ); ?></p>

<div class="wp-badge"><?php printf( __( 'Version %s' ), $display_version ); ?></div>

<h2 class="nav-tab-wrapper wp-clearfix">
	<a href="about.php" class="nav-tab"><?php _e( 'What&#8217;s New' ); ?></a>
	<a href="credits.php" class="nav-tab"><?php _e( 'Credits' ); ?></a>
	<a href="freedoms.php" class="nav-tab nav-tab-active"><?php _e( 'Freedoms' ); ?></a>
	<a href="privacy.php" class="nav-tab"><?php _e( 'Privacy' ); ?></a>
</h2>

<div class="about-wrap-content">
	<p class="about-description"><?php printf( __( 'WordPress is Free and open source software, built by a distributed community of mostly volunteer developers from around the world. WordPress comes with some awesome, worldview-changing rights courtesy of its <a href="%s">license</a>, the GPL.' ), 'https://wordpress.org/about/license/' ); ?></p>

	<ol start="0">
		<li><p><?php _e( 'You have the freedom to run the program, for any purpose.' ); ?></p></li>
		<li><p><?php _e( 'You have access to the source code, the freedom to study how the program works, and the freedom to change it to make it do what you wish.' ); ?></p></li>
		<li><p><?php _e( 'You have the freedom to redistribute copies of the original program so you can help your neighbor.' ); ?></p></li>
		<li><p><?php _e( 'You have the freedom to distribute copies of your modified versions to others. By doing this you can give the whole community a chance to benefit from your changes.' ); ?></p></li>
	</ol>

	<p><?php printf( __( 'WordPress grows when people like you tell their friends about it, and the thousands of businesses and services that are built on and around WordPress share that fact with their users. We&#8217;re flattered every time someone spreads the good word, just make sure to <a href="%s">check out our tradema