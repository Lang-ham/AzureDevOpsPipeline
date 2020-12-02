<?php
/**
 * Administration: Community Events class.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 4.8.0
 */

/**
 * Class WP_Community_Events.
 *
 * A client for api.wordpress.org/events.
 *
 * @since 4.8.0
 */
class WP_Community_Events {
	/**
	 * ID for a WordPress user account.
	 *
	 * @since 4.8.0
	 *
	 * @var int
	 */
	protected $user_id = 0;

	/**
	 * Stores location data for the user.
	 *
	 * @since 4.8.0
	 *
	 * @var bool|array
	 */
	protected $user_location = false;

	/**
	 * Constructor for WP_Community_Events.
	 *
	 * @since 4.8.0
	 *
	 * @param int        $user_id       WP user ID.
	 * @param bool|array $user_location Stored location data for the user.
	 *                                  false to pass no location;
	 *                                  array to pass a location {
	 *     @type string $description The name of the location
	 *     @type string $latitude    The latitude in decimal degrees notation, without the degree
	 *                               symbol. e.g.: 47.615200.
	 *     @type string $longitude   The longitude in decimal degrees notation, without the degree
	 *                               symbol. e.g.: -122.341100.
	 *     @type string $country     The ISO 3166-1 alpha-2 country code. e.g.: BR
	 * }
	 */
	public function __construct( $user_id, $user_location = false ) {
		$this->user_id       = absint( $user_id );
		$this->user_location = $user_location;
	}

	/**
	 * Gets data about events near a particular location.
	 *
	 * Cached events will be immediately returned if the `user_location` property
	 * is set for the current user, and cached events exist for that location.
	 *
	 * Otherwise, this method sends a request to the w.org Events API with location
	 * data. The API will send back a recognized location based on the data, along
	 * with nearby events.
	 *
	 * The browser's request for events is proxied with this method, rather
	 * than having the browser make the request directly to api.wordpress.org,
	 * because it allows results to be cached server-side and shared with other
	 * users and sites in the network. This makes the process more efficient,
	 * since increasing the number of visits that get cached data means users
	 * don't have to wait as often; if the user's browser made the request
	 * directly, it would also need to make a second request to WP in order to
	 * pass the data for caching. Having WP make the request also introduces
	 * the opportunity to anonymize the IP before sending it to w.org, which
	 * mitigates possible privacy concerns.
	 *
	 * @since 4.8.0
	 *
	 * @param string $location_search Optional. City name to help determine the location.
	 *                                e.g., "Seattle". Default empty string.
	 * @param string $timezone        Optional. Timezone to help determine the location.
	 *                                Default empty string.
	 * @return array|WP_Error A WP_Error on failure; an array with location and events on
	 *                        success.
	 */
	public function get_events( $location_search = '', $timezone = '' ) {
		$cached_events = $this->get_cached_events();

		if ( ! $location_search && $cached_events ) {
			return $cached_events;
		}

		// include an unmodified $wp_version
		include( ABSPATH . WPINC . '/version.php' );

		$api_url      = 'http://api.wordpress.org/events/1.0/';
		$request_args = $this->get_request_args( $location_search, $timezone );
		$request_args['user-agent'] = 'WordPress/' . $wp_version . '; ' . home_url( '/' );

		if ( wp_http_supports( array( 'ssl' ) ) ) {
			$api_url = set_url_scheme( $api_url, 'https' );
		}

		$response       = wp_remote_get( $api_url, $request_args );
		$response_code  = wp_remote_retrieve_response_code( $response );
		$response_body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$response_error = null;

		if ( is_wp_error( $response ) ) {
			$response_error = $response;
		} elseif ( 200 !== $response_code ) {
			$response_error = new WP_Error(
				'api-error',
				/* translators: %d: numeric HTTP status code, e.g. 400, 403, 500, 504, etc. */
				sprintf( __( 'Invalid API response code (%d)' ), $response_code )
			);
		} elseif ( ! isset( $response_body['location'], $response_body['events'] ) ) {
			$response_error = new WP_Error(
				'api-invalid-response',
				isset( $response_body['error'] ) ? $response_body['error'] : __( 'Unknown API error.' )
			);
		}

		if ( is_wp_error( $response_error ) ) {
			return $response_error;
		} else {
			$expiration = false;

			if ( isset( $response_body['ttl'] ) ) {
				$expiration = $response_body['ttl'];
				unset( $response_body['ttl'] );
			}

			/*
			 * The IP in the response is usually the same as the one that was sent
			 * in the request, but in some cases it is different. In those cases,
			 * it's important to reset it back to the IP from the request.
			 *
			 * For example, if the IP sent in the request is private (e.g., 192.168.1.100),
			 * then the API will ignore that and use the corresponding public IP instead,
			 * and the public IP will get returned. If the public IP were saved, though,
			 * then get_cached_events() would always return `false`, because the transient
			 * would be generated based on the public IP when saving the cache, but generated
			 * based on the private IP when retrieving the cache.
			 */
			if ( ! empty( $response_body['location']['ip'] ) ) {
				