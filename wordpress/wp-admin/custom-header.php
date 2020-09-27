<?php
/**
 * The custom header image script.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * The custom header image class.
 *
 * @since 2.1.0
 */
class Custom_Image_Header {

	/**
	 * Callback for administration header.
	 *
	 * @var callable
	 * @since 2.1.0
	 */
	public $admin_header_callback;

	/**
	 * Callback for header div.
	 *
	 * @var callable
	 * @since 3.0.0
	 */
	public $admin_image_div_callback;

	/**
	 * Holds default headers.
	 *
	 * @var array
	 * @since 3.0.0
	 */
	public $default_headers = array();

	/**
	 * Used to trigger a success message when settings updated and set to true.
	 *
	 * @since 3.0.0
	 * @var bool
	 */
	private $updated;

	/**
	 * Constructor - Register administration header callback.
	 *
	 * @since 2.1.0
	 * @param callable $admin_header_callback
	 * @param callable $admin_image_div_callback Optional custom image div output callback.
	 */
	public function __construct($admin_header_callback, $admin_image_div_callback = '') {
		$this->admin_header_callback = $admin_header_callback;
		$this->admin_image_div_callback = $admin_image_div_callback;

		add_action( 'admin_menu', array( $this, 'init' ) );

		add_action( 'customize_save_after',         array( $this, 'customize_set_last_used' ) );
		add_action( 'wp_ajax_custom-header-crop',   array( $this, 'ajax_header_crop'        ) );
		add_action( 'wp_ajax_custom-header-add',    array( $this, 'ajax_header_add'         ) );
		add_action( 'wp_ajax_custom-header-remove', array( $this, 'ajax_header_rem