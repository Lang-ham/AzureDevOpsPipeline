<?php
/**
 * REST API: WP_REST_Comments_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 4.7.0
 */

/**
 * Core controller used to access comments via the REST API.
 *
 * @since 4.7.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Comments_Controller extends WP_REST_Controller {

	/**
	 * Instance of a comment meta fields object.
	 *
	 * @since 4.7.0
	 * @var WP_REST_Comment_Meta_Fields
	 */
	protected $meta;

	/**
	 * Constructor.
	 *
	 * @since 4.7.0
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'comments';

		$this->meta = new WP_REST_Comment_Meta_Fields();
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 4.7.0
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'   => WP_REST_Server::READABLE,
				'callback'  => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'      => $this->get_collection_params(),
			),
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'     => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			'args' => array(
				'id' => array(
					'description' => __( 'Unique identifier for the object.' ),
					'type'        => 'integer',
				),
			),
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'     => array(
					'context'          => $this->get_context_param( array( 'default' => 'view' ) ),
					'password' => array(
						'description' => __( 'The password for the parent post of the comment (if the post is password protected).' ),
						'type'        => 'string',
					),
				),
			),
			array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( 