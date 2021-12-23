<?php
if ( ! class_exists( 'SimplePie', false ) ) :

// Load classes we will need.
require ABSPATH . WPINC . '/SimplePie/Misc.php';
require ABSPATH . WPINC . '/SimplePie/Cache.php';
require ABSPATH . WPINC . '/SimplePie/File.php';
require ABSPATH . WPINC . '/SimplePie/Sanitize.php';
require ABSPATH . WPINC . '/SimplePie/Registry.php';
require ABSPATH . WPINC . '/SimplePie/IRI.php';
require ABSPATH . WPINC . '/SimplePie/Locator.php';
require ABSPATH . WPINC . '/SimplePie/Content/Type/Sniffer.php';
require ABSPATH . WPINC . '/SimplePie/XML/Declaration/Parser.php';
require ABSPATH . WPINC . '/SimplePie/Parser.php';
require ABSPATH . WPINC . '/SimplePie/Item.php';
require ABSPATH . WPINC . '/SimplePie/Parse/Date.php';
require ABSPATH . WPINC . '/SimplePie/Author.php';

/**
 * WordPress autoloader for SimplePie.
 *
 * @since 3.5.0
 */
function wp_simplepie_autoload( $class ) {
	if ( 0 !== strpos( $class, 'SimplePie_' ) )
		return;

	$file = ABSPATH . WPINC . '/' . str_replace( '_', '/', $class ) . '.php';
	include( $file );
}

/**
 * We autoload classes we may not need.
 */
spl_autoload_register( 'wp_simplepie_autoload' );

/**
 * SimplePie
 *
 * A PHP-Based RSS and Atom Feed Framework.
 * Takes the hard work out of managing a complete RSS/Atom solution.
 *
 * Copyright (c) 2004-2012, Ryan Parman, Geoffrey Sneddon, Ryan McCue, and contributors
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 *
 * 	* Redistributions of source code must retain the above copyright notice, this list of
 * 	  conditions and the following disclaimer.
 *
 * 	* Redistributions in binary form must reproduce the above copyright notice, this list
 * 	  of conditions and the following disclaimer in the documentation and/or other materials
 * 	  provided with the distribution.
 *
 * 	* Neither the name of the SimplePie Team nor the names of its contributors may be used
 * 	  to endorse or promote products derived from this software without specific prior
 * 	  w