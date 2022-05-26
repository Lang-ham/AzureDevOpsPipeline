<?php
/**
 * Customize API: WP_Customize_Nav_Menu_Setting class
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.4.0
 */

/**
 * Customize Setting to represent a nav_menu.
 *
 * Subclass of WP_Customize_Setting to represent a nav_menu taxonomy term, and
 * the IDs for the nav_menu_items associated with the nav menu.
 *
 * @since 4.3.0
 *
 * @see wp_get_nav_menu_object()
 * @see WP_Customize_Setting
 */
class WP_Customize_Nav_Menu_Setting extends WP_Customize_Setting {

	const ID_PATTERN = '/^nav_menu\[(?P<id>-?\d+)\]$/';

	const TAXONOMY = 'nav_menu';

	const TYPE = 'nav_menu';

	/**
	 * Setting type.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public $type = self::TYPE;

	/**
	 * Default setting value.
	 *
	 * @since 4.3.0
	 * @var array
	 *
	 * @see wp_get_nav_menu_object()
	 */
	public $default = array(
		'name'        => '',
		'description' => '',
		'parent'      => 0,
		'auto_add'    => false,
	);

	/**
	 * Default transport.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public $transport = 'postMessage';

	/**
	 * The term ID represented by this setting instance.
	 *
	 * A negative value represents a placeholder ID for a new menu not yet saved.
	 *
	 * @since 4.3.0
	 * @var int
	 */
	public $term_id;

	/**
	 * Previous (placeholder) term ID used before creating a new menu.
	 *
	 * This value will be exported to JS via the {@see 'customize_save_response'} filter
	 * so that JavaScript can update the settings to refer to the newly-assigned
	 * term ID. This value is always negative to indicate it does not refer to
	 * a real term.
	 *
	 * @since 4.3.0
	 * @var int
	 *
	 * @see WP_Customize_Nav_Menu_Setting::update()
	 * @see WP_Customize_Nav_Menu_Setting::amend_customize_save_response()
	 */
	public $previous_term_id;

	/**
	 * Whether or not update() was called.
	 *
	 * @since 4.3.0
	 * @var bool
	 */
	protected $is_updated = false;

	/**
	 * Status for calling the update method, used in customize_save_response filter.
	 *
	 * See {@see 'customize_save_response'}.
	 *
	 * When status is inserted, the placeholder term ID is stored in `$previous_term_id`.
	 * When status is error, the error is stored in `$update_error`.
	 *
	 * @since 4.3.0
	 * @var string updated|inserted|deleted|error
	 *
	 * @see WP_Customize_Nav_Menu_Setting::update()
	 * @see WP_Customize_Nav_Menu_Setting::amend_customize_save_response()
	 */
	public $update_status;

	/**
	 * Any error object returned by wp_update_nav_menu_object() when setting is updated.
	 *
	 * @since 4.3.0
	 * @var WP_Error
	 *
	 * @see WP_Customize_Nav_Menu_Setting::update()
	 * @see WP_Customize_Nav_Menu_Setting::amend_customize_save_response()
	 */
	public $update_error;

	/**
	 * Constructor.
	 *
	 * Any supplied $args override class property defaults.
	 *
	 * @since 4.3.0
	 *
	 * @param WP_Customize_Manager $manager Bootstrap Customizer instance.
	 * @param string               $id      An specific ID of the setting. Can be a
	 *                                      theme mod or option name.
	 * @param array                $args    Optional. Setting arguments.
	 *
	 * @throws Exception If $id is not valid for this setting type.
	 */
	public function __construct( WP_Customize_Manager $manager, $id, array $args = array() ) {
		if ( empty( $manager->nav_menus ) ) {
			throw new Exception( 'Expected WP_Customize_Manager::$nav_menus to be set.' );
		}

		if ( ! preg_match( self::ID_PATTERN, $id, $matches ) ) {
			throw new Exception( "Illegal widget setting ID: $id" );
		}

		$this->term_id = intval( $matches['id'] );

		parent::__construct( $manager, $id, $args );
	}

	/**
	 * Get the instance data for a given widget setting.
	 *
	 * @since 4.3.0
	 *
	 * @see wp_get_nav_menu_object()
	 *
	 * @return array Instance data.
	 */
	public function value() {
		if ( $this->is_previewed && $this->_previewed_blog_id === get_current_blog_id() ) {
			$undefined  = new stdClass(); // Symbol.
			$post_value = $this->post_value( $undefined );

			if ( $undefined === $post_value ) {
				$value = $this->_original_value;
			} else {
				$value = $post_value;
	