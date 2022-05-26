
<?php
/**
 * Customize API: WP_Customize_Nav_Menu_Item_Setting class
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
 * @see WP_Customize_Setting
 */
class WP_Customize_Nav_Menu_Item_Setting extends WP_Customize_Setting {

	const ID_PATTERN = '/^nav_menu_item\[(?P<id>-?\d+)\]$/';

	const POST_TYPE = 'nav_menu_item';

	const TYPE = 'nav_menu_item';

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
	 * @see wp_setup_nav_menu_item()
	 */
	public $default = array(
		// The $menu_item_data for wp_update_nav_menu_item().
		'object_id'        => 0,
		'object'           => '', // Taxonomy name.
		'menu_item_parent' => 0, // A.K.A. menu-item-parent-id; note that post_parent is different, and not included.
		'position'         => 0, // A.K.A. menu_order.
		'type'             => 'custom', // Note that type_label is not included here.
		'title'            => '',
		'url'              => '',
		'target'           => '',
		'attr_title'       => '',
		'description'      => '',
		'classes'          => '',
		'xfn'              => '',
		'status'           => 'publish',
		'original_title'   => '',
		'nav_menu_term_id' => 0, // This will be supplied as the $menu_id arg for wp_update_nav_menu_item().
		'_invalid'         => false,
	);

	/**
	 * Default transport.
	 *
	 * @since 4.3.0
	 * @since 4.5.0 Default changed to 'refresh'
	 * @var string
	 */
	public $transport = 'refresh';

	/**
	 * The post ID represented by this setting instance. This is the db_id.
	 *
	 * A negative value represents a placeholder ID for a new menu not yet saved.
	 *
	 * @since 4.3.0
	 * @var int
	 */
	public $post_id;

	/**
	 * Storage of pre-setup menu item to prevent wasted calls to wp_setup_nav_menu_item().
	 *
	 * @since 4.3.0
	 * @var array
	 */
	protected $value;

	/**
	 * Previous (placeholder) post ID used before creating a new menu item.
	 *
	 * This value will be exported to JS via the customize_save_response filter
	 * so that JavaScript can update the settings to refer to the newly-assigned
	 * post ID. This value is always negative to indicate it does not refer to
	 * a real post.
	 *
	 * @since 4.3.0
	 * @var int
	 *
	 * @see WP_Customize_Nav_Menu_Item_Setting::update()
	 * @see WP_Customize_Nav_Menu_Item_Setting::amend_customize_save_response()
	 */
	public $previous_post_id;

	/**
	 * When previewing or updating a menu item, this stores the previous nav_menu_term_id
	 * which ensures that we can apply the proper filters.
	 *
	 * @since 4.3.0
	 * @var int
	 */
	public $original_nav_menu_term_id;

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
	 * When status is inserted, the placeholder post ID is stored in $previous_post_id.
	 * When status is error, the error is stored in $update_error.
	 *
	 * @since 4.3.0
	 * @var string updated|inserted|deleted|error
	 *
	 * @see WP_Customize_Nav_Menu_Item_Setting::update()
	 * @see WP_Customize_Nav_Menu_Item_Setting::amend_customize_save_response()
	 */
	public $update_status;

	/**
	 * Any error object returned by wp_update_nav_menu_item() when setting is updated.
	 *
	 * @since 4.3.0
	 * @var WP_Error
	 *
	 * @see WP_Customize_Nav_Menu_Item_Setting::update()
	 * @see WP_Customize_Nav_Menu_Item_Setting::amend_customize_save_response()
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

		$this->post_id = intval( $matches['id'] );
		add_action( 'wp_update_nav_menu_item', array( $this, 'flush_cached_value' ), 10, 2 );

		parent::__construct( $manager, $id, $args );

		// Ensure that an initially-supplied value is valid.
		if ( isset( $this->value ) ) {
			$this->populate_value();
			foreach ( array_diff( array_keys( $this->default ), array_keys( $this->value ) ) as $missing ) {
				throw new Exception( "Supplied nav_menu_item value missing property: $missing" );
			}
		}

	}

	/**
	 * Clear the cached value when this nav menu item is updated.
	 *
	 * @since 4.3.0
	 *
	 * @param int $menu_id       The term ID for the menu.
	 * @param int $menu_item_id  The post ID for the menu item.
	 */
	public function flush_cached_value( $menu_id, $menu_item_id ) {
		unset( $menu_id );
		if ( $menu_item_id === $this->post_id ) {
			$this->value = null;
		}
	}

	/**
	 * Get the instance data for a given nav_menu_item setting.
	 *
	 * @since 4.3.0
	 *
	 * @see wp_setup_nav_menu_item()
	 *
	 * @return array|false Instance data array, or false if the item is marked for deletion.
	 */
	public function value() {
		if ( $this->is_previewed && $this->_previewed_blog_id === get_current_blog_id() ) {
			$undefined  = new stdClass(); // Symbol.
			$post_value = $this->post_value( $undefined );

			if ( $undefined === $post_value ) {
				$value = $this->_original_value;
			} else {
				$value = $post_value;
			}
			if ( ! empty( $value ) && empty( $value['original_title'] ) ) {
				$value['original_title'] = $this->get_original_title( (object) $value );
			}
		} elseif ( isset( $this->value ) ) {
			$value = $this->value;
		} else {
			$value = false;

			// Note that a ID of less than one indicates a nav_menu not yet inserted.
			if ( $this->post_id > 0 ) {
				$post = get_post( $this->post_id );
				if ( $post && self::POST_TYPE === $post->post_type ) {
					$is_title_empty = empty( $post->post_title );
					$value = (array) wp_setup_nav_menu_item( $post );
					if ( $is_title_empty ) {
						$value['title'] = '';
					}
				}
			}

			if ( ! is_array( $value ) ) {
				$value = $this->default;
			}

			// Cache the value for future calls to avoid having to re-call wp_setup_nav_menu_item().
			$this->value = $value;
			$this->populate_value();
			$value = $this->value;
		}

		if ( ! empty( $value ) && empty( $value['type_label'] ) ) {
			$value['type_label'] = $this->get_type_label( (object) $value );
		}

		return $value;
	}

	/**
	 * Get original title.
	 *
	 * @since 4.7.0
	 *
	 * @param object $item Nav menu item.
	 * @return string The original title.
	 */
	protected function get_original_title( $item ) {
		$original_title = '';
		if ( 'post_type' === $item->type && ! empty( $item->object_id ) ) {
			$original_object = get_post( $item->object_id );
			if ( $original_object ) {
				/** This filter is documented in wp-includes/post-template.php */
				$original_title = apply_filters( 'the_title', $original_object->post_title, $original_object->ID );

				if ( '' === $original_title ) {
					/* translators: %d: ID of a post */
					$original_title = sprintf( __( '#%d (no title)' ), $original_object->ID );
				}
			}
		} elseif ( 'taxonomy' === $item->type && ! empty( $item->object_id ) ) {
			$original_term_title = get_term_field( 'name', $item->object_id, $item->object, 'raw' );
			if ( ! is_wp_error( $original_term_title ) ) {
				$original_title = $original_term_title;
			}
		} elseif ( 'post_type_archive' === $item->type ) {
			$original_object = get_post_type_object( $item->object );
			if ( $original_object ) {
				$original_title = $original_object->labels->archives;
			}
		}
		$original_title = html_entity_decode( $original_title, ENT_QUOTES, get_bloginfo( 'charset' ) );
		return $original_title;
	}

	/**
	 * Get type label.
	 *
	 * @since 4.7.0
	 *
	 * @param object $item Nav menu item.
	 * @returns string The type label.
	 */
	protected function get_type_label( $item ) {
		if ( 'post_type' === $item->type ) {
			$object = get_post_type_object( $item->object );
			if ( $object ) {
				$type_label = $object->labels->singular_name;
			} else {
				$type_label = $item->object;
			}
		} elseif ( 'taxonomy' === $item->type ) {
			$object = get_taxonomy( $item->object );
			if ( $object ) {
				$type_label = $object->labels->singular_name;
			} else {