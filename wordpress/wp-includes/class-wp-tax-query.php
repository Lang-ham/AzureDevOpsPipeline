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
	 * See WP_Tax_Query::__construct() for information on tax query 