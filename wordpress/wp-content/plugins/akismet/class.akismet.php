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
				// akismet_result_spam() won't be called so bump the counter here
				if ( $incr = apply_filters( 'akismet_spam_count_incr', 1 ) ) {
					update_option( 'akismet_spam_count', get_option( 'akismet_spam_count' ) + $incr );
				}

				if ( self::$is_rest_api_call ) {
					return new WP_Error( 'akismet_rest_comment_discarded', __( 'Comment discarded.', 'akismet' ) );
				}
				else {
					// Redirect back to the previous page, or failing that, the post permalink, or failing that, the homepage of the blog.
					$redirect_to = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : ( $post ? get_permalink( $post ) : home_url() );
					wp_safe_redirect( esc_url_raw( $redirect_to ) );
					die();
				}
			}
			else if ( self::$is_rest_api_call ) {
				// The way the REST API structures its calls, we can set the comment_approved value right away.
				$commentdata['comment_approved'] = 'spam';
			}
		}
		
		// if the response is neither true nor false, hold the comment for moderation and schedule a recheck
		if ( 'true' != $response[1] && 'false' != $response[1] ) {
			if ( !current_user_can('moderate_comments') ) {
				// Comment status should be moderated
				self::$last_comment_result = '0';
			}

			if ( ! wp_next_scheduled( 'akismet_schedule_cron_recheck' ) ) {
				wp_schedule_single_event( time() + 1200, 'akismet_schedule_cron_recheck' );
				do_action( 'akismet_scheduled_recheck', 'invalid-response-' . $response[1] );
			}

			self::$prevent_moderation_email_for_these_comments[] = $commentdata;
		}

		// Delete old comments daily
		if ( ! wp_next_scheduled( 'akismet_scheduled_delete' ) ) {
			wp_schedule_event( time(), 'daily', 'akismet_scheduled_delete' );
		}

		self::set_last_comment( $commentdata );
		self::fix_scheduled_recheck();

		return $commentdata;
	}
	
	public static function get_last_comment() {
		return self::$last_comment;
	}
	
	public static function set_last_comment( $comment ) {
		if ( is_null( $comment ) ) {
			self::$last_comment = null;
		}
		else {
			// We filter it here so that it matches the filtered comment data that we'll have to compare against later.
			// wp_filter_comment expects comment_author_IP
			self::$last_comment = wp_filter_comment(
				array_merge(
					array( 'comment_author_IP' => self::get_ip_address() ),
					$comment
				)
			);
		}
	}

	// this fires on wp_insert_comment.  we can't update comment_meta when auto_check_comment() runs
	// because we don't know the comment ID at that point.
	public static function auto_check_update_meta( $id, $comment ) {
		// wp_insert_comment() might be called in other contexts, so make sure this is the same comment
		// as was checked by auto_check_comment
		if ( is_object( $comment ) && !empty( self::$last_comment ) && is_array( self::$last_comment ) ) {
			if ( self::matches_last_comment( $comment ) ) {
					
					load_plugin_textdomain( 'akismet' );
					
					// normal result: true or false
					if ( self::$last_comment['akismet_result'] == 'true' ) {
						update_comment_meta( $comment->comment_ID, 'akismet_result', 'true' );
						self::update_comment_history( $comment->comment_ID, '', 'check-spam' );
						if ( $comment->comment_approved != 'spam' )
							self::update_comment_history(
								$comment->comment_ID,
								'',
								'status-changed-'.$comment->comment_approved
							);
					}
					elseif ( self::$last_comment['akismet_result'] == 'false' ) {
						update_comment_meta( $comment->comment_ID, 'akismet_result', 'false' );
						self::update_comment_history( $comment->comment_ID, '', 'check-ham' );
						// Status could be spam or trash, depending on the WP version and whether this change applies:
						// https://core.trac.wordpress.org/changeset/34726
						if ( $comment->comment_approved == 'spam' || $comment->comment_approved == 'trash' ) {
							if ( wp_blacklist_check($comment->comment_author, $comment->comment_author_email, $comment->comment_author_url, $comment->comment_content, $comment->comment_author_IP, $comment->comment_agent) )
								self::update_comment_history( $comment->comment_ID, '', 'wp-blacklisted' );
							else
								self::update_comment_history( $comment->comment_ID, '', 'status-changed-'.$comment->comment_approved );
						}
					} // abnormal result: error
					else {
						update_comment_meta( $comment->comment_ID, 'akismet_error', time() );
						self::update_comment_history(
							$comment->comment_ID,
							'',
							'check-error',
							array( 'response' => substr( self::$last_comment['akismet_result'], 0, 50 ) )
						);
					}

					// record the complete original data as submitted for checking
					if ( isset( self::$last_comment['comment_as_submitted'] ) )
						update_comment_meta( $comment->comment_ID, 'akismet_as_submitted', self::$last_comment['comment_as_submitted'] );

					if ( isset( self::$last_comment['akismet_pro_tip'] ) )
						update_comment_meta( $comment->comment_ID, 'akismet_pro_tip', self::$last_comment['akismet_pro_tip'] );
			}
		}
	}

	public static function delete_old_comments() {
		global $wpdb;

		/**
		 * Determines how many comments will be deleted in each batch.
		 *
		 * @param int The default, as defined by AKISMET_DELETE_LIMIT.
		 */
		$delete_limit = apply_filters( 'akismet_delete_comment_limit', defined( 'AKISMET_DELETE_LIMIT' ) ? AKISMET_DELETE_LIMIT : 10000 );
		$delete_limit = max( 1, intval( $delete_limit ) );

		/**
		 * Determines how many days a comment will be left in the Spam queue before being deleted.
		 *
		 * @param int The default number of days.
		 */
		$delete_interval = apply_filters( 'akismet_delete_comment_interval', 15 );
		$delete_interval = max( 1, intval( $delete_interval ) );

		while ( $comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->comments} WHERE DATE_SUB(NOW(), INTERVAL %d DAY) > comment_date_gmt AND comment_approved = 'spam' LIMIT %d", $delete_interval, $delete_limit ) ) ) {
			if ( empty( $comment_ids ) )
				return;

			$wpdb->queries = array();

			foreach ( $comment_ids as $comment_id ) {
				do_action( 'delete_comment', $comment_id );
			}

			// Prepared as strings since comment_id is an unsigned BIGINT, and using %d will constrain the value to the maximum signed BIGINT.
			$format_string = implode( ", ", array_fill( 0, count( $comment_ids ), '%s' ) );

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->comments} WHERE comment_id IN ( " . $format_string . " )", $comment_ids ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->commentmeta} WHERE comment_id IN ( " . $format_string . " )", $comment_ids ) );

			clean_comment_cache( $comment_ids );
			do_action( 'akismet_delete_comment_batch', count( $comment_ids ) );
		}

		if ( apply_filters( 'akismet_optimize_table', ( mt_rand(1, 5000) == 11), $wpdb->comments ) ) // lucky number
			$wpdb->query("OPTIMIZE TABLE {$wpdb->comments}");
	}

	public static function delete_old_comments_meta() {
		global $wpdb;

		$interval = apply_filters( 'akismet_delete_commentmeta_interval', 15 );

		# enfore a minimum of 1 day
		$interval = absint( $interval );
		if ( $interval < 1 )
			$interval = 1;

		// akismet_as_submitted meta values are large, so expire them
		// after $interval days regardless of the comment status
		while ( $comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT m.comment_id FROM {$wpdb->commentmeta} as m INNER JOIN {$wpdb->comments} as c USING(comment_id) WHERE m.meta_key = 'akismet_as_submitted' AND DATE_SUB(NOW(), INTERVAL %d DAY) > c.comment_date_gmt LIMIT 10000", $interval ) ) ) {
			if ( empty( $comment_ids ) )
				return;

			$wpdb->queries = array();

			foreach ( $comment_ids as $comment_id ) {
				delete_comment_meta( $comment_id, 'akismet_as_submitted' );
			}

			do_action( 'akismet_delete_commentmeta_batch', count( $comment_ids ) );
		}

		if ( apply_filters( 'akismet_optimize_table', ( mt_rand(1, 5000) == 11), $wpdb->commentmeta ) ) // lucky number
			$wpdb->query("OPTIMIZE TABLE {$wpdb->commentmeta}");
	}

	// how many approved comments does this author have?
	public static function get_user_comments_approved( $user_id, $comment_author_email, $comment_author, $comment_author_url ) {
		global $wpdb;

		if ( !empty( $user_id ) )
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE user_id = %d AND comment_approved = 1", $user_id ) );

		if ( !empty( $comment_author_email ) )
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_author_email = %s AND comment_author = %s AND comment_author_url = %s AND comment_approved = 1", $comment_author_email, $comment_author, $comment_author_url ) );

		return 0;
	}

	// get the full comment history for a given comment, as an array in reverse chronological order
	public static function get_comment_history( $comment_id ) {
		$history = get_comment_meta( $comment_id, 'akismet_history', false );
		usort( $history, array( 'Akismet', '_cmp_time' ) );
		return $history;
	}

	/**
	 * Log an event for a given comment, storing it in comment_meta.
	 *
	 * @param int $comment_id The ID of the relevant comment.
	 * @param string $message The string description of the event. No longer used.
	 * @param string $event The event code.
	 * @param array $meta Metadata about the history entry. e.g., the user that reported or changed the status of a given comment.
	 */
	public static function update_comment_history( $comment_id, $message, $event=null, $meta=null ) {
		global $current_user;

		$user = '';

		$event = array(
			'time'    => self::_get_microtime(),
			'event'   => $event,
		);
		
		if ( is_object( $current_user ) && isset( $current_user->user_login ) ) {
			$event['user'] = $current_user->user_login;
		}
		
		if ( ! empty( $meta ) ) {
			$event['meta'] = $meta;
		}

		// $unique = false so as to allow multiple values per comment
		$r = add_comment_meta( $comment_id, 'akismet_history', $event, false );
	}

	public static function check_db_comment( $id, $recheck_reason = 'recheck_queue' ) {
		global $wpdb;

		$c = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_ID = %d", $id ), ARRAY_A );
		
		if ( ! $c ) {
			return new WP_Error( 'invalid-comment-id', __( 'Comment not found.', 'akismet' ) );
		}

		$c['user_ip']        = $c['comment_author_IP'];
		$c['user_agent']     = $c['comment_agent'];
		$c['referrer']       = '';
		$c['blog']           = get_option( 'home' );
		$c['blog_lang']      = get_locale();
		$c['blog_charset']   = get_option('blog_charset');
		$c['permalink']      = get_permalink($c['comment_post_ID']);
		$c['recheck_reason'] = $recheck_reason;

		$c['user_role'] = '';
		if ( ! empty( $c['user_ID'] ) ) {
			$c['user_role'] = Akismet