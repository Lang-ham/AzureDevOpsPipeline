<?php
/**
 * New User Administration Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

if ( is_multisite() ) {
	if ( ! current_user_can( 'create_users' ) && ! current_user_can( 'promote_users' ) ) {
		wp_die(
			'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
			'<p>' . __( 'Sorry, you are not allowed to add users to this network.' ) . '</p>',
			403
		);
	}
} elseif ( ! current_user_can( 'create_users' ) ) {
	wp_die(
		'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to create users.' ) . '</p>',
		403
	);
}

if ( is_multisite() ) {
	add_filter( 'wpmu_signup_user_notification_email', 'admin_created_user_email' );
}

if ( isset($_REQUEST['action']) && 'adduser' == $_REQUEST['action'] ) {
	check_admin_referer( 'add-user', '_wpnonce_add-user' );

	$user_details = null;
	$user_email = wp_unslash( $_REQUEST['email'] );
	if ( false !== strpos( $user_email, '@' ) ) {
		$user_details = get_user_by( 'email', $user_email );
	} else {
		if ( current_user_can( 'manage_network_users' ) ) {
			$user_details = get_user_by( 'login', $user_email );
		} else {
			wp_redirect( add_query_arg( array('update' => 'enter_email'), 'user-new.php' ) );
			die();
		}
	}

	if ( !$user_details ) {
		wp_redirect( add_query_arg( array('update' => 'does_not_exist'), 'user-new.php' ) );
		die();
	}

	if ( ! current_user_can( 'promote_user', $user_details->ID ) ) {
		wp_die(
			'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
			'<p>' . __( 'Sorry, you are not allowed to add users to this network.' ) . '</p>',
			403
		);
	}

	// Adding an existing user to this blog
	$new_user_email = $user_details->user_email;
	$redirect = 'user-new.php';
	$username = $user_details->user_login;
	$user_id = $user_details->ID;
	if ( $username != null && array_key_exists( $blog_id, get_blogs_of_user( $user_id ) ) ) {
		$redirect = add_query_arg( array('update' => 'addexisting'), 'user-new.php' );
	} else {
		if ( isset( $_POST[ 'noconfirmation' ] ) && current_user_can( 'manage_network_users' ) ) {
			$result = add_existing_user_to_blog( array( 'user_id' => $user_id, 'role' => $_REQUEST[ 'role' ] ) );

			if ( ! is_wp_error( $result ) ) {
				$redirect = add_query_arg( array( 'update' => 'addnoconfirmation', 'user_id' => $user_id ), 'user-new.php' );
			} else {
				$redirect = add_query_arg( array( 'update' => 'could_not_add' ), 'user-new.php' );
			}
		} else {
			$newuser_key = wp_generate_password( 20, false );
			add_option( 'new_user_' . $newuser_key, array( 'user_id' => $user_id, 'email' => $user_details->user_email, 'role' => $_REQUEST[ 'role' ] ) );

			$roles = get_editable_roles();
			$role = $roles[ $_REQUEST['role'] ];

			/**
			 * Fires immediately after a user is invited to join a site, but before the notification is sent.
			 *
			 * @since 4.4.0
			 *
			 * @param int    $user_id     The invited user's ID.
			 * @param array  $role        The role of invited user.
			 * @param string $newuser_key The key of the invitation.
			 */
			do_action( 'invite_user', $user_id, $role, $newuser_key );

			$switched_locale = switch_to_locale( get_user_locale( $user_details ) );

			/* translators: 1: Site name, 2: site URL, 3: role, 4: activation URL */
			$message = __( 'Hi,

You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.

Please click the following link to confirm the invite:
%4$s' );
			wp_mail( $new_user_email, sprintf( __( '[%s] Joining confirmation' ), wp_specialchars_decode( get_option( 'blogname' ) ) ), sprintf( $message, get_option( 'blogname' ), home_url(), wp_specialchars_decode( translate_user_role( $role['name'] ) ), home_url( "/newbloguser/$newuser_key/" ) ) );

			if ( $switched_locale ) {
				restore_previous_locale();
			}

			$redirect = add_query_arg( array('update' => 'add'), 'user-new.php' );
		}
	}
	wp_redirect( $redirect );
	die();
} elseif ( isset($_REQUEST['action']) && 'createuser' == $_REQUEST['action'] ) {
	check_admin_referer( 'create-user', '_wpnonce_create-user' );

	if ( ! current_user_can( 'create_users' ) ) {
		wp_die(
			'<h1>' . __( 'Cheatin&#8217; uh?' ) . '</h1>' .
			'<p>' . __( 'Sorry, you are not allowed to create users.' ) . '</p>',
			403
		);
	}

	if ( ! is_multisite() ) {
		$user_id = edit_user();

		if ( is_wp_error( $user_id ) ) {
			$add_user_errors = $user_id;
		} else {
			if ( current_user_can( 'list_users' ) )
				$redirect = 'users.php?update=add&id=' . $user_id;
			else
				$redirect = add_query_arg( 'update', 'add', 'user-new.php' );
			wp_redirect( $redirect );
			die();
		}
	} else {
		// Adding a new user to this site
		$new_user_email = wp_unslash( $_REQUEST['email'] );
		$user_details = wpmu_validate_user_signup( $_REQUEST['user_login'], $new_user_email );
		if ( is_wp_error( $user_details[ 'errors' ] ) && !empty( $user_details[ 'errors' ]->errors ) ) {
			$add_user_errors = $user_details[ 'errors' ];
		} else {
			/**
			 * Filters the user_login, also known as the username, before it is added to the site.
			 *
			 * @since 2.0.3
			 *
			 * @param string $user_login The sanitized username.
			 */
			$new_user_login = apply_filters( 'pre_user_login', sanitize_user( wp_unslash( $_REQUEST['user_login'] ), true ) );
			if ( isset( $_POST[ 'noconfirmation' ] ) && current_user_can( 'manage_network_users' ) ) {
				add_filter( 'wpmu_signup_user_notification', '__return_false' ); // Disable confirmation email
				add_filter( 'wpmu_welcome_user_notification', '__return_false' ); // Disable welcome email
			}
			wpmu_signup_user( $new_user_login, $new_user_email, array( 'add_to_blog' => get_current_blog_id(), 'new_role' => $_REQUEST['role'] ) );
			if ( isset( $_POST[ 'noconfirmation' ] ) && current_user_can( 'manage_network_users' ) ) {
				$key = $wpdb->get_var( $wpdb->prepare( "SELECT activation_key FROM {$wpdb->signups} WHERE user_login = %s AND user_email = %s", $new_user_login, $new_user_email ) );
				$new_user = wpmu_activate_signup( $key );
				if ( is_wp_error( $new_user ) ) {
					$redirect = add_query_arg( array( 'update' => 'addnoconfirmation' ), 'user-new.php' );
				} elseif ( ! is_user_member_of_blog( $new_user['user_id'] ) ) {
					$redirect = add_query_arg( array( 'update' => 'created_could_not_add' ), 'user-new.php' );
				} else {
					$redirect = add_query_arg( array( 'update' => 'addnoconfirmation', 'user_id' => $new_user['user_id'] ), 'user-new.php' );
				}
			} else {
				$redirect = add_query_arg( array('update' => 'newuserconfirmation'), 'user-new.php' );
			}
			wp_redirect( $redirect );
			die();
		}
	}
}

$title = __('Add New User');
$parent_file = 'users.php';

$do_both = false;
if ( is_multisite() && current_user_can('promote_users') && current_user_can('create_users') )
	$do_both = true;

$help = '<p>' . __('To add a new user to your site, fill in the form on this screen and click the Add New User button at the bottom.') . '</p>';

if ( is_multisite() ) {
	$help .= '<p>' . __('Because this is a multisite installation, you may add accounts that already exist on the Network by specifying a username or email, and defining a role. For more options, such as specifying a password, you have to be a Network Administrator and use the hover link under an existing user&#8217;s name to Edit the user profile under Network Admin > All Users.') . '</p>' .
	'<p>' . __('New users will receive an email letting them know they&#8217;ve been added as a user for your site. This email will also contain their password. Check the box if you don&#8217;t want the user to receive a welcome email.') . '</p>';
} else {
	$help .= '<p>' . __('New users are automatically assigned a password, which they can change after logging in. You can view or edit the assigned password by clicking the Show Password button. The username cannot be changed once the user has been added.') . '</p>' .

	'<p>' . __('By default, new users will receive an email letting them know they&#8217;ve been added as a user for your site. This email will also contain a password reset link. Uncheck the box if you don&#8217;t want to send the new user a welcome email.') . '</p>';
}

$help .= '<p>' . __('Remember to click the Add New User button at the bottom of this screen when you are finished.') . '</p>';

get_current_screen()->add_help_tab( array(
	'id'      => 'overview',
	'title'   => __('Overview'),
	'content' => $help,
) );

get_current_screen()->add_help_tab( array(
'id'      => 'user-roles',
'title'   => __('User Roles'),
'content' => '<p>' . __('Here is a basic overview of the different user roles and the permissions associated with each one:') . '</p>' .
				'<ul>' .
				'<li>' . __(