<?php
/**
 * HTTP API: WP_Http_Streams class
 *
 * @package WordPress
 * @subpackage HTTP
 * @since 4.4.0
 */

/**
 * Core class used to integrate PHP Streams as an HTTP transport.
 *
 * @since 2.7.0
 * @since 3.7.0 Combined with the fsockopen transport and switched to `stream_socket_client()`.
 */
class WP_Http_Streams {
	/**
	 * Send a HTTP request to a URI using PHP Streams.
	 *
	 * @see WP_Http::request For default options descriptions.
	 *
	 * @since 2.7.0
	 * @since 3.7.0 Combined with the fsockopen transport and switched to stream_socket_client().
	 *
	 * @param string $url The request URL.
	 * @param string|array $args Optional. Override the defaults.
	 * @return array|WP_Error Array containing 'headers', 'body', 'response', 'cookies', 'filename'. A WP_Error instance upon error
	 */
	public function request($url, $args = array()) {
		$defaults = array(
			'method' => 'GET', 'timeout' => 5,
			'redirection' => 5, 'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(), 'body' => null, 'cookies' => array()
		);

		$r = wp_parse_args( $args, $defaults );

		if ( isset( $r['headers']['User-Agent'] ) ) {
			$r['user-agent'] = $r['headers']['User-Agent'];
			unset( $r['headers']['User-Agent'] );
		} elseif ( isset( $r['headers']['user-agent'] ) ) {
			$r['user-agent'] = $r['headers']['user-agent'];
			unset( $r['headers']['user-agent'] );
		}

		// Construct Cookie: header if any cookies are set.
		WP_Http::buildCookieHeader( $r );

		$arrURL = parse_url($url);

		$connect_host = $arrURL['host'];

		$secure_transport = ( $arrURL['scheme'] == 'ssl' || $arrURL['scheme'] == 'https' );
		if ( ! isset( $arrURL['port'] ) ) {
			if ( $arrURL['scheme'] == 'ssl' || $arrURL['scheme'] == 'https' ) {
				$arrURL['port'] = 443;
				$secure_transport = true;
			} else {
				$arrURL['port'] = 80;
			}
		}

		// Always pass a Path, defaulting to the root in cases such as http://example.com
		if ( ! isset( $arrURL['path'] ) ) {
			$arrURL['path'] = '/';
		}

		if ( isset( $r['headers']['Host'] ) || isset( $r['headers']['host'] ) ) {
			if ( isset( $r['headers']['Host'] ) )
				$arrURL['host'] = $r['headers']['Host'];
			else
				$arrURL['host'] = $r['headers']['host'];
			unset( $r['headers']['Host'], $r['headers']['host'] );
		}

		/*
		 * Certain versions of PHP have issues with 'localhost' and IPv6, It attempts to connect
		 * to ::1, which fails when the server is not set up for it. For compatibility, always
		 * connect to the IPv4 address.
		 */
		if ( 'localhost' == strtolower( $connect_host ) )
			$connect_host = '127.0.0.1';

		$connect_host = $secure_transport ? 'ssl://' . $connect_host : 'tcp://' . $connect_host;

		$is_local = isset( $r['local'] ) && $r['local'];
		$ssl_verify = isset( $r['sslverify'] ) && $r['sslverify'];
		if ( $is_local ) {
			/**
			 * Filters whether SSL should be verified for local requests.
			 *
			 * @since 2.8.0
			 *
			 * @param bool $ssl_verify Whether to verify the SSL connection. Default true.
			 */
			$ssl_verify = apply_filters( 'https_local_ssl_verify', $ssl_verify );
		} elseif ( ! $is_local ) {
			/**
			 * Filters whether SSL should be verified for non-local requests.
			 *
			 * @since 2.8.0
			 *
			 * @param bool $ssl_verify Whether to verify the SSL connection. Default true.
			 */
			$ssl_verify = apply_filters( 'https_ssl_verify', $ssl_verify );
		}

		$proxy = new WP_HTTP_Proxy();

		$context = stream_context_create( array(
			'ssl' => array(
				'verify_peer' => $ssl_verify,
				//'CN_match' => $arrURL['host'], // This is handled by self::verify_ssl_certificate()
				'capture_peer_cert' => $ssl_verify,
				'SNI_enabled' => true,
				'cafile' => $r['sslcertificates'],
				'allow_self_signed' => ! $ssl_verify,
			)
		) );

		$timeout = (int) floor( $r['timeout'] );
		$utimeout = $timeout == $r['timeout'] ? 0 : 1000000 * $r['timeout'] % 1000000;
		$connect_timeout = max( $timeout, 1 );

		// Store error number.
		$connection_error = null;

		// Store error string.
		$connection_error_str = null;

		if ( !WP_DEBUG ) {
			// In the event that the SSL connection fails, silence the many PHP Warnings.
			if ( $secure_transport )
				$error_reporting = error_reporting(0);

			if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) )
				$handle = @stream_socket_client