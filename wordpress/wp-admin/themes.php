<?php
/**
 * Themes administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

if ( ! current_user_can( 'switch_themes' ) && ! current_user_can( 'edit_theme_options' ) ) {
	wp_die(
		'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to edit theme options on this site.' ) . '</p>',
		403
	);
}

if ( current_user_can( 'switch_themes' ) && isset($_GET['action'] ) ) {
	if ( 'activate' == $_GET['action'] ) {
		check_admin_referer('switch-theme_' . $_GET['stylesheet']);
		$theme = wp_get_theme( $_GET['stylesheet'] );

		if ( ! $theme->exists() || ! $theme->is_allowed() ) {
			wp_die(
				'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
				'<p>' . __( 'The requested theme does not exist.' ) . '</p>',
				403
			);
		}

		switch_theme( $theme->get_stylesheet() );
		wp_redirect( admin_url('themes.php?activated=true') );
		exit;
	} elseif ( 'delete' == $_GET['action'] ) {
		check_admin_referer('delete-theme_' . $_GET['stylesheet']);
		$theme = wp_get_theme( $_GET['stylesheet'] );

		if ( ! current_user_can( 'delete_themes' ) ) {
			wp_die(
				'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
				'<p>' . __( 'Sorry, you are not allowed to delete this item.' ) . '</p>',
				403
			);
		}

		if ( ! $theme->exists() ) {
			wp_die(
				'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
				'<p>' . __( 'The requested theme does not exist.' ) . '</p>',
				403
			);
		}

		$active = wp_get_theme();
		if ( $active->get( 'Template' ) == $_GET['stylesheet'] ) {
			wp_redirect( admin_url( 'themes.php?delete-active-child=true' ) );
		} else {
			delete_theme( $_GET['stylesheet'] );
			wp_redirect( admin_url( 'themes.php?deleted=true' ) );
		}
		exit;
	}
}

$title = __('Manage Themes');
$parent_file = 'themes.php';

// Help tab: Overview
if ( current_user_can( 'switch_themes' ) ) {
	$help_overview  = '<p>' . __( 'This screen is used for managing your installed themes. Aside from the default theme(s) included with your WordPress installation, themes are designed and developed by third parties.' ) . '</p>' .
		'<p>' . __( 'From this screen you can:' ) . '</p>' .
		'<ul><li>' . __( 'Hover or tap to see Activate and Live Preview buttons' ) . '</li>' .
		'<li>' . __( 'Click on the theme to see the theme name, version, author, description, tags, and the Delete link' ) . '</li>' .
		'<li>' . __( 'Click Customize for the current theme or Live Preview for any other theme to see a live preview' ) . '</li></ul>' .
		'<p>' . __( 'The current theme is displayed highlighted as the first theme.' ) . '</p>' .
		'<p>' . __( 'The search for installed themes will search for terms in their name, description, author, or tag.' ) . ' <span id="live-search-desc">' . __( 'The search results will be updated as you type.' ) . '</span></p>';

	get_current_screen()->add_help_tab( array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => $help_overview
	) );
} // switch_themes

// Help tab: Adding Themes
if ( current_user_can( 'install_themes' ) ) {
	if ( is_multisite() ) {
		$help_install = '<p>' . __('Installing themes on Multisite can only be done from the Network Admin section.') . '</p>';
	} else {
		$help_install = '<p>' . sprintf( __('If you would like to see more themes to choose from, click on the &#8220;Add New&#8221; button and you will be able to browse or search for additional themes from the <a href="%s">WordPress Theme Directory</a>. Themes in the WordPress Theme Directory are designed and developed by third parties, and are compatible with the license WordPress uses. Oh, and they&#8217;re free!'), __( 'https://wordpress.org/themes/' ) ) . '</p>';
	}

	get_current_screen()->add_help_tab( array(
		'id'      => 'adding-themes',
		'title'   => __('Adding Themes'),
		'content' => $help_install
	) );
} // install_themes

// Help tab: Previewing and Customizing
if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
	$help_customize =
		'<p>' . __( 'Tap or hover on any theme then click the Live Preview button to see a live preview of that theme and change theme options in a separate, full-screen view. You can also find a Live Preview button at the bottom of the theme details screen. Any installed theme can be previewed and customized in this way.' ) . '</p>'.
		'<p>' . __( 'The theme being previewed is fully interactive &mdash; navigate to different pages to see how the theme handles posts, archives, and other page templates. The settings may differ depending on what theme features the theme being previewed supports. To accept the new settings and activate the theme all in one step, click the Publish &amp; Activate button above the menu.' ) . '</p>' .
		'<p>' . __( 'When previewing on smaller monitors, you can use the collapse icon at the bottom of the left-hand pane. This will hide the pane, giving you more room to preview your site in the new theme. To bring the pane back, click on the collapse icon again.' ) . '</p>';

	get_current_screen()->add_help_tab( array(
		'id'		=> 'customize-preview-themes',
		'title'		=> __( 'Previewing and Customizing' ),
		'content'	=> $help_customize
	) );
} // edit_theme_options && customize

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
	'<p>' . __( '<a href="https://codex.wordpress.org/Using_Themes">Documentation on Using Themes</a>' ) . '</p>' .
	'<p>' . __( '<a href="https://wordpress.org/support/">Support Forums</a>' ) . '</p>'
);

if ( current_user_can( 'switch_themes' ) ) {
	$themes = wp_prepare_themes_for_js();
} else {
	$themes = wp_prepare_themes_for_js( array( wp_get_theme() ) );
}
wp_reset_vars( array( 'theme', 'search' ) );

wp_localize_script( 'theme', '_wpThemeSettings', array(
	'themes'   => $themes,
	'settings' => array(
		'canInstall'    => ( ! is_multisite() && current_user_can( 'install_themes' ) ),
		'installURI'    => ( ! is_multisite() && current_user_can( 'install_themes' ) ) ? admin_url( 'theme-install.php' ) : null,
		'confirmDelete' => __( "Are you sure you want to delete this theme?\n\nClick 'Cancel' to go back, 'OK' to confirm the delete." ),
		'adminUrl'      => parse_url( admin_url(), PHP_URL_PATH ),
	),
 	'l10n' => array(
 		'addNew'            => __( 'Add New Theme' ),
 		'search'            => __( 'Search Installed Themes' ),
 		'searchPlaceholder' => __( 'Search installed themes...' ), // placeholder (no ellipsis)
		'themesFound'       => __( 'Number of Themes found: %d' ),
		'noThemesFound'     => __( 'No themes found. Try a different search.' ),
  	),
) );

add_thickbox();
wp_enqueue_script( 'theme' );
wp_enqueue_script( 'updates' );

require_once( ABSPATH . 'wp-admin/admin-header.php' );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Themes' ); ?>
		<span class="title-count theme-count"><?php echo ! empty( $_GET['search'] ) ? __( '&hellip;' ) : count( $themes ); ?></span>
	</h1>

	<?php if ( ! is_multisite() && current_user_can( 'install_themes' ) ) : ?>
		<a href="<?php echo admin_url( 'theme-install.php' ); ?>" class="hide-if-no-js page-title-action"><?php echo esc_html_x( 'Add New', 'Add new theme' ); ?></a>
	<?php endif; ?>

	<form class="search-form"></form>

	<hr class="wp-header-end">
<?php
if ( ! validate_current_theme() || isset( $_GET['broken'] ) ) : ?>
<div id="message1" class="updated notice is-dismissible"><p><?php _e('The active theme is broken. Reverting to the default theme.'); ?></p></div>
<?php elseif ( isset($_GET['activated']) ) :
		if ( isset( $_GET['previewed'] ) ) { ?>
		<div id="message2" class="updated notice is-dismissible"><p><?php _e( 'Settings saved and theme activated.' ); ?> <a href="<?php echo home_url( '/' ); ?>"><?php _e( 'Visit site' ); ?></a></p></div>
		<?php } else { ?>
<div id="message2" class="updated notice is-dismissible"><p><?php _e( 'New theme activated.' ); ?> <a href="<?php echo home_url( '/' ); ?>"><?php _e( 'Visit site' ); ?></a></p></div><?php
		}
	elseif ( isset($_GET['deleted']) ) : ?>
<div id="message3" class="updated notice is-dismissible"><p><?php _e('Theme deleted.') ?></p></div>
<?php elseif ( isset( $_GET['delete-active-child'] ) ) : ?>
	<div id="message4" class="error"><p><?php _e( 'You cannot delete a theme while it has an active child theme.' ); ?></p></div>
<?php
endif;

$ct = wp_get_theme();

if ( $ct->errors() && ( ! is_multisite() || current_user_can( 'manage_network_themes' ) ) ) {
	echo '<div class="error"><p>' . __( 'ERROR:' ) . ' ' . $ct->errors()->get_error_message() . '</p></div>';
}

/*
// Certain error codes are less fatal than others. We can still display theme information in most cases.
if ( ! $ct->errors() || ( 1 == count( $ct->errors()->get_error_codes() )
	&& in_array( $ct->errors()->get_error_code(), array( 'theme_no_parent', 'theme_parent_invalid', 'theme_no_index' ) ) ) ) : ?>
*/

	// Pretend you didn't see this.
	$current_theme_actions = array();
	if ( is_array( $submenu ) && isset( $submenu['themes.php'] ) ) {
		foreach ( (array) $submenu['themes.php'] as $item) {
			$class = '';
			if ( 'themes.php' == $item[2] || 'theme-editor.php' == $item[2] || 0 === strpos( $item[2], 'customize.php' ) )
				continue;
			// 0 = name, 1 = capability, 2 = file
			if ( ( strcmp($self, $item[2]) == 0 && empty($parent_file)) || ($parent_file && ($item[2] == $parent_file)) )
				$class = ' current';
			if ( !empty($submenu[$item[2]]) ) {
				$submenu[$item[2]] = array_values($submenu[$item[2]]); // Re-index.
				$menu_hook = get_plugin_page_hook($submenu[$item[2]][0][2], $item[2]);
				if ( file_exists(WP_PLUGIN_DIR . "/{$submenu[$item[2]][0][2]}") || !empty($menu_hook))
					$current_theme_actions[] = "<a class='button$class' href='admin.php?page={$submenu[$item[2]][0][2]}'>{$item[0]}</a>";
				else
					$current_theme_actions[] = "<a class='button$class' href='{$submenu[$item[2]][0][2]}'>{$item[0]}</a>";
			} elseif ( ! empty( $item[2] ) && current_user_can( $item[1] ) ) {
				$menu_file = $item[2];

				if ( current_user_can( 'customize' ) ) {
					if ( 'custom-header' === $menu_file ) {
						$current_theme_actions[] = "<a class='button hide-if-no-customize$class' href='customize.php?autofocus[control]=header_image'>{$item[0]}</a>";
					} elseif ( 'custom-background' === $menu_file ) {
						$current_theme_actions[] = "<a class='button hide-if-no-customize$class' href='customize.php?autofocus[control]=background_image'>{$item[0]}</a>";
					}
				}

				if ( false !== ( $pos = strpos( $menu_file, '?' ) ) ) {
					$menu_file = substr( $menu_file, 0, $pos );
				}

				if ( file_exists( ABSPATH . "wp-admin/$menu_file" ) ) {
					$current_theme_actions[] = "<a class='button$class' href='{$item[2]}'>{$item[0]}</a>";
				} else {
					$current_theme_actions[] = "<a class='button$class' href='themes.php?page={$item[2]}'>{$item[0]}</a>";
				}
			}
		}
	}

?>

<?php
$class_name = 'theme-browser';
if ( ! empty( $_GET['search'] ) ) {
	$class_name .= ' search-loading';
}
?>
<div class="<?php echo esc_attr( $class_name ); ?>">
	<div class="themes wp-clearfix">

<?php
/*
 * This PHP is synchronized with the tmpl-theme template below!
 */

foreach ( $themes as $theme ) :
	$aria_action = esc_attr( $theme['id'] . '-action' );
	$aria_name   = esc_attr( $theme['id'] . '-name' );
	?>
<div class="theme<?php if ( $theme['active'] ) echo ' active'; ?>" tabindex="0" aria-describedby="<?php echo $aria_action . ' ' . $aria_name; ?>">
	<?php if ( ! empty( $theme['screenshot'][0] ) ) { ?>
		<div class="theme-screenshot">
			<img src="<?php echo $theme['screenshot'][0]; ?>" alt="" />
		</div>
	<?php } else { ?>
		<div class="theme-screenshot blank"></div>
	<?php } ?>

	<?php if ( $theme['hasUpdate'] ) : ?>
		<div class="update-message notice inline notice-warning notice-alt">
		<?php if ( $theme['hasPackage'] ) : ?>
			<p><?php _e( 'New version available. <button class="button-link" type="button">Update now</button>' ); ?></p>
		<?php else : ?>
			<p><?php _e( 'New version available.' ); ?></p>
		<?php endif; ?>
		</div>
	<?php endif; ?>

	<span class="more-details" id="<?php echo $aria_action; ?>"><?php _e( 'Theme Details' ); ?></span>
	<div class="theme-author"><?php printf( __( 'By %s' ), $theme['author'] ); ?></div>

	<div class="theme-id-container">
		<?php if ( $theme['activ