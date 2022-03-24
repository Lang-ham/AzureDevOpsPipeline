<?php
/**
 * User API: WP_User_Query class
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.4.0
 */

/**
 * Core class used for querying users.
 *
 * @since 3.1.0
 *
 * @see WP_User_Query::prepare_query() for information on accepted arguments.
 */
class WP_User_Query {

	/**
	 * Query vars, after parsing
	 *
	 * @since 3.5.0
	 * @var array
	 */
	public $query_vars = array();

	/**
	 * List of found user ids
	 *
	 * @since 3.1.0
	 * @var array
	 */
	private $results;

	/**
	 * Total number of found users for the current query
	 *
	 * @since 3.1.0
	 * @var int
	 */
	private $total_users = 0;

	/**
	 * Metadata query container.
	 *
	 * @since 4.2.0
	 * @var WP_Meta_Query
	 */
	public $meta_query = false;

	/**
	 * The SQL query used to fetch matching users.
	 *
	 * @since 4.4.0
	 * @var string
	 */
	public $request;

	private $compat_fields = array( 'results', 'total_users' );

	// SQL clauses
	public $query_fields;
	public $query_from;
	public $query_where;
	public $query_orderby;
	public $query_limit;

	/**
	 * PHP5 constructor.
	 *
	 * @since 3.1.0
	 *
	 * @param null|string|array $query Optional. The query variables.
	 */
	public function __construct( $query = null ) {
		if ( ! empty( $query ) ) {
			$this->prepare_query( $query );
			$this->query();
		}
	}

	/**
	 * Fills in missing query variables with default values.
	 *
	 * @since 4.4.0
	 *
	 * @param array $args Query vars, as passed to `WP_User_Query`.
	 * @return array Complete query variables with undefined ones filled in with defaults.
	 */
	public static function fill_query_vars( $args ) {
		$defaults = array(
			'blog_id' => get_current_blog_id(),
			'role' => '',
			'role__in' => array(),
			'role__not_in' => array(),
			'meta_key' => '',
			'meta_value' => '',
			'meta_compare' => '',
			'include' => array(),
			'exclude' => array(),
			'search' => '',
			'search_columns' => array(),
			'orderby' => 'login',
			'order' => 'ASC',
			'offset' => '',
			'number' => '',
			'paged' => 1,
			'count_total' => true,
			'fields' => 'all',
			'who' => '',
			'has_published_posts' => null,
			'nicename' => '',
			'nicename__in' => array(),
			'nicename__not_in' => array(),
			'login' => '',
			'login__in' => array(),
			'login__not_in' => array()
		);

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Prepare the query variables.
	 *
	 * @since 3.1.0
	 * @since 4.1.0 Added the ability to order by the `include` value.
	 * @since 4.2.0 Added 'meta_value_num' support for `$orderby` parameter. Added multi-dimensional array syntax
	 *              for `$orderby` parameter.
	 * @since 4.3.0 Added 'has_published_posts' parameter.
	 * @since 4.4.0 Added 'paged', 'role__in', and 'role__not_in' parameters. The 'role' parameter was updated to
	 *              permit an array or comma-separated list of values. The 'number' parameter was updated to support
	 *              querying for all users with using -1.
	 * @since 4.7.0 Added 'nicename', 'nicename__in', 'nicename__not_in', 'login', 'login__in',
	 *              and 'login__not_in' parameters.
	 *
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @global int  $blog_id
	 *
	 * @param string|array $query {
	 *     Optional. Array or string of Query parameters.
	 *
	 *     @type int          $blog_id             The site ID. Default is the current site.
	 *     @type string|array $role                An array or a comma-separated list of role names that users must match
	 *                                             to be included in results. Note that this is an inclusive list: users
	 *                                             must match *each* role. Default empty.
	 *     @type array        $role__in            An array of role names. Matched users must have at least one of these
	 *                                             roles. Default empty array.
	 *     @type array        $role__not_in        An array of role names to exclude. Users matching one or more of these
	 *                                             roles will not be included in results. Default empty array.
	 *     @type string       $meta_key            User meta key. Default empty.
	 *     @type string       $meta_value          User meta value. Default empty.
	 *     @type string       $meta_compare        Comparison operator to test the `$meta_value`. Accepts '=', '!=',
	 *                                             '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN',
	 *                                             'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS', 'REGEXP',
	 *                                             'NOT REGEXP', or 'RLIKE'. Default '='.
	 *     @type array        $include             An array of user IDs to include. Default empty array.
	 *     @type array        $exclude             An array of user IDs to exclude. Default empty array.
	 *     @type string       $search              Search keyword. Searches for possible string matches on columns.
	 *                                             When `$search_columns` is left empty, it tries to determine which
	 *                                             column to search in based on search string. Default empty.
	 *     @type array        $search_columns      Array of column names to be searched. Accepts 'ID', 'login',
	 *                                             'nicename', 'email', 'url'. Default empty array.
	 *     @type string|array $orderby             Field(s) to sort the retrieved users by. May be a single value,
	 *                                             an array of values, or a multi-dimensional array with fields as
	 *                                             keys and orders ('ASC' or 'DESC') as values. Accepted values are
	 *                                             'ID', 'display_name' (or 'name'), 'include', 'user_login'
	 *                                             (or 'login'), 'login__in', 'user_nicename' (or 'nicename'),
	 *                                             'nicename__in', 'user_email (or 'email'), 'user_url' (or 'url'),
	 *                                             'user_registered' (or 'registered'), 'post_count', 'meta_value',
	 *                                             'meta_value_num', the value of `$meta_key`, or an array key of
	 *                                             `$meta_query`. To use 'meta_value' or 'meta_value_num', `$meta_key`
	 *                                             must be also be defined. Default 'user_login'.
	 *     @type string       $order               Designates ascending or descending order of users. Order values
	 *                                             passed as part of an `$orderby` array take precedence over this
	 *                                             parameter. Accepts 'ASC', 'DESC'. Default 'ASC'.
	 *     @type int          $offset              Number of users to offset in retrieved results. Can be used in
	 *                                             conjunction with pagination. Default 0.
	 *     @type int          $number              Number of users to limit the query for. Can be