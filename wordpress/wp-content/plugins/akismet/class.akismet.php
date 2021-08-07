<?php

class Akismet {
	const API_HOST = 'rest.akismet.com';
	const API_PORT = 80;
	const MAX_DELAY_BEFORE_MODERATION_EMAIL = 86400; // One day in seconds

	private static $last_comment = '';
	private static $initiated = false;
	private static $prevent_moderation_email_for_these_comments = array();
	private static $last_comment_result = null;
	private static $comment_as_submitted_allowed_keys = array( 'blog' => '', 'blog_charset' => '', 'blog_lang' => '', 'blog_ua' => '', 'comment_agent' => '', 'comment_author' => '', 'comment_author_IP' => '', 'comment_author_email' => '', 'comment_author_url' => '', 'comment_content' => '', 'comment_date_gmt' => '', 'comment_tags' => '', 'comment_type' => '', 'guid' => '', 'is_test' => '', 'permalink' => '', 'reporter' => '', 'site_domain' => '', 'submit_referer' => '', 'submit_uri' => '', 'user_ID' => '', 'user_agent' => '', 'user_id' => '', 'user_ip' => '' );
	private static $is_rest_api_call = false;
	
	public static function init() {
		if ( ! self::$initiated ) {
			self::init_hooks();
		}
	}

	/**
	 * Initializes WordPress hooks
	 */
	private static function init_hooks() {
		self::$initiated = true;

		add_action( 'wp_insert_comment', array( 'Akismet', 'auto_check_update_meta' ), 10, 2 );
		add_filter( 'preprocess_comment', array( 'Akismet', 'auto_check_comment' ), 1 );
		add_filter( 'rest_pre_insert_comment', array( 'Akismet', 'rest_auto_check_comment' ), 1 );

		add_action( 'akismet_scheduled_delete', array( 'Akismet', 'delete_old_comments' ) );
		add_action( 'akismet_scheduled_delete', array( 'Akismet', 'delete_old_comments_meta' ) );
		add_action( 'akismet_schedule_cron_recheck', array( 'Akismet', 'cron_recheck' ) );

		add_action( 'comment_form',  array( 'Akismet',  'add_comment_nonce' ), 1 );

		add_action( 'admin_head-edit-comments.php', array( 'Akismet', 'load_form_js' ) );
		add_action( 'comment_form', array( 'Akismet', 'load_form_js' ) );
		add_action( 'comment_form', array( 'Akismet', 'inject_ak_js' ) );
		add_filter( 'script_loader_tag', array( 'Akismet', 'set_form_js_async' ), 10, 3 );

		add_filter( 'comment_moderation_recipients', array( 'Akismet', 'disable_moderation_emails_if_unreachable' ), 1000, 2 );
		add_filter( 'pre_comment_approved', array( 'Akismet', 'last_comment_status' ), 10, 2 );
		
		add_action( 'transition_comment_status', array( 'Akismet', 'transition_comment_status' ), 10, 3 );

		// Run this early in the pingback call, before doing a remote fetch of the source uri
		add_action( 'xmlrpc_call', array( 'Akismet', 'pre_check_pingback' ) );
		
		// Jetpack compatibility
		add_filter( 'jetpack_options_whitelist', array( 'Akismet', 'add_to_jetpack_options_whitelist' ) );
		add_action( 'update_option_wordpress_api_key', array( 'Akismet', 'updated_option' ), 10, 2 );
	}

	public static function get_api_key() {
		return apply_filters( 'akismet_get_api_key', defined('WPCOM_API_KEY') ? constant('WPCOM_API_KEY') : get_option('wordpress_api_key') );
	}

	public static function check_key_status( $key, $ip = null ) {
		return self::http_post( Akismet::build_query( array( 'key' => $key, 'blog' => get_option( 'home' ) ) ), 'verify-key', $ip );
	}

	public static function verify_key( $key, $ip = null ) {
		$response = self::check_key_status( $key, $ip );

		if ( $response[1] != 'valid' && $response[1] != 'invalid' )
			return 'failed';

		return $response[1];
	}

	public static function deactivate_key( $key ) {
		$response = self::http_post( Akismet::build_query( array( 'key' => $key, 'blog' => get_option( 'home' ) ) ), 'deactivate' );

		if ( $response[1] != 'deactivated' )
			return 'failed';

		return $response[1];
	}

	/**
	 * Add the akismet option to the Jetpack options management whitelist.
	 *
	 * @param array $options The list of whitelisted option names.
	 * @return array The updated whitelist
	 */
	public static function add_to_jetpack_options_whitelist( $options ) {
		$options[] = 'wordpress_api_key';
		return $options;
	}

	/**
	 * When the akismet option is updated, run the registration call.
	 *
	 * This should only be run when the option is updated from the Jetpack/WP.com
	 * API call, and only if the new key is different than the old key.
	 *
	 * @param mixed  $old_value   The old option value.
	 * @param mixed  $value       The new option value.
	 */
	public static function updated_option( $old_value, $value ) {
		// Not an API call
		if ( ! class_exists( 'WPCOM_JSON_API_Update_Option_Endpoint' ) ) {
			return;
		}
		// Only run the registration if the old key is different.
		if ( $old_value !== $value ) {
			self::verify_key( $value );
		}
	}
	
	public static function rest_auto_check_comment( $commentdata ) {
		self::$is_rest_api_call = true;
		
		return self::auto_check_comment( $commentdata );
	}

	public static function auto_check_comment( $commentdata ) {
		self::$last_comment_result = null;

		$comment = $commentdata;

		$comment['user_ip']      = self::get_ip_address();
		$comment['user_agent']   = self::get_user_agent();
		$comment['referrer']     = self::get_referer();
		$comment['blog']         = get_option( 'home' );
		$comment['blog_lang']    = get_locale();
		$comment['blog_charset'] = get_option('blog_charset');
		$comment['permalink']    = get_permalink( $comment['comment_post_ID'] );

		if ( ! empty( $comment['user_ID'] ) ) {
			$comment['user_role'] = Akismet::get_user_roles( $comment['user_ID'] );
		}

		/** See filter documentation in init_hooks(). */
		$akismet_nonce_option = apply_filters( 'akismet_comment_nonce', get_option( 'akismet_comment_nonce' ) );
		$comment['akismet_comment_nonce'] = 'inactive';
		if ( $akismet_nonce_option == 'true' || $akismet_nonce_option == '' ) {
			$comment['akismet_comment_nonce'] = 'failed';
			if ( isset( $_POST['akismet_comment_nonce'] ) && wp_verify_nonce( $_POST['akismet_comment_nonce'], 'akismet_comment_nonce_' . $comment['comment_post_ID'] ) )
				$comment['akismet_comment_nonce'] = 'passed';

			// comment reply in wp-admin
			if ( isset( $_POST['_ajax_nonce-replyto-comment'] ) && check_ajax_referer( 'replyto-comment', '_ajax_nonce-replyto-comment' ) )
				$comment['akismet_comment_nonce'] = 'passed';

		}

		if ( self::is_test_mode() )
			$comment['is_test'] = 'true';

		foreach( $_POST as $key => $value ) {
			if ( is_string( $value ) )
				$comment["POST_{$key}"] = $value;
		}

		foreach ( $_SERVER as $key => $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			if ( preg_match( "/^HTTP_COOKIE/", $key ) ) {
				continue;
			}

			// Send any potentially useful $_SERVER vars, but avoid sending junk we don't need.
			if ( preg_match( "/^(HTTP_|REMOTE_ADDR|REQUEST_URI|DOCUMENT_URI)/", $key ) ) {
				$comment[ "$key" ] = $value;
			}
		}

		$post = get_post( $comment['comment_post_ID'] );

		if ( ! is_null( $post ) ) {
			// $post can technically be null, although in the past, it's always been an indicator of another plugin interfering.
			$comment[ 'comment_post_modified_gmt' ] = $post->post_modified_gmt;
		}

		$response = self::http_post( Akismet::build_query( $comment ), 'comment-check' );

		do_action( 'akismet_comment_check_response', $response );

		$commentdata['comment_as_submitted'] = array_intersect_key( $comment, self::$comment_as_submitted_allowed_keys );
		$commentdata['akismet_result']       = $response[1];

		if ( isset( $response[0]['x-akismet-pro-tip'] ) )
	        $commentdata['akismet_pro_tip'] = $response[0]['x-akismet-pro-tip'];

		if ( isset( $response[0]['x-akismet-error'] ) ) {
			// An error occurred that we anticipated (like a suspended key) and want the user to act on.
			// Send to moderation.
			self::$last_comment_result = '0';
		}
		else if ( 'true' == $response[1] ) {
			// akismet_spam_count will be incremented later by comment_is_spam()
			self::$last_comment_result = 'spam';

			$discard = ( isset( $commentdata['akismet_pro_tip'] ) && $commentdata['akismet_pro_tip'] === 'discard' && self::allow_discard() );

			do_action( 'akismet_spam_caught', $discard );

			if ( $discard ) {
				// The spam is obvious, so we're bailing out early. 
				// akismet_result_spam() won't be called so bump