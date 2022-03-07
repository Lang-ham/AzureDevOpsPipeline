<?php
/**
 * WordPress Customize Panel classes
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.0.0
 */

/**
 * Customize Panel class.
 *
 * A UI container for sections, managed by the WP_Customize_Manager.
 *
 * @since 4.0.0
 *
 * @see WP_Customize_Manager
 */
class WP_Customize_Panel {

	/**
	 * Incremented with each new class instantiation, then stored in $instance_number.
	 *
	 * Used when sorting two instances whose priorities are equal.
	 *
	 * @since 4.1.0
	 *
	 * @static
	 * @var int
	 */
	protected static $instance_count = 0;

	/**
	 * Order in which this instance was created in relation to other instances.
	 *
	 * @since 4.1.0
	 * @var int
	 */
	public $instance_number;

	/**
	 * WP_Customize_Manager instance.
	 *
	 * @since 4.0.0
	 * @var WP_Customize_Manager
	 */
	public $manager;

	/**
	 * Unique identifier.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public $id;

	/**
	 * Priority of the panel, defining the display order of panels and sections.
	 *
	 * @since 4.0.0
	 * @var integer
	 */
	public $priority = 160;

	/**
	 * Capability required for the panel.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public $capability = 'edit_theme_options';

	/**
	 * Theme feature support for the panel.
	 *
	 * @since 4.0.0
	 * @var string|array
	 */
	public $theme_supports = '';

	/**
	 * Title of the panel to show in UI.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public $title = '';

	/**
	 * Description to show in the UI.
	 *
	 * @since 4.0.0
	 * @var string
	 */
	public $description = '';

	/**
	 * Auto-expand a section in a panel when the panel is expanded when the panel only has the one section.
	 *
	 * @since 4.7.4
	 * @var bool
	 */
	public $auto_expand_sole_section = false;

	/**
	 * Customizer sections for this panel.
	 *
	 * @since 4.0.0
	 * @var array
	 */
	public $sections;

	/**
	 * Type of this panel.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	public $type = 'default';

	/**
	 * Active callback.
	 *
	 * @since 4.1.0
	 *
	 * @see WP_Customize_Section::active()
	 *
	 * @var callable Callback is called with one argument, the instance of
	 *               WP_Customize_Section, and returns bool to indicate whether
	 *               the section is active (such as it relates to the URL currently
	 *               being previewed).
	 */
	public $active_callback = '';

	/**
	 * Constructor.
	 *
	 * Any supplied $args override class property defaults.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_Customize_Manager $manager Customizer bootstrap instance.
	 * @param string               $id      An specific ID for the panel.
	 * @param array                $args    Panel arguments.
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
		if ( empty( $this->active_callback ) ) {
			$this->active_callback = array( $this, 'active_callback' );
		}
		self::$instance_count += 1;
		$this->instance_number = self::$instance_count;

		$this->sections = array(); // Users cannot customize the $sections array.
	}

	/**
	 * Check whether panel is active to current Customizer preview.
	 *
	 * @since 4.1.0
	 *
	 * @return bool Whether the panel is active to the current preview.
	 */
	final public function active() {
		$panel = $this;
		$active = call_user_func( $this->active_callback, $this );

		/**
		 * Filters response of WP_Customize_Panel::active().
		 *
		 * @since 4.1.0
		 *
		 * @param bool               $active Whether the Customizer 