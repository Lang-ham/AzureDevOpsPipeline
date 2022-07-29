<?php
/**
 * Main WordPress API
 *
 * @package WordPress
 */

require( ABSPATH . WPINC . '/option.php' );

/**
 * Convert given date string into a different format.
 *
 * $format should be either a PHP date format string, e.g. 'U' for a Unix
 * timestamp, or 'G' for a Unix timestamp assuming that $date is GMT.
 *
 * If $translate is true then the given date and format string will
 * be passed to date_i18n() for translation.
 *
 * @since 0.71
 *
 * @param string $format    Format of the date to return.
 * @param string $date      Date string to convert.
 * @param bool   $translate Whether the return date should be translated. Default true.
 * @return string|int|bool Formatted date string or Unix timestamp. False if $date is empty.
 */
function mysql2date( $format, $date, $translate = true ) {
	if ( empty( $date ) )
		return false;

	if ( 'G' == $format )
		return strtotime( $date . ' +0000' );

	$i = strtotime( $date );

	if ( 'U' == $format )
		return $i;

	if ( $translate )
		return date_i18n( $format, $i );
	else
		return date( $format, $i );
}

/**
 * Retrieve the current time based on specified type.
 *
 * The 'mysql' type will return the time in the format for MySQL DATETIME field.
 * The 'timestamp' type will return the current timestamp.
 * Other strings will be interpreted as PHP date formats (e.g. 'Y-m-d').
 *
 * If $gmt is set to either '1' or 'true', then both types will use GMT time.
 * if $gmt is false, the output is adjusted with the GMT offset in the WordPress option.
 *
 * @since 1.0.0
 *
 * @param string   $type Type of time to retrieve. Accepts 'mysql', 'timestamp', or PHP date
 *                       format string (e.g. 'Y-m-d').
 * @param int|bool $gmt  Optional. Whether to use GMT timezone. Default false.
 * @return int|string Integer if $type is 'timestamp', string otherwise.
 */
function current_time( $type, $gmt = 0 ) {
	switch ( $type ) {
		case 'mysql':
			return ( $gmt ) ? gmdate( 'Y-m-d H:i:s' ) : gmdate( 'Y-m-d H:i:s', ( time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );
		case 'timestamp':
			return ( $gmt ) ? time() : time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		default:
			return ( $gmt ) ? date( $type ) : date( $type, time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}
}

/**
 * Retrieve the date in localized format, based on timestamp.
 *
 * If the locale specifies the locale month and weekday, then the locale will
 * take over the format for the date. If it isn't, then the date format string
 * will be used instead.
 *
 * @since 0.71
 *
 * @global WP_Locale $wp_locale
 *
 * @param string   $dateformatstring Format to display the date.
 * @param bool|int $unixtimestamp    Optional. Unix timestamp. Default false.
 * @param bool     $gmt              Optional. Whether to use GMT timezone. Default false.
 *
 * @return string The date, translated if locale specifies it.
 */
function date_i18n( $dateformatstring, $unixtimestamp = false, $gmt = false ) {
	global $wp_locale;
	$i = $unixtimestamp;

	if ( false === $i ) {
		$i = current_time( 'timestamp', $gmt );
	}

	/*
	 * Store original value for language with untypical grammars.
	 * See https://core.trac.wordpress.org/ticket/9396
	 */
	$req_format = $dateformatstring;

	if ( ( !empty( $wp_locale->month ) ) && ( !empty( $wp_locale->weekday ) ) ) {
		$datemonth = $wp_locale->get_month( date( 'm', $i ) );
		$datemonth_abbrev = $wp_locale->get_month_abbrev( $datemonth );
		$dateweekday = $wp_locale->get_weekday( date( 'w', $i ) );
		$dateweekday_abbrev = $wp_locale->get_weekday_abbrev( $dateweekday );
		$datemeridiem = $wp_locale->get_meridiem( date( 'a', $i ) );
		$datemeridiem_capital = $wp_locale->get_meridiem( date( 'A', $i ) );
		$dateformatstring = ' '.$dateformatstring;
		$dateformatstring = preg_replace( "/([^\\\])D/", "\\1" . backslashit( $dateweekday_abbrev ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])F/", "\\1" . backslashit( $datemonth ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])l/", "\\1" . backslashit( $dateweekday ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])M/", "\\1" . backslashit( $datemonth_abbrev ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])a/", "\\1" . backslashit( $datemeridiem ), $dateformatstring );
		$dateformatstring = preg_replace( "/([^\\\])A/", "\\1" . backslashit( $datemeridiem_capital ), $dateformatstring );

		$dateformatstring = substr( $dateformatstring, 1, strlen( $dateformatstring ) -1 );
	}
	$timezone_formats = array( 'P', 'I', 'O', 'T', 'Z', 'e' );
	$timezone_formats_re = implode( '|', $timezone_formats );
	if ( preg_match( "/$timezone_formats_re/", $dateformatstring ) ) {
		$timezone_string = get_option( 'timezone_string' );
		if ( $timezone_string ) {
			$timezone_object = timezone_open( $timezone_string );
			$date_object = date_create( null, $timezone_object );
			foreach ( $timezone_formats as $timezone_format ) {
				if ( false !== strpos( $dateformatstring, $timezone_format ) ) {
					$formatted = date_format( $date_object, $timezone_format );
					$dateformatstring = ' '.$dateformatstring;
					$dateformatstring = preg_replace( "/([^\\\])$timezone_format/", "\\1" . backslashit( $formatted ), $dateformatstring );
					$dateformatstring = substr( $dateformatstring, 1, strlen( $dateformatstring ) -1 );
				}
			}
		}
	}
	$j = @date( $dateformatstring, $i );

	/**
	 * Filters the date formatted based on the locale.
	 *
	 * @since 2.8.0
	 *
	 * @param string $j          Formatted date string.
	 * @param string $req_format Format to display the date.
	 * @param int    $i          Unix timestamp.
	 * @param bool   $gmt        Whether to convert to GMT for time. Default false.
	 */
	$j = apply_filters( 'date_i18n', $j, $req_format, $i, $gmt );
	return $j;
}

/**
 * Determines if the date should be declined.
 *
 * If the locale specifies that month names require a genitive case in certain
 * formats (like 'j F Y'), the month name will be replaced with a correct form.
 *
 * @since 4.4.0
 *
 * @global WP_Locale $wp_locale
 *
 * @param string $date Formatted date string.
 * @return string The date, declined if locale specifies it.
 */
function wp_maybe_decline_date( $date ) {
	global $wp_locale;

	// i18n functions are not available in SHORTINIT mode
	if ( ! function_exists( '_x' ) ) {
		return $date;
	}

	/* translators: If months in your language require a genitive case,
	 * translate this to 'on'. Do not translate into your own language.
	 */
	if ( 'on' === _x( 'off', 'decline months names: on or off' ) ) {
		// Match a format like 'j F Y' or 'j. F'
		if ( @preg_match( '#^\d{1,2}\.? [^\d ]+#u', $date ) ) {
			$months          = $wp_locale->month;
			$months_genitive = $wp_locale->month_genitive;

			foreach ( $months as $key => $month ) {
				$months[ $key ] = '# ' . $month . '( |$)#u';
			}

			foreach ( $months_genitive as $key => $month ) {
				$months_genitive[ $key ] = ' ' . $month . '$1';
			}

			$date = preg_replace( $months, $months_genitive, $date );
		}
	}

	// Used for locale-specific rules
	$locale = get_locale();

	if ( 'ca' === $locale ) {
		// " de abril| de agost| de octubre..." -> " d'abril| d'agost| d'octubre..."
		$date = preg_replace( '# de ([ao])#i', " d'\\1", $date );
	}

	return $date;
}

/**
 * Convert float number to format based on the locale.
 *
 * @since 2.3.0
 *
 * @global WP_Locale $wp_locale
 *
 * @param float $number   The number to convert based on locale.
 * @param int   $decimals Optional. Precision of the number of decimal places. Default 0.
 * @return string Converted number in string format.
 */
function number_format_i18n( $number, $decimals = 0 ) {
	global $wp_locale;

	if ( isset( $wp_locale ) ) {
		$formatted = number_format( $number, absint( $decimals ), $wp_locale->number_format['decimal_point'], $wp_locale->number_format['thousands_sep'] );
	} else {
		$formatted = number_format( $number, absint( $decimals ) );
	}

	/**
	 * Filters the number formatted based on the locale.
	 *
	 * @since 2.8.0
	 * @since 4.9.0 The `$number` and `$decimals` arguments were added.
	 *
	 * @param string $formatted Converted number in string format.
	 * @param float  $number    The number to convert based on locale.
	 * @param int    $decimals  Precision of the number of decimal places.
	 */
	return apply_filters( 'number_format_i18n', $formatted, $number, $decimals );
}

/**
 * Convert number of bytes largest unit bytes will fit into.
 *
 * It is easier to read 1 KB than 1024 bytes and 1 MB than 1048576 bytes. Converts
 * number of bytes to human readable number by taking the number of that unit
 * that the bytes will go into it. Supports TB value.
 *
 * Please note that integers in PHP are limited to 32 bits, unless they are on
 * 64 bit architecture, then they have 64 bit size. If you need to place the
 * larger size then what PHP integer type will hold, then use a string. It will
 * be converted to a double, which should always have 64 bit length.
 *
 * Technically the correct unit names for powers of 1024 are KiB, MiB etc.
 *
 * @since 2.3.0
 *
 * @param int|string $bytes    Number of bytes. Note max integer size for integers.
 * @param int        $decimals Optional. Precision of number of decimal places. Default 0.
 * @return string|false False on failure. Number string on success.
 */
function size_format( $bytes, $decimals = 0 ) {
	$quant = array(
		'TB' => TB_IN_BYTES,
		'GB' => GB_IN_BYTES,
		'MB' => MB_IN_BYTES,
		'KB' => KB_IN_BYTES,
		'B'  => 1,
	);

	if ( 0 === $bytes ) {
		return number_format_i18n( 0, $decimals ) . ' B';
	}

	foreach ( $quant as $unit => $mag ) {
		if ( doubleval( $bytes ) >= $mag ) {
			return number_format_i18n( $bytes / $mag, $decimals ) . ' ' . $unit;
		}
	}

	return false;
}

/**
 * Get the week start and end from the datetime or date string from MySQL.
 *
 * @since 0.71
 *
 * @param string     $mysqlstring   Date or datetime field type from MySQL.
 * @param int|string $start_of_week Optional. Start of the week as an integer. Default empty string.
 * @return array Keys are 'start' and 'end'.
 */
function get_weekstartend( $mysqlstring, $start_of_week = '' ) {
	// MySQL string year.
	$my = substr( $mysqlstring, 0, 4 );

	// MySQL string month.
	$mm = substr( $mysqlstring, 8, 2 );

	// MySQL string day.
	$md = substr( $mysqlstring, 5, 2 );

	// The timestamp for MySQL string day.
	$day = mktime( 0, 0, 0, $md, $mm, $my );

	// The day of the week from the timestamp.
	$weekday = date( 'w', $day );

	if ( !is_numeric($start_of_week) )
		$start_of_week = get_option( 'start_of_week' );

	if ( $weekday < $start_of_week )
		$weekday += 7;

	// The most recent week start day on or before $day.
	$start = $day - DAY_IN_SECONDS * ( $weekday - $start_of_week );

	// $start + 1 week - 1 second.
	$end = $start + WEEK_IN_SECONDS - 1;
	return compact( 'start', 'end' );
}

/**
 * Unserialize value only if it was serialized.
 *
 * @since 2.0.0
 *
 * @param string $original Maybe unserialized original, if is needed.
 * @return mixed Unserialized data can be any type.
 */
function maybe_unserialize( $original ) {
	if ( is_serialized( $original ) ) // don't attempt to unserialize data that wasn't serialized going in
		return @unserialize( $original );
	return $original;
}

/**
 * Check value to find if it was serialized.
 *
 * If $data is not an string, then returned value will always be false.
 * Serialized data is always a string.
 *
 * @since 2.0.5
 *
 * @param string $data   Value to check to see if was serialized.
 * @param bool   $strict Optional. Whether to be strict about the end of the string. Default true.
 * @return bool False if not serialized and true if it was.
 */
function is_serialized( $data, $strict = true ) {
	// if it isn't a string, it isn't serialized.
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
 	if ( 'N;' == $data ) {
		return true;
	}
	if ( strlen( $data ) < 4 ) {
		return false;
	}
	if ( ':' !== $data[1] ) {
		return false;
	}
	if ( $strict ) {
		$lastc = substr( $data, -1 );
		if ( ';' !== $lastc && '}' !== $lastc ) {
			return false;
		}
	} else {
		$semicolon = strpos( $data, ';' );
		$brace     = strpos( $data, '}' );
		// Either ; or } must exist.
		if ( false === $semicolon && false === $brace )
			return false;
		// But neither must be in the first X characters.
		if ( false !== $semicolon && $semicolon < 3 )
			return false;
		if ( false !== $brace && $brace < 4 )
			return false;
	}
	$token = $data[0];
	switch ( $token ) {
		case 's' :
			if ( $strict ) {
				if ( '"' !== substr( $data, -2, 1 ) ) {
					return false;
				}
			} elseif ( false === strpos( $data, '"' ) ) {
				return false;
			}
			// or else fall through
		case 'a' :
		case 'O' :
			return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
		case 'b' :
		case 'i' :
		case 'd' :
			$end = $strict ? '$' : '';
			return (bool) preg_match( "/^{$token}:[0-9.E-]+;$end/", $data );
	}
	return false;
}

/**
 * Check whether serialized data is of string type.
 *
 * @since 2.0.5
 *
 * @param string $data Serialized data.
 * @return bool False if not a serialized string, true if it is.
 */
function is_serialized_string( $data ) {
	// if it isn't a string, it isn't a serialized string.
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
	if ( strlen( $data ) < 4 ) {
		return false;
	} elseif ( ':' !== $data[1] ) {
		return false;
	} elseif ( ';' !== substr( $data, -1 ) ) {
		return false;
	} elseif ( $data[0] !== 's' ) {
		return false;
	} elseif ( '"' !== substr( $data, -2, 1 ) ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Serialize data, if needed.
 *
 * @since 2.0.5
 *
 * @param string|array|object $data Data that might be serialized.
 * @return mixed A scalar data
 */
function maybe_serialize( $data ) {
	if ( is_array( $data ) || is_object( $data ) )
		return serialize( $data );

	// Double serialization is required for backward compatibility.
	// See https://core.trac.wordpress.org/ticket/12930
	// Also the world will end. See WP 3.6.1.
	if ( is_serialized( $data, false ) )
		return serialize( $data );

	return $data;
}

/**
 * Retrieve post title from XMLRPC XML.
 *
 * If the title element is not part of the XML, then the default post title from
 * the $post_default_title will be used instead.
 *
 * @since 0.71
 *
 * @global string $post_default_title Default XML-RPC post title.
 *
 * @param string $content XMLRPC XML Request content
 * @return string Post title
 */
function xmlrpc_getposttitle( $content ) {
	global $post_default_title;
	if ( preg_match( '/<title>(.+?)<\/title>/is', $content, $matchtitle ) ) {
		$post_title = $matchtitle[1];
	} else {
		$post_title = $post_default_title;
	}
	return $post_title;
}

/**
 * Retrieve the post category or categories from XMLRPC XML.
 *
 * If the category element is not found, then the default post category will be
 * used. The return type then would be what $post_default_category. If the
 * category is found, then it will always be an array.
 *
 * @since 0.71
 *
 * @global string $post_default_category Default XML-RPC post category.
 *
 * @param string $content XMLRPC XML Request content
 * @return string|array List of categories or category name.
 */
function xmlrpc_getpostcategory( $content ) {
	global $post_default_category;
	if ( preg_match( '/<category>(.+?)<\/category>/is', $content, $matchcat ) ) {
		$post_category = trim( $matchcat[1], ',' );
		$post_category = explode( ',', $post_category );
	} else {
		$post_category = $post_default_category;
	}
	return $post_category;
}

/**
 * XMLRPC XML content without title and category elements.
 *
 * @since 0.71
 *
 * @param string $content XML-RPC XML Request content.
 * @return string XMLRPC XML Request content without title and category elements.
 */
function xmlrpc_removepostdata( $content ) {
	$content = preg_replace( '/<title>(.+?)<\/title>/si', '', $content );
	$content = preg_replace( '/<category>(.+?)<\/category>/si', '', $content );
	$content = trim( $content );
	return $content;
}

/**
 * Use RegEx to extract URLs from arbitrary content.
 *
 * @since 3.7.0
 *
 * @param string $content Content to extract URLs from.
 * @return array URLs found in passed string.
 */
function wp_extract_urls( $content ) {
	preg_match_all(
		"#([\"']?)("
			. "(?:([\w-]+:)?//?)"
			. "[^\s()<>]+"
			. "[.]"
			. "(?:"
				. "\([\w\d]+\)|"
				. "(?:"
					. "[^`!()\[\]{};:'\".,<>«»“”‘’\s]|"
					. "(?:[:]\d+)?/?"
				. ")+"
			. ")"
		. ")\\1#",
		$content,
		$post_links
	);

	$post_links = array_unique( array_map( 'html_entity_decode', $post_links[2] ) );

	return array_values( $post_links );
}

/**
 * Check content for video and audio links to add as enclosures.
 *
 * Will not add enclosures that have already been added and will
 * remove enclosures that are no longer in the post. This is called as
 * pingbacks and trackbacks.
 *
 * @since 1.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $content Post Content.
 * @param int    $post_ID Post ID.
 */
function do_enclose( $content, $post_ID ) {
	global $wpdb;

	//TODO: Tidy this ghetto code up and make the debug code optional
	include_once( ABSPATH . WPINC . '/class-IXR.php' );

	$post_links = array();

	$pung = get_enclosed( $post_ID );

	$post_links_temp = wp_extract_urls( $content );

	foreach ( $pung as $link_test ) {
		if ( ! in_array( $link_test, $post_links_temp ) ) { // link no longer in post
			$mids = $wpdb->get_col( $wpdb->prepare("SELECT meta_id FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = 'enclosure' AND meta_value LIKE %s", $post_ID, $wpdb->esc_like( $link_test ) . '%') );
			foreach ( $mids as $mid )
				delete_metadata_by_mid( 'post', $mid );
		}
	}

	foreach ( (array) $post_links_temp as $link_test ) {
		if ( !in_array( $link_test, $pung ) ) { // If we haven't pung it already
			$test = @parse_url( $link_test );
			if ( false === $test )
				continue;
			if ( isset( $test['query'] ) )
				$post_links[] = $link_test;
			elseif ( isset($test['path']) && ( $test['path'] != '/' ) &&  ($test['path'] != '' ) )
				$post_links[] = $link_test;
		}
	}

	/**
	 * Filters the list of enclosure links before querying the database.
	 *
	 * Allows for the addition and/or removal of potential enclosures to save
	 * to postmeta before checking the database for existing enclosures.
	 *
	 * @since 4.4.0
	 *
	 * @param array $post_links An array of enclosure links.
	 * @param int   $post_ID    Post ID.
	 */
	$post_links = apply_filters( 'enclosure_links', $post_links, $post_ID );

	foreach ( (array) $post_links as $url ) {
		if ( $url != '' && !$wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = 'enclosure' AND meta_value LIKE %s", $post_ID, $wpdb->esc_like( $url ) . '%' ) ) ) {

			if ( $headers = wp_get_http_headers( $url) ) {
				$len = isset( $headers['content-length'] ) ? (int) $headers['content-length'] : 0;
				$type = isset( $headers['content-type'] ) ? $headers['content-type'] : '';
				$allowed_types = array( 'video', 'audio' );

				// Check to see if we can figure out the mime type from
				// the extension
				$url_parts = @parse_url( $url );
				if ( false !== $url_parts ) {
					$extension = pathinfo( $url_parts['path'], PATHINFO_EXTENSION );
					if ( !empty( $extension ) ) {
						foreach ( wp_get_mime_types() as $exts => $mime ) {
							if ( preg_match( '!^(' . $exts . ')$!i', $extension ) ) {
								$type = $mime;
								break;
							}
						}
					}
				}

				if ( in_array( substr( $type, 0, strpos( $type, "/" ) ), $allowed_types ) ) {
					add_post_meta( $post_ID, 'enclosure', "$url\n$len\n$mime\n" );
				}
			}
		}
	}
}

/**
 * Retrieve HTTP Headers from URL.
 *
 * @since 1.5.1
 *
 * @param string $url        URL to retrieve HTTP headers from.
 * @param bool   $deprecated Not Used.
 * @return bool|string False on failure, headers on success.
 */
function wp_get_http_headers( $url, $deprecated = false ) {
	if ( !empty( $deprecated ) )
		_deprecated_argument( __FUNCTION__, '2.7.0' );

	$response = wp_safe_remote_head( $url );

	if ( is_wp_error( $response ) )
		return false;

	return wp_remote_retrieve_headers( $response );
}

/**
 * Whether the publish date of the current post in the loop is different from the
 * publish date of the previous post in the loop.
 *
 * @since 0.71
 *
 * @global string $currentday  The day of the current post in the loop.
 * @global string $previousday The day of the previous post in the loop.
 *
 * @return int 1 when new day, 0 if not a new day.
 */
function is_new_day() {
	global $currentday, $previousday;
	if ( $currentday != $previousday )
		return 1;
	else
		return 0;
}

/**
 * Build URL query based on an associative and, or indexed array.
 *
 * This is a convenient function for easily building url queries. It sets the
 * separator to '&' and uses _http_build_query() function.
 *
 * @since 2.3.0
 *
 * @see _http_build_query() Used to build the query
 * @link https://secure.php.net/manual/en/function.http-build-query.php for more on what
 *		 http_build_query() does.
 *
 * @param array $data URL-encode key/value pairs.
 * @return string URL-encoded string.
 */
function build_query( $data ) {
	return _http_build_query( $data, null, '&', '', false );
}

/**
 * From php.net (modified by Mark Jaquith to behave like the native PHP5 function).
 *
 * @since 3.2.0
 * @access private
 *
 * @see https://secure.php.net/manual/en/function.http-build-query.php
 *
 * @param array|object  $data       An array or object of data. Converted to array.
 * @param string        $prefix     Optional. Numeric index. If set, start parameter numbering with it.
 *                                  Default null.
 * @param string        $sep        Optional. Argument separator; defaults to 'arg_separator.output'.
 *                                  Default null.
 * @param string        $key        Optional. Used to prefix key name. Default empty.
 * @param bool          $urlencode  Optional. Whether to use urlencode() in the result. Default true.
 *
 * @return string The query string.
 */
function _http_build_query( $data, $prefix = null, $sep = null, $key = '', $urlencode = true ) {
	$ret = array();

	foreach ( (array) $data as $k => $v ) {
		if ( $urlencode)
			$k = urlencode($k);
		if ( is_int($k) && $prefix != null )
			$k = $prefix.$k;
		if ( !empty($key) )
			$k = $key . '%5B' . $k . '%5D';
		if ( $v === null )
			continue;
		elseif ( $v === false )
			$v = '0';

		if ( is_array($v) || is_object($v) )
			array_push($ret,_http_build_query($v, '', $sep, $k, $urlencode));
		elseif ( $urlencode )
			array_push($ret, $k.'='.urlencode($v));
		else
			array_push($ret, $k.'='.$v);
	}

	if ( null === $sep )
		$sep = ini_get('arg_separator.output');

	return implode($sep, $ret);
}

/**
 * Retrieves a modified URL query string.
 *
 * You can rebuild the URL and append query variables to the URL query by using this function.
 * There are two ways to use this function; either a single key and value, or an associative array.
 *
 * Using a single key and value:
 *
 *     add_query_arg( 'key', 'value', 'http://example.com' );
 *
 * Using an associative array:
 *
 *     add_query_arg( array(
 *         'key1' => 'value1',
 *         'key2' => 'value2',
 *     ), 'http://example.com' );
 *
 * Omitting the URL from either use results in the current URL being used
 * (the value of `$_SERVER['REQUEST_URI']`).
 *
 * Values are expected to be encoded appropriately with urlencode() or rawurlencode().
 *
 * Setting any query variable's value to boolean false removes the key (see remove_query_arg()).
 *
 * Important: The return value of add_query_arg() is not escaped by default. Output should be
 * late-escaped with esc_url() or similar to help prevent vulnerability to cross-site scripting
 * (XSS) attacks.
 *
 * @since 1.5.0
 *
 * @param string|array $key   Either a query variable key, or an associative array of query variables.
 * @param string       $value Optional. Either a query variable value, or a URL to act upon.
 * @param string       $url   Optional. A URL to act upon.
 * @return string New URL query string (unescaped).
 */
function add_query_arg() {
	$args = func_get_args();
	if ( is_array( $args[0] ) ) {
		if ( count( $args ) < 2 || false === $args[1] )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = $args[1];
	} else {
		if ( count( $args ) < 3 || false === $args[2] )
			$uri = $_SERVER['REQUEST_URI'];
		else
			$uri = $args[2];
	}

	if ( $frag = strstr( $uri, '#' ) )
		$uri = substr( $uri, 0, -strlen( $frag ) );
	else
		$frag = '';

	if ( 0 === stripos( $uri, 'http://' ) ) {
		$protocol = 'http://';
		$uri = substr( $uri, 7 );
	} elseif ( 0 === stripos( $uri, 'https://' ) ) {
		$protocol = 'https://';
		$uri = substr( $uri, 8 );
	} else {
		$protocol = '';
	}

	if ( strpos( $uri, '?' ) !== false ) {
		list( $base, $query ) = explode( '?', $uri, 2 );
		$base .= '?';
	} elseif ( $protocol || strpos( $uri, '=' ) === false ) {
		$base = $uri . '?';
		$query = '';
	} else {
		$base = '';
		$query = $uri;
	}

	wp_parse_str( $query, $qs );
	$qs = urlencode_deep( $qs ); // this re-URL-encodes things that were already in the query string
	if ( is_array( $args[0] ) ) {
		foreach ( $args[0] as $k => $v ) {
			$qs[ $k ] = $v;
		}
	} else {
		$qs[ $args[0] ] = $args[1];
	}

	foreach ( $qs as $k => $v ) {
		if ( $v === false )
			unset( $qs[$k] );
	}

	$ret = build_query( $qs );
	$ret = trim( $ret, '?' );
	$ret = preg_replace( '#=(&|$)#', '$1', $ret );
	$ret = $protocol . $base . $ret . $frag;
	$ret = rtrim( $ret, '?' );
	return $ret;
}

/**
 * Removes an item or items from a query string.
 *
 * @since 1.5.0
 *
 * @param string|array $key   Query key or keys to remove.
 * @param bool|string  $query Optional. When false uses the current URL. Default false.
 * @return string New URL query string.
 */
function remove_query_arg( $key, $query = false ) {
	if ( is_array( $key ) ) { // removing multiple keys
		foreach ( $key as $k )
			$query = add_query_arg( $k, false, $query );
		return $query;
	}
	return add_query_arg( $key, false, $query );
}

/**
 * Returns an array of single-use query variable names that can be removed from a URL.
 *
 * @since 4.4.0
 *
 * @return array An array of parameters to remove from the URL.
 */
function wp_removable_query_args() {
	$removable_query_args = array(
		'activate',
		'activated',
		'approved',
		'deactivate',
		'deleted',
		'disabled',
		'enabled',
		'error',
		'hotkeys_highlight_first',
		'hotkeys_highlight_last',
		'locked',
		'message',
		'same',
		'saved',
		'settings-updated',
		'skipped',
		'spammed',
		'trashed',
		'unspammed',
		'untrashed',
		'update',
		'updated',
		'wp-post-new-reload',
	);

	/**
	 * Filters the list of query variables to remove.
	 *
	 * @since 4.2.0
	 *
	 * @param array $removable_query_args An array of query variables to remove from a URL.
	 */
	return apply_filters( 'removable_query_args', $removable_query_args );
}

/**
 * Walks the array while sanitizing the contents.
 *
 * @since 0.71
 *
 * @param array $array Array to walk while sanitizing contents.
 * @return array Sanitized $array.
 */
function add_magic_quotes( $array ) {
	foreach ( (array) $array as $k => $v ) {
		if ( is_array( $v ) ) {
			$array[$k] = add_magic_quotes( $v );
		} else {
			$array[$k] = addslashes( $v );
		}
	}
	return $array;
}

/**
 * HTTP request for URI to retrieve content.
 *
 * @since 1.5.1
 *
 * @see wp_safe_remote_get()
 *
 * @param string $uri URI/URL of web page to retrieve.
 * @return false|string HTTP content. False on failure.
 */
function wp_remote_fopen( $uri ) {
	$parsed_url = @parse_url( $uri );

	if ( !$parsed_url || !is_array( $parsed_url ) )
		return false;

	$options = array();
	$options['timeout'] = 10;

	$response = wp_safe_remote_get( $uri, $options );

	if ( is_wp_error( $response ) )
		return false;

	return wp_remote_retrieve_body( $response );
}

/**
 * Set up the WordPress query.
 *
 * @since 2.0.0
 *
 * @global WP       $wp_locale
 * @global WP_Query $wp_query
 * @global WP_Query $wp_the_query
 *
 * @param string|array $query_vars Default WP_Query arguments.
 */
function wp( $query_vars = '' ) {
	global $wp, $wp_query, $wp_the_query;
	$wp->main( $query_vars );

	if ( !isset($wp_the_query) )
		$wp_the_query = $wp_query;
}

/**
 * Retrieve the description for the HTTP status.
 *
 * @since 2.3.0
 *
 * @global array $wp_header_to_desc
 *
 * @param int $code HTTP status code.
 * @return string Empty string if not found, or description if found.
 */
function get_status_header_desc( $code ) {
	global $wp_header_to_desc;

	$code = absint( $code );

	if ( !isset( $wp_header_to_desc ) ) {
		$wp_header_to_desc = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',

			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			226 => 'IM Used',

			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => 'Reserved',
			307 => 'Temporary Redirect',
			308 => 'Permanent Redirect',

			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			418 => 'I\'m a teapot',
			421 => 'Misdirected Request',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			426 => 'Upgrade Required',
			428 => 'Precondition Required',
			429 => 'Too Many Requests',
			431 => 'Request Header Fields Too Large',
			451 => 'Unavailable For Legal Reasons',

			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			510 => 'Not Extended',
			511 => 'Network Authentication Required',
		);
	}

	if ( isset( $wp_header_to_desc[$code] ) )
		return $wp_header_to_desc[$code];
	else
		return '';
}

/**
 * Set HTTP status header.
 *
 * @since 2.0.0
 * @since 4.4.0 Added the `$description` parameter.
 *
 * @see get_status_header_desc()
 *
 * @param int    $code        HTTP status code.
 * @param string $description Optional. A custom description for the HTTP status.
 */
function status_header( $code, $description = '' ) {
	if ( ! $description ) {
		$description = get_status_header_desc( $code );
	}

	if ( empty( $description ) ) {
		return;
	}

	$protocol = wp_get_server_protocol();
	$status_header = "$protocol $code $description";
	if ( function_exists( 'apply_filters' ) )

		/**
		 * Filters an HTTP status header.
		 *
		 * @since 2.2.0
		 *
		 * @param string $status_header HTTP status header.
		 * @param int    $code          HTTP status code.
		 * @param string $description   Description for the status code.
		 * @param string $protocol      Server protocol.
		 */
		$status_header = apply_filters( 'status_header', $status_header, $code, $description, $protocol );

	@header( $status_header, true, $code );
}

/**
 * Get the header information to prevent caching.
 *
 * The several different headers cover the different ways cache prevention
 * is handled by different browsers
 *
 * @since 2.8.0
 *
 * @return array The associative array of header names and field values.
 */
function wp_get_nocache_headers() {
	$headers = array(
		'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT',
		'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
	);

	if ( function_exists('apply_filters') ) {
		/**
		 * Filters the cache-controlling headers.
		 *
		 * @since 2.8.0
		 *
		 * @see wp_get_nocache_headers()
		 *
		 * @param array $headers {
		 *     Header names and field values.
		 *
		 *     @type string $Expires       Expires header.
		 *     @type string $Cache-Control Cache-Control header.
		 * }
		 */
		$headers = (array) apply_filters( 'nocache_headers', $headers );
	}
	$headers['Last-Modified'] = false;
	return $headers;
}

/**
 * Set the headers to prevent caching for the different browsers.
 *
 * Different browsers support different nocache headers, so several
 * headers must be sent so that all of them get the point that no
 * caching should occur.
 *
 * @since 2.0.0
 *
 * @see wp_get_nocache_headers()
 */
function nocache_headers() {
	$headers = wp_get_nocache_headers();

	unset( $headers['Last-Modified'] );

	// In PHP 5.3+, make sure we are not sending a Last-Modified header.
	if ( function_exists( 'header_remove' ) ) {
		@header_remove( 'Last-Modified' );
	} else {
		// In PHP 5.2, send an empty Last-Modified header, but only as a
		// last resort to override a header already sent. #WP23021
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'Last-Modified' ) ) {
				$headers['Last-Modified'] = '';
				break;
			}
		}
	}

	foreach ( $headers as $name => $field_value )
		@header("{$name}: {$field_value}");
}

/**
 * Set the headers for caching for 10 days with JavaScript content type.
 *
 * @since 2.1.0
 */
function cache_javascript_headers() {
	$expiresOffset = 10 * DAY_IN_SECONDS;

	header( "Content-Type: text/javascript; charset=" . get_bloginfo( 'charset' ) );
	header( "Vary: Accept-Encoding" ); // Handle proxies
	header( "Expires: " . gmdate( "D, d M Y H:i:s", time() + $expiresOffset ) . " GMT" );
}

/**
 * Retrieve the number of database queries during the WordPress execution.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return int Number of database queries.
 */
function get_num_queries() {
	global $wpdb;
	return $wpdb->num_queries;
}

/**
 * Whether input is yes or no.
 *
 * Must be 'y' to be true.
 *
 * @since 1.0.0
 *
 * @param string $yn Character string containing either 'y' (yes) or 'n' (no).
 * @return bool True if yes, false on anything else.
 */
function bool_from_yn( $yn ) {
	return ( strtolower( $yn ) == 'y' );
}

/**
 * Load the feed template from the use of an action hook.
 *
 * If the feed action does not have a hook, then the function will die with a
 * message telling the visitor that the feed is not valid.
 *
 * It is better to only have one hook for each feed.
 *
 * @since 2.1.0
 *
 * @global WP_Query $wp_query Used to tell if the use a comment feed.
 */
function do_feed() {
	global $wp_query;

	$feed = get_query_var( 'feed' );

	// Remove the pad, if present.
	$feed = preg_replace( '/^_+/', '', $feed );

	if ( $feed == '' || $feed == 'feed' )
		$feed = get_default_feed();

	if ( ! has_action( "do_feed_{$feed}" ) ) {
		wp_die( __( 'ERROR: This is not a valid feed template.' ), '', array( 'response' => 404 ) );
	}

	/**
	 * Fires once the given feed is loaded.
	 *
	 * The dynamic portion of the hook name, `$feed`, refers to the feed template name.
	 * Possible values include: 'rdf', 'rss', 'rss2', and 'atom'.
	 *
	 * @since 2.1.0
	 * @since 4.4.0 The `$feed` parameter was added.
	 *
	 * @param bool   $is_comment_feed Whether the feed is a comment feed.
	 * @param string $feed            The feed name.
	 */
	do_action( "do_feed_{$feed}", $wp_query->is_comment_feed, $feed );
}

/**
 * Load the RDF RSS 0.91 Feed template.
 *
 * @since 2.1.0
 *
 * @see load_template()
 */
function do_feed_rdf() {
	load_template( ABSPATH . WPINC . '/feed-rdf.php' );
}

/**
 * Load the RSS 1.0 Feed Template.
 *
 * @since 2.1.0
 *
 * @see load_template()
 */
function do_feed_rss() {
	load_template( ABSPATH . WPINC . '/feed-rss.php' );
}

/**
 * Load either the RSS2 comment feed or the RSS2 posts feed.
 *
 * @since 2.1.0
 *
 * @see load_template()
 *
 * @param bool $for_comments True for the comment feed, false for normal feed.
 */
function do_feed_rss2( $for_comments ) {
	if ( $for_comments )
		load_template( ABSPATH . WPINC . '/feed-rss2-comments.php' );
	else
		load_template( ABSPATH . WPINC . '/feed-rss2.php' );
}

/**
 * Load either Atom comment feed or Atom posts feed.
 *
 * @since 2.1.0
 *
 * @see load_template()
 *
 * @param bool $for_comments True for the comment feed, false for normal feed.
 */
function do_feed_atom( $for_comments ) {
	if ($for_comments)
		load_template( ABSPATH . WPINC . '/feed-atom-comments.php');
	else
		load_template( ABSPATH . WPINC . '/feed-atom.php' );
}

/**
 * Display the robots.txt file content.
 *
 * The echo content should be with usage of the permalinks or for creating the
 * robots.txt file.
 *
 * @since 2.1.0
 */
function do_robots() {
	header( 'Content-Type: text/plain; charset=utf-8' );

	/**
	 * Fires when displaying the robots.txt file.
	 *
	 * @since 2.1.0
	 */
	do_action( 'do_robotstxt' );

	$output = "User-agent: *\n";
	$public = get_option( 'blog_public' );
	if ( '0' == $public ) {
		$output .= "Disallow: /\n";
	} else {
		$site_url = parse_url( site_url() );
		$path = ( !empty( $site_url['path'] ) ) ? $site_url['path'] : '';
		$output .= "Disallow: $path/wp-admin/\n";
		$output .= "Allow: $path/wp-admin/admin-ajax.php\n";
	}

	/**
	 * Filters the robots.txt output.
	 *
	 * @since 3.0.0
	 *
	 * @param string $output Robots.txt output.
	 * @param bool   $public Whether the site is considered "public".
	 */
	echo apply_filters( 'robots_txt', $output, $public );
}

/**
 * Test whether WordPress is already installed.
 *
 * The cache will be checked first. If you have a cache plugin, which saves
 * the cache values, then this will work. If you use the default WordPress
 * cache, and the database goes away, then you might have problems.
 *
 * Checks for the 'siteurl' option for whether WordPress is installed.
 *
 * @since 2.1.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return bool Whether the site is already installed.
 */
function is_blog_installed() {
	global $wpdb;

	/*
	 * Check cache first. If options table goes away and we have true
	 * cached, oh well.
	 */
	if ( wp_cache_get( 'is_blog_installed' ) )
		return true;

	$suppress = $wpdb->suppress_errors();
	if ( ! wp_installing() ) {
		$alloptions = wp_load_alloptions();
	}
	// If siteurl is not set to autoload, check it specifically
	if ( !isset( $alloptions['siteurl'] ) )
		$installed = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'siteurl'" );
	else
		$installed = $alloptions['siteurl'];
	$wpdb->suppress_errors( $suppress );

	$installed = !empty( $installed );
	wp_cache_set( 'is_blog_installed', $installed );

	if ( $installed )
		return true;

	// If visiting repair.php, return true and let it take over.
	if ( defined( 'WP_REPAIRING' ) )
		return true;

	$suppress = $wpdb->suppress_errors();

	/*
	 * Loop over the WP tables. If none exist, then scratch installation is allowed.
	 * If one or more exist, suggest table repair since we got here because the
	 * options table could not be accessed.
	 */
	$wp_tables = $wpdb->tables();
	foreach ( $wp_tables as $table ) {
		// The existence of custom user tables shouldn't suggest an insane state or prevent a clean installation.
		if ( defined( 'CUSTOM_USER_TABLE' ) && CUSTOM_USER_TABLE == $table )
			continue;
		if ( defined( 'CUSTOM_USER_META_TABLE' ) && CUSTOM_USER_META_TABLE == $table )
			continue;

		if ( ! $wpdb->get_results( "DESCRIBE $table;" ) )
			continue;

		// One or more tables exist. We are insane.

		wp_load_translations_early();

		// Die with a DB error.
		$wpdb->error = sprintf(
			/* translators: %s: database repair URL */
			__( 'One or more database tables are unavailable. The database may need to be <a href="%s">repaired</a>.' ),
			'maint/repair.php?referrer=is_blog_installed'
		);

		dead_db();
	}

	$wpdb->suppress_errors( $suppress );

	wp_cache_set( 'is_blog_installed', false );

	return false;
}

/**
 * Retrieve URL with nonce added to URL query.
 *
 * @since 2.0.4
 *
 * @param string     $actionurl URL to add nonce action.
 * @param int|string $action    Optional. Nonce action name. Default -1.
 * @param string     $name      Optional. Nonce name. Default '_wpnonce'.
 * @return string Escaped URL with nonce action added.
 */
function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
	$actionurl = str_replace( '&amp;', '&', $actionurl );
	return esc_html( add_query_arg( $name, wp_create_nonce( $action ), $actionurl ) );
}

/**
 * Retrieve or display nonce hidden field for forms.
 *
 * The nonce field is used to validate that the contents of the form came from
 * the location on the current site and not somewhere else. The nonce does not
 * offer absolute protection, but should protect against most cases. It is very
 * important to use nonce field in forms.
 *
 * The $action and $name are optional, but if you want to have better security,
 * it is strongly suggested to set those two parameters. It is easier to just
 * call the function without any parameters, because validation of the nonce
 * doesn't require any parameters, but since crackers know what the default is
 * it won't be difficult for them to find a way around your nonce and cause
 * damage.
 *
 * The input name will be whatever $name value you gave. The input value will be
 * the nonce creation value.
 *
 * @since 2.0.4
 *
 * @param int|string $action  Optional. Action name. Default -1.
 * @param string     $name    Optional. Nonce name. Default '_wpnonce'.
 * @param bool       $referer Optional. Whether to set the referer field for validation. Default true.
 * @param bool       $echo    Optional. Whether to display or return hidden form field. Default true.
 * @return string Nonce field HTML markup.
 */
function wp_nonce_field( $action = -1, $name = "_wpnonce", $referer = true , $echo = true ) {
	$name = esc_attr( $name );
	$nonce_field = '<input type="hidden" id="' . $name . '" name="' . $name . '" value="' . wp_create_nonce( $action ) . '" />';

	if ( $referer )
		$nonce_field .= wp_referer_field( false );

	if ( $echo )
		echo $nonce_field;

	return $nonce_field;
}

/**
 * Retrieve or display referer hidden field for forms.
 *
 * The referer link is the current Request URI from the server super global. The
 * input name is '_wp_http_referer', in case you wanted to check manually.
 *
 * @since 2.0.4
 *
 * @param bool $echo Optional. Whether to echo or return the referer field. Default true.
 * @return string Referer field HTML markup.
 */
function wp_referer_field( $echo = true ) {
	$referer_field = '<input type="hidden" name="_wp_http_referer" value="'. esc_attr( wp_unslash( $_SERVER['REQUEST_URI'] ) ) . '" />';

	if ( $echo )
		echo $referer_field;
	return $referer_field;
}

/**
 * Retrieve or display original referer hidden field for forms.
 *
 * The input name is '_wp_original_http_referer' and will be either the same
 * value of wp_referer_field(), if that was posted already or it will be the
 * current page, if it doesn't exist.
 *
 * @since 2.0.4
 *
 * @param bool   $echo         Optional. Whether to echo the original http referer. Default true.
 * @param string $jump_back_to Optional. Can be 'previous' or page you want to jump back to.
 *                             Default 'current'.
 * @return string Original referer field.
 */
function wp_original_referer_field( $echo = true, $jump_back_to = 'current' ) {
	if ( ! $ref = wp_get_original_referer() ) {
		$ref = 'previous' == $jump_back_to ? wp_get_referer() : wp_unslash( $_SERVER['REQUEST_URI'] );
	}
	$orig_referer_field = '<input type="hidden" name="_wp_original_http_referer" value="' . esc_attr( $ref ) . '" />';
	if ( $echo )
		echo $orig_referer_field;
	return $orig_referer_field;
}

/**
 * Retrieve referer from '_wp_http_referer' or HTTP referer.
 *
 * If it's the same as the current request URL, will return false.
 *
 * @since 2.0.4
 *
 * @return false|string False on failure. Referer URL on success.
 */
function wp_get_referer() {
	if ( ! function_exists( 'wp_validate_redirect' ) ) {
		return false;
	}

	$ref = wp_get_raw_referer();

	if ( $ref && $ref !== wp_unslash( $_SERVER['REQUEST_URI'] ) && $ref !== home_url() . wp_unslash( $_SERVER['REQUEST_URI'] ) ) {
		return wp_validate_redirect( $ref, false );
	}

	return false;
}

/**
 * Retrieves unvalidated referer from '_wp_http_referer' or HTTP referer.
 *
 * Do not use for redirects, use wp_get_referer() instead.
 *
 * @since 4.5.0
 *
 * @return string|false Referer URL on success, false on failure.
 */
function wp_get_raw_referer() {
	if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) {
		return wp_unslash( $_REQUEST['_wp_http_referer'] );
	} else if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
		return wp_unslash( $_SERVER['HTTP_REFERER'] );
	}

	return false;
}

/**
 * Retrieve original referer that was posted, if it exists.
 *
 * @since 2.0.4
 *
 * @return string|false False if no original referer or original referer if set.
 */
function wp_get_original_referer() {
	if ( ! empty( $_REQUEST['_wp_original_http_referer'] ) && function_exists( 'wp_validate_redirect' ) )
		return wp_validate_redirect( wp_unslash( $_REQUEST['_wp_original_http_referer'] ), false );
	return false;
}

/**
 * Recursive directory creation based on full path.
 *
 * Will attempt to set permissions on folders.
 *
 * @since 2.0.1
 *
 * @param string $target Full path to attempt to create.
 * @return bool Whether the path was created. True if path already exists.
 */
function wp_mkdir_p( $target ) {
	$wrapper = null;

	// Strip the protocol.
	if ( wp_is_stream( $target ) ) {
		list( $wrapper, $target ) = explode( '://', $target, 2 );
	}

	// From php.net/mkdir user contributed notes.
	$target = str_replace( '//', '/', $target );

	// Put the wrapper back on the target.
	if ( $wrapper !== null ) {
		$target = $wrapper . '://' . $target;
	}

	/*
	 * Safe mode fails with a trailing slash under certain PHP versions.
	 * Use rtrim() instead of untrailingslashit to avoid formatting.php dependency.
	 */
	$target = rtrim($target, '/');
	if ( empty($target) )
		$target = '/';

	if ( file_exists( $target ) )
		return @is_dir( $target );

	// We need to find the permissions of the parent folder that exists and inherit that.
	$target_parent = dirname( $target );
	while ( '.' != $target_parent && ! is_dir( $target_parent ) ) {
		$target_parent = dirname( $target_parent );
	}

	// Get the permission bits.
	if ( $stat = @stat( $target_parent ) ) {
		$dir_perms = $stat['mode'] & 0007777;
	} else {
		$dir_perms = 0777;
	}

	if ( @mkdir( $target, $dir_perms, true ) ) {

		/*
		 * If a umask is set that modifies $dir_perms, we'll have to re-set
		 * the $dir_perms correctly with chmod()
		 */
		if ( $dir_perms != ( $dir_perms & ~umask() ) ) {
			$folder_parts = explode( '/', substr( $target, strlen( $target_parent ) + 1 ) );
			for ( $i = 1, $c = count( $folder_parts ); $i <= $c; $i++ ) {
				@chmod( $target_parent . '/' . implode( '/', array_slice( $folder_parts, 0, $i ) ), $dir_perms );
			}
		}

		return true;
	}

	return false;
}

/**
 * Test if a given filesystem path is absolute.
 *
 * For example, '/foo/bar', or 'c:\windows'.
 *
 * @since 2.5.0
 *
 * @param string $path File path.
 * @return bool True if path is absolute, false is not absolute.
 */
function path_is_absolute( $path ) {
	/*
	 * This is definitive if true but fails if $path does not exist or contains
	 * a symbolic link.
	 */
	if ( realpath($path) == $path )
		return true;

	if ( strlen($path) == 0 || $path[0] == '.' )
		return false;

	// Windows allows absolute paths like this.
	if ( preg_match('#^[a-zA-Z]:\\\\#', $path) )
		return true;

	// A path starting with / or \ is absolute; anything else is relative.
	return ( $path[0] == '/' || $path[0] == '\\' );
}

/**
 * Join two filesystem paths together.
 *
 * For example, 'give me $path relative to $base'. If the $path is absolute,
 * then it the full path is returned.
 *
 * @since 2.5.0
 *
 * @param string $base Base path.
 * @param string $path Path relative to $base.
 * @return string The path with the base or absolute path.
 */
function path_join( $base, $path ) {
	if ( path_is_absolute($path) )
		return $path;

	return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Normalize a filesystem path.
 *
 * On windows systems, replaces backslashes with forward slashes
 * and forces upper-case drive letters.
 * Allows for two leading slashes for Windows network shares, but
 * ensures that all other duplicate slashes are reduced to a single.
 *
 * @since 3.9.0
 * @since 4.4.0 Ensures upper-case drive letters on Windows systems.
 * @since 4.5.0 Allows for Windows network shares.
 *
 * @param string $path Path to normalize.
 * @return string Normalized path.
 */
function wp_normalize_path( $path ) {
	$path = str_replace( '\\', '/', $path );
	$path = preg_replace( '|(?<=.)/+|', '/', $path );
	if ( ':' === substr( $path, 1, 1 ) ) {
		$path = ucfirst( $path );
	}
	return $path;
}

/**
 * Determine a writable directory for temporary files.
 *
 * Function's preference is the return value of sys_get_temp_dir(),
 * followed by your PHP temporary upload directory, followed by WP_CONTENT_DIR,
 * before finally defaulting to /tmp/
 *
 * In the event that this function does not find a writable location,
 * It may be overridden by the WP_TEMP_DIR constant in your wp-config.php file.
 *
 * @since 2.5.0
 *
 * @staticvar string $temp
 *
 * @return string Writable temporary directory.
 */
function get_temp_dir() {
	static $temp = '';
	if ( defined('WP_TEMP_DIR') )
		return trailingslashit(WP_TEMP_DIR);

	if ( $temp )
		return trailingslashit( $temp );

	if ( function_exists('sys_get_temp_dir') ) {
		$temp = sys_get_temp_dir();
		if ( @is_dir( $temp ) && wp_is_writable( $temp ) )
			return trailingslashit( $temp );
	}

	$temp = ini_get('upload_tmp_dir');
	if ( @is_dir( $temp ) && wp_is_writable( $temp ) )
		return trailingslashit( $temp );

	$temp = WP_CONTENT_DIR . '/';
	if ( is_dir( $temp ) && wp_is_writable( $temp ) )
		return $temp;

	return '/tmp/';
}

/**
 * Determine if a directory is writable.
 *
 * This function is used to work around certain ACL issues in PHP primarily
 * affecting Windows Servers.
 *
 * @since 3.6.0
 *
 * @see win_is_writable()
 *
 * @param string $path Path to check for write-ability.
 * @return bool Whether the path is writable.
 */
function wp_is_writable( $path ) {
	if ( 'WIN' === strtoupper( substr( PHP_OS, 0, 3 ) ) )
		return win_is_writable( $path );
	else
		return @is_writable( $path );
}

/**
 * Workaround for Windows bug in is_writable() function
 *
 * PHP has issues with Windows ACL's for determine if a
 * directory is writable or not, this works around them by
 * checking the ability to open files rather than relying
 * upon PHP to interprate the OS ACL.
 *
 * @since 2.8.0
 *
 * @see https://bugs.php.net/bug.php?id=27609
 * @see https://bugs.php.net/bug.php?id=30931
 *
 * @param string $path Windows path to check for write-ability.
 * @return bool Whether the path is writable.
 */
function win_is_writable( $path ) {

	if ( $path[strlen( $path ) - 1] == '/' ) { // if it looks like a directory, check a random file within the directory
		return win_is_writable( $path . uniqid( mt_rand() ) . '.tmp');
	} elseif ( is_dir( $path ) ) { // If it's a directory (and not a file) check a random file within the directory
		return win_is_writable( $path . '/' . uniqid( mt_rand() ) . '.tmp' );
	}
	// check tmp file for read/write capabilities
	$should_delete_tmp_file = !file_exists( $path );
	$f = @fopen( $path, 'a' );
	if ( $f === false )
		return false;
	fclose( $f );
	if ( $should_delete_tmp_file )
		unlink( $path );
	return true;
}

/**
 * Retrieves uploads directory information.
 *
 * Same as wp_upload_dir() but "light weight" as it doesn't attempt to create the uploads directory.
 * Intended for use in themes, when only 'basedir' and 'baseurl' are needed, generally in all cases
 * when not uploading files.
 *
 * @since 4.5.0
 *
 * @see wp_upload_dir()
 *
 * @return array See wp_upload_dir() for description.
 */
function wp_get_upload_dir() {
	return wp_upload_dir( null, false );
}

/**
 * Get an array containing the current upload directory's path and url.
 *
 * Checks the 'upload_path' option, which should be from the web root folder,
 * and if it isn't empty it will be used. If it is empty, then the path will be
 * 'WP_CONTENT_DIR/uploads'. If the 'UPLOADS' constant is defined, then it will
 * override the 'upload_path' option and 'WP_CONTENT_DIR/uploads' path.
 *
 * The upload URL path is set either by the 'upload_url_path' option or by using
 * the 'WP_CONTENT_URL' constant and appending '/uploads' to the path.
 *
 * If the 'uploads_use_yearmonth_folders' is set to true (checkbox if checked in
 * the administration settings panel), then the time will be used. The format
 * will be year first and then month.
 *
 * If the path couldn't be created, then an error will be returned with the key
 * 'error' containing the error message. The error suggests that the parent
 * directory is not writable by the server.
 *
 * On success, the returned array will have many indices:
 * 'path' - base directory and sub directory or full path to upload directory.
 * 'url' - base url and sub directory or absolute URL to upload directory.
 * 'subdir' - sub directory if uploads use year/month folders option is on.
 * 'basedir' - path without subdir.
 * 'baseurl' - URL path without subdir.
 * 'error' - false or error message.
 *
 * @since 2.0.0
 * @uses _wp_upload_dir()
 *
 * @staticvar array $cache
 * @staticvar array $tested_paths
 *
 * @param string $time Optional. Time formatted in 'yyyy/mm'. Default null.
 * @param bool   $create_dir Optional. Whether to check and create the uploads directory.
 *                           Default true for backward compatibility.
 * @param bool   $refresh_cache Optional. Whether to refresh the cache. Default false.
 * @return array See above for description.
 */
function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) {
	static $cache = array(), $tested_paths = array();

	$key = sprintf( '%d-%s', get_current_blog_id(), (string) $time );

	if ( $refresh_cache || empty( $cache[ $key ] ) ) {
		$cache[ $key ] = _wp_upload_dir( $time );
	}

	/**
	 * Filters the uploads directory data.
	 *
	 * @since 2.0.0
	 *
	 * @param array $uploads Array of upload directory data with keys of 'path',
	 *                       'url', 'subdir, 'basedir', and 'error'.
	 */
	$uploads = apply_filters( 'upload_dir', $cache[ $key ] );

	if ( $create_dir ) {
		$path = $uploads['path'];

		if ( array_key_exists( $path, $tested_paths ) ) {
			$uploads['error'] = $tested_paths[ $path ];
		} else {
			if ( ! wp_mkdir_p( $path ) ) {
				if ( 0 === strpos( $uploads['basedir'], ABSPATH ) ) {
					$error_path = str_replace( ABSPATH, '', $uploads['basedir'] ) . $uploads['subdir'];
				} else {
					$error_path = basename( $uploads['basedir'] ) . $uploads['subdir'];
				}

				$uploads['error'] = sprintf(
					/* translators: %s: directory path */
					__( 'Unable to create directory %s. Is its parent directory writable by the server?' ),
					esc_html( $error_path )
				);
			}

			$tested_paths[ $path ] = $uploads['error'];
		}
	}

	return $uploads;
}

/**
 * A non-filtered, non-cached version of wp_upload_dir() that doesn't check the path.
 *
 * @since 4.5.0
 * @access private
 *
 * @param string $time Optional. Time formatted in 'yyyy/mm'. Default null.
 * @return array See wp_upload_dir()
 */
function _wp_upload_dir( $time = null ) {
	$siteurl = get_option( 'siteurl' );
	$upload_path = trim( get_option( 'upload_path' ) );

	if ( empty( $upload_path ) || 'wp-content/uploads' == $upload_path ) {
		$dir = WP_CONTENT_DIR . '/uploads';
	} elseif ( 0 !== strpos( $upload_path, ABSPATH ) ) {
		// $dir is absolute, $upload_path is (maybe) relative to ABSPATH
		$dir = path_join( ABSPATH, $upload_path );
	} else {
		$dir = $upload_path;
	}

	if ( !$url = get_option( 'upload_url_path' ) ) {
		if ( empty($upload_path) || ( 'wp-content/uploads' == $upload_path ) || ( $upload_path == $dir ) )
			$url = WP_CONTENT_URL . '/uploads';
		else
			$url = trailingslashit( $siteurl ) . $upload_path;
	}

	/*
	 * Honor the value of UPLOADS. This happens as long as ms-files rewriting is disabled.
	 * We also sometimes obey UPLOADS when rewriting is enabled -- see the next block.
	 */
	if ( defined( 'UPLOADS' ) && ! ( is_multisite() && get_site_option( 'ms_files_rewriting' ) ) ) {
		$dir = ABSPATH . UPLOADS;
		$url = trailingslashit( $siteurl ) . UPLOADS;
	}

	// If multisite (and if not the main site in a post-MU network)
	if ( is_multisite() && ! ( is_main_network() && is_main_site() && defined( 'MULTISITE' ) ) ) {

		if ( ! get_site_option( 'ms_files_rewriting' ) ) {
			/*
			 * If ms-files rewriting is disabled (networks created post-3.5), it is fairly
			 * straightforward: Append sites/%d if we're not on the main site (for post-MU
			 * networks). (The extra directory prevents a four-digit ID from conflicting with
			 * a year-based directory for the main site. But if a MU-era network has disabled
			 * ms-files rewriting manually, they don't need the extra directory, as they never
			 * had wp-content/uploads for the main site.)
			 */

			if ( defined( 'MULTISITE' ) )
				$ms_dir = '/sites/' . get_current_blog_id();
			else
				$ms_dir = '/' . get_current_blog_id();

			$dir .= $ms_dir;
			$url .= $ms_dir;

		} elseif ( defined( 'UPLOADS' ) && ! ms_is_switched() ) {
			/*
			 * Handle the old-form ms-files.php rewriting if the network still has that enabled.
			 * When ms-files rewriting is enabled, then we only listen to UPLOADS when:
			 * 1) We are not on the main site in a post-MU network, as wp-content/uploads is used
			 *    there, and
			 * 2) We are not switched, as ms_upload_constants() hardcodes these constants to reflect
			 *    the original blog ID.
			 *
			 * Rather than UPLOADS, we actually use BLOGUPLOADDIR if it is set, as it is absolute.
			 * (And it will be set, see ms_upload_constants().) Otherwise, UPLOADS can be used, as
			 * as it is relative to ABSPATH. For the final piece: when UPLOADS is used with ms-files
			 * rewriting in multisite, the resulting URL is /files. (#WP22702 for background.)
			 */

			if ( defined( 'BLOGUPLOADDIR' ) )
				$dir = untrailingslashit( BLOGUPLOADDIR );
			else
				$dir = ABSPATH . UPLOADS;
			$url = trailingslashit( $siteurl ) . 'files';
		}
	}

	$basedir = $dir;
	$baseurl = $url;

	$subdir = '';
	if ( get_option( 'uploads_use_yearmonth_folders' ) ) {
		// Generate the yearly and monthly dirs
		if ( !$time )
			$time = current_time( 'mysql' );
		$y = substr( $time, 0, 4 );
		$m = substr( $time, 5, 2 );
		$subdir = "/$y/$m";
	}

	$dir .= $subdir;
	$url .= $subdir;

	return array(
		'path'    => $dir,
		'url'     => $url,
		'subdir'  => $subdir,
		'basedir' => $basedir,
		'baseurl' => $baseurl,
		'error'   => false,
	);
}

/**
 * Get a filename that is sanitized and unique for the given directory.
 *
 * If the filename is not unique, then a number will be added to the filename
 * before the extension, and will continue adding numbers until the filename is
 * unique.
 *
 * The callback is passed three parameters, the first one is the directory, the
 * second is the filename, and the third is the extension.
 *
 * @since 2.5.0
 *
 * @param string   $dir                      Directory.
 * @param string   $filename                 File name.
 * @param callable $unique_filename_callback Callback. Default null.
 * @return string New filename, if given wasn't unique.
 */
function wp_unique_filename( $dir, $filename, $unique_filename_callback = null ) {
	// Sanitize the file name before we begin processing.
	$filename = sanitize_file_name($filename);

	// Separate the filename into a name and extension.
	$ext = pathinfo( $filename, PATHINFO_EXTENSION );
	$name = pathinfo( $filename, PATHINFO_BASENAME );
	if ( $ext ) {
		$ext = '.' . $ext;
	}

	// Edge case: if file is named '.ext', treat as an empty name.
	if ( $name === $ext ) {
		$name = '';
	}

	/*
	 * Increment the file number until we have a unique file to save in $dir.
	 * Use callback if supplied.
	 */
	if ( $unique_filename_callback && is_callable( $unique_filename_callback ) ) {
		$filename = call_user_func( $unique_filename_callback, $dir, $name, $ext );
	} else {
		$number = '';

		// Change '.ext' to lower case.
		if ( $ext && strtolower($ext) != $ext ) {
			$ext2 = strtolower($ext);
			$filename2 = preg_replace( '|' . preg_quote($ext) . '$|', $ext2, $filename );

			// Check for both lower and upper case extension or image sub-sizes may be overwritten.
			while ( file_exists($dir . "/$filename") || file_exists($dir . "/$filename2") ) {
				$new_number = (int) $number + 1;
				$filename = str_replace( array( "-$number$ext", "$number$ext" ), "-$new_number$ext", $filename );
				$filename2 = str_replace( array( "-$number$ext2", "$number$ext2" ), "-$new_number$ext2", $filename2 );
				$number = $new_number;
			}

			/**
			 * Filters the result when generating a unique file name.
			 *
			 * @since 4.5.0
			 *
			 * @param string        $filename                 Unique file name.
			 * @param string        $ext                      File extension, eg. ".png".
			 * @param string        $dir                      Directory path.
			 * @param callable|null $unique_filename_callback Callback function that generates the unique file name.
			 */
			return apply_filters( 'wp_unique_filename', $filename2, $ext, $dir, $unique_filename_callback );
		}

		while ( file_exists( $dir . "/$filename" ) ) {
			$new_number = (int) $number + 1;
			if ( '' == "$number$ext" ) {
				$filename = "$filename-" . $new_number;
			} else {
				$filename = str_replace( array( "-$number$ext", "$number$ext" ), "-" . $new_number . $ext, $filename );
			}
			$number = $new_number;
		}
	}

	/** This filter is documented in wp-includes/functions.php */
	return apply_filters( 'wp_unique_filename', $filename, $ext, $dir, $unique_filename_callback );
}

/**
 * Create a file in the upload folder with given content.
 *
 * If there is an error, then the key 'error' will exist with the error message.
 * If success, then the key 'file' will have the unique file path, the 'url' key
 * will have the link to the new file. and the 'error' key will be set to false.
 *
 * This function will not move an uploade