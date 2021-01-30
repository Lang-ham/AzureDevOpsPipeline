
<?php
/**
 * WordPress Post Administration API.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Rename $_POST data from form names to DB post columns.
 *
 * Manipulates $_POST directly.
 *
 * @since 2.6.0
 *
 * @param bool $update Are we updating a pre-existing post?
 * @param array $post_data Array of post data. Defaults to the contents of $_POST.
 * @return object|bool WP_Error on failure, true on success.
 */
function _wp_translate_postdata( $update = false, $post_data = null ) {

	if ( empty($post_data) )
		$post_data = &$_POST;

	if ( $update )
		$post_data['ID'] = (int) $post_data['post_ID'];

	$ptype = get_post_type_object( $post_data['post_type'] );

	if ( $update && ! current_user_can( 'edit_post', $post_data['ID'] ) ) {
		if ( 'page' == $post_data['post_type'] )
			return new WP_Error( 'edit_others_pages', __( 'Sorry, you are not allowed to edit pages as this user.' ) );
		else
			return new WP_Error( 'edit_others_posts', __( 'Sorry, you are not allowed to edit posts as this user.' ) );
	} elseif ( ! $update && ! current_user_can( $ptype->cap->create_posts ) ) {
		if ( 'page' == $post_data['post_type'] )
			return new WP_Error( 'edit_others_pages', __( 'Sorry, you are not allowed to create pages as this user.' ) );
		else
			return new WP_Error( 'edit_others_posts', __( 'Sorry, you are not allowed to create posts as this user.' ) );
	}

	if ( isset( $post_data['content'] ) )
		$post_data['post_content'] = $post_data['content'];

	if ( isset( $post_data['excerpt'] ) )
		$post_data['post_excerpt'] = $post_data['excerpt'];

	if ( isset( $post_data['parent_id'] ) )
		$post_data['post_parent'] = (int) $post_data['parent_id'];

	if ( isset($post_data['trackback_url']) )
		$post_data['to_ping'] = $post_data['trackback_url'];

	$post_data['user_ID'] = get_current_user_id();

	if (!empty ( $post_data['post_author_override'] ) ) {
		$post_data['post_author'] = (int) $post_data['post_author_override'];
	} else {
		if (!empty ( $post_data['post_author'] ) ) {
			$post_data['post_author'] = (int) $post_data['post_author'];
		} else {
			$post_data['post_author'] = (int) $post_data['user_ID'];
		}
	}

	if ( isset( $post_data['user_ID'] ) && ( $post_data['post_author'] != $post_data['user_ID'] )
		 && ! current_user_can( $ptype->cap->edit_others_posts ) ) {
		if ( $update ) {
			if ( 'page' == $post_data['post_type'] )
				return new WP_Error( 'edit_others_pages', __( 'Sorry, you are not allowed to edit pages as this user.' ) );
			else
				return new WP_Error( 'edit_others_posts', __( 'Sorry, you are not allowed to edit posts as this user.' ) );
		} else {
			if ( 'page' == $post_data['post_type'] )
				return new WP_Error( 'edit_others_pages', __( 'Sorry, you are not allowed to create pages as this user.' ) );
			else
				return new WP_Error( 'edit_others_posts', __( 'Sorry, you are not allowed to create posts as this user.' ) );
		}
	}

	if ( ! empty( $post_data['post_status'] ) ) {
		$post_data['post_status'] = sanitize_key( $post_data['post_status'] );

		// No longer an auto-draft
		if ( 'auto-draft' === $post_data['post_status'] ) {
			$post_data['post_status'] = 'draft';
		}

		if ( ! get_post_status_object( $post_data['post_status'] ) ) {
			unset( $post_data['post_status'] );
		}
	}

	// What to do based on which button they pressed
	if ( isset($post_data['saveasdraft']) && '' != $post_data['saveasdraft'] )
		$post_data['post_status'] = 'draft';
	if ( isset($post_data['saveasprivate']) && '' != $post_data['saveasprivate'] )
		$post_data['post_status'] = 'private';
	if ( isset($post_data['publish']) && ( '' != $post_data['publish'] ) && ( !isset($post_data['post_status']) || $post_data['post_status'] != 'private' ) )
		$post_data['post_status'] = 'publish';
	if ( isset($post_data['advanced']) && '' != $post_data['advanced'] )
		$post_data['post_status'] = 'draft';
	if ( isset($post_data['pending']) && '' != $post_data['pending'] )
		$post_data['post_status'] = 'pending';

	if ( isset( $post_data['ID'] ) )
		$post_id = $post_data['ID'];
	else
		$post_id = false;
	$previous_status = $post_id ? get_post_field( 'post_status', $post_id ) : false;

	if ( isset( $post_data['post_status'] ) && 'private' == $post_data['post_status'] && ! current_user_can( $ptype->cap->publish_posts ) ) {
		$post_data['post_status'] = $previous_status ? $previous_status : 'pending';
	}

	$published_statuses = array( 'publish', 'future' );

	// Posts 'submitted for approval' present are submitted to $_POST the same as if they were being published.
	// Change status from 'publish' to 'pending' if user lacks permissions to publish or to resave published posts.
	if ( isset($post_data['post_status']) && (in_array( $post_data['post_status'], $published_statuses ) && !current_user_can( $ptype->cap->publish_posts )) )
		if ( ! in_array( $previous_status, $published_statuses ) || !current_user_can( 'edit_post', $post_id ) )
			$post_data['post_status'] = 'pending';

	if ( ! isset( $post_data['post_status'] ) ) {
		$post_data['post_status'] = 'auto-draft' === $previous_status ? 'draft' : $previous_status;
	}

	if ( isset( $post_data['post_password'] ) && ! current_user_can( $ptype->cap->publish_posts ) ) {
		unset( $post_data['post_password'] );
	}

	if (!isset( $post_data['comment_status'] ))
		$post_data['comment_status'] = 'closed';

	if (!isset( $post_data['ping_status'] ))
		$post_data['ping_status'] = 'closed';

	foreach ( array('aa', 'mm', 'jj', 'hh', 'mn') as $timeunit ) {
		if ( !empty( $post_data['hidden_' . $timeunit] ) && $post_data['hidden_' . $timeunit] != $post_data[$timeunit] ) {
			$post_data['edit_date'] = '1';
			break;
		}
	}

	if ( !empty( $post_data['edit_date'] ) ) {
		$aa = $post_data['aa'];
		$mm = $post_data['mm'];
		$jj = $post_data['jj'];
		$hh = $post_data['hh'];
		$mn = $post_data['mn'];
		$ss = $post_data['ss'];
		$aa = ($aa <= 0 ) ? date('Y') : $aa;
		$mm = ($mm <= 0 ) ? date('n') : $mm;
		$jj = ($jj > 31 ) ? 31 : $jj;
		$jj = ($jj <= 0 ) ? date('j') : $jj;
		$hh = ($hh > 23 ) ? $hh -24 : $hh;
		$mn = ($mn > 59 ) ? $mn -60 : $mn;
		$ss = ($ss > 59 ) ? $ss -60 : $ss;
		$post_data['post_date'] = sprintf( "%04d-%02d-%02d %02d:%02d:%02d", $aa, $mm, $jj, $hh, $mn, $ss );
		$valid_date = wp_checkdate( $mm, $jj, $aa, $post_data['post_date'] );
		if ( !$valid_date ) {
			return new WP_Error( 'invalid_date', __( 'Invalid date.' ) );
		}
		$post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
	}

	if ( isset( $post_data['post_category'] ) ) {
		$category_object = get_taxonomy( 'category' );
		if ( ! current_user_can( $category_object->cap->assign_terms ) ) {
			unset( $post_data['post_category'] );
		}
	}

	return $post_data;
}

/**
 * Update an existing post with values provided in $_POST.
 *
 * @since 1.5.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $post_data Optional.
 * @return int Post ID.
 */
function edit_post( $post_data = null ) {
	global $wpdb;

	if ( empty($post_data) )
		$post_data = &$_POST;

	// Clear out any data in internal vars.
	unset( $post_data['filter'] );

	$post_ID = (int) $post_data['post_ID'];
	$post = get_post( $post_ID );
	$post_data['post_type'] = $post->post_type;
	$post_data['post_mime_type'] = $post->post_mime_type;

	if ( ! empty( $post_data['post_status'] ) ) {
		$post_data['post_status'] = sanitize_key( $post_data['post_status'] );

		if ( 'inherit' == $post_data['post_status'] ) {
			unset( $post_data['post_status'] );
		}
	}

	$ptype = get_post_type_object($post_data['post_type']);
	if ( !current_user_can( 'edit_post', $post_ID ) ) {
		if ( 'page' == $post_data['post_type'] )
			wp_die( __('Sorry, you are not allowed to edit this page.' ));
		else
			wp_die( __('Sorry, you are not allowed to edit this post.' ));
	}

	if ( post_type_supports( $ptype->name, 'revisions' ) ) {
		$revisions = wp_get_post_revisions( $post_ID, array( 'order' => 'ASC', 'posts_per_page' => 1 ) );
		$revision = current( $revisions );

		// Check if the revisions have been upgraded
		if ( $revisions && _wp_get_post_revision_version( $revision ) < 1 )
			_wp_upgrade_revisions_of_post( $post, wp_get_post_revisions( $post_ID ) );
	}

	if ( isset($post_data['visibility']) ) {
		switch ( $post_data['visibility'] ) {
			case 'public' :
				$post_data['post_password'] = '';
				break;
			case 'password' :
				unset( $post_data['sticky'] );
				break;
			case 'private' :
				$post_data['post_status'] = 'private';
				$post_data['post_password'] = '';
				unset( $post_data['sticky'] );
				break;
		}
	}

	$post_data = _wp_translate_postdata( true, $post_data );
	if ( is_wp_error($post_data) )
		wp_die( $post_data->get_error_message() );

	// Post Formats
	if ( isset( $post_data['post_format'] ) )
		set_post_format( $post_ID, $post_data['post_format'] );

	$format_meta_urls = array( 'url', 'link_url', 'quote_source_url' );
	foreach ( $format_meta_urls as $format_meta_url ) {
		$keyed = '_format_' . $format_meta_url;
		if ( isset( $post_data[ $keyed ] ) )
			update_post_meta( $post_ID, $keyed, wp_slash( esc_url_raw( wp_unslash( $post_data[ $keyed ] ) ) ) );
	}

	$format_keys = array( 'quote', 'quote_source_name', 'image', 'gallery', 'audio_embed', 'video_embed' );

	foreach ( $format_keys as $key ) {
		$keyed = '_format_' . $key;
		if ( isset( $post_data[ $keyed ] ) ) {
			if ( current_user_can( 'unfiltered_html' ) )
				update_post_meta( $post_ID, $keyed, $post_data[ $keyed ] );
			else
				update_post_meta( $post_ID, $keyed, wp_filter_post_kses( $post_data[ $keyed ] ) );
		}
	}

	if ( 'attachment' === $post_data['post_type'] && preg_match( '#^(audio|video)/#', $post_data['post_mime_type'] ) ) {
		$id3data = wp_get_attachment_metadata( $post_ID );
		if ( ! is_array( $id3data ) ) {
			$id3data = array();
		}

		foreach ( wp_get_attachment_id3_keys( $post, 'edit' ) as $key => $label ) {
			if ( isset( $post_data[ 'id3_' . $key ] ) ) {
				$id3data[ $key ] = sanitize_text_field( wp_unslash( $post_data[ 'id3_' . $key ] ) );
			}
		}
		wp_update_attachment_metadata( $post_ID, $id3data );
	}

	// Meta Stuff
	if ( isset($post_data['meta']) && $post_data['meta'] ) {
		foreach ( $post_data['meta'] as $key => $value ) {
			if ( !$meta = get_post_meta_by_id( $key ) )
				continue;
			if ( $meta->post_id != $post_ID )
				continue;
			if ( is_protected_meta( $meta->meta_key, 'post' ) || ! current_user_can( 'edit_post_meta', $post_ID, $meta->meta_key ) )
				continue;
			if ( is_protected_meta( $value['key'], 'post' ) || ! current_user_can( 'edit_post_meta', $post_ID, $value['key'] ) )
				continue;
			update_meta( $key, $value['key'], $value['value'] );
		}
	}

	if ( isset($post_data['deletemeta']) && $post_data['deletemeta'] ) {
		foreach ( $post_data['deletemeta'] as $key => $value ) {
			if ( !$meta = get_post_meta_by_id( $key ) )
				continue;
			if ( $meta->post_id != $post_ID )
				continue;
			if ( is_protected_meta( $meta->meta_key, 'post' ) || ! current_user_can( 'delete_post_meta', $post_ID, $meta->meta_key ) )
				continue;
			delete_meta( $key );
		}
	}

	// Attachment stuff
	if ( 'attachment' == $post_data['post_type'] ) {
		if ( isset( $post_data[ '_wp_attachment_image_alt' ] ) ) {
			$image_alt = wp_unslash( $post_data['_wp_attachment_image_alt'] );
			if ( $image_alt != get_post_meta( $post_ID, '_wp_attachment_image_alt', true ) ) {
				$image_alt = wp_strip_all_tags( $image_alt, true );
				// update_meta expects slashed.
				update_post_meta( $post_ID, '_wp_attachment_image_alt', wp_slash( $image_alt ) );
			}
		}

		$attachment_data = isset( $post_data['attachments'][ $post_ID ] ) ? $post_data['attachments'][ $post_ID ] : array();

		/** This filter is documented in wp-admin/includes/media.php */
		$post_data = apply_filters( 'attachment_fields_to_save', $post_data, $attachment_data );
	}

	// Convert taxonomy input to term IDs, to avoid ambiguity.
	if ( isset( $post_data['tax_input'] ) ) {
		foreach ( (array) $post_data['tax_input'] as $taxonomy => $terms ) {
			// Hierarchical taxonomy data is already sent as term IDs, so no conversion is necessary.
			if ( is_taxonomy_hierarchical( $taxonomy ) ) {
				continue;
			}

			/*
			 * Assume that a 'tax_input' string is a comma-separated list of term names.
			 * Some languages may use a character other than a comma as a delimiter, so we standardize on
			 * commas before parsing the list.
			 */
			if ( ! is_array( $terms ) ) {
				$comma = _x( ',', 'tag delimiter' );
				if ( ',' !== $comma ) {
					$terms = str_replace( $comma, ',', $terms );
				}
				$terms = explode( ',', trim( $terms, " \n\t\r\0\x0B," ) );
			}

			$clean_terms = array();
			foreach ( $terms as $term ) {
				// Empty terms are invalid input.
				if ( empty( $term ) ) {
					continue;
				}

				$_term = get_terms( $taxonomy, array(
					'name' => $term,
					'fields' => 'ids',
					'hide_empty' => false,
				) );

				if ( ! empty( $_term ) ) {
					$clean_terms[] = intval( $_term[0] );
				} else {
					// No existing term was found, so pass the string. A new term will be created.
					$clean_terms[] = $term;
				}
			}

			$post_data['tax_input'][ $taxonomy ] = $clean_terms;
		}
	}

	add_meta( $post_ID );

	update_post_meta( $post_ID, '_edit_last', get_current_user_id() );

	$success = wp_update_post( $post_data );
	// If the save failed, see if we can sanity check the main fields and try again
	if ( ! $success && is_callable( array( $wpdb, 'strip_invalid_text_for_column' ) ) ) {
		$fields = array( 'post_title', 'post_content', 'post_excerpt' );

		foreach ( $fields as $field ) {
			if ( isset( $post_data[ $field ] ) ) {
				$post_data[ $field ] = $wpdb->strip_invalid_text_for_column( $wpdb->posts, $field, $post_data[ $field ] );
			}
		}

		wp_update_post( $post_data );
	}

	// Now that we have an ID we can fix any attachment anchor hrefs
	_fix_attachment_links( $post_ID );

	wp_set_post_lock( $post_ID );

	if ( current_user_can( $ptype->cap->edit_others_posts ) && current_user_can( $ptype->cap->publish_posts ) ) {
		if ( ! empty( $post_data['sticky'] ) )
			stick_post( $post_ID );
		else
			unstick_post( $post_ID );
	}

	return $post_ID;
}

/**
 * Process the post data for the bulk editing of posts.
 *
 * Updates all bulk edited posts/pages, adding (but not removing) tags and
 * categories. Skips pages when they would be their own parent or child.
 *
 * @since 2.7.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param array $post_data Optional, the array of post data to process if not provided will use $_POST superglobal.
 * @return array
 */
function bulk_edit_posts( $post_data = null ) {
	global $wpdb;

	if ( empty($post_data) )
		$post_data = &$_POST;

	if ( isset($post_data['post_type']) )
		$ptype = get_post_type_object($post_data['post_type']);
	else
		$ptype = get_post_type_object('post');

	if ( !current_user_can( $ptype->cap->edit_posts ) ) {
		if ( 'page' == $ptype->name )
			wp_die( __('Sorry, you are not allowed to edit pages.'));
		else
			wp_die( __('Sorry, you are not allowed to edit posts.'));
	}

	if ( -1 == $post_data['_status'] ) {
		$post_data['post_status'] = null;
		unset($post_data['post_status']);
	} else {
		$post_data['post_status'] = $post_data['_status'];
	}
	unset($post_data['_status']);

	if ( ! empty( $post_data['post_status'] ) ) {
		$post_data['post_status'] = sanitize_key( $post_data['post_status'] );

		if ( 'inherit' == $post_data['post_status'] ) {
			unset( $post_data['post_status'] );
		}
	}

	$post_IDs = array_map( 'intval', (array) $post_data['post'] );

	$reset = array(
		'post_author', 'post_status', 'post_password',
		'post_parent', 'page_template', 'comment_status',
		'ping_status', 'keep_private', 'tax_input',
		'post_category', 'sticky', 'post_format',
	);

	foreach ( $reset as $field ) {
		if ( isset($post_data[$field]) && ( '' == $post_data[$field] || -1 == $post_data[$field] ) )
			unset($post_data[$field]);
	}

	if ( isset($post_data['post_category']) ) {
		if ( is_array($post_data['post_category']) && ! empty($post_data['post_category']) )
			$new_cats = array_map( 'absint', $post_data['post_category'] );
		else
			unset($post_data['post_category']);
	}

	$tax_input = array();
	if ( isset($post_data['tax_input'])) {
		foreach ( $post_data['tax_input'] as $tax_name => $terms ) {
			if ( empty($terms) )
				continue;
			if ( is_taxonomy_hierarchical( $tax_name ) ) {
				$tax_input[ $tax_name ] = array_map( 'absint', $terms );
			} else {
				$comma = _x( ',', 'tag delimiter' );
				if ( ',' !== $comma )
					$terms = str_replace( $comma, ',', $terms );
				$tax_input[ $tax_name ] = explode( ',', trim( $terms, " \n\t\r\0\x0B," ) );
			}
		}
	}

	if ( isset($post_data['post_parent']) && ($parent = (int) $post_data['post_parent']) ) {
		$pages = $wpdb->get_results("SELECT ID, post_parent FROM $wpdb->posts WHERE post_type = 'page'");
		$children = array();

		for ( $i = 0; $i < 50 && $parent > 0; $i++ ) {
			$children[] = $parent;

			foreach ( $pages as $page ) {
				if ( $page->ID == $parent ) {
					$parent = $page->post_parent;
					break;
				}
			}
		}
	}

	$updated = $skipped = $locked = array();
	$shared_post_data = $post_data;

	foreach ( $post_IDs as $post_ID ) {
		// Start with fresh post data with each iteration.
		$post_data = $shared_post_data;

		$post_type_object = get_post_type_object( get_post_type( $post_ID ) );

		if ( !isset( $post_type_object ) || ( isset($children) && in_array($post_ID, $children) ) || !current_user_can( 'edit_post', $post_ID ) ) {
			$skipped[] = $post_ID;
			continue;
		}

		if ( wp_check_post_lock( $post_ID ) ) {
			$locked[] = $post_ID;
			continue;
		}

		$post = get_post( $post_ID );
		$tax_names = get_object_taxonomies( $post );
		foreach ( $tax_names as $tax_name ) {
			$taxonomy_obj = get_taxonomy($tax_name);
			if ( isset( $tax_input[$tax_name]) && current_user_can( $taxonomy_obj->cap->assign_terms ) )
				$new_terms = $tax_input[$tax_name];
			else
				$new_terms = array();

			if ( $taxonomy_obj->hierarchical )
				$current_terms = (array) wp_get_object_terms( $post_ID, $tax_name, array('fields' => 'ids') );
			else
				$current_terms = (array) wp_get_object_terms( $post_ID, $tax_name, array('fields' => 'names') );

			$post_data['tax_input'][$tax_name] = array_merge( $current_terms, $new_terms );
		}

		if ( isset($new_cats) && in_array( 'category', $tax_names ) ) {
			$cats = (array) wp_get_post_categories($post_ID);
			$post_data['post_category'] = array_unique( array_merge($cats, $new_cats) );
			unset( $post_data['tax_input']['category'] );
		}

		$post_data['post_type'] = $post->post_type;
		$post_data['post_mime_type'] = $post->post_mime_type;
		$post_data['guid'] = $post->guid;

		foreach ( array( 'comment_status', 'ping_status', 'post_author' ) as $field ) {
			if ( ! isset( $post_data[ $field ] ) ) {
				$post_data[ $field ] = $post->$field;
			}
		}

		$post_data['ID'] = $post_ID;
		$post_data['post_ID'] = $post_ID;

		$post_data = _wp_translate_postdata( true, $post_data );
		if ( is_wp_error( $post_data ) ) {
			$skipped[] = $post_ID;
			continue;
		}

		if ( isset( $post_data['post_format'] ) ) {
			set_post_format( $post_ID, $post_data['post_format'] );
			unset( $post_data['tax_input']['post_format'] );
		}

		$updated[] = wp_update_post( $post_data );

		if ( isset( $post_data['sticky'] ) && current_user_can( $ptype->cap->edit_others_posts ) ) {
			if ( 'sticky' == $post_data['sticky'] )
				stick_post( $post_ID );
			else
				unstick_post( $post_ID );
		}
	}

	return array( 'updated' => $updated, 'skipped' => $skipped, 'locked' => $locked );
}

/**
 * Default post information to use when populating the "Write Post" form.
 *
 * @since 2.0.0
 *
 * @param string $post_type    Optional. A post type string. Default 'post'.
 * @param bool   $create_in_db Optional. Whether to insert the post into database. Default false.
 * @return WP_Post Post object containing all the default post data as attributes
 */
function get_default_post_to_edit( $post_type = 'post', $create_in_db = false ) {
	$post_title = '';
	if ( !empty( $_REQUEST['post_title'] ) )
		$post_title = esc_html( wp_unslash( $_REQUEST['post_title'] ));

	$post_content = '';
	if ( !empty( $_REQUEST['content'] ) )
		$post_content = esc_html( wp_unslash( $_REQUEST['content'] ));

	$post_excerpt = '';
	if ( !empty( $_REQUEST['excerpt'] ) )
		$post_excerpt = esc_html( wp_unslash( $_REQUEST['excerpt'] ));

	if ( $create_in_db ) {
		$post_id = wp_insert_post( array( 'post_title' => __( 'Auto Draft' ), 'post_type' => $post_type, 'post_status' => 'auto-draft' ) );
		$post = get_post( $post_id );
		if ( current_theme_supports( 'post-formats' ) && post_type_supports( $post->post_type, 'post-formats' ) && get_option( 'default_post_format' ) )
			set_post_format( $post, get_option( 'default_post_format' ) );
	} else {
		$post = new stdClass;
		$post->ID = 0;
		$post->post_author = '';
		$post->post_date = '';
		$post->post_date_gmt = '';
		$post->post_password = '';
		$post->post_name = '';
		$post->post_type = $post_type;
		$post->post_status = 'draft';
		$post->to_ping = '';
		$post->pinged = '';
		$post->comment_status = get_default_comment_status( $post_type );
		$post->ping_status = get_default_comment_status( $post_type, 'pingback' );
		$post->post_pingback = get_option( 'default_pingback_flag' );
		$post->post_category = get_option( 'default_category' );
		$post->page_template = 'default';
		$post->post_parent = 0;
		$post->menu_order = 0;
		$post = new WP_Post( $post );
	}

	/**
	 * Filters the default post content initially used in the "Write Post" form.
	 *
	 * @since 1.5.0
	 *
	 * @param string  $post_content Default post content.
	 * @param WP_Post $post         Post object.
	 */
	$post->post_content = apply_filters( 'default_content', $post_content, $post );

	/**
	 * Filters the default post title initially used in the "Write Post" form.
	 *
	 * @since 1.5.0
	 *
	 * @param string  $post_title Default post title.
	 * @param WP_Post $post       Post object.
	 */
	$post->post_title = apply_filters( 'default_title', $post_title, $post );

	/**
	 * Filters the default post excerpt initially used in the "Write Post" form.
	 *
	 * @since 1.5.0
	 *
	 * @param string  $post_excerpt Default post excerpt.
	 * @param WP_Post $post         Post object.
	 */
	$post->post_excerpt = apply_filters( 'default_excerpt', $post_excerpt, $post );

	return $post;
}

/**
 * Determine if a post exists based on title, content, and date
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $title Post title
 * @param string $content Optional post content
 * @param string $date Optional post date
 * @return int Post ID if post exists, 0 otherwise.
 */
function post_exists($title, $content = '', $date = '') {
	global $wpdb;

	$post_title = wp_unslash( sanitize_post_field( 'post_title', $title, 0, 'db' ) );
	$post_content = wp_unslash( sanitize_post_field( 'post_content', $content, 0, 'db' ) );
	$post_date = wp_unslash( sanitize_post_field( 'post_date', $date, 0, 'db' ) );

	$query = "SELECT ID FROM $wpdb->posts WHERE 1=1";
	$args = array();

	if ( !empty ( $date ) ) {