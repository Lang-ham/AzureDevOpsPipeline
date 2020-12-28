<?php
/**
 * Multisite administration functions.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

/**
 * Determine if uploaded file exceeds space quota.
 *
 * @since 3.0.0
 *
 * @param array $file $_FILES array for a given file.
 * @return array $_FILES array with 'error' key set if file exceeds quota. 'error' is empty otherwise.
 */
function check_upload_size( $file ) {
	if ( get_site_option( 'upload_space_check_disabled' ) )
		return $file;

	if ( $file['error'] != '0' ) // there's already an error
		return $file;

	if ( defined( 'WP_IMPORTING' ) )
		return $file;

	$space_left = get_upload_space_available();

	$file_size = filesize( $file['tmp_name'] );
	if ( $space_left < $file_size ) {
		/* translators: 1: Required disk space in kilobytes */
		$file['error'] = sprintf( __( 'Not enough space to upload. %1$s KB needed.' ), number_format( ( $file_size - $space_left ) / KB_IN_BYTES ) );
	}

	if ( $file_size > ( KB_IN_BYTES * get_site_option( 'fileupload_maxk', 1500 ) ) ) {
		/* translators: 1: Maximum allowed file size in kilobytes */
		$file['error'] = sprintf( __( 'This file is too big. Files must be less than %1$s KB in size.' ), get_site_option( 'fileupload_maxk', 1500 ) );
	}

	if ( upload_is_user_over_quota( false ) ) {
		$file['error'] = __( 'You have used your space quota. Please delete files before uploading.' );
	}

	if ( $file['error'] != '0' && ! isset( $_POST['html-upload'] ) && ! wp_doing_ajax() ) {
		wp_die( $file['error'] . ' <a href="javascript:history.go(-1)">' . __( 'Back' ) . '</a>' );
	}

	return $file;
}

/**
 * Delete a site.
 *
 * @since 3.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int  $blog_id Site ID.
 * @param bool $drop    True if site's database tables should be dropped. Default is false.
 */
function wpmu_delete_blog( $blog_id, $drop = false ) {
	global $wpdb;

	$switch = false;
	if ( get_current_blog_id() != $blog_id ) {
		$switch = true;
		switch_to_blog( $blog_id );
	}

	$blog = get_site( $blog_id );
	/**
	 * Fires before a site is deleted.
	 *
	 * @since MU (3.0.0)
	 *
	 * @param int  $blog_id The site ID.
	 * @param bool $drop    True if site's table should be dropped. Default is false.
	 */
	do_action( 'delete_blog', $blog_id, $drop );

	$users = get_users( array( 'blog_id' => $blog_id, 'fields' => 'ids' ) );

	// Remove users from this blog.
	if ( ! empty( $users ) ) {
		foreach ( $users as $user_id ) {
			remove_user_from_blog( $user_id, $blog_id );
		}
	}

	update_blog_status( $blog_id, 'deleted', 1 );

	$current_network = get_network();

	// If a full blog object is not available, do not destroy anything.
	if ( $drop && ! $blog ) {
		$drop = false;
	}

	// Don't destroy the initial, main, or root blog.
	if ( $drop && ( 1 == $blog_id || is_main_site( $blog_id ) || ( $blog->path == $current_network->path && $blog->domain == $current_network->domain ) ) ) {
		$drop = false;
	}

	$upload_path = trim( get_option( 'upload_path' ) );

	// If ms_files_rewriting is enabled and upload_path is empty, wp_upload_dir is not reliable.
	if ( $drop && get_site_option( 'ms_files_rewriting' ) && empty( $upload_path ) ) {
		$drop = false;
	}

	if ( $drop ) {
		$uploads = wp_get_upload_dir();

		$tables = $wpdb->tables( 'blog' );
		/**
		 * Filters the tables to drop when the site is deleted.
		 *
		 * @since MU (3.0.0)
		 *
		 * @param array $tables  The site tables to be dropped.
		 * @param int   $blog_id The ID of the site to drop tables for.
		 */
		$drop_tables = apply_filters( 'wpmu_drop_tables', $tables, $blog_id );

		foreach ( (array) $drop_tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `$table`" );
		}

		$wpdb->delete( $wpdb->blogs, array( 'blog_id' => $blog_id ) );

		/**
		 * Filters the upload base directory to delete when the site is deleted.
		 *
		 * @since MU (3.0.0)
		 *
		 * @param string $uploads['basedir'] Uploads path without subdirectory. @see wp_upload_dir()
		 * @param int    $blog_id            The site ID.
		 */
		$dir = apply_filters( 'wpmu_delete_blog_upload_dir', $uploads['basedir'], $blog_id );
		$dir = rtrim( $dir, DIRECTORY_SEPARATOR );
		$top_dir = $dir;
		$stack = array($dir);
		$index = 0;

		while ( $index < count( $stack ) ) {
			// Get indexed directory from stack
			$dir = $stack[$index];

			$dh = @opendir( $dir );
			if ( $dh ) {
				while ( ( $file = @readdir( $dh ) ) !== false ) {
					if ( $file == '.' || $file == '..' )
						continue;

					if ( @is_dir( $dir . DIRECTORY_SEPARATOR . $file ) ) {
						$stack[] = $dir . DIRECTORY_SEPARATOR . $file;
					} elseif ( @is_file( $dir . DIRECTORY_SEPARATOR . $file ) ) {
						@unlink( $dir . DIRECTORY_SEPARATOR . $file );
					}
				}
				@closedir( $dh );
			}
			$index++;
		}

		$stack = array_reverse( $stack ); // Last added dirs are deepest
		foreach ( (array) $stack as $dir ) {
			if ( $dir != $top_dir)
			@rmdir( $dir );
		}

		clean_blog_cache( $blog );
	}

	/**
	 * Fires after the site is deleted from the network.
	 *
	 * @since 4.8.0
	 *
	 * @param int  $blog_id The site ID.
	 * @param bool $drop    True if site's tables should be dropped. Default is false.
	 */
	do_action( 'deleted_blog', $blog_id, $drop );

	if ( $switch )
		restore_current_blog();
}

/**
 * Delete a user from the network and remove from all sites.
 *
 * @since 3.0.0
 *
 * @todo Merge with wp_delete_user() ?
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $id The user ID.
 * @return bool True if the user was deleted, otherwise false.
 */
function wpmu_delete_user( $id ) {
	global $wpdb;

	if ( ! is_numeric( $id ) ) {
		return false;
	}

	$id = (int) $id;
	$user = new WP_User( $id );

	if ( !$user->exists() )
		return false;

	// Global super-administrators are protected, and cannot be deleted.
	$_super_admins = get_super_admins();
	if ( in_array( $user->user_login, $_super_admins, true ) ) {
		return false;
	}

	/**
	 * Fires before a user is deleted from the network.
	 *
	 * @since MU (3.0.0)
	 *
	 * @param int $id ID of the user about to be deleted from the network.
	 */
	do_action( 'wpmu_delete_user', $id );

	$blogs = get_blogs_of_user( $id );

	if ( ! empty( $blogs ) ) {
		foreach ( $blogs as $blog ) {
			switch_to_blog( $blog->userblog_id );
			remove_user_from_blog( $id, $blog->userblog_id );

			$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d", $id ) );
			foreach ( (array) $post_ids as $post_id ) {
				wp_delete_post( $post_id );
			}

			// Clean links
			$link_ids = $wpdb->get_col( $wpdb->prepare( "SELECT link_id FROM $wpdb->links WHERE link_owner = %d", $id ) );

			if ( $link_ids ) {
				foreach ( $link_ids as $link_id )
					wp_delete_link( $link_id );
			}

			restore_current_blog();
		}
	}

	$meta = $wpdb->get_col( $wpdb->prepare( "SELECT umeta_id FROM $wpdb->usermeta WHERE user_id = %d", $id ) );
	foreach ( $meta as $mid )
		delete_metadata_by_mid( 'user', $mid );

	$wpdb->delete( $wpdb->users, array( 'ID' => $id ) );

	clean_user_cache( $user );

	/** This action is documented in wp-admin/includes/user.php */
	do_action( 'deleted_user', $id, null );

	return true;
}

/**
 * Check whether a site has used its allotted upload space.
 *
 * @since MU (3.0.0)
 *
 * @param bool $echo Optional. If $echo is set and the quota is exceeded, a warning message is echoed. Default is true.
 * @return bool True if user is over upload space quota, otherwise false.
 */
function upload_is_user_over_quota( $echo = true ) {
	if ( get_site_option( 'upload_space_check_disabled' ) )
		return false;

	$space_allowed = get_space_allowed();
	if ( ! is_numeric( $space_allowed ) ) {
		$space_allowed = 10; // Default space allowed is 10 MB
	}
	$space_used = get_space_used();

	if ( ( $space_allowed - $space_used ) < 0 ) {
		if ( $echo )
			_e( 'Sorry, you have used your space allocation. Please delete some files to upload more files.' );
		return true;
	} else {
		return false;
	}
}

/**
 * Displays the amount of disk space used by the current site. Not used in core.
 *
 * @since MU (3.0.0)
 */
function display_space_usage() {
	$space_allowed = get_space_allowed();
	$space_used = get_space_used();

	$percent_used = ( $space_used / $space_allowed ) * 100;

	if ( $space_allowed > 1000 ) {
		$space = number_format( $space_allowed / KB_IN_BYTES );
		/* translators: Gigabytes */
		$space .= __( 'GB' );
	} else {
		$space = number_format( $space_allowed );
		/* translators: Megabytes */
		$space .= __( 'MB' );
	}
	?>
	<strong><?php
		/* translators: Storage space that's been used. 1: Percentage of used space, 2: Total space allowed in megabytes or gigabytes */
		printf( __( 'Used: %1$s%% of %2$s' ), number_format( $percent_used ), $space );
	?></strong>
	<?php
}

/**
 * Get the remaining upload space for this site.
 *
 * @since MU (3.0.0)
 *
 * @param int $size Current max size in bytes
 * @return int Max size in bytes
 */
function fix_import_form_size( $size ) {
	if ( upload_is_user_over_quota( false ) ) {
		return 0;
	}
	$available = get_upload_space_available();
	return min( $size, $available );
}

/**
 * Displays the site upload space quota setting form on the Edit Site Settings screen.
 *
 * @since 3.0.0
 *
 * @param int $id The ID of the site to display the setting for.
 */
function upload_space_setting( $id ) {
	switch_to_blog( $id );
	$quota = get_option( 'blog_upload_space' );
	restore_current_blog();

	if ( !$quota )
		$quota = '';

	?>
	<tr>
		<th><label for="blog-upload-space-number"><?php _e( 'Site Upload Space Quota' ); ?></label></th>
		<td>
			<input type="number" step="1" min="0" style="width: 100px" name="option[blog_upload_space]" id="blog-upload-space-number" aria-describedby="blog-upload-space-desc" value="<?php echo $quota; ?>" />
			<span id="blog-upload-space-desc"><span class="screen-reader-text"><?php _e( 'Size in megabytes' ); ?></span> <?php _e( 'MB (Leave blank for network default)' ); ?></span>
		</td>
	</tr>
	<?php
}

/**
 * Update the status of a user in the database.
 *
 * Used in core to mark a user as spam or "ham" (not spam) in Multisite.
 *
 * @since 3.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int    $id         The user ID.
 * @param string $pref       The column in the wp_users table to update the user's status
 *                           in (presumably user_status, spam, or deleted).
 * @param int    $value      The new status for the user.
 * @param null   $deprecated Deprecated as of 3.0.2 and should not be used.
 * @return int   The initially passed $value.
 */
function update_user_status( $id, $pref, $value, $deprecated = null ) {
	global $wpdb;

	if ( null !== $deprecated )
		_deprecated_argument( __FUNCTION__, '3.0.2' );

	$wpdb->update( $wpdb->users, array( sanitize_key( $pref ) => $value ), array( 'ID' => $id ) );

	$user = new WP_User( $id );
	clean_user_cache( $user );

	if ( $pref == 'spam' ) {
		if ( $value == 1 ) {
			/**
			 * Fires after the user is marked as a SPAM user.
			 *
			 * @since 3.0.0
			 *
			 * @param int $id ID of the user marked as SPAM.
			 */
			do_action( 'make_spam_user', $id );
		} else {
			/**
			 * Fires after the user is marked as a HAM user. Opposite of SPAM.
			 *
			 * @since 3.0.0
			 *
			 * @param int $id ID of the user marked as HAM.
			 */
			do_action( 'make_ham_user', $id );
		}
	}

	return $value;
}

/**
 * Cleans the user cache for a specific user.
 *
 * @since 3.0.0
 *
 * @param int $id The user ID.
 * @return bool|int The ID of the refreshed user or false if the user does not exist.
 */
function refresh_user_details( $id ) {
	$id = (int) $id;

	if ( !$user = get_userdata( $id ) )
		return false;

	clean_user_cache( $user );

	return $id;
}

/**
 * Returns the language for a language code.
 *
 * @since 3.0.0
 *
 * @param string $code Optional. The two-letter language code. Default empty.
 * @return string The language corresponding to $code if it exists. If it does not exist,
 *                then the first two letters of $code is returned.
 */
function format_code_lang( $code = '' ) {
	$code = strtolower( substr( $code, 0, 2 ) );
	$lang_codes = array(
		'aa' => 'Afar', 'ab' => 'Abkhazian', 'af' => 'Afrikaans', 'ak' => 'Akan', 'sq' => 'Albanian', 'am' => 'Amharic', 'ar' => 'Arabic', 'an' => 'Aragonese', 'hy' => 'Armenian', 'as' => 'Assamese', 'av' => 'Avaric', 'ae' => 'Avestan', 'ay' => 'Aymara', 'az' => 'Azerbaijani', 'ba' => 'Bashkir', 'bm' => 'Bambara', 'eu' => 'Basque', 'be' => 'Belarusian', 'bn' => 'Bengali',
		'bh' => 'Bihari', 'bi' => 'Bislama', 'bs' => 'Bosnian', 'br' => 'Breton', 'bg' => 'Bulgarian', 'my' => 'Burmese', 'ca' => 'Catalan; Valencian', 'ch' => 'Chamorro', 'ce' => 'Chechen', 'zh' => 'Chinese', 'cu' => 'Church Slavic; Old Slavonic; Church Slavonic; Old Bulgarian; Old Church Slavonic', 'cv' => 'Chuvash', 'kw' => 'Cornish', 'co' => 'Corsican', 'cr' => 'Cree',
		'cs' => 'Czech', 'da' => 'Danish', 'dv' => 'Divehi; Dhivehi; Maldivian', 'nl' => 'Dutch; Flemish', 'dz' => 'Dzongkha', 'en' => 'English', 'eo' => 'Esperanto', 'et' => 'Estonian', 'ee' => 'Ewe', 'fo' => 'Faroese', 'fj' => 'Fijjian', 'fi' => 'Finnish', 'fr' => 'French', 'fy' => 'Western Frisian', 'ff' => 'Fulah', 'ka' => 'Georgian', 'de' => 'German', 'gd' => 'Gaelic; Scottish Gaelic',
		'ga' => 'Irish', 'gl' => 'Galician', 'gv' => 'Manx', 'el' => 'Greek, Modern', 'gn' => 'Guarani', 'gu' => 'Gujarati', 'ht' => 'Haitian; Haitian Creole', 'ha' => 'Hausa', 'he' => 'Hebrew', 'hz' => 'Herero', 'hi' => 'Hindi', 'ho' => 'Hiri Motu', 'hu' => 'Hungarian', 'ig' => 'Igbo', 'is' => 'Icelandic', 'io' => 'Ido', 'ii' => 'Sichuan Yi', 'iu' => 'Inuktitut', 'ie' => 'Interlingue',
		'ia' => 'Interlingua (International Auxiliary Language Association)', 'id' => 'Indonesian', 'ik' => 'Inupiaq', 'it' => 'Italian', 'jv' => 'Javanese', 'ja' => 'Japanese', 'kl' => 'Kalaallisut; Greenlandic', 'kn' => 'Kannada', 'ks' => 'Kashmiri', 'kr' => 'Kanuri', 'kk' => 'Kazakh', 'km' => 'Central Khmer', 'ki' => 'Kikuyu; Gikuyu', 'rw' => 'Kinyarwanda', 'ky' => 'Kirghiz; Kyrgyz',
		'kv' => 'Komi', 'kg' => 'Kon