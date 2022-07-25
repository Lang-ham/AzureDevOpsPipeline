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
			505 => 