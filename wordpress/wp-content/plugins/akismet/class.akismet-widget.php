<?php
/**
 * @package Akismet
 */
class Akismet_Widget extends WP_Widget {

	function __construct() {
		load_plugin_textdomain( 'akismet' );
		
		parent::__construct(
			'akismet_widget',
			__( 'Akismet Widget' , 'akismet'),
			array( 'description' => __( 'Display the number of spam comments Akismet has caught' , 'akismet') )
		);

		if ( is_active_widget( false, false, $this->id_base )