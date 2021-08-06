<?php

class Akismet_Admin {
	const NONCE = 'akismet-update-key';

	private static $initiated = false;
	private static $notices   = array();
	private static $allowed   = array(
	    'a' => array(
	        'href' => true,
	        'title' => true,
	    ),
	    'b' => array(),
	    'code' => array(),
	    'del' => array(
	        'datetime' => true,
	    ),
	    'em' => array(),
	    'i' => array(),
	    'q' => array(
	        'cite' => true,
	    ),
	    'strike' => array(),
	    'strong' => array(),
	);

	public static function init() {
		if ( ! self::$initiated ) {
			self::init_hooks();
		}

		if ( isset( $_POST['action'] ) && $_POST['action'] == 'enter-key' ) {
			self::enter_api_key();
		}
	}

	public static function init_hooks() {
		// The standalone stats page was removed in 3.0 for an all-in-one config and stats page.
		// Redirect any links that might have been bookmarked or in browser history.
		if ( isset( $_GET['page'] ) && 'akismet-stats-display' == $_GET['page'] ) {
			wp_safe_redirect( esc_url_raw( self::get_page_url( 'stats' ) ), 301 );
			die;
		}

		self::$initiated = true;

		add_action( 'admin_init', array( 'Akismet_Admin', 'admin_init' ) );
		add_action( 'admin_menu', array( 'Akismet_Admin', 'admin_menu' ), 5 ); # Priority 5, so it's called before Jetpack's admin_menu.
		add_action( 'admin_notices', array( 'Akismet_Admin', 'display_notice' ) );
		add_action( 'admin_enqueue_scripts', array( 'Akismet_Admin', 'load_resources' ) );
		add_action( 'activity_box_end', array( 'Akismet_Admin', 'dashboard_stats' ) );
		add_action( 'rightnow_end', array( 'Akismet_Admin', 'rightnow_stats' ) );
		add_action( 'manage_comments_nav', array( 'Akismet_Admin', 'check_for_spam_button' ) );
		add_action( 'admin_action_akismet_recheck_queue', array( 'Akismet_Admin', 'recheck_queue' ) );
		add_action( 'wp_ajax_akismet_recheck_queue', array( 'Akismet_Admin', 'recheck_queue' ) );
		add_action( 'wp_ajax_comment_author_deurl', array( 'Akismet_Admin', 'remove_comment_author_url' ) );
		add_action( 'wp_ajax_comment_author_reurl', array( 'Akismet_Admin', 'add_comment_author_url' ) );
		add_action( 'jetpack_auto_activate_akismet', array( 'Akismet_Admin', 'connect_jetpack_user' ) );

		add_filter( 'plugin_action_links', array( 'Akismet_Admin', 'plugin_action_links' ), 10, 2 );
		add_filter( 'comment_row_actions', array( 'Akismet_Admin', 'comment_row_action' ), 10, 2 );
		
		add_filter( 'plugin_action_links_'.plugin_basename( plugin_dir_path( __FILE__ ) . 'akismet.php'), array( 'Akismet_Admin', 'admin_plugin_settings_link' ) );
		
		add_filter( 'wxr_export_skip_commentmeta', array( 'Akismet_Admin', 'exclude_commentmeta_from_export' ), 10, 3 );
		
		add_filter( 'all_plugins', array( 'Akismet_Admin', 'modify_plugin_description' ) );
	}

	public static function admin_init() {
		load_plugin_textdomain( 'akismet' );
		add_meta_box( 'akismet-status', __('Comment History', 'akismet'), array( 'Akismet_Admin', 'comment_status_meta_box' ), 'comment', 'normal' );
	}

	public static function admin_menu() {
		if ( class_exists( 'Jetpack' ) )
			add_action( 'jetpack_admin_menu', array( 'Akismet_Admin', 'load_menu' ) );
		else
			self::load_menu();
	}

	public static function admin_head() {
		if ( !current_user_can( 'manage_options' ) )
			return;
	}
	
	public static function admin_plugin_settings_link( $links ) { 
  		$settings_link = '<a href="'.esc_url( self::get_page_url() ).'">'.__('Settings', 'akismet').'</a>';
  		array_unshift( $links, $settings_link ); 
  		return $links; 
	}

	public static function load_menu() {
		if ( class_exists( 'Jetpack' ) ) {
			$hook = add_submenu_page( 'jetpack', __( 'Akismet Anti-Spam' , 'akismet'), __( 'Akismet Anti-Spam' , 'akismet'), 'manage_options', 'akismet-key-config', array( 'Akismet_Admin', 'display_page' ) );
		}
		else {
			$hook = add_options_page( __('Akismet Anti-Spam', 'akismet'), __('Akismet Anti-Spam', 'akismet'), 'manage_options', 'akismet-key-config', array( 'Akismet_Admin', 'display_page' ) );
		}
		
		if ( $hook ) {
			add_action( "load-$hook", array( 'Akismet_Admin', 'admin_help' ) );
		}
	}

	public static function load_resources() {
		global $hook_suffix;

		if ( in_array( $hook_suffix, apply_filters( 'akismet_admin_page_hook_suffixes', array(
			'index.php', # dashboard
			'edit-comments.php',
			'comment.php',
			'post.php',
			'settings_page_akismet-key-config',
			'jetpack_page_akismet-key-config',
			'plugins.php',
		) ) ) ) {
			wp_register_style( 'akismet.css', plugin_dir_url( __FILE__ ) . '_inc/akismet.css', array(), AKISMET_VERSION );
			wp_enqueue_style( 'akismet.css');

			wp_register_script( 'akismet.js', plugin_dir_url( __FILE__ ) . '_inc/akismet.js', array('jquery'), AKISMET_VERSION );
			wp_enqueue_script( 'akismet.js' );
			
			$inline_js = array(
				'comment_author_url_nonce' => wp_create_nonce( 'comment_author_url_nonce' ),
				'strings' => array(
					'Remove this URL' => __( 'Remove this URL' , 'akismet'),
					'Removing...'     => __( 'Removing...' , 'akismet'),
					'URL removed'     => __( 'URL removed' , 'akismet'),
					'(undo)'          => __( '(undo)' , 'akismet'),
					'Re-adding...'    => __( 'Re-adding...' , 'akismet'),
				)
			);

			if ( isset( $_GET['akismet_recheck'] ) && wp_verify_nonce( $_GET['akismet_recheck'], 'akismet_recheck' ) ) {
				$inline_js['start_recheck'] = true;
			}

			wp_localize_script( 'akismet.js', 'WPAkismet', $inline_js );
		}
	}

	/**
	 * Add help to the Akismet page
	 *
	 * @return false if not the Akismet page
	 */
	public static function admin_help() {
		$current_screen = get_current_screen();

		// Screen Content
		if ( current_user_can( 'manage_options' ) ) {
			if ( !Akismet::get_api_key() || ( isset( $_GET['view'] ) && $_GET['view'] == 'start' ) ) {
				//setup page
				$current_screen->add_help_tab(
					array(
						'id'		=> 'overview',
						'title'		=> __( 'Overview' , 'akismet'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Akismet Setup' , 'akismet') . '</strong></p>' .
							'<p>' . esc_html__( 'Akismet filters out spam, so you can focus on more important things.' , 'akismet') . '</p>' .
							'<p>' . esc_html__( 'On this page, you are able to set up the Akismet plugin.' , 'akismet') . '</p>',
					)
				);

				$current_screen->add_help_tab(
					array(
						'id'		=> 'setup-signup',
						'title'		=> __( 'New to Akismet' , 'akismet'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Akismet Setup' , 'akismet') . '</strong></p>' .
							'<p>' . esc_html__( 'You need to enter an API key to activate the Akismet service on your site.' , 'akismet') . '</p>' .
							'<p>' . sprintf( __( 'Sign up for an account on %s to get an API Key.' , 'akismet'), '<a href="https://akismet.com/plugin-signup/" target="_blank">Akismet.com</a>' ) . '</p>',
					)
				);

				$current_screen->add_help_tab(
					array(
						'id'		=> 'setup-manual',
						'title'		=> __( 'Enter an API Key' , 'akismet'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Akismet Setup' , 'akismet') . '</strong></p>' .
							'<p>' . esc_html__( 'If you already have an API key' , 'akismet') . '</p>' .
							'<ol>' .
								'<li>' . esc_html__( 'Copy and paste the API key into the text field.' , 'akismet') . '</li>' .
								'<li>' . esc_html__( 'Click the Use this Key button.' , 'akismet') . '</li>' .
							'</ol>',
					)
				);
			}
			elseif ( isset( $_GET['view'] ) && $_GET['view'] == 'stats' ) {
				//stats page
				$current_screen->add_help_tab(
					array(
						'id'		=> 'overview',
						'title'		=> __( 'Overview' , 'akismet'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Akismet Stats' , 'akismet') . '</strong></p>' .
							'<p>' . esc_html__( 'Akismet filters out spam, so you can focus on more important things.' , 'akismet') . '</p>' .
							'<p>' . esc_html__( 'On this page, you are able to view stats on spam filtered on your site.' , 'akismet') . '</p>',
					)
				);
			}
			else {
				//configuration page
				$current_screen->add_help_tab(
					array(
						'id'		=> 'overview',
						'title'		=> __( 'Overview' , 'akismet'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Akismet Configuration' , 'akismet') . '</strong></p>' .
							'<p>' . esc_html__( 'Akismet filters out spam, so you can focus on more important things.' , 'akismet') . '</p>' .
							'<p>' . esc_html__( 'On this page, you are able to update your Akismet settings and view spam stats.' , 'akismet') . '</p>',
					)
				);

				$current_screen->add_help_tab(
					array(
						'id'		=> 'settings',
						'title'		=> __( 'Settings' , 'akismet'),
						'content'	=>
							'<p><strong>' . esc_html__( 'Akismet Configuration' , 'akismet') . '</strong></p>' .
							( Akismet::predefined_api_key() ? '' : '<p><strong>' . esc_html__( 'API Key' , 'akismet') . '</strong> - ' . esc_html__( 'Enter/remove an API key.' , 'akismet') . '</p>' ) .
							'<p><strong>' . esc_html__( 'Comments' , 'akismet') . '</strong> - ' . esc_html__( 'Show the number of approved comments beside each comment author in the comments list page.' , 'akismet') . '</p>' .
							'<p><strong>' . esc_html__( 'Strictness' , 'akismet') . '</strong> - ' . esc_html__( 'Choose to either discard the worst spam automatically or to always put all spam in spam folder.' , 'akismet') . '</p>',
					)
				);

				if ( ! Akismet::predefined_api_key() ) {
					$current_screen->add_help_tab(
						array(
							'id'		=> 'account',
							'title'		=> __( 'Account' , 'akismet'),
							'content'	=>
								'<p><strong>' . esc_html__( 'Akismet Configuration' , 'akismet') . '</strong></p>' .
								'<p><strong>' . esc_html__( 'Subscription Type' , 'akismet') . '</strong> - ' . esc_html__( 'The Akismet subscription plan' , 'akismet') . '</p>' .
								'<p><strong>' . esc_html__( 'Status' , 'akismet') . '</strong> - ' . esc_html__( 'The subscription status - active, cancelled or suspended' , 'akismet') . '</p>',
						)
					);
				}
			}
		}

		// Help Sidebar
		$current_screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'For more information:' , 'akismet') . '</strong></p>' .
			'<p><a href="https://akismet.com/faq/" target="_blank">'     . esc_html__( 'Akismet FAQ' , 'akismet') . '</a></p>' .
			'<p><a href="https://akismet.com/support/" target="_blank">' . esc_html__( 'Akismet Support' , 'akismet') . '</a></p>'
		);
	}

	public static function enter_api_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			die( __( 'Cheatin&#8217; uh?', 'akismet' ) );
		}

		if ( !wp_verify_nonce( $_POST['_wpnonce'], self::NONCE ) )
			return false;

		foreach( array( 'akismet_strictness', 'akismet_show_user_comments_approved' ) as $option ) {
			update_option( $option, isset( $_POST[$option] ) && (int) $_POST[$option] == 1 ? '1' : '0' );
		}
		
		if ( Akismet::predefined_api_key() ) {
			return false; //shouldn't have option to save key if already defined
		}
		
		$new_key = preg_replace( '/[^a-f0-9]/i', '', $_POST['key'] );
		$old_key = Akismet::get_api_key();

		if ( empty( $new_key ) ) {
			if ( !empty( $old_key ) ) {
				delete_option( 'wordpress_api_key' );
				self::$notices[] = 'new-key-empty';
			}
		}
		elseif ( $new_key != $old_key ) {
			self::save_key( $new_key );
		}

		return true;
	}

	public static function save_key( $api_key ) {
		$key_status = Akismet::verify_key( $api_key );

		if ( $key_status == 'valid' ) {
			$akismet_user = self::get_akismet_user( $api_key );
			
			if ( $akismet_user ) {				
				if ( in_array( $akismet_user->status, array( 'active', 'active-dunning', 'no-sub' ) ) )
					update_option( 'wordpress_api_key', $api_key );
				
				if ( $akismet_user->status == 'active' )
					self::$notices['status'] = 'new-key-valid';
				elseif ( $akismet_user->status == 'notice' )
					self::$notices['status'] = $akismet_user;
				else
					self::$notices['status'] = $akismet_user->status;
			}
			else
				self::$notices['status'] = 'new-key-invalid';
		}
		elseif ( in_array( $key_status, array( 'invalid', 'failed' ) ) )
			self::$notices['status'] = 'new-key-'.$key_status;
	}

	public static function dashboard_stats() {
		if ( did_action( 'rightnow_end' ) ) {
			return; // We already displayed this info in the "Right Now" section
		}

		if ( !$count = get_option('akismet_spam_count') )
			return;

		global $submenu;

		echo '<h3>' . esc_html( _x( 'Spam', 'comments' , 'akismet') ) . '</h3>';

		echo '<p>'.sprintf( _n(
				'<a href="%1$s">Akismet</a> has protected your site from <a href="%2$s">%3$s spam comment</a>.',
				'<a href="%1$s">Akismet</a> has protected your site from <a href="%2$s">%3$s spam comments</a>.',
				$count
			, 'akismet'), 'https://akismet.com/wordpress/', esc_url( add_query_arg( array( 'page' => 'akismet-admin' ), admin_url( isset( $submenu['edit-comments.php'] ) ? 'edit-comments.php' : 'edit.php' ) ) ), number_format_i18n($count) ).'</p>';
	}

	// WP 2.5+
	public static function rightnow_stats() {
		if ( $count = get_option('akismet_spam_count') ) {
			$intro = sprintf( _n(
				'<a href="%1$s">Akismet</a> has protected your site from %2$s spam comment already. ',
				'<a href="%1$s">Akismet</a> has protected your site from %2$s spam comments already. ',
				$count
			, 'akismet'), 'https://akismet.com/wordpress/', number_format_i18n( $count ) );
		} else {
			$intro = sprintf( __('<a href="%s">Akismet</a> blocks spam from getting to your blog. ', 'akismet'), 'https://akismet.com/wordpress/' );
		}

		$link = add_query_arg( array( 'comment_status' => 'spam' ), admin_url( 'edit-comments.php' ) );

		if ( $queue_count = self::get_spam_count() ) {
			$queue_text = sprintf( _n(
				'There&#8217;s <a href="%2$s">%1$s comment</a> in your spam queue right now.',
				'There are <a href="%2$s">%1$s comments</a> in your spam queue right now.',
				$queue_count
			, 'akismet'), number_format_i18n( $queue_count ), esc_url( $link ) );
		} else {
			$queue_text = sprintf( __( "There&#8217;s nothing in your <a href='%s'>spam queue</a> at the moment." , 'akismet'), esc_url( $link ) );
		}

		$text = $intro . '<br />' . $queue_text;
		echo "<p class='akismet-right-now'>$text</p>\n";
	}

	public static function check_for_spam_button( $comment_status ) {
		// The "Check for Spam" button should only appear when the page might be showing
		// a comment with comment_approved=0, which means an un-trashed, un-spammed,
		// not-yet-moderated comment.
		if ( 'all' != $comment_status && 'moderated' != $comment_status ) {
			return;
		}

		$link = add_query_arg( array( 'action' => 'akismet_recheck_queue' ), admin_url( 'admin.php' ) );

		$comments_count = wp_count_comments();
		
		echo '</div>';
		echo '<div class="alignleft">';
		echo '<a
				class="button-secondary checkforspam"
				href="' . esc_url( $link ) . '"
				data-active-label="' . esc_attr( __( 'Checking for Spam', 'akismet' ) ) . '"
				data-progress-label-format="' . esc_attr( __( '(%1$s%)', 'akismet' ) ) . '"
				data-success-url="' . esc_attr( remove_query_arg( 'akismet_recheck', add_query_arg( array( 'akismet_recheck_complete' => 1, 'recheck_count' => urlencode( '__recheck_count__' ), 'spam_count' => urlencode( '__spam_count__' ) ) ) ) ) . '"
				data-pending-comment-count="' . esc_attr( $comments_count->moderated ) . '"
				>';
			echo '<span class="akismet-label">' . esc_html__('Check for Spam', 'akismet') . '</span>';
			echo '<span class="checkforspam-progress"></span