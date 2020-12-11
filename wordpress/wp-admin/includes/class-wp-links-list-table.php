<?php
/**
 * List Table API: WP_Links_List_Table class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 */

/**
 * Core class used to implement displaying links in a list table.
 *
 * @since 3.1.0
 * @access private
 *
 * @see WP_List_Tsble
 */
class WP_Links_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 3.1.0
	 *
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 *
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( array(
			'plural' => 'bookmarks',
			'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
		) );
	}

	/**
	 *
	 * @return bool
	 */
	public function ajax_user_can() {
		return current_user_can( 'manage_links' );
	}

	/**
	 *
	 * @global int    $cat_id
	 * @global string $s
	 * @global string $orderby
	 * @global string $order
	 */
	public function prepare_items() {
		global $cat_id, $s, $orderby, $order;

		wp_reset_vars( array( 'action', 'cat_id', 'link_id', 'orderby', 'order', 's' ) );

		$args = array( 'hide_invisible' => 0, 'hide_empty' => 0 );

		if ( 'all' != $cat_id )
			$args['category'] = $cat_id;
		if ( !empty( $s ) )
			$args['search'] = $s;
		if ( !empty( $orderby ) )
			$args['orderby'] = $orderby;
		if ( !empty( $order ) )
			$args['order'] = $order;

		$this->items = get_bookmarks( $args );
	}

	/**
	 */
	public function no_items() {
		_e( 'No links found.' );
	}

	/**
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array();
		$actions['delete'] = __( 'Delete' );

		return $actions;
	}

	/**
	 *
	 * @global int $cat_id
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		global $cat_id;

		if ( 'top' != $which )
			return;
?>
		<div class="alignleft actions">
<?php
			$dropdown_options = array(
				'selected' => $cat_id,
				'name' => 'cat_id',
				'taxonomy' => 'link_category',
				'show_option_all' => get_taxonomy( 'link_category' )->labels->all_items,
				'hide_empty' => true,
				'hierarchical' => 1,
				'show_count' => 0,
				'orderby' => 'name',
			);

			echo '<label class="screen-reader-text" for="cat_id">' . __( 'Filter by category' ) . '</label>';
			wp_dropdown_categories( $dropdown_options );
			submit_button( __( 'Filter' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
?>
		</div>
<?php
	}

	/**
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => _x( 'Name', 'link name' ),
			'url'        => __( 'URL' ),
			'categories' => __( 'Categories' ),
			'rel'        => __( 'Relationship' ),
			'visible'    => __( 'Visible' ),
			'rating'     => __( 'Rating' )
		);
	}

	/**
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'name'    => 'name',
			'url'     => 'url',
			'visible' => 'visible',
			'rating'  => 'rating'
		);
	}

	/**
	 * Get the name of the default primary column.
	 *
	 * @since 4.3.0
	 *
	 * @return string Name of the default primary column, in this case, 'name'.
	 */
	protected function get_default_primary_column_name() {
		return 'name';
	}

	/**
	 * Handles the checkbox column output.
	 *
	 * @since 4.3.0
	 *
	 * @param object $link The current link object.
	 */
	public function column_cb( $link ) {
		?>
		<label class="screen-reader-text" for="cb-select-<?php echo $link->link_id; ?>"><?php echo sprintf( __( 'Select %s' ), $link->link_name ); ?></label>
		<input type="checkbox" name="linkcheck[]" id="cb-select-<?php echo $link->link_id; ?>" value="<?php echo esc_attr( $link->link_id ); ?>" />
		<?php
	}

	/**
	 * Handles the link name column output.
	 *
	 * @since 4.3.0
	 *
	 * @param object $link The current link object.
	 */
	public function column_name( $link ) {
		$edit_link = get_edit_bookmark_link( $link );
		printf( '<strong><a class="row-title" href="%s" aria-label="%s">%s</a></strong>',
			$edit_link,
		