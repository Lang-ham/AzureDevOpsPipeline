
<?php
/**
 * Core HTTP Request API
 *
 * Standardizes the HTTP requests for WordPress. Handles cookies, gzip encoding and decoding, chunk
 * decoding, if HTTP 1.1 and various other difficult HTTP protocol implementations.
 *
 * @package WordPress
 * @subpackage HTTP
 */

/**
 * Returns the initialized WP_Http Object
 *
 * @since 2.7.0
 * @access private
 *
 * @staticvar WP_Http $http
 *
 * @return WP_Http HTTP Transport object.
 */
function _wp_http_get_object() {
	static $http = null;

	if ( is_null( $http ) ) {
		$http = new WP_Http();
	}
	return $http;
}

/**
 * Retrieve the raw response from a safe HTTP request.
 *
 * This function is ideal when the HTTP request is being made to an arbitrary
 * URL. The URL is validated to avoid redirection and request forgery attacks.
 *
 * @since 3.6.0
 *
 * @see wp_remote_request() For more information on the response array format.
 * @see WP_Http::request() For default arguments information.
 *
 * @param string $url  Site URL to retrieve.
 * @param array  $args Optional. Request arguments. Default empty array.
 * @return WP_Error|array The response or WP_Error on failure.
 */
function wp_safe_remote_request( $url, $args = array() ) {
	$args['reject_unsafe_urls'] = true;
	$http = _wp_http_get_object();
	return $http->request( $url, $args );
}

/**
 * Retrieve the raw response from a safe HTTP request using the GET method.
 *
 * This function is ideal when the HTTP request is being made to an arbitrary
 * URL. The URL is validated to avoid redirection and request forgery attacks.
 *
 * @since 3.6.0
 *
 * @see wp_remote_request() For more information on the response array format.
 * @see WP_Http::request() For default arguments information.
 *
 * @param string $url  Site URL to retrieve.
 * @param array  $args Optional. Request arguments. Default empty array.
 * @return WP_Error|array The response or WP_Error on failure.
 */
function wp_safe_remote_get( $url, $args = array() ) {
	$args['reject_unsafe_urls'] = true;
	$http = _wp_http_get_object();
	return $http->get( $url, $args );
}

/**
 * Retrieve the raw response from a safe HTTP request using the POST method.
 *
 * This function is ideal when the HTTP request is being made to an arbitrary
 * URL. The URL is validated to avoid redirection and request forgery attacks.
 *
 * @since 3.6.0
 *
 * @see wp_remote_request() For more information on the response array format.
 * @see WP_Http::request() For default arguments information.
 *
 * @param string $url  Site URL to retrieve.
 * @param array  $args Optional. Request arguments. Default empty array.
 * @return WP_Error|array The response or WP_Error on failure.
 */
function wp_safe_remote_post( $url, $args = array() ) {
	$args['reject_unsafe_urls'] = true;
	$http = _wp_http_get_object();
	return $http->post( $url, $args );
}

/**
 * Retrieve the raw response from a safe HTTP request using the HEAD method.
 *
 * This function is ideal when the HTTP request is being made to an arbitrary
 * URL. The URL is validated to avoid redirection and request forgery attacks.
 *
 * @since 3.6.0
 *
 * @see wp_remote_request() For more information on the response array format.
 * @see WP_Http::request() For default arguments information.
 *
 * @param string $url Site URL to retrieve.
 * @param array $args Optional. Request arguments. Default empty array.
 * @return WP_Error|array The response or WP_Error on failure.
 */
function wp_safe_remote_head( $url, $args = array() ) {
	$args['reject_unsafe_urls'] = true;
	$http = _wp_http_get_object();
	return $http->head( $url, $args );
}

/**
 * Retrieve the raw response from the HTTP request.
 *
 * The array structure is a little complex:
 *
 *     $res = array(
 *         'headers'  => array(),
 *         'response' => array(
 *             'code'    => int,
 *             'message' => string
 *         )
 *     );
 *
 * All of the headers in $res['headers'] are with the name as the key and the
 * value as the value. So to get the User-Agent, you would do the following.