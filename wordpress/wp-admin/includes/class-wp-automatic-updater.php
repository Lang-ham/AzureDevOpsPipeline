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
	 * @param string $type The type of update being checked: 'core', 't