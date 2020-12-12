<?php
/**
 * List Table API: WP_Themes_List_Table class
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 */

/**
 * Core class used to implement displaying installed themes in a list table.
 *
 * @since 3.1.0
 * @access private
 *
 * @see WP_List_Table
 */
class WP_Themes_List_Table extends WP_List_Table {

	protected $search_terms = array();
	public $features = array();

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
			'ajax' => true,
			'screen' => isset( $args['screen'] ) ? $args['screen'] : null,
		) );
	}

	/**
	 *
	 * @return bool
	 */
	public function ajax_user_can() {
		// Do not check edit_theme_options here. Ajax calls for available themes require switch_themes.
		return current_user_can( 'switch_themes' );
	}

	/**
	 */
	public function prepare_items() {
		$themes = wp_get_themes( array( 'allowed' => true ) );

		if ( ! empty( $_REQUEST['s'] ) )
			$this->search_terms = array_unique( array_filter( array_map( 'trim', explode( ',', strtolower( wp_unslash( $_REQUEST['s'] ) ) ) ) ) );

		if ( ! empty( $_REQUEST['features'] ) )
			$this->features = $_REQUEST['features'];

		if ( $this->search_terms || $this->features ) {
			foreach ( $themes as $key => $theme ) {
				if ( ! $this->search_theme( $theme ) )
					unset( $themes[ $key ] );
			}
		}

		unset( $themes[ get_option( 'stylesheet' ) ] );
		WP_Theme::sort_by_name( $themes );

		$per_page = 36;
		$page = $this->get_pagenum();

		$start = ( $page - 1 ) * $per_page;

		$this->items = array_slice( $themes, $start, $per_page, true );

		$this->set_pagination_args( array(
			'total_items' => count( $themes ),
			'per_page' => $per_page,
			'infinite_scroll' => true,
		) );
	}

	/**
	 */
	public function no_items() {
		if ( $this->search_terms || $this->features ) {
			_e( 'No items found.' );
			return;
		}

		$blog_id = get_current_blog_id();
		if ( is_multisite() ) {
			if ( current_user_can( 'install_themes' ) && current_user_can( 'manage_network_themes' ) ) {
				printf( __( 'You only have one theme enabled for this site right now. Visit the Network Admin to <a href="%1$s">enable</a> or <a href="%2$s">install</a> more themes.' ), network_admin_url( 'site-themes.php?id=' . $blog_id ), network_admin_url( 'theme-install.php' ) );

				return;
			} elseif ( current_user_can( 'manage_network_themes' ) ) {
				printf( __( 'You only have one theme enabled for this site right now. Visit the Network Admin to <a href="%1$s">enable</a> more themes.' ), network_admin_url( 'site-themes.php?id=' . $blog_id ) );

				return;
			}
			// Else, fallthrough. install_themes doesn't help if you can't enable it.
		} else {
			if ( current_user_can( 'install_themes' ) ) {
				printf( __( 'You only have one theme installed right now. Live a little! You can choose from over 1,000 free themes in the WordPress Theme Directory at any time: just click on the <a href="%s">Install Themes</a> tab above.' ), admin_url( 'theme-install.php' ) );

				return;
			}
		}
		// Fallthrough.
		printf( __( 'Only the current theme is available to you. Contact the %s administrator for information about accessing additional themes.' ), get_site_option( 'site_name' ) );
	}

	/**
	 * @param string $which
	 */
	public function tablenav( $which = 'top' ) {
		if ( $this->get_pagination_arg( 'total_pages' ) <= 1 )
			return;
		?>
		<div class="tablenav themes <?php echo $which; ?>">
			<?php $this->pagination( $which ); ?>
			<span class="spinner"></span>
			<br class="clear" />
		</div>
		<?php
	}

	/**
	 */
	public function display() {
		wp_nonce_field( "fetch-list-" . get_class( $this ), '_ajax_fetch_list_nonce' );
?>
		<?php $this->tablenav( 'top' ); ?>

		<div id="availablethemes">
			<?php $this->display_rows_or_placeholder(); ?>
		</div>

		<?php $this->tablenav( 'bottom' ); ?>
<?php
	}

	/**
	 *
	 * @return array
	 */
	public function get_columns() {
		return array();
	}

	/**
	 */
	public function display_rows_or_placeholder() {
		if ( $this->has_items() ) {
			$this->display_rows();
		} else {
			echo '<div class="no-items">';
			$this->no_items();
