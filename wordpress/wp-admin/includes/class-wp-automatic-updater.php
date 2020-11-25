<?php
/**
 * Upgrade API: WP_Automatic_Updater class
 *
 * @package WordPress
 * @subpackage Upgrader
 * @since 4.6.0
 */

/**
 * Core class used for handling automatic background updates.
 *
 * @since 3.7.0
 * @since 4.6.0 Moved to its own file from wp-admin/includes/class-wp-upgrader.php.
 */
class WP_Automatic_Updater {

	/**
	 * Tracks update results during processing.
	 *
	 * @var array
	 */
	protected $update_results = array();

	/**
	 * Whether the entire automatic updater is disabled.
	 *
	 * @since 3.7.0
	 */
	public function is_disabled() {
		// Background updates are disabled if you don't want file changes.
		if ( ! wp_is_file_mod_allowed( 'automatic_updater' ) )
			return true;

		if ( wp_installing() )
			return true;

		// More fine grained control can be done through the WP_AUTO_UPDATE_CORE constant and filters.
		$disabled = defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED;

		/**
		 * Filters whether to entirely disable background updates.
		 *
		 * There are more fine-grained filters and controls for selective disabling.
		 * This filter parallels the AUTOMATIC_UPDATER_DISABLED constant in name.
		 *
		 * This also disables update notification emails. That may change in the future.
		 *
		 * @since 3.7.0
		 *
		 * @param bool $disabled Whether the updater should be disabled.
		 */
		return apply_filters( 'automatic_updater_disabled', $disabled );
	}

	/**
	 * Check for version control checkouts.
	 *
	 * Checks for Subversion, Git, Mercurial, and Bazaar. It recursively looks up the
	 * filesystem to the top of the drive, erring on the side of detecting a VCS
	 * checkout somewhere.
	 *
	 * ABSPATH is always checked in addition to whatever $context is (which may be the
	 * wp-content directory, for example). The underlying assumption is that if you are
	 * using version control *anywhere*, then you should be making decisions for
	 * how things get updated.
	 *
	 * @since 3.7.0
	 *
	 * @param string $context The filesystem path to check, in addition to ABSPATH.
	 */
	public function is_vcs_checkout( $context ) {
		$context_dirs = array( untrailingslashit( $context ) );
		if ( $context !== ABSPATH )
			$context_dirs[] = untrailingslashit( ABSPATH );

		$vcs_dirs = array( '.svn', '.git', '.hg', '.bzr' );
		$check_dirs = array();

		foreach ( $context_dirs as $context_dir ) {
			// Walk up from $context_dir to the root.
			do {
				$check_dirs[] = $context_dir;

				// Once we've hit '/' or 'C:\', we need to stop. dirname will keep returning the input here.
				if ( $context_dir == dirname( $context_dir ) )
					break;

			// Continue one level at a time.
			} while ( $context_dir = dirname( $context_dir ) );
		}

		$check_dirs = array_unique( $check_dirs );

		// Search all directories we've found for evidence of version control.
		foreach ( $vcs_dirs as $vcs_dir ) {
			foreach ( $check_dirs as $check_dir ) {
				if ( $checkout = @is_dir( rtrim( $check_dir, '\\/' ) . "/$vcs_dir" ) )
					break 2;
			}
		}

		/**
		 * Filters whether the automatic updater should consider a filesystem
		 * location to be potentially managed by a version control system.
		 *
		 * @since 3.7.0
		 *
		 * @param bool $checkout  Whether a VCS checkout was discovered at $context
		 *                        or ABSPATH, or anywhere higher.
		 * @param string $context The filesystem context (a path) against which
		 *                        filesystem status should be checked.
		 */
		return apply_filters( 'automatic_updates_is_vcs_checkout', $checkout, $context );
	}

	/**
	 * Tests to see if we can and should update a specific item.
	 *
	 * @since 3.7.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $type    The type of update being checked: 'core', 'theme',
	 *                        'plugin', 'translation'.
	 * @param object $item    The update offer.
	 * @param string $context The filesystem context (a path) against which filesystem
	 *                        access and status should be checked.
	 */
	public function should_update( $type, $item, $context ) {
		// Used to see if WP_Filesystem is set up to allow unattended updates.
		$skin = new Automatic_Upgrader_Skin;

		if ( $this->is_disabled() )
			return false;

		// Only relax the filesystem checks when the update doesn't include new files
		$allow_relaxed_file_ownership = false;
		if ( 'core' == $type && isset( $item->new_files ) && ! $item->new_files ) {
			$allow_relaxed_file_ownership = true;
		}

		// If we can't do an auto core update, we may still be able to email the user.
		if ( ! $skin->request_filesystem_credentials( false, $context, $allow_relaxed_file_ownership ) || $this->is_vcs_checkout( $context ) ) {
			if ( 'core' == $type )
				$this->send_core_update_notification_email( $item );
			return false;
		}

		// Next up, is this an item we can update?
		if ( 'core' == $type )
			$update = Core_Upgrader::should_update_to_version( $item->current );
		else
			$update = ! empty( $item->autoupdate );

		/**
		 * Filters whether to automatically update core, a plugin, a theme, or a language.
		 *
		 * The dynamic portion of the hook name, `$type`, refers to the type of update
		 * being checked. Can be 'core', 'theme', 'plugin', or 'translation'.
		 *
		 * Generally speaking, plugins, themes, and major core versions are not updated
		 * by default, while translations and minor and development versions for core
		 * are updated by default.
		 *
		 * See the {@see 'allow_dev_auto_core_updates', {@see 'allow_minor_auto_core_updates'},
		 * and {@see 'allow_major_auto_core_updates'} filters for a more straightforward way to
		 * adjust core updates.
		 *
		 * @since 3.7.0
		 *
		 * @param bool   $update Whether to update.
		 * @param object $item   The update offer.
		 */
		$update = apply_filters( "auto_update_{$type}", $update, $item );

		if ( ! $update ) {
			if ( 'core' == $type )
				$this->send_core_update_notification_email( $item );
			return false;
		}

		// If it's a core update, are we actually compatible with its requirements?
		if ( 'core' == $type ) {
			global $wpdb;

			$php_compat = version_compare( phpversion(), $item->php_version, '>=' );
			if ( file_exists( WP_CONTENT_DIR . '/db.php' ) && empty( $wpdb->is_mysql ) )
				$mysql_compat = true;
			else
				$mysql_compat = version_compare( $wpdb->db_version(), $item->mysql_version, '>=' );

			if ( ! $php_compat || ! $mysql_compat )
				return false;
		}

		return true;
	}

	/**
	 * Notifies an administrator of a core update.
	 *
	 * @since 3.7.0
	 *
	 * @param object $item The update offer.
	 */
	protected function send_core_update_notification_email( $item ) {
		$notified = get_site_option( 'auto_core_update_notified' );

		// Don't notify if we've already notified the same email address of the same version.
		if ( $notified && $notified['email'] == get_site_option( 'admin_email' ) && $notified['version'] == $item->current )
			return false;

		// See if we need to notify users of a core update.
		$notify = ! empty( $item->notify_email );

		/**
		 * Filters whether to notify the site administrator of a new core update.
		 *
		 * By default, administrators are notified when the update offer received
		 * from WordPress.org sets a particular flag. This allows some discretion
		 * in if and when to notify.
		 *
		 * This filter is only evaluated once per release. If the same email address
		 * was already notified of the same new version, WordPress won't repeatedly
		 * email the administrator.
		 *
		 * This filter is also used on about.php to check if a plugin has disabled
		 * these notifications.
		 *
		 * @since 3.7.0
		 *
		 * @param bool   $notify Whether the site administrator is notified.
		 * @param object $item   The update offer.
		 */
		if ( ! apply_filters( 'send_core_update_notification_email', $notify, $item ) )
			return false;

		$this->send_email( 'manual', $item );
		return true;
	}

	/**
	 * Update an item, if appropriate.
	 *
	 * @since 3.7.0
	 *
	 * @param string $type The type of update being checked: 'core', 'theme', 'plugin', 'translation'.
	 * @param object $item The update offer.
	 *
	 * @return null|WP_Error
	 */
	public function update( $type, $item ) {
		$skin = new Automatic_Upgrader_Skin;

		switch ( $type ) {
			case 'core':
				// The Core upgrader doesn't use the Upgrader's skin during the actual main part of the upgrade, instead, firing a filter.
				add_filter( 'update_feedback', array( $skin, 'feedback' ) );
				$upgrader = new Core_Upgrader( $skin );
				$context  = ABSPATH;
				break;
			case 'plugin':
				$upgrader = new Plugin_Upgrader( $skin );
				$context  = WP_PLUGIN_DIR; // We don't support custom Plugin directories, or updates for WPMU_PLUGIN_DIR
				break;
			case 'theme':
				$upgrader = new Theme_Upgrader( $skin );
				$context  = get_theme_root( $item->theme );
				break;
			case 'translation':
				$upgrader = new Language_Pack_Upgrader( $skin );
				$context  = WP_CONTENT_DIR; // WP_LANG_DIR;
				break;
		}

		// Determine whether we can and should perform this update.
		if ( ! $this->should_update( $type, $item, $context ) )
			return false;

		/**
		 * Fires immediately prior to an auto-update.
		 *
		 * @since 4.4.0
		 *
		 * @param string $type    The type of update being checked: 'core', 'theme', 'plugin', or 'translation'.
		 * @param object $item    The update offer.
		 * @param string $context The filesystem context (a path) against which filesystem access and status
		 *                        should be checked.
		 */
		do_action( 'pre_auto_update', $type, $item, $context );

		$upgrader_item = $item;
		switch ( $type ) {
			case 'core':
				$skin->feedback( __( 'Updating to WordPress %s' ), $item->version );
				$item_name = sprintf( __( 'WordPress %s' ), $item->version );
				break;
			case 'theme':
				$upgrader_item = $item->theme;
				$theme = wp_get_theme( $upgrader_item );
				$item_name = $theme->Get( 'Name' );
				$skin->feedback( __( 'Updating theme: %s' ), $item_name );
				break;
			case 'plugin':
				$upgrader_item = $item->plugin;
				$plugin_data = get_plugin_data( $context . '/' . $upgrader_item );
				$item_name = $plugin_data['Name'];
				$skin->feedback( __( 'Updating plugin: %s' ), $item_name );
				break;
			case 'translation':
				$language_item_name = $upgrader->get_name_for_update( $item );
				$item_name = sprintf( __( 'Translations for %s' ), $language_item_name );
				$skin->feedback( sprintf( __( 'Updating translations for %1$s (%2$s)&#8230;' ), $language_item_name, $item->language ) );
				break;
		}

		$allow_relaxed_file_ownership = false;
		if ( 'core' == $type && isset( $item->new_files ) && ! $item->new_files ) {
			$allow_relaxed_file_ownership = true;
		}

		// Boom, This sites about to get a whole new splash of paint!
		$upgrade_result = $upgrader->upgrade( $upgrader_item, array(
			'clear_update_cache' => false,
			// Always use partial builds if possible for core updates.
			'pre_check_md5'      => false,
			// Only available for core updates.
			'attempt_rollback'   => true,
			// Allow relaxed file ownership in some scenarios
			'allow_relaxed_file_ownership' => $allow_relaxed_file_ownership,
		) );

		// If the filesystem is unavailable, false is returned.
		if ( false === $upgrade_result ) {
			$upgrade_result = new WP_Error( 'fs_unavailable', __( 'Could not access filesystem.' ) );
		}

		if ( 'core' == $type ) {
			if ( is_wp_error( $upgrade_result ) && ( 'up_to_date' == $upgrade_result->get_error_code() || 'locked' == $upgrade_result->get_error_code() ) ) {
				// These aren't actual errors, treat it as a skipped-update instead to avoid triggering the post-core update failure routines.
				return false;
			}

			// Core doesn't output this, so let's append it so we don't get confused.
			if ( is_wp_error( $upgrade_result ) ) {
				$skin->error( __( 'Installation Failed' ), $upgrade_result );
			} else {
				$skin->feedback( __( 'WordPress updated successfully' ) );
			}
		}

		$this->update_results[ $type ][] = (object) array(
			'item'     => $item,
			'result'   => $upgrade_result,
			'name'     => $item_name,
			'messages' => $skin->get_upgrade_messages()
		);

		return $upgrade_result;
	}

	/**
	 * Kicks off the background update process, looping through all pending updates.
	 *
	 * @since 3.7.0
	 */
	public function run() {
		if ( $this->is_disabled() )
			return;

		if ( ! is_main_network() || ! is_main_site() )
			return;

		if ( ! WP_Upgrader::create_lock( 'auto_updater' ) )
			return;

		// Don't automatically run these thins, as we'll handle it ourselves
		remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );
		remove_action( 'upgrader_process_complete', 'wp_version_check' );
		remove_action( 'upgrader_process_complete', 'wp_update_plugins' );
		remove_action( 'upgrader_process_complete', 'wp_update_themes' );

		// Next, Plugins
		wp_update_plugins(); // Check for Plugin updates
		$plugin_updates = get_site_transient( 'update_plugins' );
		if ( $plugin_updates && !empty( $plugin_updates->response ) ) {
			foreach ( $plugin_updates->response as $plugin ) {
				$this->update( 'plugin', $plugin );
			}
			// Force refresh of plugin update information
			wp_clean_plugins_cache();
		}

		// Next, those themes we all love
		wp_update_themes();  // Check for Theme updates
		$theme_updates = get_site_transient( 'update_themes' );
		if ( $theme_updates && !empty( $theme_updates->response ) ) {
			foreach ( $theme_updates->response as $theme ) {
				$this->update( 'theme', (object) $theme );
			}
			// Force refresh of theme update information
			wp_clean_themes_cache();
		}

		// Next, Process any core update
		wp_version_check(); // Check for Core updates
		$core_update = find_core_auto_update();

		if ( $core_update )
			$this->update( 'core', $core_update );

		// Clean up, and check for any pending translations
		// (Core_Upgrader checks for core updates)
		$theme_stats = array();
		if ( isset( $this->update_results['theme'] ) ) {
			foreach ( $this->update_results['theme'] as $upgrade ) {
				$theme_stats[ $upgrade->item->theme ] = ( true === $upgrade->result );
			}
		}
		wp_update_themes( $theme_stats );  // Check for Theme updates

		$plugin_stats = array();
		if ( isset( $this->update_results['plugin'] ) ) {
			foreach ( $this->update_results['plugin'] as $upgrade ) {
				$plugin_stats[ $upgrade->item->plugin ] = ( true === $upgrade->result );
			}
		}
		wp_update_plugins( $plugin_stats ); // Check for Plugin updates

		// Finally, Process any new translations
		$language_updates = wp_get_translation_updates();
		if ( $language_updates ) {
			foreach ( $language_updates as $update ) {
				$this->update( 'translation', $update );
			}

			// Clear existing caches
			wp_clean_update_cache();

			wp_version_check();  // check for Core updates
			wp_update_themes();  // Check for Theme updates
			wp_update_plugins(); // Check for Plugin updates
		}

		// Send debugging email to admin for all development installations.
		if ( ! empty( $this->update_results ) ) {
			$development_version = false !== strpos( get_bloginfo( 'version' ), '-' );

			/**
			 * Filters whether to send a debugging email for each automatic background update.
			 *
			 * @since 3.7.0
			 *
			 * @param bool $development_version By default, emails are sent if the
			 *                                  install is a development version.
			 *                                  Return false to avoid the email.
			 */
			if ( apply_filters( 'automatic_updates_send_debug_email', $development_version ) )
				$this->send_debug_email();

			if ( ! empty( $this->update_results['core'] ) )
				$this->after_core_update( $this->update_results['core'][0] );

			/**
			 * Fires after all automatic updates have run.
			 *
			 * @since 3.8.0
			 *
			 * @param array $update_results The results of all attempted updates.
			 */
			do_action( 'automatic_updates_complete', $this->update_results );
		}

		WP_Upgrader::release_lock( 'auto_updater' );
	}

	/**
	 * If we tried to perform a core update, check if we should send an email,
	 * and if we need to avoid processing future updates.
	 *
	 * @since 3.7.0
	 *
	 * @param object $update_result The result of the core update. Includes the update offer and result.
	 */
	protected function after_core_update( $update_result ) {
		$wp_version = get_bloginfo( 'version' );

		$core_update = $update_result->item;
		$result      = $update_result->result;

		if ( ! is_wp_error( $result ) ) {
			$this->send_email( 'success', $core_update );
			return;
		}

		$error_code = $result->get_error_code();

		// Any of these WP_Error codes are critical failures, as in they occurred after we started to copy core files.
		// We should not try to perform a background update again until there is a successful one-click update performed by the user.
		$critical = false;
		if ( $error_code === 'disk_full' || false !== strpos( $error_code, '__copy_dir' ) ) {
			$critical = true;
		} elseif ( $error_code === 'rollback_was_required' && is_wp_error( $result->get_error_data()->rollback ) ) {
			// A rollback is only critical if it failed too.
			$critical = true;
			$rollback_result = $result->get_error_data()->rollback;
		} elseif ( false !== strpos( $error_code, 'do_rollback' ) ) {
			$critical = true;
		}

		if ( $critical ) {
			$critical_data = array(
				'attempted'  => $core_update->current,
				'current'    => $wp_version,
				'error_code' => $error_code,
				'error_data' => $result->get_error_data(),
				'timestamp'  => time(),
				'critical'   => true,
			);
			if ( isset( $rollback_result ) ) {
				$critical_data['rollback_code'] = $rollback_result->get_error_code();
				$critical_data['rollback_data'] = $rollback_result->get_error_data();
			}
			update_site_option( 'auto_core_update_failed', $critical_data );
			$this->send_email( 'critical', $core_update, $result );
			return;
		}

		/*
		 * Any other WP_Error code (like download_failed or files_not_writable) occurs before
		 * we tried to copy over core files. Thus, the failures are early and graceful.
		 *
		 * We should avoid trying to perform a background update again for the same version.
		 * But we can try again if another version is released.
		 *
		 * For certain 'transient' failures, like download_failed, we should allow retries.
		 * In fact, let's schedule a special update for an hour from now. (It's possible
		 * the issue could actually be on WordPress.org's side.) If that one fails, then email.
		 */
		$send = true;
  		$transient_failures = array( 'incompatible_archive', 'download_failed', 'insane_distro', 'locked' );
  		if ( in_array( $error_code, $transient_failures ) && ! get_site_option( 'auto_core_update_failed' ) ) {
  			wp_schedule_single_eve