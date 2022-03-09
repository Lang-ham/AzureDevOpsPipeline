<?php
/**
 * HTTP API: WP_Http_Encoding class
 *
 * @package WordPress
 * @subpackage HTTP
 * @since 4.4.0
 */

/**
 * Core class used to implement deflate and gzip transfer encoding support for HTTP requests.
 *
 * Includes RFC 1950, RFC 1951, and RFC 1952.
 *
 * @since 2.8.0
 */
class WP_Http_Encoding {

	/**
	 * Compress raw string using the deflate format.
	 *
	 * Supports the RFC 1951 standard.
	 *
	 * @since 2.8.0
	 *
	 * @static
	 *
	 * @param string $raw String to compress.
	 * @param int $level Optional, default is 9. Compression level, 9 is highest.
	 * @param string $supports Optional, not used. When implemented it will choose the right compression based on what the server supports.
	 * @return string|false False on failure.
	 */
	public static function compress( $raw, $level = 9, $supports = null ) {
		return gzdeflate( $raw, $level );
	}

	/**
	 * Decompression of deflated string.
	 *
	 * Will attempt to decompress using the RFC 1950 standard, and if that fails
	 * then the RFC 1951 standard deflate will be attempted. Finally, the RFC
	 * 1952 standard gzip decode will be attempted. If all fail, then the
	 * original compressed string will be returned.
	 *
	 * @since 2.8.0
	 *
	 * @static
	 *
	 * @param string $compressed String to decompress.
	 * @param int $length The optional length of the compressed data.
	 * @return string|bool False on failure.
	 */
	public static function decompress( $compressed, $length = null ) {

		if ( empty($compressed) )
			return $compressed;

		if ( false !== ( $decompressed = @gzinflate( $compressed ) ) )
			return $decompressed;

		if ( false !== ( $decompressed = self::compatible_gzinflate( $compressed ) ) )
			return $decompressed;

		if ( false !== ( $decompressed = @gzuncompress( $compressed ) ) )
			return $decompressed;

		if ( function_exists('gzdecode') ) {
			$decompressed = @gzdecode( $compressed );

			if ( false !== $decompressed )
				return $decompressed;
		}

		return $compressed;
	}

	/**
	 * Decompression of deflated string while staying compatible with the majority of servers.
	 *
	 * Certain Servers will return deflated data with headers which PHP's gzinflate()
	 * function cannot handle out of the box. The following function has been created from
	 * various snippets on the gzinflate() PHP documentation.
	 *
	 * Warning: Magic numbers within. Due to the potential different formats that the compressed
	 * data may be returned in, some "magic offsets" are needed to ensure proper decompression
	 * takes place. For a simple progmatic way to determine the magic offset in u