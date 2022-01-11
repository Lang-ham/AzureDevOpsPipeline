<?php
/**
 * WordPress Customize Manager classes
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */

/**
 * Customize Manager class.
 *
 * Bootstraps the Customize experience on the server-side.
 *
 * Sets up the theme-switching process if a theme other than the active one is
 * being previewed and customized.
 *
 * Serves as a factory for Customize Controls and Settings, and
 * instantiates default Customize Controls and Settings.
 *
 * @since 3.4.0
 */
final class WP_Customize_Manager {
	/**
	 * An instance of the theme being previewed.
	 *
	 * @since 3.4.0
	 * @var WP_Theme
	 */
	protected $theme;

	/**
	 * The directory name of the previously active theme (within the theme_root).
	 *
	 * @since 3.4.0
	 * @var string
	 */
	protected $original_stylesheet;

	/**
	 * Whether this is a Customizer pageload.
	 *
	 * @since 3.4.0
	 * @var bool
	 */
	protected $previewing = false;

	/**
	 * Methods and properties dealing with managing widgets in the Customizer.
	 *
	 * @since 3.9.0
	 * @var WP_Customize_Widgets
	 */
	public $widgets;

	/**
	 * Methods and properties dealing with managing nav menus in the Customizer.
	 *
	 * @since 4.3.0
	 * @var WP_Customize_Nav_Menus
	 */
	public $nav_menus;

	/**
	 * Methods and properties dealing with selective refresh in the Customizer preview.
	 *
	 * @since 4.5.0
	 * @var WP_Customize_Selective_Refresh
	 */
	public $selective_refresh;

	/**
	 * Registered instances of WP_Customize_Setting.
	 *
	 * @since 3.4.0
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Sorted top-level instances of WP_Customize_Panel and WP_Customize_Section.
	 *
	 * @since 4.0.0
	 * @var array
	 */
	protected $containers = array();

	/**
	 * Registered instances of WP_Customize_Panel.
	 *
	 * @since 4.0.0
	 * @var array
	 */
	protected $panels = array();

	/**
	 * List of core components.
	 *
	 * @since 4.5.0
	 * @var array
	 */
	protected $components = array( 'widgets', 'nav_menus' );

	/**
	 * Registered instances of WP_Customize_Section.
	 *
	 * @since 3.4.0
	 * @var array
	 */
	protected $sections = array();

	/**
	 * Registered instances of WP_Customize_Control.
	 *
	 * @since 3.4.0
	 * @var array
	 */
	protected $controls = array();

	/**
	 * Panel types that may be rendered from JS templates.
	 *
	 * @since 4.3.0
	 * @var array
	 */
	protected $registered_panel_types = array();

	/**
	 * Section types that may be rendered from JS templates.
	 *
	 * @since 4.3.0
	 * @var array
	 */
	protected $registered_section_types = array();

	/**
	 * Control types that may be rendered from JS templates.
	 *
	 * @since 4.1.0
	 * @var array
	 */
	protected $registered_control_types = array();

	/**
	 * Initial URL being previewed.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	protected $preview_url;

	/**
	 * URL to link the user to when closing the Customizer.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	protected $return_url;

	/**
	 * Mapping of 'panel', 'section', 'control' to the ID which should be autofocused.
	 *
	 * @since 4.4.0
	 * @var array
	 */
	protected $autofocus = array();

	/**
	 * Messenger channel.
	 *
	 * @since 4.7.0
	 * @var string
	 */
	protected $messenger_channel;

	/**
	 * Whether the autosave revision of the changeset should be loaded.
	 *
	 * @since 4.9.0
	 * @var bool
	 */
	protected $autosaved = false;

	/**
	 * Whether the changeset branching is allowed.
	 *
	 * @since 4.9.0
	 * @var bool
	 */
	protected $branching = true;

	/**
	 * Whether settings should be previewed.
	 *
	 * @since 4.9.0
	 * @var bool
	 */
	protected $settings_previewed = true;

	/**
	 * Whether a starter content changeset was saved.
	 *
	 * @since 4.9.0
	 * @var bool
	 */
	protected $saved_starter_content_changeset = false;

	/**
	 * Unsanitized values for Customize Settings parsed from $_POST['customized'].
	 *
	 * @var array
	 */
	private $_post_values;

	/**
	 * Changeset UUID.
	 *
	 * @since 4.7.0
	 * @var string
	 */
	private $_changeset_uuid;

	/**
	 * Changeset post ID.
	 *
	 * @since 4.7.0
	 * @var int|false
	 */
	private $_changeset_post_id;

	/**
	 * Changeset data loaded from a customize_changeset post.
	 *
	 * @since 4.7.0
	 * @var array
	 */
	private $_changeset_data;

	/**
	 * Constructor.
	 *
	 * @since 3.4.0
	 * @since 4.7.0 Added $args param.
	 *
	 * @param array $args {
	 *     Args.
	 *
	 *     @type null|string|false $changeset_uuid     Changeset UUID, the `post_name` for the customize_changeset post containing the customized state.
	 *                                                 Defaults to `null` resulting in a UUID to be immediately generated. If `false` is provided, then
	 *                                                 then the changeset UUID will be determined during `after_setup_theme`: when the
	 *                                                 `customize_changeset_branching` filter returns false, then the default UUID will be that
	 *                                                 of the most recent `customize_changeset` post that has a status other than 'auto-draft',
	 *                                                 'publish', or 'trash'. Otherwise, if changeset branching is enabled, then a random UUID will be used.
	 *     @type string            $theme              Theme to be previewed (for theme switch). Defaults to customize_theme or theme query params.
	 *     @type string            $messenger_channel  Messenger channel. Defaults to customize_messenger_channel query param.
	 *     @type bool              $settings_previewed If settings should be previewed. Defaults to true.
	 *     @type bool              $branching          If changeset branching is allowed; otherwise, changesets are linear. Defaults to true.
	 *     @type bool              $autosaved          If data from a changeset's autosaved revision should be loaded if it exists. Defaults to false.
	 * }
	 */
	public function __construct( $args = array() ) {

		$args = array_merge(
			array_fill_keys( array( 'changeset_uuid', 'theme', 'messenger_channel', 'settings_previewed', 'autosaved', 'branching' ), null ),
			$args
		);

		// Note that the UUID format will be validated in the setup_theme() method.
		if ( ! isset( $args['changeset_uuid'] ) ) {
			$args['changeset_uuid'] = wp_generate_uuid4();
		}

		// The theme and messenger_channel should be supplied via $args, but they are also looked at in the $_REQUEST global here for back-compat.
		if ( ! isset( $args['theme'] ) ) {
			if ( isset( $_REQUEST['customize_theme'] ) ) {
				$args['theme'] = wp_unslash( $_REQUEST['customize_theme'] );
			} elseif ( isset( $_REQUEST['theme'] ) ) { // Deprecated.
				$args['theme'] = wp_unslash( $_REQUEST['theme'] );
			}
		}
		if ( ! isset( $args['messenger_channel'] ) && isset( $_REQUEST['customize_messenger_channel'] ) ) {
			$args['messenger_channel'] = sanitize_key( wp_unslash( $_REQUEST['customize_messenger_channel'] ) );
		}

		$this->original_stylesheet = get_stylesheet();
		$this->theme = wp_get_theme( 0 === validate_file( $args['theme'] ) ? $args['theme'] : null );
		$this->messenger_channel = $args['messenger_channel'];
		$this->_changeset_uuid = $args['changeset_uuid'];

		foreach ( array( 'settings_previewed', 'autosaved', 'branching' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = (bool) $args[ $key ];
			}
		}

		require_once( ABSPATH . WPINC . '/class-wp-customize-setting.php' );
		require_once( ABSPATH . WPINC . '/class-wp-customize-panel.php' );
		require_once( ABSPATH . WPINC . '/class-wp-customize-section.php' );
		require_once( ABSPATH . WPINC . '/class-wp-customize-control.php' );

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-color-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-media-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-upload-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-background-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-background-position-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-cropped-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-site-icon-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-header-image-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-theme-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-code-editor-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-widget-area-customize-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-widget-form-customize-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-location-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-name-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-locations-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-auto-add-control.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-new-menu-control.php' ); // @todo Remove in 5.0. See #42364.

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menus-panel.php' );

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-themes-panel.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-themes-section.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-sidebar-section.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-section.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-new-menu-section.php' ); // @todo Remove in 5.0. See #42364.

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-custom-css-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-filter-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-header-image-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-background-image-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-item-setting.php' );
		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-nav-menu-setting.php' );

		/**
		 * Filters the core Customizer components to load.
		 *
		 * This allows Core components to be excluded from being instantiated by
		 * filtering them out of the array. Note that this filter generally runs
		 * during the {@see 'plugins_loaded'} action, so it cannot be added
		 * in a theme.
		 *
		 * @since 4.4.0
		 *
		 * @see WP_Customize_Manager::__construct()
		 *
		 * @param array                $components List of core components to load.
		 * @param WP_Customize_Manager $this       WP_Customize_Manager instance.
		 */
		$components = apply_filters( 'customize_loaded_components', $this->components, $this );

		require_once( ABSPATH . WPINC . '/customize/class-wp-customize-selective-refresh.php' );
		$this->selective_refresh = new WP_Customize_Selective_Refresh( $this );

		if ( in_array( 'widgets', $components, true ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-widgets.php' );
			$this->widgets = new WP_Customize_Widgets( $this );
		}

		if ( in_array( 'nav_menus', $components, true ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-nav-menus.php' );
			$this->nav_menus = new WP_Customize_Nav_Menus( $this );
		}

		add_action( 'setup_theme', array( $this, 'setup_theme' ) );
		add_action( 'wp_loaded',   array( $this, 'wp_loaded' ) );

		// Do not spawn cron (especially the alternate cron) while running the Customizer.
		remove_action( 'init', 'wp_cron' );

		// Do not run update checks when rendering the controls.
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );

		add_action( 'wp_ajax_customize_save',                     array( $this, 'save' ) );
		add_action( 'wp_ajax_customize_trash',                    array( $this, 'handle_changeset_trash_request' ) );
		add_action( 'wp_ajax_customize_refresh_nonces',           array( $this, 'refresh_nonces' ) );
		add_action( 'wp_ajax_customize_load_themes',              array( $this, 'handle_load_themes_request' ) );
		add_filter( 'heartbeat_settings',                         array( $this, 'add_customize_screen_to_heartbeat_settings' ) );
		add_filter( 'heartbeat_received',                         array( $this, 'check_changeset_lock_with_heartbeat' ), 10, 3 );
		add_action( 'wp_ajax_customize_override_changeset_lock',  array( $this, 'handle_override_changeset_lock_request' ) );
		add_action( 'wp_ajax_customize_dismiss_autosave_or_lock', array( $this, 'handle_dismiss_autosave_or_lock_request' ) );

		add_action( 'customize_register',                 array( $this, 'register_controls' ) );
		add_action( 'customize_register',                 array( $this, 'register_dynamic_settings' ), 11 ); // allow code to create settings first
		add_action( 'customize_controls_init',            array( $this, 'prepare_controls' ) );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_control_scripts' ) );

		// Render Common, Panel, Section, and Control templates.
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_panel_templates' ), 1 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_section_templates' ), 1 );
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'render_control_templates' ), 1 );

		// Export header video settings with the partial response.
		add_filter( 'customize_render_partials_response', array( $this, 'export_header_video_settings' ), 10, 3 );

		// Export the settings to JS via the _wpCustomizeSettings variable.
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'customize_pane_settings' ), 1000 );

		// Add theme update notices.
		if ( current_user_can( 'install_themes' ) || current_user_can( 'update_themes' ) ) {
			require_once ABSPATH . '/wp-admin/includes/update.php';
			add_action( 'customize_controls_print_footer_scripts', 'wp_print_admin_notice_templates' );
		}
	}

	/**
	 * Return true if it's an Ajax request.
	 *
	 * @since 3.4.0
	 * @since 4.2.0 Added `$action` param.
	 *
	 * @param string|null $action Whether the supplied Ajax action is being run.
	 * @return bool True if it's an Ajax request, false otherwise.
	 */
	public function doing_ajax( $action = null ) {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		if ( ! $action ) {
			return true;
		} else {
			/*
			 * Note: we can't just use doing_action( "wp_ajax_{$action}" ) because we need
			 * to check before admin-ajax.php gets to that point.
			 */
			return isset( $_REQUEST['action'] ) && wp_unslash( $_REQUEST['action'] ) === $action;
		}
	}

	/**
	 * Custom wp_die wrapper. Returns either the standard message for UI
	 * or the Ajax message.
	 *
	 * @since 3.4.0
	 *
	 * @param mixed $ajax_message Ajax return
	 * @param mixed $message UI message
	 */
	protected function wp_die( $ajax_message, $message = null ) {
		if ( $this->doing_ajax() ) {
			wp_die( $ajax_message );
		}

		if ( ! $message ) {
			$message = __( 'Cheatin&#8217; uh?' );
		}

		if ( $this->messenger_channel ) {
			ob_start();
			wp_enqueue_scripts();
			wp_print_scripts( array( 'customize-base' ) );

			$settings = array(
				'messengerArgs' => array(
					'channel' => $this->messenger_channel,
					'url' => wp_customize_url(),
				),
				'error' => $ajax_message,
			);
			?>
			<script>
			( function( api, settings ) {
				var preview = new api.Messenger( settings.messengerArgs );
				preview.send( 'iframe-loading-error', settings.error );
			} )( wp.customize, <?php echo wp_json_encode( $settings ) ?> );
			</script>
			<?php
			$message .= ob_get_clean();
		}

		wp_die( $message );
	}

	/**
	 * Return the Ajax wp_die() handler if it's a customized request.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 *
	 * @return callable Die handler.
	 */
	public function wp_die_handler() {
		_deprecated_function( __METHOD__, '4.7.0' );

		if ( $this->doing_ajax() || isset( $_POST['customized'] ) ) {
			return '_ajax_wp_die_handler';
		}

		return '_default_wp_die_handler';
	}

	/**
	 * Start preview and customize theme.
	 *
	 * Check if customize query variable exist. Init filters to filter the current theme.
	 *
	 * @since 3.4.0
	 *
	 * @global string $pagenow
	 */
	public function setup_theme() {
		global $pagenow;

		// Check permissions for customize.php access since this method is called before customize.php can run any code,
		if ( 'customize.php' === $pagenow && ! current_user_can( 'customize' ) ) {
			if ( ! is_user_logged_in() ) {
				auth_redirect();
			} else {
				wp_die(
					'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
					'<p>' . __( 'Sorry, you are not allowed to customize this site.' ) . '</p>',
					403
				);
			}
			return;
		}

		// If a changeset was provided is invalid.
		if ( isset( $this->_changeset_uuid ) && false !== $this->_changeset_uuid && ! wp_is_uuid( $this->_changeset_uuid ) ) {
			$this->wp_die( -1, __( 'Invalid changeset UUID' ) );
		}

		/*
		 * Clear incoming post data if the user lacks a CSRF token (nonce). Note that the customizer
		 * application will inject the customize_preview_nonce query parameter into all Ajax requests.
		 * For similar behavior elsewhere in WordPress, see rest_cookie_check_errors() which logs out
		 * a user when a valid nonce isn't present.
		 */
		$has_post_data_nonce = (
			check_ajax_referer( 'preview-customize_' . $this->get_stylesheet(), 'nonce', false )
			||
			check_ajax_referer( 'save-customize_' . $this->get_stylesheet(), 'nonce', false )
			||
			check_ajax_referer( 'preview-customize_' . $this->get_stylesheet(), 'customize_preview_nonce', false )
		);
		if ( ! current_user_can( 'customize' ) || ! $has_post_data_nonce ) {
			unset( $_POST['customized'] );
			unset( $_REQUEST['customized'] );
		}

		/*
		 * If unauthenticated then require a valid changeset UUID to load the preview.
		 * In this way, the UUID serves as a secret key. If the messenger channel is present,
		 * then send unauthenticated code to prompt re-auth.
		 */
		if ( ! current_user_can( 'customize' ) && ! $this->changeset_post_id() ) {
			$this->wp_die( $this->messenger_channel ? 0 : -1, __( 'Non-existent changeset UUID.' ) );
		}

		if ( ! headers_sent() ) {
			send_origin_headers();
		}

		// Hide the admin bar if we're embedded in the customizer iframe.
		if ( $this->messenger_channel ) {
			show_admin_bar( false );
		}

		if ( $this->is_theme_active() ) {
			// Once the theme is loaded, we'll validate it.
			add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
		} else {
			// If the requested theme is not the active theme and the user doesn't have the
			// switch_themes cap, bail.
			if ( ! current_user_can( 'switch_themes' ) ) {
				$this->wp_die( -1, __( 'Sorry, you are not allowed to edit theme options on this site.' ) );
			}

			// If the theme has errors while loading, bail.
			if ( $this->theme()->errors() ) {
				$this->wp_die( -1, $this->theme()->errors()->get_error_message() );
			}

			// If the theme isn't allowed per multisite settings, bail.
			if ( ! $this->theme()->is_allowed() ) {
				$this->wp_die( -1, __( 'The requested theme does not exist.' ) );
			}
		}

		// Make sure changeset UUID is established immediately after the theme is loaded.
		add_action( 'after_setup_theme', array( $this, 'establish_loaded_changeset' ), 5 );

		/*
		 * Import theme starter content for fresh installations when landing in the customizer.
		 * Import starter content at after_setup_theme:100 so that any
		 * add_theme_support( 'starter-content' ) calls will have been made.
		 */
		if ( get_option( 'fresh_site' ) && 'customize.php' === $pagenow ) {
			add_action( 'after_setup_theme', array( $this, 'import_theme_starter_content' ), 100 );
		}

		$this->start_previewing_theme();
	}

	/**
	 * Establish the loaded changeset.
	 *
	 * This method runs right at after_setup_theme and applies the 'customize_changeset_branching' filter to determine
	 * whether concurrent changesets are allowed. Then if the Customizer is not initialized with a `changeset_uuid` param,
	 * this method will determine which UUID should be used. If changeset branching is disabled, then the most saved
	 * changeset will be loaded by default. Otherwise, if there are no existing saved changesets or if changeset branching is
	 * enabled, then a new UUID will be generated.
	 *
	 * @since 4.9.0
	 * @global string $pagenow
	 */
	public function establish_loaded_changeset() {
		global $pagenow;

		if ( empty( $this->_changeset_uuid ) ) {
			$changeset_uuid = null;

			if ( ! $this->branching() && $this->is_theme_active() ) {
				$unpublished_changeset_posts = $this->get_changeset_posts( array(
					'post_status' => array_diff( get_post_stati(), array( 'auto-draft', 'publish', 'trash', 'inherit', 'private' ) ),
					'exclude_restore_dismissed' => false,
					'author' => 'any',
					'posts_per_page' => 1,
					'order' => 'DESC',
					'orderby' => 'date',
				) );
				$unpublished_changeset_post = array_shift( $unpublished_changeset_posts );
				if ( ! empty( $unpublished_changeset_post ) && wp_is_uuid( $unpublished_changeset_post->post_name ) ) {
					$changeset_uuid = $unpublished_changeset_post->post_name;
				}
			}

			// If no changeset UUID has been set yet, then generate a new one.
			if ( empty( $changeset_uuid ) ) {
				$changeset_uuid = wp_generate_uuid4();
			}

			$this->_changeset_uuid = $changeset_uuid;
		}

		if ( is_admin() && 'customize.php' === $pagenow ) {
			$this->set_changeset_lock( $this->changeset_post_id() );
		}
	}

	/**
	 * Callback to validate a theme once it is loaded
	 *
	 * @since 3.4.0
	 */
	public function after_setup_theme() {
		$doing_ajax_or_is_customized = ( $this->doing_ajax() || isset( $_POST['customized'] ) );
		if ( ! $doing_ajax_or_is_customized && ! validate_current_theme() ) {
			wp_redirect( 'themes.php?broken=true' );
			exit;
		}
	}

	/**
	 * If the theme to be previewed isn't the active theme, add filter callbacks
	 * to swap it out at runtime.
	 *
	 * @since 3.4.0
	 */
	public function start_previewing_theme() {
		// Bail if we're already previewing.
		if ( $this->is_preview() ) {
			return;
		}

		$this->previewing = true;

		if ( ! $this->is_theme_active() ) {
			add_filter( 'template', array( $this, 'get_template' ) );
			add_filter( 'stylesheet', array( $this, 'get_stylesheet' ) );
			add_filter( 'pre_option_current_theme', array( $this, 'current_theme' ) );

			// @link: https://core.trac.wordpress.org/ticket/20027
			add_filter( 'pre_option_stylesheet', array( $this, 'get_stylesheet' ) );
			add_filter( 'pre_option_template', array( $this, 'get_template' ) );

			// Handle custom theme roots.
			add_filter( 'pre_option_stylesheet_root', array( $this, 'get_stylesheet_root' ) );
			add_filter( 'pre_option_template_root', array( $this, 'get_template_root' ) );
		}

		/**
		 * Fires once the Customizer theme preview has started.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $this WP_Customize_Manager instance.
		 */
		do_action( 'start_previewing_theme', $this );
	}

	/**
	 * Stop previewing the selected theme.
	 *
	 * Removes filters to change the current theme.
	 *
	 * @since 3.4.0
	 */
	public function stop_previewing_theme() {
		if ( ! $this->is_preview() ) {
			return;
		}

		$this->previewing = false;

		if ( ! $this->is_theme_active() ) {
			remove_filter( 'template', array( $this, 'get_template' ) );
			remove_filter( 'stylesheet', array( $this, 'get_stylesheet' ) );
			remove_filter( 'pre_option_current_theme', array( $this, 'current_theme' ) );

			// @link: https://core.trac.wordpress.org/ticket/20027
			remove_filter( 'pre_option_stylesheet', array( $this, 'get_stylesheet' ) );
			remove_filter( 'pre_option_template', array( $this, 'get_template' ) );

			// Handle custom theme roots.
			remove_filter( 'pre_option_stylesheet_root', array( $this, 'get_stylesheet_root' ) );
			remove_filter( 'pre_option_template_root', array( $this, 'get_template_root' ) );
		}

		/**
		 * Fires once the Customizer theme preview has stopped.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $this WP_Customize_Manager instance.
		 */
		do_action( 'stop_previewing_theme', $this );
	}

	/**
	 * Gets whether settings are or will be previewed.
	 *
	 * @since 4.9.0
	 * @see WP_Customize_Setting::preview()
	 *
	 * @return bool
	 */
	public function settings_previewed() {
		return $this->settings_previewed;
	}

	/**
	 * Gets whether data from a changeset's autosaved revision should be loaded if it exists.
	 *
	 * @since 4.9.0
	 * @see WP_Customize_Manager::changeset_data()
	 *
	 * @return bool Is using autosaved changeset revision.
	 */
	public function autosaved() {
		return $this->autosaved;
	}

	/**
	 * Whether the changeset branching is allowed.
	 *
	 * @since 4.9.0
	 * @see WP_Customize_Manager::establish_loaded_changeset()
	 *
	 * @return bool Is changeset branching.
	 */
	public function branching() {

		/**
		 * Filters whether or not changeset branching is allowed.
		 *
		 * By default in core, when changeset branching is not allowed, changesets will operate
		 * linearly in that only one saved changeset will exist at a time (with a 'draft' or
		 * 'future' status). This makes the Customizer operate in a way that is similar to going to
		 * "edit" to one existing post: all users will be making changes to the same post, and autosave
		 * revisions will be made for that post.
		 *
		 * By contrast, when changeset branching is allowed, then the model is like users going
		 * to "add new" for a page and each user makes changes independently of each other since
		 * they are all operating on their own separate pages, each getting their own separate
		 * initial auto-drafts and then once initially saved, autosave revisions on top of that
		 * user's specific post.
		 *
		 * Since linear changesets are deemed to be more suitable for the majority of WordPress users,
		 * they are the default. For WordPress sites that have heavy site management in the Customizer
		 * by multiple users then branching changesets should be enabled by means of this filter.
		 *
		 * @since 4.9.0
		 *
		 * @param bool                 $allow_branching Whether branching is allowed. If `false`, the default,
		 *                                              then only one saved changeset exists at a time.
		 * @param WP_Customize_Manager $wp_customize    Manager instance.
		 */
		$this->branching = apply_filters( 'customize_changeset_branching', $this->branching, $this );

		return $this->branching;
	}

	/**
	 * Get the changeset UUID.
	 *
	 * @since 4.7.0
	 * @see WP_Customize_Manager::establish_loaded_changeset()
	 *
	 * @return string UUID.
	 */
	public function changeset_uuid() {
		if ( empty( $this->_changeset_uuid ) ) {
			$this->establish_loaded_changeset();
		}
		return $this->_changeset_uuid;
	}

	/**
	 * Get the theme being customized.
	 *
	 * @since 3.4.0
	 *
	 * @return WP_Theme
	 */
	public function theme() {
		if ( ! $this->theme ) {
			$this->theme = wp_get_theme();
		}
		return $this->theme;
	}

	/**
	 * Get the registered settings.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * Get the registered controls.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	public function controls() {
		return $this->controls;
	}

	/**
	 * Get the registered containers.
	 *
	 * @since 4.0.0
	 *
	 * @return array
	 */
	public function containers() {
		return $this->containers;
	}

	/**
	 * Get the registered sections.
	 *
	 * @since 3.4.0
	 *
	 * @return array
	 */
	public function sections() {
		return $this->sections;
	}

	/**
	 * Get the registered panels.
	 *
	 * @since 4.0.0
	 *
	 * @return array Panels.
	 */
	public function panels() {
		return $this->panels;
	}

	/**
	 * Checks if the current theme is active.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public function is_theme_active() {
		return $this->get_stylesheet() == $this->original_stylesheet;
	}

	/**
	 * Register styles/scripts and initialize the preview of each setting
	 *
	 * @since 3.4.0
	 */
	public function wp_loaded() {

		// Unconditionally register core types for panels, sections, and controls in case plugin unhooks all customize_register actions.
		$this->register_panel_type( 'WP_Customize_Panel' );
		$this->register_panel_type( 'WP_Customize_Themes_Panel' );
		$this->register_section_type( 'WP_Customize_Section' );
		$this->register_section_type( 'WP_Customize_Sidebar_Section' );
		$this->register_section_type( 'WP_Customize_Themes_Section' );
		$this->register_control_type( 'WP_Customize_Color_Control' );
		$this->register_control_type( 'WP_Customize_Media_Control' );
		$this->register_control_type( 'WP_Customize_Upload_Control' );
		$this->register_control_type( 'WP_Customize_Image_Control' );
		$this->register_control_type( 'WP_Customize_Background_Image_Control' );
		$this->register_control_type( 'WP_Customize_Background_Position_Control' );
		$this->register_control_type( 'WP_Customize_Cropped_Image_Control' );
		$this->register_control_type( 'WP_Customize_Site_Icon_Control' );
		$this->register_control_type( 'WP_Customize_Theme_Control' );
		$this->register_control_type( 'WP_Customize_Code_Editor_Control' );
		$this->register_control_type( 'WP_Customize_Date_Time_Control' );

		/**
		 * Fires once WordPress has loaded, allowing scripts and styles to be initialized.
		 *
		 * @since 3.4.0
		 *
		 * @param WP_Customize_Manager $this WP_Customize_Manager instance.
		 */
		do_action( 'customize_register', $this );

		if ( $this->settings_previewed() ) {
			foreach ( $this->settings as $setting ) {
				$setting->preview();
			}
		}

		if ( $this->is_preview() && ! is_admin() ) {
			$this->customize_preview_init();
		}
	}

	/**
	 * Prevents Ajax requests from following redirects when previewing a theme
	 * by issuing a 200 response instead of a 30x.
	 *
	 * Instead, the JS will sniff out the location header.
	 *
	 * @since 3.4.0
	 * @deprecated 4.7.0
	 *
	 * @param int $status Status.
	 * @return int
	 */
	public function wp_redirect_status( $status ) {
		_deprecated_function( __FUNCTION__, '4.7.0' );

		if ( $this->is_preview() && ! is_admin() ) {
			return 200;
		}

		return $status;
	}

	/**
	 * Find the changeset post ID for a given changeset UUID.
	 *
	 * @since 4.7.0
	 *
	 * @param string $uuid Changeset UUID.
	 * @return int|null Returns post ID on success and null on failure.
	 */
	public function find_changeset_post_id( $uuid ) {
		$cache_group = 'customize_changeset_post';
		$changeset_post_id = wp_cache_get( $uuid, $cache_group );
		if ( $changeset_post_id && 'customize_changeset' === get_post_type( $changeset_post_id ) ) {
			return $changeset_post_id;
		}

		$changeset_post_query = new WP_Query( array(
			'post_type' => 'customize_changeset',
			'post_status' => get_post_stati(),
			'name' => $uuid,
			'posts_per_page' => 1,
			'no_found_rows' => true,
			'cache_results' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'lazy_load_term_meta' => false,
		) );
		if ( ! empty( $changeset_post_query->posts ) ) {
			// Note: 'fi