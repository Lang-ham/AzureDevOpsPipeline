<?php
/**
 * A simple set of functions to check our version 1.0 update service.
 *
 * @package WordPress
 * @since 2.3.0
 */

/**
 * Check WordPress version against the newest version.
 *
 * The WordPress version, PHP version, and Locale is sent. Checks against the
 * WordPress server at api.wordpress.org server. Will only check if WordPress
 * isn't installing.
 *
 * @since 2.3.0
 * @global string $wp_version Used to check against the newest WordPress version.
 * @global wpdb   $wpdb
 * @global string $wp_local_package
 *
 * @param array $extra_stats Extra statistics to report to the WordPress.org API.
 * @param bool  $force_check Whether to bypass the transient cache and force a fresh update check. Defaults to false, true if $extra_stats is set.
 */
function wp_version_check( $extra_stats = array(), $force_check = false ) {
	if ( wp_installing() ) {
		return;
	}

	global $wpdb, $wp_local_package;
	// include an unmodified $wp_version
	include( ABSPATH . WPINC . '/version.php' );
	$php_version = phpversion();

	$current = get_site_transient( 'update_core' );
	$translations = wp_get_installed_translations( 'core' );

	// Invalidate the transient when $wp_version changes
	if ( is_object( $current ) && $wp_version != $current->version_checked )
		$current = false;

	if ( ! is_object($current) ) {
		$current = new stdClass;
		$current->updates = array();
		$current->version_checked = $wp_version;
	}

	if ( ! empty( $extra_stats ) )
		$force_check = true;

	// Wait 60 seconds between multiple version check requests
	$timeout = 60;
	$time_not_changed = isset( $current->last_checked ) && $timeout > ( time() - $current->last_checked );
	if ( ! $force_check && $time_not_changed ) {
		return;
	}

	/**
	 * Filters the locale requested for WordPress core translations.
	 *
	 * @since 2.8.0
	 *
	 * @param string $locale Current locale.
	 */
	$locale = apply_filters( 'core_version_check_locale', get_locale() );

	// Update last_checked for current to prevent multiple blocking requests if request hangs
	$current->last_checked = time();
	set_site_transient( 'update_core', $current );

	if ( method_exists( $wpdb, 'db_version' ) )
		$mysql_version = preg_replace('/[^0-9.].*/', '', $wpdb->db_version());
	else
		$mysql_version = 'N/A';

	if ( is_multisite() ) {
		$user_count = get_user_count();
		$num_blogs = get_blog_count();
		$wp_install = network_site_url();
		$multisite_enabled = 1;
	} else {
		$user_count = count_users();
		$user_count = $user_count['total_users'];
		$multisite_enabled = 0;
		$num_blogs = 1;
		$wp_install = home_url( '/' );
	}

	$query = array(
		'version'            => $wp_version,
		'php'                => $php_version,
		'locale'             => $locale,
		'mysql'              => $mysql_version,
		'local_package'      => isset( $wp_local_package ) ? $wp_local_package : '',
		'blogs'              => $num_blogs,
		'users'              => $user_count,
		'multisite_enabled'  => $multisite_enabled,
		'initial_db_version' => get_site_option( 'initial_db_version' ),
	);

	/**
	 * Filter the query arguments sent as part of the core version check.
	 *
	 * WARNING: Changing this data may result in your site not receiving security updates.
	 * Please exercise extreme caution.
	 *
	 * @since 4.9.0
	 *
	 * @param array $query {
	 *     Version check query arguments. 
	 *
	 *     @type string $version            WordPress version number.
	 *     @type string $php                PHP version number.
	 *     @type string $locale             The locale to retrieve updates for.
	 *     @type string $mysql              MySQL version number.
	 *     @type string $local_package      The value of the $wp_local_package global, when set.
	 *     @type int    $blogs              Number of sites on this WordPress installation.
	 *     @type int    $users              Number of users on this WordPress installation.
	 *     @type int    $multisite_enabled  Whether this WordPress installation uses Multisite.
	 *     @type int    $initial_db_version Database version of WordPress at time of installation.
	 * }
	 */
	$query = apply_filters( 'core_version_check_query_args', $query );

	$post_body = array(
		'translations' => wp_json_encode( $translations ),
	);

	if ( is_array( $extra_stats ) )
		$post_body = array_merge( $post_body, $extra_stats );

	$url = $http_url = 'http://api.wordpress.org/core/version-check/1.7/?' . http_build_query( $query, null, '&' );
	if ( $ssl = wp_http_supports( array( 'ssl' ) ) )
		$url = set_url_scheme( $url, 'https' );

	$doing_cron = wp_doing_cron();

	$options = array(
		'timeout' => $doing_cron ? 30 : 3,
		'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url( '/' ),
		'headers' => array(
			'wp_install' => $wp_install,
			'wp_blog' => home_url( '/' )
		),
		'body' => $post_body,
	);

	$response = wp_remote_post( $url, $options );
	if ( $ssl && is_wp_error( $response ) ) {
		trigger_error(
			sprintf(
				/* translators: %s: support forums URL */
				__( 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="%s">support forums</a>.' ),
				__( 'https://wordpress.org/support/' )
			) . ' ' . __( '(WordPress could not establish a secure connection to WordPress.org. Please contact your server administrator.)' ),
			headers_sent() || WP_DEBUG ? E_USER_WARNING : E_USER_NOTICE
		);
		$response = wp_remote_post( $http_url, $options );
	}

	if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
		return;
	}

	$body = trim( wp_remote_retrieve_body( $response ) );
	$body = json_decode( $body, true );

	if ( ! is_array( $body ) || ! isset( $body['offers'] ) ) {
		return;
	}

	$offers = $body['offers'];

	foreach ( $offers as &$offer ) {
		foreach ( $offer as $offer_key => $value ) {
			if ( 'packages' == $offer_key )
				$offer['packages'] = (object) array_intersect_key( array_map( 'esc_url', $offer['packages'] ),
					array_fill_keys( array( 'full', 'no_content', 'new_bundled', 'partial', 'rollback' ), '' ) );
			elseif ( 'download' == $offer_key )
				$offer['download'] = esc_url( $value );
			else
				$offer[ $offer_key ] = esc_html( $value );
		}
		$offer = (object) array_intersect_key( $offer, array_fill_keys( array( 'response', 'download', 'locale',
			'packages', 'current', 'version', 'php_version', 'mysql_version', 'new_bundled', 'partial_version', 'notify_email', 'support_email', 'new_files' ), '' ) );
	}

	$updates = new stdClass();
	$updates->updates = $offers;
	$updates->last_checked = time();
	$updates->version_checked = $wp_version;

	if ( isset( $body['translations'] ) )
		$updates->translations = $body['translations'];

	set_site_transient( 'update_core', $updates );

	if ( ! empty( $body['ttl'] ) ) {
		$ttl = (int) $body['ttl'];
		if ( $ttl && ( time() + $ttl < wp_next_scheduled( 'wp_version_check' ) ) ) {
			// Queue an event to re-run the update check in $ttl seconds.
			wp_schedule_single_event( time() + $ttl, 'wp_version_check' );
		}
	}

	// Trigger background updates if running non-interactively, and we weren't called from the update handler.
	if ( $doing_cron && ! doing_action( 'wp_maybe_auto_update' ) ) {
		do_action( 'wp_maybe_auto_update' );
	}
}

/**
 * Check plugin versions against the latest versions hosted on WordPress.org.
 *
 * The WordPress version, PHP version, and Locale is sent along with a list of
 * all plugins installed. Checks against the WordPress server at
 * api.wordpress.org. Will only check if WordPress isn't installing.
 *
 * @since 2.3.0
 * @global string $wp_version Used to notify the WordPress version.
 *
 * @param array $extra_stats Extra statistics to report to the WordPress.org API.
 */
function wp_update_plugins( $extra_stats = array() ) {
	if ( wp_installing() ) {
		return;
	}

	// include an unmodified $wp_version
	include( ABSPATH . WPINC . '/version.php' );

	// If running blog-side, bail unless we've not checked in the last 12 hours
	if ( !function_exists( 'get_plugins' ) )
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

	$plugins = get_plugins();
	$translations = wp_get_installed_translations( 'plugins' );

	$active  = get_option( 'active_plugins', array() );
	$current = get_site_transient( 'update_pl