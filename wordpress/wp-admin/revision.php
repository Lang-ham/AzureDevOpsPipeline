<?php
/**
 * Revisions administration panel
 *
 * Requires wp-admin/includes/revision.php.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 2.6.0
 *
 * @param int    revision Optional. The revision ID.
 * @param string action   The action to take.
 *                        Accepts 'restore', 'view' or 'edit'.
 * @param int    from     The revision to compare from.
 * @param int    to       Optional, required if revision missing. The revision to compare to.
 */

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

require ABSPATH . 'wp-admin/includes/revision.php';

wp_reset_vars( array( 'revision', 'action', 'from', 'to' ) );

$revision_id = absint( $revision );

$from = is_numeric( $from ) ? absint( $from ) : null;
if ( ! $revision_id )
	$revision_id = absint( $to );
$redirect = 'edit.php';

switch ( $action ) {
case 'restore' :
	if ( ! $revision = wp_get_post_revision( $revision_id ) )
		break;

	if ( ! current_user_can( 'edit_post', $revision->post_parent ) )
		break;

	if ( ! $post = get_post( $revision->post_parent ) )
		break;

	// Restore if revisions are enabled or this is an autosave.
	if ( ! wp_revisions_enabled( $post ) && ! wp_is_post_autosave( $revision ) ) {
		$redirect = 'edit.php?post_type=' . $post->post_type;
		break;
	}

	// Don't allow revision restore when post is locked
	if ( wp_check_post_lock( $post->ID ) )
		break;

	check_admin_referer( "restore-post_{$revision->ID}" );

	wp_restore_post_revision( $revision->ID );
	$redirect = add_query_arg( array( 'message' => 5, 'revision' => $revision->ID ), get_edit_post_link( $post->ID, 'url' ) );
	break;
case 'view' :
case 'edit' :
default :
	if ( ! $revision = wp_get_post_revision( $revision_id ) )
		break;
	if ( ! $post = get_post( $revision->post_parent ) )
		break;

	if ( ! current_user_can( 'read_post', $revision->ID ) || ! current_user_can( 'edit_post', $revision->post_parent ) )
		break;

	// Revisions disabled and we're not looking at an autosave
	if ( ! wp_revisions_enabled( $post ) && ! wp_is_post_autosave( $revision ) ) {
		$redirect = 'edit.php?post_type=' . $post->post_type;
		break;
	}

	$post_edit_link = get_edit_post_link();
	$post_title     = '<a href="' . $post_edit_link . '">' . _draft_or_post_title() . '</a>';
	/* translators: 1: Post title */
	$h1             = sprintf( __( 'Compare Revisions of &#8220;%1$s&#8221;' ), $post_title );
	$return_to_post = '<a href="' . $post_edit_link . '">' . __( '&larr; Return to editor' ) . '</a>';
	$title          = __( 'Revisions' );

	$redirect = false;
	break;
}

// Empty post_type means either malformed object found, or no valid parent was found.
if ( ! $redirect && empty( $post->post_type ) )
	$redirect = 'edit.php';

if ( ! empty( $redirect ) ) {
	wp_redirect( $redirect );
	exit;
}

// This is so that the correct "Edit" menu item is selected.
if ( ! empty( $post->post_type ) && 'post' != $post->post_type )
	$parent_file = $submenu_file = 'edit.php?post_type=' . $post->post_type;
else
	$parent_file = $submenu_file = 'edit.php';

wp_enqueue_script( 'revisions' );
wp_localize_scr