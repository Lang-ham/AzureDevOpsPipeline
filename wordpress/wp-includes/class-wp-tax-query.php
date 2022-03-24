<?php
/**
 * Taxonomy API: WP_Tax_Query class
 *
 * @package WordPress
 * @subpackage Taxonomy
 * @since 4.4.0
 */

/**
 * Core class used to implement taxonomy queries for the Taxonomy API.
 *
 * Used for generating SQL clauses that filter a primary query according to object
 * taxonomy terms.
 *
 * WP_Tax_Query is a helper that allows primary query classes, such as WP_Query, to filter
 * their results by object metadata, by generating `JOIN` and `WHERE` subclauses to be
 * attached to the primary SQL query string.
 *
 * @since 3.1.0
 */
class WP_Tax_Query {

	/**
	 * Array of taxonomy queries.
	 *
	 * See WP_Tax_Query::__construct() for information on tax query arguments.
	 *
	 * @since 3.1.0
	 * @var array
	 */
	public $queries = array();

	/**
	 * The relation between the queries. Can be one of 'AND' or 'OR'.
	 *
	 * @since 3.1.0
	 * @var string
	 */
	public $relation;

	/**
	 * Standard response when the query should not return any rows.
	 *
	 * @since 3.2.0
	 *
	 * @static
	 * @var string
	 */
	private static $no_results = array( 'join' => array( '' ), 'where' => array( '0 = 1' ) );

	/**
	 * A flat list of table aliases used in the JOIN clauses.
	 *
	 * @since 4.1.0
	 * @var array
	 */
	protected $table_aliases = array();

	/**
	 * Terms and taxonomies fetched by this query.
	 *
	 * We store this data in a flat array because they are referenced in a
	 * number of places by WP_Query.
	 *
	 * @since 4.1.0
	 * @var array
	 */
	public $queried_terms = array();

	/**
	 * Database table that where the metadata's objects are stored (eg $wpdb->users).
	 *
	 * @since 4.1.0
	 * @var string
	 */
	public $primary_table;

	/**
	 * Column in 'primary_table' that represents the ID of the object.
	 *
	 * @since 4.1.0
	 * @var string
	 */
	public $primary_id_column;

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 * @since 4.1.0 Added support for `$operator` 'NOT EXISTS' and 'EXISTS' values.
	 *
	 * @param array $tax_query {
	 *     Array of taxonomy query clauses.
	 *
	 *     @type string $relation Optional. The MySQL keyword used to join
	 *                            the clauses of the query. Accepts 'AND', or 'OR'. Default 'AND'.
	 *     @type array {
	 *         Optional. An array of first-order clause parameters, or another fully-formed tax query.
	 *
	 *         @type string           $taxonomy         Taxonomy being queried. Optional when field=term_taxonomy_id.
	 *         @type string|int|array $terms            Term or terms to filter by.
	 *         @type string           $field            Field to match $terms against. Accepts 'term_id', 'slug',
	 *                                                 'name', or 'term_taxonomy_id'. Default: 'term_id'.
	 *         @type string           $operator         MySQL operator to be used with $terms in the WHERE clause.
	 *                                                  Accepts 'AND', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS'.
	 *                                                  Default: 'IN'.
	 *         @type bool             $include_children Optional. Whether to include child terms.
	 *                                                  Requires a $taxonomy. Default: true.
	 *     }
	 * }
	 */
	public function __construct( $tax_query ) {
		if ( isset( $tax_query['relation'] ) ) {
			$this->relation = $this->sanitize_relation( $tax_query['relation'] );
		} else {
			$this->relation = 'AND';
		}

		$this->queries = $this->sanitize_query( $tax_query );
	}

	/**
	 * Ensure the 'tax_query' argument passed to the class constructor is well-formed.
	 *
	 * Ensures that each query-level clause has a 'relation' key, and that
	 * each first-order clause contains all the necessary keys from `$defaults`.
	 *
	 * @since 4.1.0
	 *
	 * @param array $queries Array of queries clauses.
	 * @return array Sanitized array of query clauses.
	 */
	public function sanitize_query( $queries ) {
		$cleaned_query = array();

		$defaults = array(
			'taxonomy' => '',
			'terms' => array(),
			'field' => 'term_id',
			'operator' => 'IN',
			'include_children' => true,
		);

		foreach ( $queries as $key => $query ) {
			if ( 'relation' === $key ) {
				$cleaned_query['relation'] = $this->sanitize_relation( $query );

			// First-order clause.
			} elseif ( self::is_first_order_clause( $query ) ) {

				$cleaned_clause = array_merge( $defaults, $query );
				$cleaned_clause['terms'] = (array) $cleaned_clause['terms'];
				$cleaned_query[] = $cleaned_clause;

				/*
				 * Keep a copy of the clause in the flate
				 * $queried_terms array, for use in WP_Query.
				 */
				if ( ! empty( $cleaned_clause['taxonomy'] ) && 'NOT IN' !== $cleaned_clause['operator'] ) {
					$taxonomy = $cleaned_clause['taxonomy'];
					if ( ! isset( $this->queried_terms[ $taxonomy ] ) ) {
						$this->queried_terms[ $taxonomy ] = array();
					}

					/*
					 * Backward compatibility: Only store the first
					 * 'terms' and 'field' found for a given taxonomy.
					 */
					if ( ! empty( $cleaned_clause['terms'] ) && ! isset( $this->queried_terms[ $taxonomy ]['terms'] ) ) {
						$this->queried_terms[ $taxonomy ]['terms'] = $cleaned_clause['terms'];
					}

					if ( ! empty( $cleaned_clause['field'] ) && ! isset( $this->queried_terms[ $taxonomy ]['field'] ) ) {
						$this->queried_terms[ $taxonomy ]['field'] = $cleaned_clause['field'];
					}
				}

			// Otherwise, it's a nested query, so we recurse.
			} elseif ( is_array( $query ) ) {
				$cleaned_subquery = $this->sanitize_query( $query );

				if ( ! empty( $cleaned_subquery ) ) {
					// All queries with children must have a relation.
					if ( ! isset( $cleaned_subquery['relation'] ) ) {
						$cleaned_subquery['relation'] = 'AND';
					}

					$cleaned_query[] = $cleaned_subquery;
				}
			}
		}

		return $cleaned_query;
	}

	/**
	 * Sanitize a 'relation' operator.
	 *
	 * @since 4.1.0
	 *
	 * @param string $relation Raw relation key from the query argument.
	 * @return string Sanitized relation ('AND' or 'OR').
	 */
	public function sanitize_relation( $relation ) {
		if ( 'OR' === strtoupper( $relation ) ) {
			return 'OR';
		} else {
			return 'AND';
		}
	}

	/**
	 * Determine whether a clause is first-order.
	 *
	 * A "first-order" clause is one that contains any of the first-order
	 * clause keys ('terms', 'taxonomy', 'include_children', 'field',
	 * 'operator'). An empty clause also counts as a first-order clause,
	 * for backward compatibility. Any clause that doesn't meet this is
	 * determined, by process of elimination, to be a higher-order query.
	 *
	 * @since 4.1.0
	 *
	 * @static
	 *
	 * @param array $query Tax query arguments.
	 * @return bool Whether the query clause is a first-order clause.
	 */
	protected static function is_first_order_clause( $query ) {
		return is_array( $query ) && ( empty( $query ) || array_key_exists( 'terms', $query ) || array_key_exists( 'taxonomy', $query ) || array_key_exists( 'include_children', $query ) || array_key_exists( 'field', $query ) || array_key_exists( 'operator', $query ) );
	}

	/**
	 * Generates SQL clauses to be appended to a main query.
	 *
	 * @since 3.1.0
	 *
	 * @static
	 *
	 * @param string $primary_table     Database table where the object being filtered is stored (eg wp_users).
	 * @param string $primary_id_column ID column for the filtered object in $primary_table.
	 * @return array {
	 *     Array containing JOIN and WHERE SQL clauses to append to the main query.
	 *
	 *     @type string $join  SQL fragment to append to the main JOIN clause.
	 *     @type string $where SQL fragment to append to the main WHERE clause.
	 * }
	 */
	public function get_sql( $primary_table, $primary_id_column ) {
		$this->primary_table = $primary_table;
		$this->primary_id_column = $primary_id_column;

		return $this->get_sql_clauses();
	}

	/**
	 * Generate SQL clauses to be appended to a main query.
	 *
	 * Called by the public WP_Tax_Query::get_sql(), this method
	 * is abstracted out to maintain parity with the other Query classes.
	 *
	 * @since 4.1.0
	 *
	 * @return array {
	 *     Array containing JOIN and WHERE SQL clauses to append to the main query.
	 *
	 *     @type string $join  SQL fragment to append to the main JOIN clause.
	 *     @type string $where SQL fragment to append to the main WHERE clause.
	 * }
	 */
	protected function get_sql_clauses() {
		/*
		 * $queries are passed by reference to get_sql_for_query() for recursion.
		 * To keep $this->queries unaltered, pass a copy.
		 */
		$queries = $this->queries;
		$sql = $this->get_sql_for_query( $queries );

		if ( ! empty( $sql['where'] ) ) {
			$sql['where'] = ' AND ' . $sql['where'];
		}

		return $sql;
	}

	/**
	 * Generate SQL clauses for a single query array.
	 *
	 * If nested subqueries are found, this method recurses the tree to
	 * produce the properly nested SQL.
	 *
	 * @since 4.1.0
	 *
	 * @param array $query Query to parse (passed by reference).
	 * @param int   $depth Optional. Number of tree levels deep we currently are.
	 *                     Used to calculate indentation. Default 0.
	 * @return array {
	 *     Array containing JOIN and WHERE SQL clauses to append to a single query array.
	 *
	 *     @type string $join  SQL fragment to append to the main JOIN clause.
	 *     @type string $where SQL fragment to append to the main WHERE clause.
	 * }
	 */
	protected function get_sql_for_query( &$query, $depth = 0 ) {
		$sql_chunks = array(
			'join'  => array(),
			'where' => array(),
		);

		$sql = array(
			'join'  => '',
			'where' => '',
		);

		$indent = '';
		for ( $i = 0; $i < $depth; $i++ ) {
			$indent .= "  ";
		}

		foreach ( $query as $key => &$clause ) {
			if ( 'relation' === $key ) {
				$relation = $query['relation'];
			} elseif ( is_array( $clause ) ) {

				// This is a first-order clause.
				if ( $this->is_first_order_clause( $clause ) ) {
					$clause_sql = $this->get_sql_for_clause( $clause, $query );

					$where_count = count( $clause_sql['where'] );
					if ( ! $where_count ) {
						$sql_chunks['where'][] = '';
					} elseif ( 1 === $where_count ) {
						$sql_chunks['where'][] = $clause_sql['where'][0];
					} else {
						$sql_chunks['where'][] = '( ' . implode( ' AND ', $clause_sql['where'] ) . ' )';
					}

					$sql_chunks['join'] = array_merge( $sql_chunks['join'], $clause_sql['join'] );
				// This is a subquery, so we recurse.
				} else {
					$clause_sql = $this->get_sql_for_query( $clause, $depth + 1 );

					$sql_chunks['where'][] = $clause_sql['where'];
					$sql_chunks['join'][]  = $clause_sql['join'];
				}
			}
		}

		// Filter to remove empties.
		$sql_chunks['join']  = array_filter( $sql_chunks['join'] );
		$sql_chunks['where'] = array_filter( $sql_chunks['where'] );

		if ( empty( $relation ) ) {
			$relation = 'AND';
		}

		// Filter duplicate JOIN clauses and combine into a single string.
		if ( ! empty( $sql_chunks['join'] ) ) {
			$sql['join'] = implode( ' ', array_unique( $sql_chunks['join'] ) );
		}

		// Generate a single WHERE clause with proper brackets and indentation.
		if ( ! empty( $sql_chunks['where'] ) ) {
			$sql['where'] = '( ' . "\n  " . $indent . implode( ' ' . "\n  " . $indent . $relation . ' ' . "\n  " . $indent, $sql_chunks['where'] ) . "\n" . $indent . ')';
		}

		return $sql;
	}

	/**
	 * 