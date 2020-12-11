<?php
/**
 * List Table API: WP_MS_Users_List_Table class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 */

/**
 * Core class used to implement displaying users in a list table for the network admin.
 *
 * @since 3.1.0
 * @access private
 *
 * @see WP_List_Table
 */
class WP_MS_Users_List_Table extends WP_List_Table {
	/**
	 *
	 * @return bool
	 */
	public function ajax_user_can() {
		return current_user_can( 'manage_network_users' );
	}

	/**
	 *
	 * @global string $usersearch
	 * @global string $role
	 * @global wpdb   $wpdb
	 * @global string $mode
	 */
	public function prepare_items() {
		global $usersearch, $role, $wpdb, $mode;

		$usersearch = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';

		$users_per_page = $this->get_items_per_page( 'users_network_per_page' );

		$role = isset( $_REQUEST['role'] ) ? $_REQUEST['role'] : '';

		$paged = $this->get_pagenum();

		$args = array(
			'number' => $users_per_page,
			'offset' => ( $paged-1 ) * $users_per_page,
			'search' => $usersearch,
			'blog_id' => 0,
			'fields' => 'all_with_meta'
		);

		if ( wp_is_large_network( 'users' ) ) {
			$args['search'] = ltrim( $args['search'], '*' );
		} else if ( '' !== $args['search'] ) {
			$args['search'] = trim( $args['search'], '*' );
			$args['search'] = '*' . $args['search'] . '*';
		}

		if ( $role === 'super' ) {
			$logins = implode( "', '", get_super_admins() );
			$args['include'] = $wpdb->get_col( "SELECT ID FROM $wpdb->users WHERE user_login IN ('$logins')" );
		}

		/*
		 * If the network is large and a search is not being performed,
		 * show only the latest users with no paging in order to avoid
		 * expensive count queries.
		 */
		if ( !$usersearch && wp_is_large_network( 'users' ) ) {
			if ( !isset($_REQUEST['orderby']) )
				$_GET['orderby'] = $_REQUEST['orderby'] = 'id';
			if ( !isset($_REQUEST['order']) )
				$_GET['order'] = $_REQUEST['order'] = 'DESC';
			$args['count_total'] = false;
		}

		if ( isset( $_REQUEST['orderby'] ) )
			$args['orderby'] = $_REQUEST['orderby'];

		if ( isset( $_REQUEST['order'] ) )
			$args['order'] = $_REQUEST['order'];

		if ( ! empty( $_REQUEST['mode'] ) ) {
			$mode = $_REQUEST['mode'] === 'excerpt' ? 'excerpt' : 'list';
			set_user_setting( 'network_users_list_mode', $mode );
		} else {
			$mode = get_user_setting( 'network_users_list_mode', 'list' );
		}

		/** This filter is documented in wp-admin/includes/class-wp-users-list-table.php */
		$args = apply_filters( 'users_list_table_query_args', $args );

		// Query the user IDs for this page
		$wp_user_search = new WP_User_Query( $args );

		$this->items = $wp_user_search->get_results();

		$this->set_pagination_args( array(
			'total_items' => $wp_user_search->get_total(),
			'per_page' => $user