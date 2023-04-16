
<?php
/**
 * WordPress Post Template Functions.
 *
 * Gets content for the current post in the loop.
 *
 * @package WordPress
 * @subpackage Template
 */

/**
 * Display the ID of the current item in the WordPress Loop.
 *
 * @since 0.71
 */
function the_ID() {
	echo get_the_ID();
}

/**
 * Retrieve the ID of the current item in the WordPress Loop.
 *
 * @since 2.1.0
 *
 * @return int|false The ID of the current item in the WordPress Loop. False if $post is not set.
 */
function get_the_ID() {
	$post = get_post();
	return ! empty( $post ) ? $post->ID : false;
}

/**
 * Display or retrieve the current post title with optional markup.
 *
 * @since 0.71
 *
 * @param string $before Optional. Markup to prepend to the title. Default empty.
 * @param string $after  Optional. Markup to append to the title. Default empty.
 * @param bool   $echo   Optional. Whether to echo or return the title. Default true for echo.
 * @return string|void Current post title if $echo is false.
 */
function the_title( $before = '', $after = '', $echo = true ) {
	$title = get_the_title();

	if ( strlen($title) == 0 )
		return;

	$title = $before . $title . $after;

	if ( $echo )
		echo $title;
	else
		return $title;
}

/**
 * Sanitize the current title when retrieving or displaying.
 *
 * Works like the_title(), except the parameters can be in a string or
 * an array. See the function for what can be override in the $args parameter.
 *
 * The title before it is displayed will have the tags stripped and esc_attr()
 * before it is passed to the user or displayed. The default as with the_title(),
 * is to display the title.
 *
 * @since 2.3.0
 *
 * @param string|array $args {
 *     Title attribute arguments. Optional.
 *
 *     @type string  $before Markup to prepend to the title. Default empty.
 *     @type string  $after  Markup to append to the title. Default empty.
 *     @type bool    $echo   Whether to echo or return the title. Default true for echo.
 *     @type WP_Post $post   Current post object to retrieve the title for.
 * }
 * @return string|void String when echo is false.
 */
function the_title_attribute( $args = '' ) {
	$defaults = array( 'before' => '', 'after' =>  '', 'echo' => true, 'post' => get_post() );
	$r = wp_parse_args( $args, $defaults );

	$title = get_the_title( $r['post'] );

	if ( strlen( $title ) == 0 ) {
		return;
	}

	$title = $r['before'] . $title . $r['after'];
	$title = esc_attr( strip_tags( $title ) );

	if ( $r['echo'] ) {
		echo $title;
	} else {
		return $title;
	}
}

/**
 * Retrieve post title.
 *
 * If the post is protected and the visitor is not an admin, then "Protected"
 * will be displayed before the post title. If the post is private, then
 * "Private" will be located before the post title.
 *
 * @since 0.71
 *
 * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global $post.
 * @return string
 */
function get_the_title( $post = 0 ) {
	$post = get_post( $post );

	$title = isset( $post->post_title ) ? $post->post_title : '';
	$id = isset( $post->ID ) ? $post->ID : 0;

	if ( ! is_admin() ) {
		if ( ! empty( $post->post_password ) ) {

			/**
			 * Filters the text prepended to the post title for protected posts.
			 *
			 * The filter is only applied on the front end.
			 *
			 * @since 2.8.0
			 *
			 * @param string  $prepend Text displayed before the post title.
			 *                         Default 'Protected: %s'.
			 * @param WP_Post $post    Current post object.
			 */
			$protected_title_format = apply_filters( 'protected_title_format', __( 'Protected: %s' ), $post );
			$title = sprintf( $protected_title_format, $title );
		} elseif ( isset( $post->post_status ) && 'private' == $post->post_status ) {

			/**
			 * Filters the text prepended to the post title of private posts.
			 *
			 * The filter is only applied on the front end.
			 *
			 * @since 2.8.0
			 *
			 * @param string  $prepend Text displayed before the post title.
			 *                         Default 'Private: %s'.
			 * @param WP_Post $post    Current post object.
			 */
			$private_title_format = apply_filters( 'private_title_format', __( 'Private: %s' ), $post );
			$title = sprintf( $private_title_format, $title );
		}
	}

	/**
	 * Filters the post title.
	 *
	 * @since 0.71
	 *
	 * @param string $title The post title.
	 * @param int    $id    The post ID.
	 */
	return apply_filters( 'the_title', $title, $id );
}

/**
 * Display the Post Global Unique Identifier (guid).
 *
 * The guid will appear to be a link, but should not be used as a link to the
 * post. The reason you should not use it as a link, is because of moving the
 * blog across domains.
 *
 * URL is escaped to make it XML-safe.
 *
 * @since 1.5.0
 *
 * @param int|WP_Post $post Optional. Post ID or post object. Default is global $post.
 */
function the_guid( $post = 0 ) {
	$post = get_post( $post );

	$guid = isset( $post->guid ) ? get_the_guid( $post ) : '';
	$id   = isset( $post->ID ) ? $post->ID : 0;

	/**
	 * Filters the escaped Global Unique Identifier (guid) of the post.
	 *
	 * @since 4.2.0
	 *
	 * @see get_the_guid()
	 *
	 * @param string $guid Escaped Global Unique Identifier (guid) of the post.
	 * @param int    $id   The post ID.
	 */
	echo apply_filters( 'the_guid', $guid, $id );
}

/**
 * Retrieve the Post Global Unique Identifier (guid).
 *
 * The guid will appear to be a link, but should not be used as an link to the
 * post. The reason you should not use it as a link, is because of moving the
 * blog across domains.
 *
 * @since 1.5.0
 *
 * @param int|WP_Post $post Optional. Post ID or post object. Default is global $post.
 * @return string
 */
function get_the_guid( $post = 0 ) {
	$post = get_post( $post );

	$guid = isset( $post->guid ) ? $post->guid : '';
	$id   = isset( $post->ID ) ? $post->ID : 0;

	/**
	 * Filters the Global Unique Identifier (guid) of the post.
	 *
	 * @since 1.5.0
	 *
	 * @param string $guid Global Unique Identifier (guid) of the post.
	 * @param int    $id   The post ID.
	 */
	return apply_filters( 'get_the_guid', $guid, $id );
}

/**
 * Display the post content.
 *
 * @since 0.71
 *
 * @param string $more_link_text Optional. Content for when there is more text.
 * @param bool   $strip_teaser   Optional. Strip teaser content before the more text. Default is false.
 */
function the_content( $more_link_text = null, $strip_teaser = false) {
	$content = get_the_content( $more_link_text, $strip_teaser );

	/**
	 * Filters the post content.
	 *
	 * @since 0.71
	 *
	 * @param string $content Content of the current post.
	 */
	$content = apply_filters( 'the_content', $content );
	$content = str_replace( ']]>', ']]&gt;', $content );
	echo $content;
}

/**
 * Retrieve the post content.
 *
 * @since 0.71
 *
 * @global int   $page      Page number of a single post/page.
 * @global int   $more      Boolean indicator for whether single post/page is being viewed.
 * @global bool  $preview   Whether post/page is in preview mode.
 * @global array $pages     Array of all pages in post/page. Each array element contains part of the content separated by the <!--nextpage--> tag.
 * @global int   $multipage Boolean indicator for whether multiple pages are in play.
 *
 * @param string $more_link_text Optional. Content for when there is more text.
 * @param bool   $strip_teaser   Optional. Strip teaser content before the more text. Default is false.
 * @return string
 */
function get_the_content( $more_link_text = null, $strip_teaser = false ) {
	global $page, $more, $preview, $pages, $multipage;

	$post = get_post();

	if ( null === $more_link_text ) {
		$more_link_text = sprintf(
			'<span aria-label="%1$s">%2$s</span>',
			sprintf(
				/* translators: %s: Name of current post */
				__( 'Continue reading %s' ),
				the_title_attribute( array( 'echo' => false ) )
			),
			__( '(more&hellip;)' )
		);
	}

	$output = '';
	$has_teaser = false;

	// If post password required and it doesn't match the cookie.
	if ( post_password_required( $post ) )
		return get_the_password_form( $post );

	if ( $page > count( $pages ) ) // if the requested page doesn't exist
		$page = count( $pages ); // give them the highest numbered page that DOES exist

	$content = $pages[$page - 1];
	if ( preg_match( '/<!--more(.*?)?-->/', $content, $matches ) ) {
		$content = explode( $matches[0], $content, 2 );
		if ( ! empty( $matches[1] ) && ! empty( $more_link_text ) )
			$more_link_text = strip_tags( wp_kses_no_null( trim( $matches[1] ) ) );

		$has_teaser = true;
	} else {
		$content = array( $content );
	}

	if ( false !== strpos( $post->post_content, '<!--noteaser-->' ) && ( ! $multipage || $page == 1 ) )
		$strip_teaser = true;

	$teaser = $content[0];

	if ( $more && $strip_teaser && $has_teaser )
		$teaser = '';

	$output .= $teaser;

	if ( count( $content ) > 1 ) {
		if ( $more ) {
			$output .= '<span id="more-' . $post->ID . '"></span>' . $content[1];
		} else {
			if ( ! empty( $more_link_text ) )

				/**
				 * Filters the Read More link text.
				 *
				 * @since 2.8.0
				 *
				 * @param string $more_link_element Read More link element.
				 * @param string $more_link_text    Read More text.
				 */
				$output .= apply_filters( 'the_content_more_link', ' <a href="' . get_permalink() . "#more-{$post->ID}\" class=\"more-link\">$more_link_text</a>", $more_link_text );
			$output = force_balance_tags( $output );
		}
	}

	if ( $preview ) // Preview fix for JavaScript bug with foreign languages.
		$output =	preg_replace_callback( '/\%u([0-9A-F]{4})/', '_convert_urlencoded_to_entities', $output );

	return $output;
}

/**
 * Preview fix for JavaScript bug with foreign languages.
 *
 * @since 3.1.0
 * @access private
 *
 * @param array $match Match array from preg_replace_callback.
 * @return string
 */
function _convert_urlencoded_to_entities( $match ) {
	return '&#' . base_convert( $match[1], 16, 10 ) . ';';
}

/**
 * Display the post excerpt.
 *
 * @since 0.71
 */
function the_excerpt() {

	/**
	 * Filters the displayed post excerpt.
	 *
	 * @since 0.71
	 *
	 * @see get_the_excerpt()
	 *
	 * @param string $post_excerpt The post excerpt.
	 */
	echo apply_filters( 'the_excerpt', get_the_excerpt() );
}

/**
 * Retrieves the post excerpt.
 *
 * @since 0.71
 * @since 4.5.0 Introduced the `$post` parameter.
 *
 * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global $post.
 * @return string Post excerpt.
 */
function get_the_excerpt( $post = null ) {
	if ( is_bool( $post ) ) {
		_deprecated_argument( __FUNCTION__, '2.3.0' );
	}

	$post = get_post( $post );
	if ( empty( $post ) ) {
		return '';
	}

	if ( post_password_required( $post ) ) {
		return __( 'There is no excerpt because this is a protected post.' );
	}

	/**
	 * Filters the retrieved post excerpt.
	 *
	 * @since 1.2.0
	 * @since 4.5.0 Introduced the `$post` parameter.
	 *
	 * @param string $post_excerpt The post excerpt.
	 * @param WP_Post $post Post object.
	 */
	return apply_filters( 'get_the_excerpt', $post->post_excerpt, $post );
}

/**
 * Whether the post has a custom excerpt.
 *
 * @since 2.3.0
 *
 * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global $post.
 * @return bool True if the post has a custom excerpt, false otherwise.
 */
function has_excerpt( $post = 0 ) {
	$post = get_post( $post );
	return ( !empty( $post->post_excerpt ) );
}

/**
 * Display the classes for the post div.
 *
 * @since 2.7.0
 *
 * @param string|array $class   One or more classes to add to the class list.
 * @param int|WP_Post  $post_id Optional. Post ID or post object. Defaults to the global `$post`.
 */
function post_class( $class = '', $post_id = null ) {
	// Separates classes with a single space, collates classes for post DIV
	echo 'class="' . join( ' ', get_post_class( $class, $post_id ) ) . '"';
}

/**
 * Retrieves the classes for the post div as an array.
 *
 * The class names are many. If the post is a sticky, then the 'sticky'
 * class name. The class 'hentry' is always added to each post. If the post has a
 * post thumbnail, 'has-post-thumbnail' is added as a class. For each taxonomy that
 * the post belongs to, a class will be added of the format '{$taxonomy}-{$slug}' -
 * eg 'category-foo' or 'my_custom_taxonomy-bar'.
 *
 * The 'post_tag' taxonomy is a special
 * case; the class has the 'tag-' prefix instead of 'post_tag-'. All classes are
 * passed through the filter, {@see 'post_class'}, with the list of classes, followed by
 * $class parameter value, with the post ID as the last parameter.
 *
 * @since 2.7.0
 * @since 4.2.0 Custom taxonomy classes were added.
 *
 * @param string|array $class   One or more classes to add to the class list.
 * @param int|WP_Post  $post_id Optional. Post ID or post object.
 * @return array Array of classes.
 */
function get_post_class( $class = '', $post_id = null ) {
	$post = get_post( $post_id );

	$classes = array();

	if ( $class ) {
		if ( ! is_array( $class ) ) {
			$class = preg_split( '#\s+#', $class );
		}
		$classes = array_map( 'esc_attr', $class );
	} else {
		// Ensure that we always coerce class to being an array.
		$class = array();
	}

	if ( ! $post ) {
		return $classes;
	}

	$classes[] = 'post-' . $post->ID;
	if ( ! is_admin() )
		$classes[] = $post->post_type;
	$classes[] = 'type-' . $post->post_type;
	$classes[] = 'status-' . $post->post_status;

	// Post Format
	if ( post_type_supports( $post->post_type, 'post-formats' ) ) {
		$post_format = get_post_format( $post->ID );

		if ( $post_format && !is_wp_error($post_format) )
			$classes[] = 'format-' . sanitize_html_class( $post_format );
		else
			$classes[] = 'format-standard';
	}

	$post_password_required = post_password_required( $post->ID );

	// Post requires password.
	if ( $post_password_required ) {
		$classes[] = 'post-password-required';
	} elseif ( ! empty( $post->post_password ) ) {
		$classes[] = 'post-password-protected';
	}

	// Post thumbnails.
	if ( current_theme_supports( 'post-thumbnails' ) && has_post_thumbnail( $post->ID ) && ! is_attachment( $post ) && ! $post_password_required ) {
		$classes[] = 'has-post-thumbnail';
	}

	// sticky for Sticky Posts
	if ( is_sticky( $post->ID ) ) {
		if ( is_home() && ! is_paged() ) {
			$classes[] = 'sticky';
		} elseif ( is_admin() ) {
			$classes[] = 'status-sticky';
		}
	}

	// hentry for hAtom compliance
	$classes[] = 'hentry';

	// All public taxonomies
	$taxonomies = get_taxonomies( array( 'public' => true ) );
	foreach ( (array) $taxonomies as $taxonomy ) {
		if ( is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
			foreach ( (array) get_the_terms( $post->ID, $taxonomy ) as $term ) {
				if ( empty( $term->slug ) ) {
					continue;
				}

				$term_class = sanitize_html_class( $term->slug, $term->term_id );
				if ( is_numeric( $term_class ) || ! trim( $term_class, '-' ) ) {
					$term_class = $term->term_id;
				}

				// 'post_tag' uses the 'tag' prefix for backward compatibility.
				if ( 'post_tag' == $taxonomy ) {
					$classes[] = 'tag-' . $term_class;
				} else {
					$classes[] = sanitize_html_class( $taxonomy . '-' . $term_class, $taxonomy . '-' . $term->term_id );
				}
			}
		}
	}

	$classes = array_map( 'esc_attr', $classes );

	/**
	 * Filters the list of CSS classes for the current post.
	 *
	 * @since 2.7.0
	 *
	 * @param array $classes An array of post classes.
	 * @param array $class   An array of additional classes added to the post.
	 * @param int   $post_id The post ID.
	 */
	$classes = apply_filters( 'post_class', $classes, $class, $post->ID );

	return array_unique( $classes );
}

/**
 * Display the classes for the body element.
 *
 * @since 2.8.0
 *
 * @param string|array $class One or more classes to add to the class list.
 */
function body_class( $class = '' ) {
	// Separates classes with a single space, collates classes for body element
	echo 'class="' . join( ' ', get_body_class( $class ) ) . '"';
}

/**
 * Retrieve the classes for the body element as an array.
 *
 * @since 2.8.0
 *
 * @global WP_Query $wp_query
 *
 * @param string|array $class One or more classes to add to the class list.
 * @return array Array of classes.
 */
function get_body_class( $class = '' ) {
	global $wp_query;

	$classes = array();

	if ( is_rtl() )
		$classes[] = 'rtl';

	if ( is_front_page() )
		$classes[] = 'home';
	if ( is_home() )
		$classes[] = 'blog';
	if ( is_archive() )
		$classes[] = 'archive';
	if ( is_date() )
		$classes[] = 'date';
	if ( is_search() ) {
		$classes[] = 'search';
		$classes[] = $wp_query->posts ? 'search-results' : 'search-no-results';
	}
	if ( is_paged() )
		$classes[] = 'paged';
	if ( is_attachment() )
		$classes[] = 'attachment';
	if ( is_404() )
		$classes[] = 'error404';

	if ( is_singular() ) {
		$post_id = $wp_query->get_queried_object_id();
		$post = $wp_query->get_queried_object();
		$post_type = $post->post_type;

		if ( is_page_template() ) {
			$classes[] = "{$post_type}-template";

			$template_slug  = get_page_template_slug( $post_id );
			$template_parts = explode( '/', $template_slug );

			foreach ( $template_parts as $part ) {
				$classes[] = "{$post_type}-template-" . sanitize_html_class( str_replace( array( '.', '/' ), '-', basename( $part, '.php' ) ) );
			}
			$classes[] = "{$post_type}-template-" . sanitize_html_class( str_replace( '.', '-', $template_slug ) );
		} else {
			$classes[] = "{$post_type}-template-default";
		}

		if ( is_single() ) {
			$classes[] = 'single';
			if ( isset( $post->post_type ) ) {
				$classes[] = 'single-' . sanitize_html_class( $post->post_type, $post_id );
				$classes[] = 'postid-' . $post_id;

				// Post Format
				if ( post_type_supports( $post->post_type, 'post-formats' ) ) {
					$post_format = get_post_format( $post->ID );

					if ( $post_format && !is_wp_error($post_format) )
						$classes[] = 'single-format-' . sanitize_html_class( $post_format );
					else
						$classes[] = 'single-format-standard';
				}
			}
		}

		if ( is_attachment() ) {
			$mime_type = get_post_mime_type($post_id);
			$mime_prefix = array( 'application/', 'image/', 'text/', 'audio/', 'video/', 'music/' );
			$classes[] = 'attachmentid-' . $post_id;
			$classes[] = 'attachment-' . str_replace( $mime_prefix, '', $mime_type );
		} elseif ( is_page() ) {
			$classes[] = 'page';

			$page_id = $wp_query->get_queried_object_id();

			$post = get_post($page_id);

			$classes[] = 'page-id-' . $page_id;

			if ( get_pages( array( 'parent' => $page_id, 'number' => 1 ) ) ) {
				$classes[] = 'page-parent';
			}

			if ( $post->post_parent ) {
				$classes[] = 'page-child';
				$classes[] = 'parent-pageid-' . $post->post_parent;
			}
		}
	} elseif ( is_archive() ) {
		if ( is_post_type_archive() ) {
			$classes[] = 'post-type-archive';
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) )
				$post_type = reset( $post_type );
			$classes[] = 'post-type-archive-' . sanitize_html_class( $post_type );
		} elseif ( is_author() ) {
			$author = $wp_query->get_queried_object();
			$classes[] = 'author';
			if ( isset( $author->user_nicename ) ) {
				$classes[] = 'author-' . sanitize_html_class( $author->user_nicename, $author->ID );
				$classes[] = 'author-' . $author->ID;
			}
		} elseif ( is_category() ) {
			$cat = $wp_query->get_queried_object();
			$classes[] = 'category';
			if ( isset( $cat->term_id ) ) {
				$cat_class = sanitize_html_class( $cat->slug, $cat->term_id );
				if ( is_numeric( $cat_class ) || ! trim( $cat_class, '-' ) ) {
					$cat_class = $cat->term_id;
				}

				$classes[] = 'category-' . $cat_class;
				$classes[] = 'category-' . $cat->term_id;
			}
		} elseif ( is_tag() ) {
			$tag = $wp_query->get_queried_object();
			$classes[] = 'tag';
			if ( isset( $tag->term_id ) ) {
				$tag_class = sanitize_html_class( $tag->slug, $tag->term_id );
				if ( is_numeric( $tag_class ) || ! trim( $tag_class, '-' ) ) {
					$tag_class = $tag->term_id;
				}

				$classes[] = 'tag-' . $tag_class;
				$classes[] = 'tag-' . $tag->term_id;
			}
		} elseif ( is_tax() ) {
			$term = $wp_query->get_queried_object();
			if ( isset( $term->term_id ) ) {
				$term_class = sanitize_html_class( $term->slug, $term->term_id );
				if ( is_numeric( $term_class ) || ! trim( $term_class, '-' ) ) {
					$term_class = $term->term_id;
				}

				$classes[] = 'tax-' . sanitize_html_class( $term->taxonomy );
				$classes[] = 'term-' . $term_class;
				$classes[] = 'term-' . $term->term_id;
			}
		}
	}

	if ( is_user_logged_in() )
		$classes[] = 'logged-in';

	if ( is_admin_bar_showing() ) {
		$classes[] = 'admin-bar';
		$classes[] = 'no-customize-support';
	}

	if ( get_background_color() !== get_theme_support( 'custom-background', 'default-color' ) || get_background_image() )
		$classes[] = 'custom-background';

	if ( has_custom_logo() ) {
		$classes[] = 'wp-custom-logo';
	}

	$page = $wp_query->get( 'page' );

	if ( ! $page || $page < 2 )
		$page = $wp_query->get( 'paged' );

	if ( $page && $page > 1 && ! is_404() ) {
		$classes[] = 'paged-' . $page;

		if ( is_single() )
			$classes[] = 'single-paged-' . $page;
		elseif ( is_page() )