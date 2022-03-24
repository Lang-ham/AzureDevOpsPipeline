
<?php
/**
 * WP_Theme Class
 *
 * @package WordPress
 * @subpackage Theme
 * @since 3.4.0
 */
final class WP_Theme implements ArrayAccess {

	/**
	 * Whether the theme has been marked as updateable.
	 *
	 * @since 4.4.0
	 * @var bool
	 *
	 * @see WP_MS_Themes_List_Table
	 */
	public $update = false;

	/**
	 * Headers for style.css files.
	 *
	 * @static
	 * @var array
	 */
	private static $file_headers = array(
		'Name'        => 'Theme Name',
		'ThemeURI'    => 'Theme URI',
		'Description' => 'Description',
		'Author'      => 'Author',
		'AuthorURI'   => 'Author URI',
		'Version'     => 'Version',
		'Template'    => 'Template',
		'Status'      => 'Status',
		'Tags'        => 'Tags',
		'TextDomain'  => 'Text Domain',
		'DomainPath'  => 'Domain Path',
	);

	/**
	 * Default themes.
	 *
	 * @static
	 * @var array
	 */
	private static $default_themes = array(
		'classic'         => 'WordPress Classic',
		'default'         => 'WordPress Default',
		'twentyten'       => 'Twenty Ten',
		'twentyeleven'    => 'Twenty Eleven',
		'twentytwelve'    => 'Twenty Twelve',
		'twentythirteen'  => 'Twenty Thirteen',
		'twentyfourteen'  => 'Twenty Fourteen',
		'twentyfifteen'   => 'Twenty Fifteen',
		'twentysixteen'   => 'Twenty Sixteen',
		'twentyseventeen' => 'Twenty Seventeen',
	);

	/**
	 * Renamed theme tags.
	 *
	 * @static
	 * @var array
	 */
	private static $tag_map = array(
		'fixed-width'    => 'fixed-layout',
		'flexible-width' => 'fluid-layout',
	);

	/**
	 * Absolute path to the theme root, usually wp-content/themes
	 *
	 * @var string
	 */
	private $theme_root;

	/**
	 * Header data from the theme's style.css file.
	 *
	 * @var array
	 */
	private $headers = array();

	/**
	 * Header data from the theme's style.css file after being sanitized.
	 *
	 * @var array
	 */
	private $headers_sanitized;

	/**
	 * Header name from the theme's style.css after being translated.
	 *
	 * Cached due to sorting functions running over the translated name.
	 *
	 * @var string
	 */
	private $name_translated;

	/**
	 * Errors encountered when initializing the theme.
	 *
	 * @var WP_Error
	 */
	private $errors;

	/**
	 * The directory name of the theme's files, inside the theme root.
	 *
	 * In the case of a child theme, this is directory name of the child theme.
	 * Otherwise, 'stylesheet' is the same as 'template'.
	 *
	 * @var string
	 */
	private $stylesheet;

	/**
	 * The directory name of the theme's files, inside the theme root.
	 *
	 * In the case of a child theme, this is the directory name of the parent theme.
	 * Otherwise, 'template' is the same as 'stylesheet'.
	 *
	 * @var string
	 */
	private $template;

	/**
	 * A reference to the parent theme, in the case of a child theme.
	 *
	 * @var WP_Theme
	 */
	private $parent;

	/**
	 * URL to the theme root, usually an absolute URL to wp-content/themes
	 *
	 * @var string
	 */
	private $theme_root_uri;

	/**
	 * Flag for whether the theme's textdomain is loaded.
	 *
	 * @var bool
	 */
	private $textdomain_loaded;

	/**
	 * Stores an md5 hash of the theme root, to function as the cache key.
	 *
	 * @var string
	 */
	private $cache_hash;

	/**
	 * Flag for whether the themes cache bucket should be persistently cached.
	 *
	 * Default is false. Can be set with the {@see 'wp_cache_themes_persistently'} filter.
	 *
	 * @static
	 * @var bool
	 */
	private static $persistently_cache;

	/**
	 * Expiration time for the themes cache bucket.
	 *
	 * By default the bucket is not cached, so this value is useless.
	 *
	 * @static
	 * @var bool
	 */
	private static $cache_expiration = 1800;

	/**
	 * Constructor for WP_Theme.
	 *
	 * @since  3.4.0
	 *
	 * @global array $wp_theme_directories
	 *
	 * @param string $theme_dir Directory of the theme within the theme_root.
	 * @param string $theme_root Theme root.
	 * @param WP_Error|void $_child If this theme is a parent theme, the child may be passed for validation purposes.
	 */
	public function __construct( $theme_dir, $theme_root, $_child = null ) {
		global $wp_theme_directories;

		// Initialize caching on first run.
		if ( ! isset( self::$persistently_cache ) ) {
			/** This action is documented in wp-includes/theme.php */
			self::$persistently_cache = apply_filters( 'wp_cache_themes_persistently', false, 'WP_Theme' );
			if ( self::$persistently_cache ) {
				wp_cache_add_global_groups( 'themes' );
				if ( is_int( self::$persistently_cache ) )
					self::$cache_expiration = self::$persistently_cache;
			} else {
				wp_cache_add_non_persistent_groups( 'themes' );
			}
		}

		$this->theme_root = $theme_root;
		$this->stylesheet = $theme_dir;

		// Correct a situation where the theme is 'some-directory/some-theme' but 'some-directory' was passed in as part of the theme root instead.
		if ( ! in_array( $theme_root, (array) $wp_theme_directories ) && in_array( dirname( $theme_root ), (array) $wp_theme_directories ) ) {
			$this->stylesheet = basename( $this->theme_root ) . '/' . $this->stylesheet;
			$this->theme_root = dirname( $theme_root );
		}

		$this->cache_hash = md5( $this->theme_root . '/' . $this->stylesheet );
		$theme_file = $this->stylesheet . '/style.css';

		$cache = $this->cache_get( 'theme' );

		if ( is_array( $cache ) ) {
			foreach ( array( 'errors', 'headers', 'template' ) as $key ) {
				if ( isset( $cache[ $key ] ) )
					$this->$key = $cache[ $key ];
			}
			if ( $this->errors )
				return;
			if ( isset( $cache['theme_root_template'] ) )
				$theme_root_template = $cache['theme_root_template'];
		} elseif ( ! file_exists( $this->theme_root . '/' . $theme_file ) ) {
			$this->headers['Name'] = $this->stylesheet;
			if ( ! file_exists( $this->theme_root . '/' . $this->stylesheet ) )
				$this->errors = new WP_Error( 'theme_not_found', sprintf( __( 'The theme directory "%s" does not exist.' ), esc_html( $this->stylesheet ) ) );
			else
				$this->errors = new WP_Error( 'theme_no_stylesheet', __( 'Stylesheet is missing.' ) );
			$this->template = $this->stylesheet;
			$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template ) );
			if ( ! file_exists( $this->theme_root ) ) // Don't cache this one.
				$this->errors->add( 'theme_root_missing', __( 'ERROR: The themes directory is either empty or doesn&#8217;t exist. Please check your installation.' ) );
			return;
		} elseif ( ! is_readable( $this->theme_root . '/' . $theme_file ) ) {
			$this->headers['Name'] = $this->stylesheet;
			$this->errors = new WP_Error( 'theme_stylesheet_not_readable', __( 'Stylesheet is not readable.' ) );
			$this->template = $this->stylesheet;
			$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template ) );
			return;
		} else {
			$this->headers = get_file_data( $this->theme_root . '/' . $theme_file, self::$file_headers, 'theme' );
			// Default themes always trump their pretenders.
			// Properly identify default themes that are inside a directory within wp-content/themes.
			if ( $default_theme_slug = array_search( $this->headers['Name'], self::$default_themes ) ) {
				if ( basename( $this->stylesheet ) != $default_theme_slug )
					$this->headers['Name'] .= '/' . $this->stylesheet;
			}
		}

		if ( ! $this->template && $this->stylesheet === $this->headers['Template'] ) {
			/* translators: %s: Template */
			$this->errors = new WP_Error( 'theme_child_invalid', sprintf( __( 'The theme defines itself as its parent theme. Please check the %s header.' ), '<code>Template</code>' ) );
			$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet ) );

			return;
		}

		// (If template is set from cache [and there are no errors], we know it's good.)
		if ( ! $this->template && ! ( $this->template = $this->headers['Template'] ) ) {
			$this->template = $this->stylesheet;
			if ( ! file_exists( $this->theme_root . '/' . $this->stylesheet . '/index.php' ) ) {
				$error_message = sprintf(
					/* translators: 1: index.php, 2: Codex URL, 3: style.css */
					__( 'Template is missing. Standalone themes need to have a %1$s template file. <a href="%2$s">Child themes</a> need to have a Template header in the %3$s stylesheet.' ),
					'<code>index.php</code>',
					__( 'https://codex.wordpress.org/Child_Themes' ),
					'<code>style.css</code>'
				);
				$this->errors = new WP_Error( 'theme_no_index', $error_message );
				$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template ) );
				return;
			}
		}

		// If we got our data from cache, we can assume that 'template' is pointing to the right place.
		if ( ! is_array( $cache ) && $this->template != $this->stylesheet && ! file_exists( $this->theme_root . '/' . $this->template . '/index.php' ) ) {
			// If we're in a directory of themes inside /themes, look for the parent nearby.
			// wp-content/themes/directory-of-themes/*
			$parent_dir = dirname( $this->stylesheet );
			if ( '.' != $parent_dir && file_exists( $this->theme_root . '/' . $parent_dir . '/' . $this->template . '/index.php' ) ) {
				$this->template = $parent_dir . '/' . $this->template;
			} elseif ( ( $directories = search_theme_directories() ) && isset( $directories[ $this->template ] ) ) {
				// Look for the template in the search_theme_directories() results, in case it is in another theme root.
				// We don't look into directories of themes, just the theme root.
				$theme_root_template = $directories[ $this->template ]['theme_root'];
			} else {
				// Parent theme is missing.
				$this->errors = new WP_Error( 'theme_no_parent', sprintf( __( 'The parent theme is missing. Please install the "%s" parent theme.' ), esc_html( $this->template ) ) );
				$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template ) );
				$this->parent = new WP_Theme( $this->template, $this->theme_root, $this );
				return;
			}
		}

		// Set the parent, if we're a child theme.
		if ( $this->template != $this->stylesheet ) {
			// If we are a parent, then there is a problem. Only two generations allowed! Cancel things out.
			if ( $_child instanceof WP_Theme && $_child->template == $this->stylesheet ) {
				$_child->parent = null;
				$_child->errors = new WP_Error( 'theme_parent_invalid', sprintf( __( 'The "%s" theme is not a valid parent theme.' ), esc_html( $_child->template ) ) );
				$_child->cache_add( 'theme', array( 'headers' => $_child->headers, 'errors' => $_child->errors, 'stylesheet' => $_child->stylesheet, 'template' => $_child->template ) );
				// The two themes actually reference each other with the Template header.
				if ( $_child->stylesheet == $this->template ) {
					$this->errors = new WP_Error( 'theme_parent_invalid', sprintf( __( 'The "%s" theme is not a valid parent theme.' ), esc_html( $this->template ) ) );
					$this->cache_add( 'theme', array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template ) );
				}
				return;
			}
			// Set the parent. Pass the current instance so we can do the crazy checks above and assess errors.
			$this->parent = new WP_Theme( $this->template, isset( $theme_root_template ) ? $theme_root_template : $this->theme_root, $this );
		}

		// We're good. If we didn't retrieve from cache, set it.
		if ( ! is_array( $cache ) ) {
			$cache = array( 'headers' => $this->headers, 'errors' => $this->errors, 'stylesheet' => $this->stylesheet, 'template' => $this->template );
			// If the parent theme is in another root, we'll want to cache this. Avoids an entire branch of filesystem calls above.
			if ( isset( $theme_root_template ) )
				$cache['theme_root_template'] = $theme_root_template;
			$this->cache_add( 'theme', $cache );
		}
	}

	/**
	 * When converting the object to a string, the theme name is returned.
	 *
	 * @since  3.4.0
	 *
	 * @return string Theme name, ready for display (translated)
	 */
	public function __toString() {
		return (string) $this->display('Name');
	}

	/**
	 * __isset() magic method for properties formerly returned by current_theme_info()
	 *
	 * @staticvar array $properties
	 *
	 * @since  3.4.0
	 *
	 * @param string $offset Property to check if set.
	 * @return bool Whether the given property is set.
	 */
	public function __isset( $offset ) {
		static $properties = array(
			'name', 'title', 'version', 'parent_theme', 'template_dir', 'stylesheet_dir', 'template', 'stylesheet',
			'screenshot', 'description', 'author', 'tags', 'theme_root', 'theme_root_uri',
		);

		return in_array( $offset, $properties );
	}

	/**
	 * __get() magic method for properties formerly returned by current_theme_info()
	 *
	 * @since  3.4.0
	 *
	 * @param string $offset Property to get.
	 * @return mixed Property value.
	 */
	public function __get( $offset ) {
		switch ( $offset ) {
			case 'name' :
			case 'title' :
				return $this->get('Name');
			case 'version' :
				return $this->get('Version');
			case 'parent_theme' :
				return $this->parent() ? $this->parent()->get('Name') : '';
			case 'template_dir' :
				return $this->get_template_directory();
			case 'stylesheet_dir' :
				return $this->get_stylesheet_directory();
			case 'template' :
				return $this->get_template();
			case 'stylesheet' :
				return $this->get_stylesheet();
			case 'screenshot' :
				return $this->get_screenshot( 'relative' );
			// 'author' and 'description' did not previously return translated data.
			case 'description' :
				return $this->display('Description');
			case 'author' :
				return $this->display('Author');
			case 'tags' :
				return $this->get( 'Tags' );
			case 'theme_root' :
				return $this->get_theme_root();
			case 'theme_root_uri' :
				return $this->get_theme_root_uri();
			// For cases where the array was converted to an object.
			default :
				return $this->offsetGet( $offset );
		}
	}

	/**
	 * Method to implement ArrayAccess for keys formerly returned by get_themes()
	 *
	 * @since  3.4.0
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 */
	public function offsetSet( $offset, $value ) {}

	/**
	 * Method to implement ArrayAccess for keys formerly returned by get_themes()
	 *
	 * @since  3.4.0
	 *
	 * @param mixed $offset
	 */
	public function offsetUnset( $offset ) {}

	/**
	 * Method to implement ArrayAccess for keys formerly returned by get_themes()
	 *
	 * @staticvar array $keys
	 *
	 * @since  3.4.0
	 *
	 * @param mixed $offset
	 * @return bool
	 */
	public function offsetExists( $offset ) {
		static $keys = array(
			'Name', 'Version', 'Status', 'Title', 'Author', 'Author Name', 'Author URI', 'Description',
			'Template', 'Stylesheet', 'Template Files', 'Stylesheet Files', 'Template Dir', 'Stylesheet Dir',
			'Screenshot', 'Tags', 'Theme Root', 'Theme Root URI', 'Parent Theme',
		);

		return in_array( $offset, $keys );
	}

	/**
	 * Method to implement ArrayAccess for keys formerly returned by get_themes().
	 *
	 * Author, Author Name, Author URI, and Description did not previously return
	 * translated data. We are doing so now as it is safe to do. However, as
	 * Name and Title could have been used as the key for get_themes(), both remain
	 * untranslated for back compatibility. This means that ['Name'] is not ideal,
	 * and care should be taken to use `$theme::display( 'Name' )` to get a properly
	 * translated header.
	 *
	 * @since  3.4.0
	 *
	 * @param mixed $offset
	 * @return mixed
	 */
	public function offsetGet( $offset ) {
		switch ( $offset ) {
			case 'Name' :
			case 'Title' :
				/*
				 * See note above about using translated data. get() is not ideal.
				 * It is only for backward compatibility. Use display().
				 */
				return $this->get('Name');
			case 'Author' :
				return $this->display( 'Author');
			case 'Author Name' :
				return $this->display( 'Author', false);
			case 'Author URI' :
				return $this->display('AuthorURI');
			case 'Description' :
				return $this->display( 'Description');
			case 'Version' :
			case 'Status' :
				return $this->get( $offset );
			case 'Template' :
				return $this->get_template();
			case 'Stylesheet' :
				return $this->get_stylesheet();
			case 'Template Files' :
				return $this->get_files( 'php', 1, true );
			case 'Stylesheet Files' :
				return $this->get_files( 'css', 0, false );
			case 'Template Dir' :
				return $this->get_template_directory();
			case 'Stylesheet Dir' :
				return $this->get_stylesheet_directory();
			case 'Screenshot' :
				return $this->get_screenshot( 'relative' );
			case 'Tags' :
				return $this->get('Tags');
			case 'Theme Root' :
				return $this->get_theme_root();
			case 'Theme Root URI' :
				return $this->get_theme_root_uri();
			case 'Parent Theme' :
				return $this->parent() ? $this->parent()->get('Name') : '';
			default :
				return null;
		}
	}

	/**
	 * Returns errors property.
	 *
	 * @since 3.4.0
	 *
	 * @return WP_Error|false WP_Error if there are errors, or false.
	 */
	public function errors() {
		return is_wp_error( $this->errors ) ? $this->errors : false;
	}

	/**
	 * Whether the theme exists.
	 *
	 * A theme with errors exists. A theme with the error of 'theme_not_found',
	 * meaning that the theme's directory was not found, does not exist.
	 *
	 * @since 3.4.0
	 *
	 * @return bool Whether the theme exists.
	 */
	public function exists() {
		return ! ( $this->errors() && in_array( 'theme_not_found', $this->errors()->get_error_codes() ) );
	}

	/**
	 * Returns reference to the parent theme.
	 *
	 * @since 3.4.0
	 *
	 * @return WP_Theme|false Parent theme, or false if the current theme is not a child theme.
	 */
	public function parent() {
		return isset( $this->parent ) ? $this->parent : false;
	}

	/**
	 * Adds theme data to cache.
	 *
	 * Cache entries keyed by the theme and the type of data.
	 *
	 * @since 3.4.0
	 *
	 * @param string $key Type of data to store (theme, screenshot, headers, post_templates)
	 * @param string $data Data to store
	 * @return bool Return value from wp_cache_add()
	 */
	private function cache_add( $key, $data ) {
		return wp_cache_add( $key . '-' . $this->cache_hash, $data, 'themes', self::$cache_expiration );
	}

	/**
	 * Gets theme data from cache.
	 *
	 * Cache entries are keyed by the theme and the type of data.
	 *
	 * @since 3.4.0
	 *
	 * @param string $key Type of data to retrieve (theme, screenshot, headers, post_templates)
	 * @return mixed Retrieved data
	 */
	private function cache_get( $key ) {
		return wp_cache_get( $key . '-' . $this->cache_hash, 'themes' );
	}

	/**
	 * Clears the cache for the theme.
	 *
	 * @since 3.4.0
	 */
	public function cache_delete() {
		foreach ( array( 'theme', 'screenshot', 'headers', 'post_templates' ) as $key )
			wp_cache_delete( $key . '-' . $this->cache_hash, 'themes' );
		$this->template = $this->textdomain_loaded = $this->theme_root_uri = $this->parent = $this->errors = $this->headers_sanitized = $this->name_translated = null;
		$this->headers = array();
		$this->__construct( $this->stylesheet, $this->theme_root );
	}

	/**
	 * Get a raw, unformatted theme header.
	 *
	 * The header is sanitized, but is not translated, and is not marked up for display.
	 * To get a theme header for display, use the display() method.
	 *
	 * Use the get_template() method, not the 'Template' header, for finding the template.
	 * The 'Template' header is only good for what was written in the style.css, while
	 * get_template() takes into account where WordPress actually located the theme and
	 * whether it is actually valid.
	 *
	 * @since 3.4.0
	 *
	 * @param string $header Theme header. Name, Description, Author, Version, ThemeURI, AuthorURI, Status, Tags.
	 * @return string|false String on success, false on failure.
	 */
	public function get( $header ) {
		if ( ! isset( $this->headers[ $header ] ) )
			return false;

		if ( ! isset( $this->headers_sanitized ) ) {
			$this->headers_sanitized = $this->cache_get( 'headers' );
			if ( ! is_array( $this->headers_sanitized ) )
				$this->headers_sanitized = array();
		}

		if ( isset( $this->headers_sanitized[ $header ] ) )
			return $this->headers_sanitized[ $header ];

		// If themes are a persistent group, sanitize everything and cache it. One cache add is better than many cache sets.
		if ( self::$persistently_cache ) {
			foreach ( array_keys( $this->headers ) as $_header )
				$this->headers_sanitized[ $_header ] = $this->sanitize_header( $_header, $this->headers[ $_header ] );
			$this->cache_add( 'headers', $this->headers_sanitized );
		} else {
			$this->headers_sanitized[ $header ] = $this->sanitize_header( $header, $this->headers[ $header ] );
		}

		return $this->headers_sanitized[ $header ];
	}

	/**
	 * Gets a theme header, formatted and translated for display.
	 *
	 * @since 3.4.0
	 *
	 * @param string $header Theme header. Name, Description, Author, Version, ThemeURI, AuthorURI, Status, Tags.
	 * @param bool $markup Optional. Whether to mark up the header. Defaults to true.
	 * @param bool $translate Optional. Whether to translate the header. Defaults to true.
	 * @return string|false Processed header, false on failure.
	 */
	public function display( $header, $markup = true, $translate = true ) {
		$value = $this->get( $header );
		if ( false === $value ) {
			return false;
		}

		if ( $translate && ( empty( $value ) || ! $this->load_textdomain() ) )
			$translate = false;

		if ( $translate )
			$value = $this->translate_header( $header, $value );

		if ( $markup )
			$value = $this->markup_header( $header, $value, $translate );

		return $value;
	}

	/**
	 * Sanitize a theme header.
	 *
	 * @since 3.4.0
	 *
	 * @staticvar array $header_tags
	 * @staticvar array $header_tags_with_a
	 *
	 * @param string $header Theme header. Name, Description, Author, Version, ThemeURI, AuthorURI, Status, Tags.
	 * @param string $value Value to sanitize.
	 * @return mixed
	 */
	private function sanitize_header( $header, $value ) {
		switch ( $header ) {
			case 'Status' :
				if ( ! $value ) {
					$value = 'publish';
					break;
				}
				// Fall through otherwise.
			case 'Name' :
				static $header_tags = array(
					'abbr'    => array( 'title' => true ),
					'acronym' => array( 'title' => true ),
					'code'    => true,
					'em'      => true,
					'strong'  => true,
				);
				$value = wp_kses( $value, $header_tags );
				break;
			case 'Author' :
				// There shouldn't be anchor tags in Author, but some themes like to be challenging.
			case 'Description' :
				static $header_tags_with_a = array(
					'a'       => array( 'href' => true, 'title' => true ),
					'abbr'    => array( 'title' => true ),
					'acronym' => array( 'title' => true ),
					'code'    => true,
					'em'      => true,
					'strong'  => true,
				);
				$value = wp_kses( $value, $header_tags_with_a );
				break;
			case 'ThemeURI' :
			case 'AuthorURI' :
				$value = esc_url_raw( $value );
				break;
			case 'Tags' :
				$value = array_filter( array_map( 'trim', explode( ',', strip_tags( $value ) ) ) );
				break;