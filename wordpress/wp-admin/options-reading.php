<?php
/**
 * Reading settings administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

if ( ! current_user_can( 'manage_options' ) )
	wp_die( __( 'Sorry, you are not allowed to manage options for this site.' ) );

$title = __( 'Reading Settings' );
$parent_file = 'options-general.php';

add_action('admin_head', 'options_reading_add_js');

get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => '<p>' . __('This screen contains the settings that affect the display of your content.') . '</p>' .
		'<p>' . sprintf(__('You can choose what&#8217;s displayed on the homepage of your site. It can be posts in reverse chronological order (classic blog), or a fixed/static page. To set a static homepage, you first need to create two <a href="%s">Pages</a>. One will become the homepage, and the other will be where your posts are displayed.'), 'post-new.php?post_type=page') . '</p>' .
		'<p>' . __('You can also control the display of your content in RSS feeds, including the maximum number of posts to display and whether to show full text or a summary.') . '</p>' .
		'<p>' . __('You must click the Save Changes button at the bottom of the screen for new settings to take effect.') . '</p>',
) );

get_current_screen()->add_help_tab( array(
	'id'      => 'site-visibility',
	'title'   => has_action( 'blog_privacy_selector' ) ? __( 'Site Visibility' ) : __( 'Search Engine Visibility' ),
	'content' => '<p>' . __( 'You can choose whether or not your site will be crawled by robots, ping services, and spiders. If you want those services to ignore your site, click the checkbox next to &#8220;Discourage search engines from indexing this site&#8221; and click the Save Changes button at the bottom of the screen. Note that your privacy is not complete; your site is still visible on the web.' ) . '</p>' .
		'<p>' . __( 'When this setting is in effect, a reminder is shown in the At a Glance box of the Dashboard that says, &#8220;Search Engines Discouraged,&#8221; to remind you that your site is not being crawled.' ) . '</p>',
) );

get_current_screen()->set_help_sidebar(
	'<p><strong>' . __('For more information:') . '</strong></p>' .
	'<p>' . __('<a href="https://codex.wordpress.org/Settings_Reading_Screen">Documentation on Reading Settings</a>') . '</p>' .
	'<p>' . __('<a href="https://wordpress.org/support/">Support Forums</a>') . '</p>'
);

include( ABSPATH . 'wp-admin/admin-header.php' );
?>

<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<form method="post" action="options.php">
<?php
settings_fields( 'reading' );

if ( ! in_array( get_option( 'blog_charset' ), array( 'utf8', 'utf-8', 'UTF8', 'UTF-8' ) ) )
	add_settings_field( 'blog_charset', __( 'Encoding for pages and feeds' ), 'options_reading_blog_charset', 'reading', 'default', array( 'label_for' => 'blog_charset' ) );
?>

<?php if ( ! get_pages() ) : ?>
<input name="show_on_front" type="hidden" value="posts" />
<table class="form-table">
<?php
	if ( 'posts' != get_option( 'show_on_front' ) ) :
		update_option( 'show_on_front', 'posts' );
	endif;

else :
	if ( 'page' == get_option( 'show_on_front' ) && ! get_option( 'page_on_front' ) && ! get_option( 'page_for_posts' ) )
		update_option( 'show_on_front', 'posts' );
?>
<table class="form-table">
<tr>
<th scope="row"><?php _e( 'Your homepage displays' ); ?></th>
<td id="front-static-pages"><fieldset><legend class="screen-reader-text"><span><?php _e( 'Your homepage displays' ); ?></span></legend>
	<p><label>
		<input name="show_on_front" type="radio" value="posts" class="tog" <?php checked( 'posts', get_option( 'show_on_front' ) ); ?> />
		<?php _e( 'Your latest posts' ); ?>
	</label>
	</p>
	<p><label>
		<input name="show_on_front" type="radio" value="page" class="tog" <?php checked( 'page', get_option( 'show_on_front' ) ); ?> />
		<?php printf( __( 'A <a href="%s">static page</a> (select below)' ), 'edit.php?post_type=page' ); ?>
	</label>
	</p>
<ul>
	<li><label for="page_on_front"><?php printf( __( 'Homepage: %s' ), wp_dropdown_pages( array( 'name' => 'page_on_front', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => get_option( 'page_on_front' ) ) ) ); ?></label></li>
	<li><label for="page_for_posts"><?php printf( __( 'Posts page: %s' ), wp_dropdown_pages( array( 'name' => 'page_for_posts', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => get_option( 'page_for_posts' ) ) ) ); ?></label></li>
</ul>
<?php if ( 'page' == get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) == get_option( 'page_on_front' ) ) : ?>
<div id="front-page-warning" class="error inline"><p><?php _e( '<strong>Warning:</strong> these pages should not be the same!' ); ?></p></div>
<?php endif; ?>
</fieldset></td>
</tr>
