
<?php
/**
 * Misc WordPress Administration API.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Returns whether the server is running Apache with the mod_rewrite module loaded.
 *
 * @since 2.0.0
 *
 * @return bool
 */
function got_mod_rewrite() {
	$got_rewrite = apache_mod_loaded('mod_rewrite', true);

	/**
	 * Filters whether Apache and mod_rewrite are present.
	 *
	 * This filter was previously used to force URL rewriting for other servers,
	 * like nginx. Use the {@see 'got_url_rewrite'} filter in got_url_rewrite() instead.
	 *
	 * @since 2.5.0
	 *
	 * @see got_url_rewrite()
	 *
	 * @param bool $got_rewrite Whether Apache and mod_rewrite are present.
	 */
	return apply_filters( 'got_rewrite', $got_rewrite );
}

/**
 * Returns whether the server supports URL rewriting.
 *
 * Detects Apache's mod_rewrite, IIS 7.0+ permalink support, and nginx.
 *
 * @since 3.7.0
 *
 * @global bool $is_nginx
 *
 * @return bool Whether the server supports URL rewriting.
 */
function got_url_rewrite() {
	$got_url_rewrite = ( got_mod_rewrite() || $GLOBALS['is_nginx'] || iis7_supports_permalinks() );

	/**
	 * Filters whether URL rewriting is available.
	 *
	 * @since 3.7.0
	 *
	 * @param bool $got_url_rewrite Whether URL rewriting is available.
	 */
	return apply_filters( 'got_url_rewrite', $got_url_rewrite );
}

/**
 * Extracts strings from between the BEGIN and END markers in the .htaccess file.
 *
 * @since 1.5.0
 *
 * @param string $filename
 * @param string $marker
 * @return array An array of strings from a file (.htaccess ) from between BEGIN and END markers.
 */
function extract_from_markers( $filename, $marker ) {
	$result = array ();

	if ( ! file_exists( $filename ) ) {
		return $result;
	}

	$markerdata = explode( "\n", implode( '', file( $filename ) ) );

	$state = false;
	foreach ( $markerdata as $markerline ) {
		if ( false !== strpos( $markerline, '# END ' . $marker ) ) {
			$state = false;
		}
		if ( $state ) {
			$result[] = $markerline;
		}
		if ( false !== strpos( $markerline, '# BEGIN ' . $marker ) ) {
			$state = true;
		}
	}

	return $result;
}

/**
 * Inserts an array of strings into a file (.htaccess ), placing it between
 * BEGIN and END markers.
 *
 * Replaces existing marked info. Retains surrounding
 * data. Creates file if none exists.
 *
 * @since 1.5.0
 *
 * @param string       $filename  Filename to alter.
 * @param string       $marker    The marker to alter.
 * @param array|string $insertion The new content to insert.
 * @return bool True on write success, false on failure.
 */
function insert_with_markers( $filename, $marker, $insertion ) {
	if ( ! file_exists( $filename ) ) {
		if ( ! is_writable( dirname( $filename ) ) ) {
			return false;
		}
		if ( ! touch( $filename ) ) {
			return false;
		}
	} elseif ( ! is_writeable( $filename ) ) {
		return false;
	}

	if ( ! is_array( $insertion ) ) {
		$insertion = explode( "\n", $insertion );
	}

	$start_marker = "# BEGIN {$marker}";
	$end_marker   = "# END {$marker}";

	$fp = fopen( $filename, 'r+' );
	if ( ! $fp ) {
		return false;
	}

	// Attempt to get a lock. If the filesystem supports locking, this will block until the lock is acquired.
	flock( $fp, LOCK_EX );

	$lines = array();
	while ( ! feof( $fp ) ) {
		$lines[] = rtrim( fgets( $fp ), "\r\n" );
	}

	// Split out the existing file into the preceding lines, and those that appear after the marker
	$pre_lines = $post_lines = $existing_lines = array();
	$found_marker = $found_end_marker = false;
	foreach ( $lines as $line ) {
		if ( ! $found_marker && false !== strpos( $line, $start_marker ) ) {
			$found_marker = true;
			continue;
		} elseif ( ! $found_end_marker && false !== strpos( $line, $end_marker ) ) {
			$found_end_marker = true;
			continue;
		}
		if ( ! $found_marker ) {
			$pre_lines[] = $line;
		} elseif ( $found_marker && $found_end_marker ) {
			$post_lines[] = $line;
		} else {
			$existing_lines[] = $line;
		}
	}

	// Check to see if there was a change
	if ( $existing_lines === $insertion ) {
		flock( $fp, LOCK_UN );
		fclose( $fp );

		return true;
	}

	// Generate the new file data
	$new_file_data = implode( "\n", array_merge(
		$pre_lines,
		array( $start_marker ),
		$insertion,
		array( $end_marker ),
		$post_lines
	) );

	// Write to the start of the file, and truncate it to that length
	fseek( $fp, 0 );
	$bytes = fwrite( $fp, $new_file_data );
	if ( $bytes ) {
		ftruncate( $fp, ftell( $fp ) );
	}
	fflush( $fp );
	flock( $fp, LOCK_UN );
	fclose( $fp );

	return (bool) $bytes;
}

/**
 * Updates the htaccess file with the current rules if it is writable.
 *
 * Always writes to the file if it exists and is writable to ensure that we
 * blank out old rules.
 *
 * @since 1.5.0
 *
 * @global WP_Rewrite $wp_rewrite
 */
function save_mod_rewrite_rules() {
	if ( is_multisite() )
		return;

	global $wp_rewrite;

	$home_path = get_home_path();
	$htaccess_file = $home_path.'.htaccess';

	/*
	 * If the file doesn't already exist check for write access to the directory
	 * and whether we have some rules. Else check for write access to the file.
	 */
	if ((!file_exists($htaccess_file) && is_writable($home_path) && $wp_rewrite->using_mod_rewrite_permalinks()) || is_writable($htaccess_file)) {
		if ( got_mod_rewrite() ) {
			$rules = explode( "\n", $wp_rewrite->mod_rewrite_rules() );
			return insert_with_markers( $htaccess_file, 'WordPress', $rules );
		}
	}

	return false;
}

/**
 * Updates the IIS web.config file with the current rules if it is writable.
 * If the permalinks do not require rewrite rules then the rules are deleted from the web.config file.
 *
 * @since 2.8.0
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @return bool True if web.config was updated successfully
 */
function iis7_save_url_rewrite_rules(){
	if ( is_multisite() )
		return;

	global $wp_rewrite;

	$home_path = get_home_path();
	$web_config_file = $home_path . 'web.config';

	// Using win_is_writable() instead of is_writable() because of a bug in Windows PHP
	if ( iis7_supports_permalinks() && ( ( ! file_exists($web_config_file) && win_is_writable($home_path) && $wp_rewrite->using_mod_rewrite_permalinks() ) || win_is_writable($web_config_file) ) ) {
		$rule = $wp_rewrite->iis7_url_rewrite_rules(false, '', '');
		if ( ! empty($rule) ) {
			return iis7_add_rewrite_rule($web_config_file, $rule);
		} else {
			return iis7_delete_rewrite_rule($web_config_file);
		}
	}
	return false;
}

/**
 * Update the "recently-edited" file for the plugin or theme editor.
 *
 * @since 1.5.0
 *
 * @param string $file
 */
function update_recently_edited( $file ) {
	$oldfiles = (array ) get_option( 'recently_edited' );
	if ( $oldfiles ) {
		$oldfiles = array_reverse( $oldfiles );
		$oldfiles[] = $file;
		$oldfiles = array_reverse( $oldfiles );
		$oldfiles = array_unique( $oldfiles );
		if ( 5 < count( $oldfiles ))
			array_pop( $oldfiles );
	} else {
		$oldfiles[] = $file;
	}
	update_option( 'recently_edited', $oldfiles );
}

/**
 * Makes a tree structure for the Theme Editor's file list.
 *
 * @since 4.9.0
 * @access private
 *
 * @param array $allowed_files List of theme file paths.
 * @return array Tree structure for listing theme files.
 */
function wp_make_theme_file_tree( $allowed_files ) {
	$tree_list = array();
	foreach ( $allowed_files as $file_name => $absolute_filename ) {
		$list = explode( '/', $file_name );
		$last_dir = &$tree_list;
		foreach ( $list as $dir ) {
			$last_dir =& $last_dir[ $dir ];
		}
		$last_dir = $file_name;
	}
	return $tree_list;
}

/**
 * Outputs the formatted file list for the Theme Editor.
 *
 * @since 4.9.0
 * @access private
 *
 * @param array|string $tree  List of file/folder paths, or filename.
 * @param int          $level The aria-level for the current iteration.
 * @param int          $size  The aria-setsize for the current iteration.
 * @param int          $index The aria-posinset for the current iteration.
 */
function wp_print_theme_file_tree( $tree, $level = 2, $size = 1, $index = 1 ) {
	global $relative_file, $stylesheet;

	if ( is_array( $tree ) ) {
		$index = 0;
		$size = count( $tree );
		foreach ( $tree as $label => $theme_file ) :
			$index++;
			if ( ! is_array( $theme_file ) ) {
				wp_print_theme_file_tree( $theme_file, $level, $index, $size );
				continue;
			}
			?>
			<li role="treeitem" aria-expanded="true" tabindex="-1"
				aria-level="<?php echo esc_attr( $level ); ?>"
				aria-setsize="<?php echo esc_attr( $size ); ?>"
				aria-posinset="<?php echo esc_attr( $index ); ?>">
				<span class="folder-label"><?php echo esc_html( $label ); ?> <span class="screen-reader-text"><?php _e( 'folder' ); ?></span><span aria-hidden="true" class="icon"></span></span>
				<ul role="group" class="tree-folder"><?php wp_print_theme_file_tree( $theme_file, $level + 1, $index, $size ); ?></ul>
			</li>
			<?php
		endforeach;
	} else {
		$filename = $tree;
		$url = add_query_arg(
			array(
				'file' => rawurlencode( $tree ),
				'theme' => rawurlencode( $stylesheet ),
			),
			self_admin_url( 'theme-editor.php' )
		);
		?>
		<li role="none" class="<?php echo esc_attr( $relative_file === $filename ? 'current-file' : '' ); ?>">
			<a role="treeitem" tabindex="<?php echo esc_attr( $relative_file === $filename ? '0' : '-1' ); ?>"
				href="<?php echo esc_url( $url ); ?>"
				aria-level="<?php echo esc_attr( $level ); ?>"
				aria-setsize="<?php echo esc_attr( $size ); ?>"
				aria-posinset="<?php echo esc_attr( $index ); ?>">
				<?php
				$file_description = esc_html( get_file_description( $filename ) );
				if ( $file_description !== $filename && basename( $filename ) !== $file_description ) {
					$file_description .= '<br /><span class="nonessential">(' . esc_html( $filename ) . ')</span>';
				}

				if ( $relative_file === $filename ) {
					echo '<span class="notice notice-info">' . $file_description . '</span>';
				} else {
					echo $file_description;
				}
				?>
			</a>
		</li>
		<?php
	}
}

/**
 * Makes a tree structure for the Plugin Editor's file list.
 *
 * @since 4.9.0
 * @access private
 *
 * @param string $plugin_editable_files List of plugin file paths.
 * @return array Tree structure for listing plugin files.
 */
function wp_make_plugin_file_tree( $plugin_editable_files ) {
	$tree_list = array();
	foreach ( $plugin_editable_files as $plugin_file ) {
		$list = explode( '/', preg_replace( '#^.+?/#', '', $plugin_file ) );
		$last_dir = &$tree_list;
		foreach ( $list as $dir ) {
			$last_dir =& $last_dir[ $dir ];
		}
		$last_dir = $plugin_file;
	}
	return $tree_list;
}

/**
 * Outputs the formatted file list for the Plugin Editor.
 *
 * @since 4.9.0
 * @access private
 *
 * @param array|string $tree  List of file/folder paths, or filename.
 * @param string       $label Name of file or folder to print.
 * @param int          $level The aria-level for the current iteration.
 * @param int          $size  The aria-setsize for the current iteration.
 * @param int          $index The aria-posinset for the current iteration.
 */
function wp_print_plugin_file_tree( $tree, $label = '', $level = 2, $size = 1, $index = 1 ) {
	global $file, $plugin;
	if ( is_array( $tree ) ) {
		$index = 0;
		$size = count( $tree );
		foreach ( $tree as $label => $plugin_file ) :
			$index++;
			if ( ! is_array( $plugin_file ) ) {
				wp_print_plugin_file_tree( $plugin_file, $label, $level, $index, $size );
				continue;
			}
			?>
			<li role="treeitem" aria-expanded="true" tabindex="-1"
				aria-level="<?php echo esc_attr( $level ); ?>"
				aria-setsize="<?php echo esc_attr( $size ); ?>"
				aria-posinset="<?php echo esc_attr( $index ); ?>">
				<span class="folder-label"><?php echo esc_html( $label ); ?> <span class="screen-reader-text"><?php _e( 'folder' ); ?></span><span aria-hidden="true" class="icon"></span></span>
				<ul role="group" class="tree-folder"><?php wp_print_plugin_file_tree( $plugin_file, '', $level + 1, $index, $size ); ?></ul>
			</li>
			<?php
		endforeach;
	} else {
		$url = add_query_arg(
			array(
				'file' => rawurlencode( $tree ),
				'plugin' => rawurlencode( $plugin ),
			),
			self_admin_url( 'plugin-editor.php' )
		);
		?>
		<li role="none" class="<?php echo esc_attr( $file === $tree ? 'current-file' : '' ); ?>">
			<a role="treeitem" tabindex="<?php echo esc_attr( $file === $tree ? '0' : '-1' ); ?>"
				href="<?php echo esc_url( $url ); ?>"
				aria-level="<?php echo esc_attr( $level ); ?>"
				aria-setsize="<?php echo esc_attr( $size ); ?>"
				aria-posinset="<?php echo esc_attr( $index ); ?>">
				<?php
				if ( $file === $tree ) {
					echo '<span class="notice notice-info">' . esc_html( $label ) . '</span>';
				} else {
					echo esc_html( $label );
				}
				?>
			</a>
		</li>
		<?php
	}
}

/**
 * Flushes rewrite rules if siteurl, home or page_on_front changed.
 *
 * @since 2.1.0
 *
 * @param string $old_value
 * @param string $value
 */
function update_home_siteurl( $old_value, $value ) {
	if ( wp_installing() )
		return;

	if ( is_multisite() && ms_is_switched() ) {
		delete_option( 'rewrite_rules' );
	} else {
		flush_rewrite_rules();
	}
}


/**
 * Resets global variables based on $_GET and $_POST
 *
 * This function resets global variables based on the names passed
 * in the $vars array to the value of $_POST[$var] or $_GET[$var] or ''
 * if neither is defined.
 *
 * @since 2.0.0
 *
 * @param array $vars An array of globals to reset.
 */
function wp_reset_vars( $vars ) {
	foreach ( $vars as $var ) {
		if ( empty( $_POST[ $var ] ) ) {
			if ( empty( $_GET[ $var ] ) ) {
				$GLOBALS[ $var ] = '';
			} else {
				$GLOBALS[ $var ] = $_GET[ $var ];
			}
		} else {
			$GLOBALS[ $var ] = $_POST[ $var ];
		}
	}
}

/**
 * Displays the given administration message.
 *
 * @since 2.1.0
 *
 * @param string|WP_Error $message
 */
function show_message($message) {
	if ( is_wp_error($message) ){
		if ( $message->get_error_data() && is_string( $message->get_error_data() ) )
			$message = $message->get_error_message() . ': ' . $message->get_error_data();
		else
			$message = $message->get_error_message();
	}
	echo "<p>$message</p>\n";
	wp_ob_end_flush_all();
	flush();
}

/**
 * @since 2.8.0
 *
 * @param string $content
 * @return array
 */
function wp_doc_link_parse( $content ) {
	if ( !is_string( $content ) || empty( $content ) )
		return array();

	if ( !function_exists('token_get_all') )
		return array();

	$tokens = token_get_all( $content );
	$count = count( $tokens );
	$functions = array();
	$ignore_functions = array();
	for ( $t = 0; $t < $count - 2; $t++ ) {
		if ( ! is_array( $tokens[ $t ] ) ) {
			continue;
		}

		if ( T_STRING == $tokens[ $t ][0] && ( '(' == $tokens[ $t + 1 ] || '(' == $tokens[ $t + 2 ] ) ) {
			// If it's a function or class defined locally, there's not going to be any docs available
			if ( ( isset( $tokens[ $t - 2 ][1] ) && in_array( $tokens[ $t - 2 ][1], array( 'function', 'class' ) ) ) || ( isset( $tokens[ $t - 2 ][0] ) && T_OBJECT_OPERATOR == $tokens[ $t - 1 ][0] ) ) {
				$ignore_functions[] = $tokens[$t][1];
			}
			// Add this to our stack of unique references
			$functions[] = $tokens[$t][1];
		}
	}

	$functions = array_unique( $functions );
	sort( $functions );

	/**
	 * Filters the list of functions and classes to be ignored from the documentation lookup.
	 *
	 * @since 2.8.0
	 *
	 * @param array $ignore_functions Functions and classes to be ignored.
	 */
	$ignore_functions = apply_filters( 'documentation_ignore_functions', $ignore_functions );

	$ignore_functions = array_unique( $ignore_functions );

	$out = array();
	foreach ( $functions as $function ) {
		if ( in_array( $function, $ignore_functions ) )
			continue;
		$out[] = $function;
	}

	return $out;
}

/**
 * Saves option for number of rows when listing posts, pages, comments, etc.
 *
 * @since 2.8.0
 */
function set_screen_options() {

	if ( isset($_POST['wp_screen_options']) && is_array($_POST['wp_screen_options']) ) {
		check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );

		if ( !$user = wp_get_current_user() )
			return;
		$option = $_POST['wp_screen_options']['option'];
		$value = $_POST['wp_screen_options']['value'];

		if ( $option != sanitize_key( $option ) )
			return;

		$map_option = $option;
		$type = str_replace('edit_', '', $map_option);
		$type = str_replace('_per_page', '', $type);
		if ( in_array( $type, get_taxonomies() ) )
			$map_option = 'edit_tags_per_page';
		elseif ( in_array( $type, get_post_types() ) )
			$map_option = 'edit_per_page';
		else
			$option = str_replace('-', '_', $option);

		switch ( $map_option ) {
			case 'edit_per_page':
			case 'users_per_page':
			case 'edit_comments_per_page':
			case 'upload_per_page':
			case 'edit_tags_per_page':
			case 'plugins_per_page':
			// Network admin
			case 'sites_network_per_page':
			case 'users_network_per_page':
			case 'site_users_network_per_page':
			case 'plugins_network_per_page':
			case 'themes_network_per_page':
			case 'site_themes_network_per_page':
				$value = (int) $value;
				if ( $value < 1 || $value > 999 )
					return;
				break;
			default:

				/**
				 * Filters a screen option value before it is set.
				 *
				 * The filter can also be used to modify non-standard [items]_per_page
				 * settings. See the parent function for a full list of standard options.
				 *
				 * Returning false to the filter will skip saving the current option.
				 *
				 * @since 2.8.0
				 *
				 * @see set_screen_options()
				 *
				 * @param bool|int $value  Screen option value. Default false to skip.
				 * @param string   $option The option name.
				 * @param int      $value  The number of rows to use.
				 */
				$value = apply_filters( 'set-screen-option', false, $option, $value );

				if ( false === $value )
					return;
				break;
		}

		update_user_meta($user->ID, $option, $value);

		$url = remove_query_arg( array( 'pagenum', 'apage', 'paged' ), wp_get_referer() );
		if ( isset( $_POST['mode'] ) ) {
			$url = add_query_arg( array( 'mode' => $_POST['mode'] ), $url );
		}

		wp_safe_redirect( $url );
		exit;
	}
}

/**
 * Check if rewrite rule for WordPress already exists in the IIS 7+ configuration file
 *
 * @since 2.8.0
 *
 * @return bool
 * @param string $filename The file path to the configuration file
 */
function iis7_rewrite_rule_exists($filename) {
	if ( ! file_exists($filename) )
		return false;
	if ( ! class_exists( 'DOMDocument', false ) ) {
		return false;
	}

	$doc = new DOMDocument();
	if ( $doc->load($filename) === false )
		return false;
	$xpath = new DOMXPath($doc);
	$rules = $xpath->query('/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'wordpress\')] | /configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'WordPress\')]');
	if ( $rules->length == 0 )
		return false;
	else
		return true;
}

/**
 * Delete WordPress rewrite rule from web.config file if it exists there
 *
 * @since 2.8.0
 *
 * @param string $filename Name of the configuration file
 * @return bool
 */
function iis7_delete_rewrite_rule($filename) {
	// If configuration file does not exist then rules also do not exist so there is nothing to delete
	if ( ! file_exists($filename) )
		return true;

	if ( ! class_exists( 'DOMDocument', false ) ) {
		return false;
	}

	$doc = new DOMDocument();
	$doc->preserveWhiteSpace = false;

	if ( $doc -> load($filename) === false )
		return false;
	$xpath = new DOMXPath($doc);
	$rules = $xpath->query('/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'wordpress\')] | /configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'WordPress\')]');
	if ( $rules->length > 0 ) {
		$child = $rules->item(0);
		$parent = $child->parentNode;
		$parent->removeChild($child);
		$doc->formatOutput = true;
		saveDomDocument($doc, $filename);
	}
	return true;
}

/**
 * Add WordPress rewrite rule to the IIS 7+ configuration file.
 *
 * @since 2.8.0
 *
 * @param string $filename The file path to the configuration file
 * @param string $rewrite_rule The XML fragment with URL Rewrite rule
 * @return bool
 */
function iis7_add_rewrite_rule($filename, $rewrite_rule) {
	if ( ! class_exists( 'DOMDocument', false ) ) {
		return false;
	}

	// If configuration file does not exist then we create one.
	if ( ! file_exists($filename) ) {
		$fp = fopen( $filename, 'w');
		fwrite($fp, '<configuration/>');
		fclose($fp);
	}

	$doc = new DOMDocument();
	$doc->preserveWhiteSpace = false;

	if ( $doc->load($filename) === false )
		return false;

	$xpath = new DOMXPath($doc);

	// First check if the rule already exists as in that case there is no need to re-add it
	$wordpress_rules = $xpath->query('/configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'wordpress\')] | /configuration/system.webServer/rewrite/rules/rule[starts-with(@name,\'WordPress\')]');
	if ( $wordpress_rules->length > 0 )
		return true;

	// Check the XPath to the rewrite rule and create XML nodes if they do not exist
	$xmlnodes = $xpath->query('/configuration/system.webServer/rewrite/rules');
	if ( $xmlnodes->length > 0 ) {
		$rules_node = $xmlnodes->item(0);
	} else {
		$rules_node = $doc->createElement('rules');

		$xmlnodes = $xpath->query('/configuration/system.webServer/rewrite');
		if ( $xmlnodes->length > 0 ) {
			$rewrite_node = $xmlnodes->item(0);
			$rewrite_node->appendChild($rules_node);
		} else {
			$rewrite_node = $doc->createElement('rewrite');
			$rewrite_node->appendChild($rules_node);

			$xmlnodes = $xpath->query('/configuration/system.webServer');
			if ( $xmlnodes->length > 0 ) {
				$system_webServer_node = $xmlnodes->item(0);
				$system_webServer_node->appendChild($rewrite_node);
			} else {
				$system_webServer_node = $doc->createElement('system.webServer');
				$system_webServer_node->appendChild($rewrite_node);

				$xmlnodes = $xpath->query('/configuration');
				if ( $xmlnodes->length > 0 ) {
					$config_node = $xmlnodes->item(0);
					$config_node->appendChild($system_webServer_node);
				} else {
					$config_node = $doc->createElement('configuration');
					$doc->appendChild($config_node);
					$config_node->appendChild($system_webServer_node);
				}
			}
		}
	}

	$rule_fragment = $doc->createDocumentFragment();
	$rule_fragment->appendXML($rewrite_rule);
	$rules_node->appendChild($rule_fragment);

	$doc->encoding = "UTF-8";
	$doc->formatOutput = true;
	saveDomDocument($doc, $filename);

	return true;
}

/**
 * Saves the XML document into a file
 *
 * @since 2.8.0
 *
 * @param DOMDocument $doc
 * @param string $filename
 */
function saveDomDocument($doc, $filename) {
	$config = $doc->saveXML();
	$config = preg_replace("/([^\r])\n/", "$1\r\n", $config);
	$fp = fopen($filename, 'w');
	fwrite($fp, $config);
	fclose($fp);
}

/**
 * Display the default admin color scheme picker (Used in user-edit.php)
 *
 * @since 3.0.0
 *
 * @global array $_wp_admin_css_colors
 *
 * @param int $user_id User ID.
 */
function admin_color_scheme_picker( $user_id ) {
	global $_wp_admin_css_colors;

	ksort( $_wp_admin_css_colors );

	if ( isset( $_wp_admin_css_colors['fresh'] ) ) {
		// Set Default ('fresh') and Light should go first.
		$_wp_admin_css_colors = array_filter( array_merge( array( 'fresh' => '', 'light' => '' ), $_wp_admin_css_colors ) );
	}

	$current_color = get_user_option( 'admin_color', $user_id );

	if ( empty( $current_color ) || ! isset( $_wp_admin_css_colors[ $current_color ] ) ) {
		$current_color = 'fresh';
	}

	?>
	<fieldset id="color-picker" class="scheme-list">
		<legend class="screen-reader-text"><span><?php _e( 'Admin Color Scheme' ); ?></span></legend>
		<?php
		wp_nonce_field( 'save-color-scheme', 'color-nonce', false );
		foreach ( $_wp_admin_css_colors as $color => $color_info ) :

			?>
			<div class="color-option <?php echo ( $color == $current_color ) ? 'selected' : ''; ?>">
				<input name="admin_color" id="admin_color_<?php echo esc_attr( $color ); ?>" type="radio" value="<?php echo esc_attr( $color ); ?>" class="tog" <?php checked( $color, $current_color ); ?> />
				<input type="hidden" class="css_url" value="<?php echo esc_url( $color_info->url ); ?>" />
				<input type="hidden" class="icon_colors" value="<?php echo esc_attr( wp_json_encode( array( 'icons' => $color_info->icon_colors ) ) ); ?>" />
				<label for="admin_color_<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $color_info->name ); ?></label>
				<table class="color-palette">
					<tr>
					<?php

					foreach ( $color_info->colors as $html_color ) {
						?>
						<td style="background-color: <?php echo esc_attr( $html_color ); ?>">&nbsp;</td>
						<?php
					}

					?>
					</tr>
				</table>
			</div>
			<?php

		endforeach;

	?>
	</fieldset>
	<?php
}

/**
 *
 * @global array $_wp_admin_css_colors
 */
function wp_color_scheme_settings() {
	global $_wp_admin_css_colors;

	$color_scheme = get_user_option( 'admin_color' );

	// It's possible to have a color scheme set that is no longer registered.
	if ( empty( $_wp_admin_css_colors[ $color_scheme ] ) ) {
		$color_scheme = 'fresh';
	}

	if ( ! empty( $_wp_admin_css_colors[ $color_scheme ]->icon_colors ) ) {
		$icon_colors = $_wp_admin_css_colors[ $color_scheme ]->icon_colors;
	} elseif ( ! empty( $_wp_admin_css_colors['fresh']->icon_colors ) ) {
		$icon_colors = $_wp_admin_css_colors['fresh']->icon_colors;
	} else {
		// Fall back to the default set of icon colors if the default scheme is missing.
		$icon_colors = array( 'base' => '#82878c', 'focus' => '#00a0d2', 'current' => '#fff' );
	}

	echo '<script type="text/javascript">var _wpColorScheme = ' . wp_json_encode( array( 'icons' => $icon_colors ) ) . ";</script>\n";
}

/**
 * @since 3.3.0
 */
function _ipad_meta() {
	if ( wp_is_mobile() ) {
		?>
		<meta name="viewport" id="viewport-meta" content="width=device-width, initial-scale=1">
		<?php
	}
}

/**
 * Check lock status for posts displayed on the Posts screen
 *
 * @since 3.6.0
 *
 * @param array  $response  The Heartbeat response.
 * @param array  $data      The $_POST data sent.
 * @param string $screen_id The screen id.
 * @return array The Heartbeat response.
 */
function wp_check_locked_posts( $response, $data, $screen_id ) {
	$checked = array();

	if ( array_key_exists( 'wp-check-locked-posts', $data ) && is_array( $data['wp-check-locked-posts'] ) ) {
		foreach ( $data['wp-check-locked-posts'] as $key ) {
			if ( ! $post_id = absint( substr( $key, 5 ) ) )
				continue;

			if ( ( $user_id = wp_check_post_lock( $post_id ) ) && ( $user = get_userdata( $user_id ) ) && current_user_can( 'edit_post', $post_id ) ) {
				$send = array( 'text' => sprintf( __( '%s is currently editing' ), $user->display_name ) );

				if ( ( $avatar = get_avatar( $user->ID, 18 ) ) && preg_match( "|src='([^']+)'|", $avatar, $matches ) )
					$send['avatar_src'] = $matches[1];

				$checked[$key] = $send;
			}
		}
	}

	if ( ! empty( $checked ) )
		$response['wp-check-locked-posts'] = $checked;

	return $response;
}

/**
 * Check lock status on the New/Edit Post screen and refresh the lock
 *
 * @since 3.6.0
 *
 * @param array  $response  The Heartbeat response.
 * @param array  $data      The $_POST data sent.
 * @param string $screen_id The screen id.
 * @return array The Heartbeat response.
 */
function wp_refresh_post_lock( $response, $data, $screen_id ) {
	if ( array_key_exists( 'wp-refresh-post-lock', $data ) ) {
		$received = $data['wp-refresh-post-lock'];
		$send = array();

		if ( ! $post_id = absint( $received['post_id'] ) )
			return $response;

		if ( ! current_user_can('edit_post', $post_id) )
			return $response;

		if ( ( $user_id = wp_check_post_lock( $post_id ) ) && ( $user = get_userdata( $user_id ) ) ) {
			$error = array(
				'text' => sprintf( __( '%s has taken over and is currently editing.' ), $user->display_name )
			);

			if ( $avatar = get_avatar( $user->ID, 64 ) ) {
				if ( preg_match( "|src='([^']+)'|", $avatar, $matches ) )
					$error['avatar_src'] = $matches[1];
			}

			$send['lock_error'] = $error;
		} else {
			if ( $new_lock = wp_set_post_lock( $post_id ) )
				$send['new_lock'] = implode( ':', $new_lock );
		}

		$response['wp-refresh-post-lock'] = $send;
	}

	return $response;
}

/**
 * Check nonce expiration on the New/Edit Post screen and refresh if needed
 *
 * @since 3.6.0
 *
 * @param array  $response  The Heartbeat response.
 * @param array  $data      The $_POST data sent.
 * @param string $screen_id The screen id.
 * @return array The Heartbeat response.
 */
function wp_refresh_post_nonces( $response, $data, $screen_id ) {
	if ( array_key_exists( 'wp-refresh-post-nonces', $data ) ) {
		$received = $data['wp-refresh-post-nonces'];
		$response['wp-refresh-post-nonces'] = array( 'check' => 1 );

		if ( ! $post_id = absint( $received['post_id'] ) ) {
			return $response;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $response;
		}

		$response['wp-refresh-post-nonces'] = array(
			'replace' => array(
				'getpermalinknonce' => wp_create_nonce('getpermalink'),
				'samplepermalinknonce' => wp_create_nonce('samplepermalink'),
				'closedpostboxesnonce' => wp_create_nonce('closedpostboxes'),
				'_ajax_linking_nonce' => wp_create_nonce( 'internal-linking' ),
				'_wpnonce' => wp_create_nonce( 'update-post_' . $post_id ),
			),
			'heartbeatNonce' => wp_create_nonce( 'heartbeat-nonce' ),
		);
	}

	return $response;
}

/**
 * Disable suspension of Heartbeat on the Add/Edit Post screens.
 *
 * @since 3.8.0
 *
 * @global string $pagenow
 *
 * @param array $settings An array of Heartbeat settings.
 * @return array Filtered Heartbeat settings.
 */
function wp_heartbeat_set_suspension( $settings ) {
	global $pagenow;

	if ( 'post.php' === $pagenow || 'post-new.php' === $pagenow ) {
		$settings['suspension'] = 'disable';
	}

	return $settings;
}

/**
 * Autosave with heartbeat
 *
 * @since 3.9.0
 *
 * @param array $response The Heartbeat response.
 * @param array $data     The $_POST data sent.
 * @return array The Heartbeat response.
 */
function heartbeat_autosave( $response, $data ) {
	if ( ! empty( $data['wp_autosave'] ) ) {
		$saved = wp_autosave( $data['wp_autosave'] );

		if ( is_wp_error( $saved ) ) {
			$response['wp_autosave'] = array( 'success' => false, 'message' => $saved->get_error_message() );
		} elseif ( empty( $saved ) ) {
			$response['wp_autosave'] = array( 'success' => false, 'message' => __( 'Error while saving.' ) );
		} else {
			/* translators: draft saved date format, see https://secure.php.net/date */
			$draft_saved_date_format = __( 'g:i:s a' );
			/* translators: %s: date and time */
			$response['wp_autosave'] = array( 'success' => true, 'message' => sprintf( __( 'Draft saved at %s.' ), date_i18n( $draft_saved_date_format ) ) );
		}
	}

	return $response;
}

/**
 * Remove single-use URL parameters and create canonical link based on new URL.
 *
 * Remove specific query string parameters from a URL, create the canonical link,
 * put it in the admin header, and change the current URL to match.
 *
 * @since 4.2.0
 */
function wp_admin_canonical_url() {
	$removable_query_args = wp_removable_query_args();

	if ( empty( $removable_query_args ) ) {
		return;
	}

	// Ensure we're using an absolute URL.
	$current_url  = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
	$filtered_url = remove_query_arg( $removable_query_args, $current_url );
	?>
	<link id="wp-admin-canonical" rel="canonical" href="<?php echo esc_url( $filtered_url ); ?>" />
	<script>
		if ( window.history.replaceState ) {
			window.history.replaceState( null, null, document.getElementById( 'wp-admin-canonical' ).href + window.location.hash );
		}
	</script>
<?php
}

/**
 * Send a referrer policy header so referrers are not sent externally from administration screens.
 *
 * @since 4.9.0
 */
function wp_admin_headers() {
	$policy = 'same-origin';

	/**
	 * Filters the admin referrer policy header value. Default 'same-origin'.
	 *
	 * @since 4.9.0
	 * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Referrer-Policy
	 *
	 * @param string $policy The referrer policy header value.
	 */
	$policy = apply_filters( 'admin_referrer_policy', $policy );

	header( sprintf( 'Referrer-Policy: %s', $policy ) );
}

/**
 * Outputs JS that reloads the page if the user navigated to it with the Back or Forward button.
 *
 * Used on the Edit Post and Add New Post screens. Needed to ensure the page is not loaded from browser cache,
 * so the post title and editor content are the last saved versions. Ideally this script should run first in the head.
 *
 * @since 4.6.0
 */
function wp_page_reload_on_back_button_js() {
	?>
	<script>
		if ( typeof performance !== 'undefined' && performance.navigation && performance.navigation.type === 2 ) {
			document.location.reload( true );
		}
	</script>
	<?php
}

/**
 * Send a confirmation request email when a change of site admin email address is attempted.
 *
 * The new site admin address will not become active until confirmed.
 *
 * @since 3.0.0
 * @since 4.9.0 This function was moved from wp-admin/includes/ms.php so it's no longer Multisite specific.
 *
 * @param string $old_value The old site admin email address.
 * @param string $value     The proposed new site admin email address.
 */
function update_option_new_admin_email( $old_value, $value ) {
	if ( $value == get_option( 'admin_email' ) || ! is_email( $value ) ) {
		return;
	}

	$hash = md5( $value . time() . mt_rand() );
	$new_admin_email = array(
		'hash'     => $hash,
		'newemail' => $value,
	);
	update_option( 'adminhash', $new_admin_email );

	$switched_locale = switch_to_locale( get_user_locale() );

	/* translators: Do not translate USERNAME, ADMIN_URL, EMAIL, SITENAME, SITEURL: those are placeholders. */
	$email_text = __( 'Howdy ###USERNAME###,

You recently requested to have the administration email address on
your site changed.

If this is correct, please click on the following link to change it:
###ADMIN_URL###

You can safely ignore and delete this email if you do not want to
take this action.

This email has been sent to ###EMAIL###

Regards,
All at ###SITENAME###
###SITEURL###' );

	/**
	 * Filters the text of the email sent when a change of site admin email address is attempted.
	 *
	 * The following strings have a special meaning and will get replaced dynamically:
	 * ###USERNAME###  The current user's username.
	 * ###ADMIN_URL### The link to click on to confirm the email change.
	 * ###EMAIL###     The proposed new site admin email address.
	 * ###SITENAME###  The name of the site.
	 * ###SITEURL###   The URL to the site.
	 *
	 * @since MU (3.0.0)
	 * @since 4.9.0 This filter is no longer Multisite specific.
	 *
	 * @param string $email_text      Text in the email.
	 * @param array  $new_admin_email {
	 *     Data relating to the new site admin email address.
	 *
	 *     @type string $hash     The secure hash used in the confirmation link URL.
	 *     @type string $newemail The proposed new site admin email address.
	 * }
	 */
	$content = apply_filters( 'new_admin_email_content', $email_text, $new_admin_email );

	$current_user = wp_get_current_user();
	$content = str_replace( '###USERNAME###', $current_user->user_login, $content );
	$content = str_replace( '###ADMIN_URL###', esc_url( self_admin_url( 'options.php?adminhash=' . $hash ) ), $content );
	$content = str_replace( '###EMAIL###', $value, $content );
	$content = str_replace( '###SITENAME###', wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $content );
	$content = str_replace( '###SITEURL###', home_url(), $content );

	wp_mail( $value, sprintf( __( '[%s] New Admin Email Address' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) ), $content );

	if ( $switched_locale ) {
		restore_previous_locale();
	}
}