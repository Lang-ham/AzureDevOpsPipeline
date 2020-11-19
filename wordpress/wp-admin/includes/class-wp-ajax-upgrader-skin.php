<?php
/**
 * Upgrader API: WP_Ajax_Upgrader_Skin class
 *
 * @package WordPress
 * @subpackage Upgrader
 * @since 4.6.0
 */

/**
 * Upgrader Skin for Ajax WordPress upgrades.
 *
 * This skin is designed to be used for Ajax updates.
 *
 * @since 4.6.0
 *
 * @see Automatic_Upgrader_Skin
 */
class WP_Ajax_Upgrader_Skin extends Automatic_Upgrader_Skin {

	/**
	 * Holds the WP_Error object.
	 *
	 * @since 4.6.0
	 * @var null|WP_Error
	 */
	protected $errors = null;

	/**
	 * Constructor.
	 *
	 * @since 4.6.0
	 *
	 * @param array $args Options for the upg