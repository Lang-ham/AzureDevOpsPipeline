<?php
/**
 * WordPress Customize Setting classes
 *
 * @package WordPress
 * @subpackage Customize
 * @since 3.4.0
 */

/**
 * Customize Setting class.
 *
 * Handles saving and sanitizing of settings.
 *
 * @since 3.4.0
 *
 * @see WP_Customize_Manager
 */
class WP_Customize_Setting {
	/**
	 * Customizer bootstrap instance.
	 *
	 * @since 3.4.0
	 * @var WP_Customize_Manager
	 */
	public $manager;

	/**
	 * Unique string identifier for the setting.
	 *
	 * @since 3.4.0
	 * @var string
	 */
	public $id;

	/**
	 * Type of customize settings.
	 *
	 * @since 3.4.0
	 * @var string
	 */
	public $type = 'theme_mod';

	/**
	 * Capability required to edit this setting.
	 *
	 * @since 3.4.0
	 * @var string|array
	 */
	public $capability = 'edit_theme_options';

	/**
	 * Feature a theme is required to support to enable this setting.
	 *
	 * @since 3.4.0
	 * @var string
	 */
	public $theme_supports = '';

	/**
	 * The default value for the setting.
	 *
	 * @since 3.4.0
	 * @var string
	 */
	public $default = '';

	/**
	 * Options for rendering the live preview of changes in Theme Customizer.
	 *
	 * Set this value to 'postMessage' to enable a custom Javascript handler to render changes to this setting
	 * as opposed to reloading the whole page.
	 *
	 * @link https://developer.wordpress.org/themes/customize-api
	 *
	 * @since 3.4.0
	 * @var string
	 */
	public $transport = 'refresh';

	/**
	 * Server-side validation callback for the setting's value.
	 *
	 * @since 4.6.0
	 * @var callable
	 */
	public $validate_callback = '';

	/**
	 * Callback to filter a Customize setting value in un-slashed form.
	 *
	 * @since 3.4.0
	 * @var callable
	 */
	public $sanitize_callback = '';

	/**
	 * Callback to convert a Customize PHP setting value to a value that is JSON serializable.
	 *
	 * @since 3.4.0
	 * @var string
	 */
	public $sanitize_js_callback = '';

	/**
	 * Whether or not the setting is initially dirty when created.
	 *
	 * This is used to ensure that a setting will be sent from the pane to the
	 * preview when loading the Customizer. Normally a setting only is synced to
	 * the preview if it has been changed. This allows the setting to be sent
	 * from the start.
	 *
	 * @since 4.2.0
	 * @var bool
	 */
	public $dirty = false;

	/**
	 * ID Data.
	 *
	 * @since 3.4.0
	 * @var array
	 */
	protected $id_data = array();

	/**
	 * Whether or not preview() was called.
	 *
	 * @since 4.4.0
	 * @var bool
	 */
	protected $is_previewed = false;

	/**
	 * Cache of multidimensional values to improve performance.
	 *
	 * @since 4.4.0
	 * @static
	 * @var array
	 */
	protected static $aggregated_multidimensionals = array();

	/**
	 * Whether the multidimensional setting is aggregated.
	 *
	 * @since 4.4.0
	 * @var bool
	 */
	protected $is_multidimensional_aggregated = false;

	/**
	 * Constructor.
	 *
	 * Any supplied $args override class property defaults.
	 *
	 * @since 3.4.0
	 *
	 * @param WP_Customize_Manager $manager
	 * @param string               $id      An specific ID of the setting. Can be a
	 *                                      theme mod or option name.
	 * @param array                $args    Setting arguments.
	 */
	public function __construct( $manager, $id, $args = array() ) {
		$keys = array_keys( get_object_vars( $this ) );
		foreach ( $keys as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = $args[ $key ];
			}
		}

		$this->manager = $manager;
		$this->id = $id;

		// Parse the ID for array keys.
		$this->id_data['keys'] = preg_split( '/\[/', str_replace( ']', '', $this->id ) );
		$this->id_data['base'] = array_shift( $this->id_data['keys'] );

		// Rebuild the ID.
		$this->id = $this->id_data[ 'base' ];
		if ( ! empty( $this->id_data[ 'keys' ] ) ) {
			$this->id .= '[' . implode( '][', $this->id_data['keys'] ) . ']';
		}

		if ( $this->validate_callback ) {
			add_filter( "customize_validate_{$this->id}", $this->validate_callback, 10, 3 );
		}
		if ( $this->sanitize_callback ) {
			add_filter( "customize_sanitize_{$this->id}", $this->sanitize_callback, 10, 2 );
		}
		if ( $this->sanitize_js_callback ) {
			add_filter( "customize_sanitize_js_{$this->id}", $this->sanitize_js_callback, 10, 2 );
		}

		if ( 'option' === $this->type || 'theme_mod' === $this->type ) {
			// Other setting types can opt-in to aggregate multidimensional explicitly.
			$this->aggregate_