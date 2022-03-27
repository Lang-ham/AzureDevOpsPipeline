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
	 *     @type int          $number              Number of users to limit the query for. Can be used in
	 *                                             conjunction with pagination. Value -1 (all) is supported, but
	 *                                             should be used with caution on larger sites.
	 *                                             Default empty (all users).
	 *     @type int          $paged               When used with number, defines the page of results to return.
	 *                                             Default 1.
	 *     @type bool         $count_total         Whether to count the total number of users found. If pagination
	 *                                             is not needed, setting this to false can improve performance.
	 *                                             Default true.
	 *     @type string|array $fields              Which fields to return. Single or all fields (string), or array
	 *                                             of fields. Accepts 'ID', 'display_name', 'user_login',
	 *                                             'user_nicename', 'user_email', 'user_url', 'user_registered'.
	 *                                             Use 'all' for all fields and 'all_with_meta' to include
	 *                                             meta fields. Default 'all'.
	 *     @type string       $who                 Type of users to query. Accepts 'authors'.
	 *                                             Default empty (all users).
	 *     @type bool|array   $has_published_posts Pass an array of post types to filter results to users who have
	 *                                             published posts in those post types. `true` is an alias for all
	 *                                             public post types.
	 *     @type string       $nicename            The user nicename. Default empty.
	 *     @type array        $nicename__in        An array of nicenames to include. Users matching one of these
	 *                                             nicenames will be included in results. Default empty array.
	 *     @type array        $nicename__not_in    An array of nicenames to exclude. Users matching one of these
	 *                                             nicenames will not be included in results. Default empty array.
	 *     @type string       $login               The user login. Default empty.
	 *     @type array        $login__in           An array of logins to include. Users matching one of these
	 *                                             logins will be included in results. Default empty array.
	 *     @type array        $login__not_in       An array of logins to exclude. Users matching one of these
	 *                                             logins will not be included in results. Default empty array.
	 * }
	 */
	public function prepare_query( $query = array() ) {
		global $wpdb;

		if ( empty( $this->query_vars ) || ! empty( $query ) ) {
			$this->query_limit = null;
			$this->query_vars = $this->fill_query_vars( $query );
		}

		/**
		 * Fires before the WP_User_Query has been parsed.
		 *
		 * The passed WP_User_Query object contains the query variables, not
		 * yet passed into SQL.
		 *
		 * @since 4.0.0
		 *
		 * @param WP_User_Query $this The current WP_User_Query instance,
		 *                            passed by reference.
		 */
		do_action( 'pre_get_users', $this );

		// Ensure that query vars are filled after 'pre_get_users'.
		$qv =& $this->query_vars;
		$qv =  $this->fill_query_vars( $qv );

		if ( is_array( $qv['fields'] ) ) {
			$qv['fields'] = array_unique( $qv['fields'] );

			$this->query_fields = array();
			foreach ( $qv['fields'] as $field ) {
				$field = 'ID' === $field ? 'ID' : sanitize_key( $field );
				$this->query_fields[] = "$wpdb->users.$field";
			}
			$this->query_fields = implode( ',', $this->query_fields );
		} elseif ( 'all' == $qv['fields'] ) {
			$this->query_fields = "$wpdb->users.*";
		} else {
			$this->query_fields = "$wpdb->users.ID";
		}

		if ( isset( $qv['count_total'] ) && $qv['count_total'] )
			$this->query_fields = 'SQL_CALC_FOUND_ROWS ' . $this->query_fields;

		$this->query_from = "FROM $wpdb->users";
		$this->query_where = "WHERE 1=1";

		// Parse and sanitize 'include', for use by 'orderby' as well as 'include' below.
		if ( ! empty( $qv['include'] ) ) {
			$include = wp_parse_id_list( $qv['include'] );
		} else {
			$include = false;
		}

		$blog_id = 0;
		if ( isset( $qv['blog_id'] ) ) {
			$blog_id = absint( $qv['blog_id'] );
		}

		if ( $qv['has_published_posts'] && $blog_id ) {
			if ( true === $qv['has_published_posts'] ) {
				$post_types = get_post_types( array( 'public' => true ) );
			} else {
				$post_types = (array) $qv['has_published_posts'];
			}

			foreach ( $post_types as &$post_type ) {
				$post_type = $wpdb->prepare( '%s', $post_type );
			}

			$posts_table = $wpdb->get_blog_prefix( $blog_id ) . 'posts';
			$this->query_where .= " AND $wpdb->users.ID IN ( SELECT DISTINCT $posts_table.post_author FROM $posts_table WHERE $posts_table.post_status = 'publish' AND $posts_table.post_type IN ( " . join( ", ", $post_types ) . " ) )";
		}

		// nicename
		if ( '' !== $qv['nicename']) {
			$this->query_where .= $wpdb->prepare( ' AND user_nicename = %s', $qv['nicename'] );
		}

		if ( ! empty( $qv['nicename__in'] ) ) {
			$sanitized_nicename__in = array_map( 'esc_sql', $qv['nicename__in'] );
			$nicename__in = implode( "','", $sanitized_nicename__in );
			$this->query_where .= " AND user_nicename IN ( '$nicename__in' )";
		}

		if ( ! empty( $qv['nicename__not_in'] ) ) {
			$sanitized_nicename__not_in = array_map( 'esc_sql', $qv['nicename__not_in'] );
			$nicename__not_in = implode( "','", $sanitized_nicename__not_in );
			$this->query_where .= " AND user_nicename NOT IN ( '$nicename__not_in' )";
		}

		// login
		if ( '' !== $qv['login']) {
			$this->query_where .= $wpdb->prepare( ' AND user_login = %s', $qv['login'] );
		}

		if ( ! empty( $qv['login__in'] ) ) {
			$sanitized_login__in = array_map( 'esc_sql', $qv['login__in'] );
			$login__in = implode( "','", $sanitized_login__in );
			$this->query_where .= " AND user_login IN ( '$login__in' )";
		}

		if ( ! empty( $qv['login__not_in'] ) ) {
			$sanitized_login__not_in = array_map( 'esc_sql', $qv['login__not_in'] );
			$login__not_in = implode( "','", $sanitized_login__not_in );
			$this->query_where .= " AND user_login NOT IN ( '$login__not_in' )";
		}

		// Meta query.
		$this->meta_query = new WP_Meta_Query();
		$this->meta_query->parse_query_vars( $qv );

		if ( isset( $qv['who'] ) && 'authors' == $qv['who'] && $blog_id ) {
			$who_query = array(
				'key' => $wpdb->get_blog_prefix( $blog_id ) . 'user_level',
				'value' => 0,
				'compare' => '!=',
			);

			// Prevent extra meta query.
			$qv['blog_id'] = $blog_id = 0;

			if ( empty( $this->meta_query->queries ) ) {
				$this->meta_query->queries = array( $who_query );
			} else {
				// Append the cap query to the original queries and reparse the query.
				$this->meta_query->queries = array(
					'relation' => 'AND',
					array( $this->meta_query->queries, $who_query ),
				);
			}

			$this->meta_query->parse_query_vars( $this->meta_query->queries );
		}

		$roles = array();
		if ( isset( $qv['role'] ) ) {
			if ( is_array( $qv['role'] ) ) {
				$roles = $qv['role'];
			} elseif ( is_string( $qv['role'] ) && ! empty( $qv['role'] ) ) {
				$roles = array_map( 'trim', explode( ',', $qv['role'] ) );
			}
		}

		$role__in = array();
		if ( isset( $qv['role__in'] ) ) {
			$role__in = (array) $qv['role__in'];
		}

		$role__not_in = array();
		if ( isset( $qv['role__not_in'] ) ) {
			$role__not_in = (array) $qv['role__not_in'];
		}

		if ( $blog_id && ( ! empty( $roles ) || ! empty( $role__in ) || ! empty( $role__not_in ) || is_multisite() ) ) {
			$role_queries  = array();

			$roles_clauses = array( 'relation' => 'AND' );
			if ( ! empty( $roles ) ) {
				foreach ( $roles as $role ) {
					$roles_clauses[] = array(
						'key'     => $wpdb->get_blog_prefix( $blog_id ) . 'capabilities',
						'value'   => '"' . $role . '"',
						'compare' => 'LIKE',
					);
				}

				$role_queries[] = $roles_clauses;
			}

			$role__in_clauses = array( 'relation' => 'OR' );
			if ( ! empty( $role__in ) ) {
				foreach ( $role__in as $role ) {
					$role__in_clauses[] = array(
						'key'     => $wpdb->get_blog_prefix( $blog_id ) . 'capabilities',
						'value'   => '"' . $role . '"',
						'compare' => 'LIKE',
					);
				}

				$role_queries[] = $role__in_clauses;
			}

			$role__not_in_clauses = array( 'relation' => 'AND' );
			if ( ! empty( $role__not_in ) ) {
				foreach ( $role__not_in as $role ) {
					$role__not_in_clauses[] = array(
						'key'     => $wpdb->get_blog_prefix( $blog_id ) . 'capabilities',
						'value'   => '"' . $role . '"',
						'compare' => 'NOT LIKE',
					);
				}

				$role_queries[] = $role__not_in_clauses;
			}

			// If there are no specific roles named, make sure the user is a member of the site.
			if ( empty( $role_queries ) ) {
				$role_queries[] = array(
					'key' => $wpdb->get_blog_prefix( $blog_id ) . 'capabilities',
					'compare' => 'EXISTS',
				);
			}

			// Specify that role queries should be joined with AND.
			$role_queries['relation'] = 'AND';

			if ( empty( $this->meta_query->queries ) ) {
				$this->meta_query->queries = $role_queries;
			} else {
				// Append the cap query to the original queries and reparse the query.
				$this->meta_query->queries = array(
					'relation' => 'AND',
					array( $this->meta_query->queries, $role_queries ),
				);
			}

			$this->meta_query->parse_query_vars( $this->meta_query->queries );
		}

		if ( ! empty( $this->meta_query->queries ) ) {
			$clauses = $this->meta_query->get_sql( 'user', $wpdb->users, 'ID', $this );
			$this->query_from .= $clauses['join'];
			$this->query_where .= $clauses['where'];

			if ( $this->meta_query->has_or_relation() ) {
				$this->query_fields = 'DISTINCT ' . $this->query_fields;
			}
		}

		// sorting
		$qv['order'] = isset( $qv['order'] ) ? strtoupper( $qv['order'] ) : '';
		$order = $this->parse_order( $qv['order'] );

		if ( empty( $qv['orderby'] ) ) {
			// Default order is by 'user_login'.
			$ordersby = array( 'user_login' => $order );
		} elseif ( is_array( $qv['orderby'] ) ) {
			$ordersby = $qv['orderby'];
		} else {
			// 'orderby' values may be a comma- or space-separated list.
			$ordersby = preg_split( '/[,\s]+/', $qv['orderby'] );
		}

		$orderby_array = array();
		foreach ( $ordersby as $_key => $_value ) {
			if ( ! $_value ) {
				continue;
			}

			if ( is_int( $_key ) ) {
				// Integer key means this is a flat array of 'orderby' fields.
				$_orderby = $_value;
				$_order = $order;
			} else {
				// Non-integer key means this the key is the field and the value is ASC/DESC.
				$_o