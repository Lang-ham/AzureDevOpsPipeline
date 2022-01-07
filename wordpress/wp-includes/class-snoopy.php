<?php

/**
 * Deprecated. Use WP_HTTP (http.php) instead.
 */
_deprecated_file( basename( __FILE__ ), '3.0.0', WPINC . '/http.php' );

if ( ! class_exists( 'Snoopy', false ) ) :
/*************************************************

Snoopy - the PHP net client
Author: Monte Ohrt <monte@ispi.net>
Copyright (c): 1999-2008 New Digital Group, all rights reserved
Version: 1.2.4

 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

You may contact the author of Snoopy by e-mail at:
monte@ohrt.com

The latest version of Snoopy can be obtained from:
http://snoopy.sourceforge.net/

*************************************************/

class Snoopy
{
	/**** Public variables ****/

	/* user definable vars */

	var $host			=	"www.php.net";		// host name we are connecting to
	var $port			=	80;					// port we are connecting to
	var $proxy_host		=	"";					// proxy host to use
	var $proxy_port		=	"";					// proxy port to use
	var $proxy_user		=	"";					// proxy user to use
	var $proxy_pass		=	"";					// proxy password to use

	var $agent			=	"Snoopy v1.2.4";	// agent we masquerade as
	var	$referer		=	"";					// referer info to pass
	var $cookies		=	array();			/