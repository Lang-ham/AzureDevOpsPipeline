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
			wp