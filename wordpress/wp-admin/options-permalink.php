<?php
/**
 * Permalink Settings Administration Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

if ( ! current_user_can( 'manage_options' ) )
	wp_die( __( 'Sorry, you are not allowed to manage options for this site.' ) );

$title = __('Permalink Settings');
$parent_file = 'options-general.php';

get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => '<p>' . __('Permalinks are the permanent URLs to your individual pages and blog posts, as well as your category and tag archives. A permalink is the web address used to link to your content. The URL to each post should be permanent, and never change &#8212; hence the name permalink.') . '</p>' .
		'<p>' . __( 'This screen allows you to choose your permalink structure. You can choose from common settings or create custom URL structures.' ) . '</p>' .
		'<p>' . __('You must click the Save Changes button at the bottom of the screen for new settings to take effect.') . '</p>',
) );

get_current_screen()->add_help_tab( array(
	'id'      => 'permalink-settings',
	'title'   => __('Permalink Settings'),
	'content' => '<p>' . __( 'Permalinks can contain useful information, such as the post date, title, or other elements. You can choose from any of the suggested permalink formats, or you can craft your own if you select Custom Structure.' ) . '</p>' .
		'<p>' . __( 'If you pick an option other than Plain, your general URL path with structure tags (terms surrounded by <code>%</code>) will also appear in the custom structure field and your path can be further modified there.' ) . '</p>' .
		'<p>' . __('When you assign multiple categories or tags to a post, only one can show up in the permalink: the lowest numbered category. This applies if your custom structure includes <code>%category%</code> or <code>%tag%</code>.') . '</p>' .
		'<p>' . __('You must click the Save Changes button at the bottom of the screen for new settings to take effect.') . '</p>',
)