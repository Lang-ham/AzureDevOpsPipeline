<?php
/**
 * Base WordPress Filesystem
 *
 * @package WordPress
 * @subpackage Filesystem
 */

/**
 * Base WordPress Filesystem class for which Filesystem implementations extend
 *
 * @since 2.5.0
 */
class WP_Filesystem_Base {
	/**
	 * Whether to display debug data for the connection.
	 *
	 * @since 2.5.0
	 * @var bool
	 */
	public $verbose = false;

	/**
	 * Cached list of local filepaths to mapped remote filepaths.
	 *
	 * @since 2.7.0
	 * @var array
	 */
	public $cache = array();

	/**
	 * The Access method of the current connection, Set automatically.
	 *
	 * @since 2.5.0
	 * @var string
	 */
	public $method = '';

	/**
	 * @var WP_Error
	 */
	public $errors = null;

	/**
	 */
	public $options = array();

	/**
	 * Return the path on the remote filesystem of ABSPATH.
	 *
	 * @since 2.7.0
	 *
	 * @return string The location of the remote path.
	 */
	public function abspath() {
		$folder = $this->find_folder(ABSPATH);
		// Perhaps the FTP folder is rooted at the WordPress install, Check for wp-includes folder in root, Could have some false positives, but rare.
		if ( ! $folder && $this->is_dir( '/' . WPINC ) )
			$folder = '/';
		return $folder;
	}

	/**
	 * Return the path on the remote filesystem of WP_CONTENT_DIR.
	 *
	 * @since 2.7.0
	 *
	 * @return string The location of the remote path.
	 */
	public function wp_content_dir() {
		return $this->find_folder(WP_CONTENT_DIR);
	}

	/**
	 * Return the path on the remote filesystem of WP_PLUGIN_DIR.
	 *
	 * @since 2.7.0
	 *
	 * @return string The location of the remote path.
	 */
	public function wp_plugins_dir() {
		return $this->find_folder(WP_PLUGIN_DIR);
	}

	/**
	 * Return the path on the remote filesystem of the Themes Directory.
	 *
	 * @since 2.7.0
	 *
	 * @param string $theme The Theme stylesheet or template for the directory.
	 * @return string The location of the remote path.
	 */
	public function wp_themes_dir( $theme = false ) {
		$theme_root = get_theme_root( $theme );

		// Account for relative theme roots
		if ( '/themes' == $theme_root || ! is_dir( $theme_root ) )
			$theme_root = WP_CONTENT_DIR . $theme_root;

		return $this->find_folder( $theme_root );
	}

	/**
	 * Return the path on the remote filesystem of WP_LANG_DIR.
	 *
	 * @since 3.2.0
	 *
	 * @return string The location of the remote path.
	 */
	public function wp_lang_dir() {
		return $this->find_folder(WP_LANG_DIR);
	}

	/**
	 * Locate a folder on the remote filesystem.
	 *
	 * @since 2.5.0
	 * @deprecated 2.7.0 use WP_Filesystem::abspath() or WP_Filesystem::wp_*_dir() instead.
	 * @see WP_Filesystem::abspath()
	 * @see WP_Filesystem::wp_content_dir()
	 * @see WP_Filesystem::wp_plugins_dir()
	 * @see WP_Filesystem::wp_themes_dir()
	 * @see WP_Filesystem::wp_lang_dir()
	 *
	 * @param string $base The folder to start searching from.
	 * @param bool   $echo True to display debug information.
	 *                     Default false.
	 * @return string The location of the remote path.
	 */
	public function find_base_dir( $base = '.', $echo = false ) {
		_deprecated_function(__FUNCTION__, '2.7.0', 'WP_Filesystem::abspath() or WP_Filesystem::wp_*_dir()' );
		$this->verbose = $echo;
		return $this->abspath();
	}

	/**
	 * Locate a folder on the remote filesystem.
	 *
	 * @since 2.5.0
	 * @deprecated 2.7.0 use WP_Filesystem::abspath() or WP_Filesystem::wp_*_dir() methods instead.
	 * @see WP_Filesystem::abspath()
	 * @see WP_Filesystem::wp_content_dir()
	 * @see WP_Filesystem::wp_plugins_dir()
	 * @see WP_Filesystem::wp_themes_dir()
	 * @see WP_Filesystem::wp_lang_dir()
	 *
	 * @param string $base The folder to start searching from.
	 * @param bool   $echo True to display debug information.
	 * @return string The location of the remote path.
	 */
	public function get_base_dir( $base = '.', $echo = false ) {
		_deprecated_function(__FUNCTION__, '2.7.0', 'WP_Filesystem::abspath() or WP_Filesystem::wp_*_dir()' );
		$this->verbose = $echo;
		return $this->abspath();
	}

	/**
	 * Locate a folder on the remote filesystem.
	 *
	 * Assumes that on Windows systems, Stripping off the Drive
	 * letter is OK Sanitizes \\ to / in windows filepaths.
	 *
	 * @since 2.7.0
	 *
	 * @param string $folder the folder to locate.
	 * @return string|false The location of the remote path, false on failure.
	 */
	public function find_folder( $folder ) {
		if ( isset( $this->cache[ $folder ] ) )
			return $this->cache[ $folder ];

		if ( stripos($this->method, 'ftp') !== false ) {
			$constant_overrides = array(
				'FTP_BASE' => ABSPATH,
				'FTP_CONTENT_DIR' => WP_CONTENT_DIR,
				'FTP_PLUGIN_DIR' => WP_PLUGIN_DIR,
				'FTP_LANG_DIR' => WP_LANG_DIR
			);

			// Direct matches ( folder = CONSTANT/ )
			foreach ( $constant_overrides as $constant => $dir ) {
				if ( ! defined( $constant ) )
					continue;
				if ( $folder === $dir )
					return trailingslashit( constant( $constant ) );
			}

			// Prefix Matches ( folder = CONSTANT/subdir )
			foreach ( $constant_overrides as $constant => $dir ) {
				if ( ! defined( $constant ) )
					continue;
				if ( 0 === stripos( $folder, $dir ) ) { // $folder starts with $dir
					$potential_folder = preg_replace( '#^' . preg_quote( $dir, '#' ) . '/#i', trailingslashit( constant( $constant ) ), $folder );
					$potential_folder = trailingslashit( $potential_folder );

					if ( $this->is_dir( $potential_folder ) ) {
						$this->cache[ $folder ] = $potential_folder;
						return $potential_folder;
					}
				}
			}
		} elseif ( 'direct' == $this->method ) {
			$folder = str_replace('\\', '/', $folder); // Windows path sanitisation
			return trailingslashit($folder);
		}

		$folder = preg_replace('|^([a-z]{1}):|i', '', $folder); // Strip out windows drive letter if it's there.
		$folder = str_replace('\\', '/', $folder); // Windows path sanitisation

		if ( isset($this->cache[ $folder ] ) )
			return $this->cache[ $folder ];

		if ( $this->exists($folder) ) { // Folder exists at that absolute path.
			$folder = trailingslashit($folder);
			$this->cache[ $folder ] = $folder;
			return $folder;
		}
		if ( $return = $this->search_for_folder($folder) )
			$this->cache[ $folder ] = $return;
		return $return;
	}

	/**
	 * Locate a folder on the remote filesystem.
	 *
	 * Expects Windows sanitized path.
	 *
	 * @since 2.7.0
	 *
	 * @param string $folder The folder to locate.
	 * @param string $base   The folder to start searching from.
	 * @param bool   $loop   If the function has recursed, Internal use only.
	 * @return string|false The location of the remote path, false to cease looping.
	 */
	public function search_for_folder( $folder, $base = '.', $loop = false ) {
		if ( empty( $base ) || '.' == $base )
			$base = trailingslashit($this->cwd());

		$folder = untrailingslashit($folder);

		if ( $this->verbose ) {
			/* translators: 1: folder to locate, 2: folder to start searching from */
			printf( "\n" . __( 'Looking for %1$s in %2$s' ) . "<br/>\n", $folder, $base );
		}

		$folder_parts = explode('/', $folder);
		$folder_part_keys = array_keys( $folder_parts );
		$last_index = array_pop( $folder_part_keys );
		$last_path = $folder_parts[ $last_index ];

		$files = $this->dirlist( $base );

		foreach ( $folder_parts as $index => $key ) {
			if ( $index == $last_index )
				continue; // We want this to be caught by the next code block.

			/*
			 * Working from /home/ to /user/ to /wordpress/ see if that file exists within
			 * the current folder, If it's found, change into it and follow through looking
			 * for it. If it cant find WordPress down that route, it'll continue onto the next
			 * folder level, and see if that matches, and so on. If it reaches the end, and still
			 * cant find it, it'll return false for the entire function.
			 */
			if ( isset($files[ $key ]) ){

				// Let's try that folder:
				$newdir = trailingslashit(path_join($base, $key));
				if ( $this->verbose ) {
					/* translators: %s: directory name */
					printf( "\n" . __( 'Changing to %s' ) . "<br/>\n", $newdir );
				}

				// Only search for the remaining path tokens in the directory, not the full path again.
				$newfolder = implode( '/', array_slice( $folder_parts, $index + 1 ) );
				if ( $ret = $this->search_for_folder( $newfolder, $newdir, $loop) )
					return $ret;
			}
		}

		// Only check this as a last resort, to prevent locating the incorrect install.
		// All above procedures will fail quickly if this is the right branch to take.
		if (isset( $files[ $last_path ] ) ) {
			if ( $this->verbose ) {
				/* translators: %s: directory name */
				printf( "\n" . __( 'Found %s' ) . "<br/>\n",  $base . $last_path );
			}
			return trailingslashit($base . $last_path);
		}

		// Prevent this function from looping again.
		// No need to proceed if we've just searched in /
		if ( $loop || '/' == $base )
			return false;

		// As an extra last resort, Change back to / if the folder wasn't found.
		// This comes into effect when the CWD is /home/user/ but WP is at /var/www/....
		return $this->search_for_folder( $folder, '/', true );

	}

	/**
	 * Return the *nix-style file permissions for a file.
	 *
	 * From the PHP documentation page for fileperms().
	 *
	 * @link https://secure.php.net/manual/en/function.fileperms.php
	 *
	 * @since 2.5.0
	 *
	 * @param string $file String filename.
	 * @return string The *nix-style representation of permissions.
	 */
	public function gethchmod( $file ){
		$perms = intval( $this->getchmod( $file ), 8 );
		if (($perms & 0xC000) == 0xC000) // Socket
			$info = 's';
		elseif (($perms & 0xA000) == 0xA000) // Symbolic Link
			$info = 'l';
		elseif (($perms & 0x8000) == 0x8000) // Regular
			$info = '-';
		elseif (($perms & 0x6000) == 0x6000) // Block special
			$info = 'b';
		elseif (($perms & 0x4000) == 0x4000) // Directory
			$info = 'd';
		elseif (($perms & 0x2000) == 0x2000) // Character special
			$info = 'c';
		elseif (($perms & 0x1000) == 0x1000) // FIFO pipe
			$info = 'p';
		else // Unknown
			$info = 'u';

		// Owner
		$info .= (($perms & 0x0100) ? 'r' : '-');
		$info .= (($perms & 0x0080) ? 'w' : '-');
		$info .= (($perms & 0x0040) ?
					(($perms & 0x0800) ? 's' : 'x' ) :
					(($perms & 0x0800) ? 'S' : '-'));

		// Group
		$info .= (($perms & 0x0020) ? 'r' : '-');
		$info .= (($perms & 0x0010) ? 'w' : '-');
		$info .= (($perms & 0x0008) ?
					(($perms & 0x0400) ? 's' : 'x' ) :
					(($perms & 0x0400) ? 'S' : '-'));

		// World
		$info .= (($perms & 0x0004) ? 'r' : '-');
		$info .= (($perms & 0x0002) ? 'w' : '-');
		$info .= (($perms & 0x0001) ?
					(($perms & 0x0200) ? 't' : 'x' ) :
					(($perms & 0x0200) ? 'T' : '-