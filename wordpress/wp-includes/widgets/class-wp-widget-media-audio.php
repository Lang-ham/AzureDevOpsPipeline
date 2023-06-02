<?php
/**
 * Widget API: WP_Widget_Media_Audio class
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 4.8.0
 */

/**
 * Core class that implements an audio widget.
 *
 * @since 4.8.0
 *
 * @see WP_Widget
 */
class WP_Widget_Media_Audio extends WP_Widget_Media {

	/**
	 * Constructor.
	 *
	 * @since  4.8.0
	 */
	public function __construct() {
		parent::__construct( 'media_audio', __( 'Audio' ), array(
			'description' => __( 'Displays an audio player.' ),
			'mime_type'   => 'audio',
		) );

		$this->l10n = array_merge( $this->l10n, array(
			'no_media_selected' => __( 'No audio selected' ),
			'add_media' => _x( 'Add Audio', 'label for button in the audio widget' ),
			'replace_media' => _x( 'Replace Audio', 'label for button in the audio widget; should preferably not be longer than ~13 characters long' ),
			'edit_media' => _x( 'Edit Audio', 'label for button in the audio widget; should preferably not be longer than ~13 characters long' ),
			'missing_attachment' => sprintf(
				/* translators: %s: URL to media library */
				__( 'We can&#8217;t find that audio file. Check your <a href="%s">media library</a> and make sure it wasn&#8217;t deleted.' ),
				esc_url( admin_url( 'upload.php' ) )
			),
			/* translators: %d: widget count */
			'media_library_state_multi' => _n_noop( 'Audio Widget (%d)', 'Audio Widget (%d)' ),
			'media_library_state_single' => __( 'Audio Widget' ),
			'unsupported_file_type' => __( 'Looks like this isn&#8217;t the correct kind of file. Please link to an audio file instead.' ),
		) );
	}

	/**
	 * Get schema for properties of a widget instance (item).
	 *
	 * @since  4.8.0
	 *
	 * @see WP_REST_Controller::get_item_schema()
	 * @see WP_REST_Controller::get_additional_fields()
	 * @link https://core.trac.wordpress.org/ticket/35574
	 * @return array Schema for properties.
	 */
	public function get_instance_schema() {
		$schema = array_merge(
			parent::get_instance_schema(),
			array(
				'preload' => array(
					'type' => 'string',
					'enum' => array( 'none', 'auto', 'metadata' ),
					'default' => 'none',