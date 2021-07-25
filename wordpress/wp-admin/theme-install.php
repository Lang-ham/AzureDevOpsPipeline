<?php
/**
 * Install theme administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );
require( ABSPATH . 'wp-admin/includes/theme-install.php' );

wp_reset_vars( array( 'tab' ) );

if ( ! current_user_can('install_themes') )
	wp_die( __( 'Sorry, you are not allowed to install themes on this site.' ) );

if ( is_multisite() && ! is_network_admin() ) {
	wp_redirect( network_admin_url( 'theme-install.php' ) );
	exit();
}

$title = __( 'Add Themes' );
$parent_file = 'themes.php';

if ( ! is_network_admin() ) {
	$submenu_file = 'themes.php';
}

$installed_themes = search_theme_directories();

if ( false === $installed_themes ) {
	$installed_themes = array();
}

foreach ( $installed_themes as $k => $v ) {
	if ( false !== strpos( $k, '/' ) ) {
		unset( $installed_themes[ $k ] );
	}
}

wp_localize_script( 'theme', '_wpThemeSettings', array(
	'themes'   => false,
	'settings' => array(
		'isInstall'  => true,
		'canInstall' => current_user_can( 'install_themes' ),
		'installURI' => current_user_can( 'install_themes' ) ? self_admin_url( 'theme-install.php' ) : null,
		'adminUrl'   => parse_url( self_admin_url(), PHP_URL_PATH )
	),
	'l10n' => array(
		'addNew'              => __( 'Add New Theme' ),
		'search'              => __( 'Search Themes' ),
		'searchPlaceholder'   => __( 'Search themes...' ), // placeholder (no ellipsis)
		'upload'              => __( 'Upload Theme' ),
		'back'                => __( 'Back' ),
		'error'               => sprintf(
			/* translators: %s: support forums URL */
			__( 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="%s">support forums</a>.' ),
			__( 'https://wordpress.org/support/' )
		),
		'tryAgain'            => __( 'Try Again' ),
		'themesFound'         => __( 'Number of Themes found: %d' ),
		'noThemesFound'       => __( 'No themes found. Try a different search.' ),
		'collapseSidebar'     => __( 'Collapse Sidebar' ),
		'expandSidebar'       => __( 'Expand Sidebar' ),
		/* translators: accessibility text */
		'selectFeatureFilter' => __( 'Select one or more Theme features to filter by' ),
	),
	'installedThemes' => array_keys( $installed_themes ),
) );

wp_enqueue_script( 'theme' );
wp_enqueue_script( 'updates' );

if ( $tab ) {
	/**
	 * Fires before each of the tabs are rendered on the Install Themes page.
	 *
	 * The dynamic portion of the hook name, `$tab`, refers to the current
	 * theme installation tab. Possible values are 'dashboard', 'search', 'upload',
	 * 'featured', 'new', or 'updated'.
	 *
	 * @since 2.8.0
	 */
	do_action( "install_themes_pre_{$tab}" );
}

$help_overview =
	'<p>' . sprintf(
			/* translators: %s: Theme Directory URL */
			__( 'You can find additional themes for your site by using the Theme Browser/Installer on this screen, which will display themes from the <a href="%s">WordPress Theme Directory</a>. These themes are designed and developed by third parties, are available free of charge, and are compatible with the license WordPress uses.' ),
			__( 'https://wordpress.org/themes/' )
		) . '</p>' .
	'<p>' . __( 'You can Search for themes by keyword, author, or tag, or can get more specific and search by criteria listed in the feature filter.' ) . ' <span id="live-search-desc">' . __( 'The search results will be updated as you type.' ) . '</span></p>' .
	'<p>' . __( 'Alternately, you can browse the themes that are Featured, Popular, or Latest. When you find a theme you like, you can preview it or install it.' ) . '</p>' .
	'<p>' . sprintf(
			/* translators: %s: /wp-content/themes */
			__( 'You can Upload a theme manually if you have already downloaded its ZIP archive onto your computer (make sure it is from a trusted and original source). You can also do it the old-fashioned way and copy a downloaded theme&#8217;s folder via FTP into your %s directory.' ),
			'<code>/wp-content/themes</code>'
		) . '</p>';

get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => $help_overview
) );

$help_installing =
	'<p>' . __('Once you have generated a list of themes, you can preview and install any of them. Click on the thumbnail of the theme you&#8217;re interested in previewing. It will open up in a full-screen Preview page to give you a better idea of how that theme will look.') . '</p>' .
	'<p>' . __('To install the theme so you can preview it with your site&#8217;s content and customize its theme options, click the "Install" button at the top of the left-hand pane. The theme files will be downloaded to your website automatically. When this is complete, the theme is now available for activation, which you can do by clicking the "Activate" link, or by navigating to your Manage Themes screen and clicking the "Live Preview" link under any installed theme&#8217;s thumbnail image.') . '</p>';

get_current_screen()->add_help_tab( array(
	'id'      => 'installing',
	'title'   => __('Previewing and Installing'),
	'content' => $help_installing
) );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __('For more information:') . '</strong></p>' .
	'<p>' . __('<a href="https://codex.wordpress.org/Using_Themes#Adding_New_Themes">Documentation on Adding New Themes</a>') . '</p>' .
	'<p>' . __('<a href="https://wordpress.org/support/">Support Forums</a>') . '</p>'
);

include(ABSPATH . 'wp-admin/admin-header.php');

?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>

	<?php

	/**
	 * Filters the tabs shown on the Add Themes screen.
	 *
	 * This filter is for backward compatibility only, for the suppression of the upload tab.
	 *
	 * @since 2.8.0
	 *
	 * @param array $tabs The tabs shown on the Add Themes screen. Default is 'upload'.
	 */
	$tabs = apply_filters( 'install_themes_tabs', array( 'upload' => __( 'Upload Theme' ) ) );
	if ( ! empty( $tabs[