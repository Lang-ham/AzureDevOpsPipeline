<?php
/**
 * Twenty Seventeen: Color Patterns
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since 1.0
 */

/**
 * Generate the CSS for the current custom color scheme.
 */
function twentyseventeen_custom_colors_css() {
	$hue = absint( get_theme_mod( 'colorscheme_hue', 250 ) );

	/**
	 * Filter Twenty Seventeen default saturation level.
	 *
	 * @since Twenty Seventeen 1.0
	 *
	 * @param int $saturation Color saturation level.
	 */
	$saturation = absint( apply_filters( 'twentyseventeen_custom_colors_saturation', 50 ) );
	$reduced_saturation = ( .8 * $saturation ) . '%';
	$saturation = $saturation . '%';
	$css = '
/**
 * Twenty Seventeen: Color Patterns
 *
 * Colors are ordered from dark to light.
 */

.colors-custom a:hover,
.colors-custom a:active,
.colors-custom .entry-content a:focus,
.colors-custom .entry-content a:hover,
.colors-custom .entry-summary a:focus,
.colors-custom .entry-summary a:hover,
.colors-custom .widget a:focus,
.colors-custom .widget a:hover,
.colors-custom .site-footer .widget-area a:focus,
.colors-custom .site-footer .widget-area a:hover,
.colors-custom .posts-navigation a:focus,
.colors-custom .posts-navigation a:hover,
.colors-custom .comment-metadata a:focus,
.colors-custom .comment-metadata a:hover,
.colors-custom .comment-metadata a.comment-edit-link:focus,
.colors-custom .comment-metadata a.comment-edit-link:hover,
.colors-custom .comment-reply-link:focus,
.colors-custom .comment-reply-link:hover,
.colors-custom .widget_authors a:focus strong,
.colors-custom .widget_authors a:hover strong,
.colors-custom .entry-title a:focus,
.colors-custom .entry-title a:hover,
.colors-custom .entry-meta a:focus,
.colors-custom .entry-meta a:hover,
.colors-custom.blog .entry-meta a.post-edit-link:focus,
.colors-custom.blog .entry-meta a.post-edit-link:hover,
.colors-custom.archive .entry-meta a.post-edit-link:focus,
.colors-custom.archive .entry-meta a.post-edit-link:hover,
.colors-custom.search .entry-meta a.post-edit-link:focus,
.colors-custom.search .entry-meta a.post-edit-link:hover,
.colors-custom .page-links a:focus .page-number,
.colors-custom .page-links a:hover .page-number,
.colors-custom .entry-footer a:focus,
.colors-custom .entry-footer a:hover,
.colors-custom .entry-footer .cat-links a:focus,
.colors-custom .entry-footer .cat-links a:hover,
.colors-custom .entry-footer .tags-links a:focus,
.colors-custom .entry-footer .tags-links a:hover,
.colors-custom .post-navigation a:focus,
.colors-custom .post-navigation a:hover,
.colors-custom .pagination a:not(.prev):not(.next):focus,
.colors-custom .pagination a:not(.prev):not(.next):hover,
.colors-custom .comments-pagination a:not(.prev):not(.next):focus,
.colors-custom .comments-pagination a:not(.prev):not(.next):hover,
.colors-custom .logged-in-as a:focus,
.colors-custom .logged-in-as a:hover,
.colors-custom a:focus .nav-title,
.colors-custom a:hover .nav-title,
.colors-custom .edit-link a:focus,
.colors-custom .edit-link a:hover,
.colors-custom .site-info a:focus,
.colors-custom .site-info a:hover,
.colors-custom .widget .widget-title a:focus,
.colors-custom .widget .widget-title a:hover,
.colors-custom .widget ul li a:focus,
.colors-custom .widget ul li a:hover {
	color: hsl( ' . $hue . ', ' . $saturation . ', 0% ); /* base: #000; */
}

.colors-custom .entry-content a,
.colors-custom .entry-summary a,
.colors-custom .widget a,
.colors-custom .site-footer .widget-area a,
.colors-custom .posts-navigation a,
.colors-custom .widget_authors a strong {
	-webkit-box-shadow: inset 0 -1px 0 hsl( ' . $hue . ', ' . $saturation . ', 6% ); /* base: rgba(15, 15, 15, 1); */
	box-shadow: inset 0 -1px 0 hsl( ' . $hue . ', ' . $saturation . ', 6% ); /* base: rgba(15, 15, 15, 1); */
}

.colors-custom button,
.colors-custom input[type="button"],
.colors-custom input[type="submit"],
.colors-custom .entry-footer .edit-link a.post-edit-link {
	background-color: hsl( ' . $hue . ', ' . $saturation . ', 13% ); /* base: #222; */
}

.colors-custom input[type="text"]:focus,
.colors-custom input[type="email"]:focus,
.colors-custom input[type="url"]:focus,
.colors-custom input[type="password"]:focus,
.colors-custom input[type="search"]:focus,
.colors-custom input[type="number"]:focus,
.colors-custom input[type="tel"]:focus,
.colors-custom input[type="range"]:focus,
.colors-custom input[type="date"]:focus,
.colors-custom input[type="month"]:focus,
.colors-custom input[type="week"]:focus,
.colors-custom input[type="time"]:focus,
.colors-custom input[type="datetime"]:focus,
.colors-custom .colors-custom input[type="datetime-local"]:focus,
.colors-custom input[type="color"]:focus,
.colors-custom textarea:focus,
.colors-custom button.secondary,
.colors-custom input[type="reset"],
.colors-custom input[type="button"].secondary,
.colors-custom input[type="reset"].secondary,
.colors-custom input[type="submit"].secondary,
.colors-custom a,
.colors-custom .site-title,
.colors-custom .site-title a,
.colors-custom .navigation-top a,
.colors-custom .dropdown-toggle,
.colors-custom .menu-toggle,
.colors-custom .page .panel-content .entry-title,
.colors-custom .page-title,
.colors-custom.page:not(.twentyseventeen-front-page) .entry-title,
.colors-custom .page-links a .page-number,
.colors-custom .comment-metadata a.comment-edit-link,
.colors-custom .comment-reply-link .icon,
.colors-custom h2.widget-title,
.colors-custom mark,
.colors-custom .post-navigation a:focus .icon,
.colors-custom .post-navigation a:hover .icon,
.colors-custom .site-content .site-content-light,
.colors-custom .twentyseventeen-panel .recent-posts .entry-header .edit-link {
	color: hsl( ' . $hue . ', ' . $saturation . ', 13% ); /* base: #222; */
}

.colors-custom .entry-content a:focus,
.colors-custom .entry-content a:hover,
.colors-custom .entry-summary a:focus,
.colors-custom .entry-summary a:hover,
.colors-custom .widget a:focus,
.colors-custom .widget a:hover,
.colors-custom .site-footer .widget-area a:focus,
.colors-custom .site-footer .widget-area a:hover,
.colors-custom .posts-navigation a:focus,
.colors-custom .posts-navigation a:hover,
.colors-custom .comment-metadata a:focus,
.colors-custom .comment-metadata a:hover,
.colors-custom .comment-metadata a.comment-edit-link:focus,
.colors-custom .comment-metadata a.comment-edit-link:hover,
.colors-custom .comment-reply-link:focus,
.colors-custom .comment-reply-link:hover,
.colors-custom .widget_authors a:focus strong,
.colors-custom .widget_authors a:hover strong,
.colors-custom .entry-title a:focus,
.colors-custom .entry-title a:hover,
.colors-custom .entry-meta a:focus,
.colors-custom .entry-meta a:hover,
.colors-custom.blog .entry-meta a.post-edit-link:focus,
.colors-custom.blog .entry-meta a.post-edit-link:hover,
.colors-custom.archive .entry-meta a.post-edit-link:focus,
.colors-custom.archive .entry-meta a.post-edit-link:hover,
.colors-custom.search .entry-meta a.post-edit-link:focus,
.colors-custom.search .entry-meta a.post-edit-link:hover,
.colors-custom .page-links a:focus .page-number,
.colors-custom .page-links a:hover .page-number,
.colors-custom .entry-footer .cat-links a:focus,
.colors-custom .entry-footer .cat-links a:hover,
.colors-custom .entry-footer .tags-links a:focus,
.colors-custom .entry-footer .tags-links a:hover,
.colors-custom .post-navigation a:focus,
.colors-custom .post-navigation a:hover,
.colors-custom .pagination a:not(.prev):not(.next):focus,
.colors-custom .pagination a:not(.prev):not(.next):hover,
.colors-custom .comments-pagination a:not(.prev):not(.next):focus,
.colors-custom .comments-pagination a:not(.prev):not(.next):hover,
.colors-custom .logged-in-as a:focus,
.colors-custom .logged-in-as a:hover,
.colors-custom a:focus .nav-title,
.colors-custom a:hover .nav-title,
.colors-custom .edit-link a:focus,
.colors-custom .edit-link a:hover,
.colors-custom .site-info a:focus,
.colors-custom .site-info a:hover,
.colors-custom .widget .widget-title a:focus,
.colors-custom .widget .widget-title a:hover,
.colors-custom .widget ul li a:focus,
.colors-custom .widget ul li a:hover {
	-webkit-box-shadow: inset 0 0 0 hsl( ' . $hue . ', ' . $saturation . ', 13% ), 0 3px 0 hsl( ' . $hue . ', ' . $saturation . ', 13% );
	box-shadow: inset 0 0 0 hsl( ' . $hue . ', ' . $saturation . ' , 13% ), 0 3px 0 hsl( ' . $hue . ', ' . $saturation . ', 13% );
}

body.colors-custom,
.colors-custom button,
.colors-custom input,
.colors-custom select,
.colors-custom textarea,
.colors-custom h3,
.colors-custom h4,
.colors-custom h6,
.colors-custom label,
.colors-custom .entry-title a,
.colors-custom.twentyseventeen-front-page .panel-content .recent-posts article,
.colors-custom .entry-footer .cat-links a,
.colors-custom .entry-footer .tags-links a,
.colors-custom .format-quote blockquote,
.colors-custom .nav-title,
.colors-custom .comment-body,
.colors-custom .site-content .wp-playlist-light .wp-playlist-current-item .wp-playlist-item-album {
	color: hsl( ' . $hue . ', ' . $reduced_saturation . ', 20% ); /* base: #333; */
}

.colors-custom .social-navigation a:hover,
.colors-custom .social-navigation a:focus {
	background: hsl( ' . $hue . ', ' . $reduced_saturation . ', 20% ); /* base: #333; */
}

.colors-custom input[type="text"]:focus,
.colors-custom input[type="email"]:focus,
.colors-custom input[type="url"]:focus,
.colors-custom input[type="password"]:focus,
.colors-custom input[type="search"]:focus,
.colors-custom input[type="number"]:focus,
.colors-custom input[type="tel"]:focus,
.colors-custom input[type="range"]:focus,
.colors-custom input[type="date"]:focus,
.colors-custom input[type="month"]:focus,
.colors-custom input[type="week"]:focus,
.colors-custom input[type="time"]:focus,
.colors-custom input[type="datetime"]:focus,
.colors-custom input[type="datetime-local"]:focus,
.colors-custom input[type="color"]:focus,
.colors-custom textarea:focus,
.bypostauthor > .comment-body > .comment-meta > .comment-author .avatar {
	border-color: hsl( ' . $hue . ', ' . $reduced_saturation . ', 20% ); /* base: #333; */
}

.colors-custom h2,
.colors-custom blockquote,
.colors-custom input[type="text"],
.colors-custom input[type="email"],
.colors-custom input[type="url"],
.colors-custom input[type="password"],
.colors-custom input[type="search"],
.colors-custom input[type="number"],
.colors-custom input[type="tel"],
.colors-custom input[type="range"],
.colors-custom input[type="date"],
.colors-custom input[type="month"],
.colors-custom input[type="week"],
.colors-custom input[type="time"],
.colors-custom input[type="datetime"],
.colors-custom input[type="datetime-local"],
.colors-custom input[type="color"],
.colors-custom textarea,
.colors-custom .site-description,
.colors-custom .entry-content blockquote.alignleft,
.colors-custom .entry-content blockquote.alignright,
.colors-custom .colors-custom .taxonomy-description,
.colors-custom .site-info a,
.colors-custom .wp-caption,
.colors-custom .gallery-caption {
	color: hsl( ' . $hue . ', ' . $saturation . ', 40% ); /* base: #666; */
}

.colors-custom abbr,
.colors-custom acronym {
	border-bottom-color: hsl( ' . $hue . ', ' . $saturation . ', 40% ); /* base: #666; */
}

.colors-custom h5,
.colors-custom .entry-meta,
.colors-custom .entry-meta a,
.colors-custom.blog .entry-meta a.post-edit-link,
.colors-custom.archive .entry-meta a.post-edit-link,
.colors-custom.search .entry-meta a.post-edit-link,
.colors-custom .nav-subtitle,
.colors-custom .comment-metadata,
.colors-custom .comment-metadata a,
.colors-custom .no-comments,
.colors-custom .comment-awaiting-moderation,
.colors-custom .page-numbers.current,
.colors-custom .page-links .page-number,
.colors-custom .navigation-top .current-menu-item > a,
.colors-custom .navigation-top .current_page_item > a,
.colors-custom .main-navigation a:hover,
.colors-custom .site-content .wp-playlist-light .wp-playlist-current-item .wp-playlist-item-artist {
	color: hsl( ' . $hue . ', ' . $saturation . ', 46% ); /* base: #767676; */
}

.colors-custom button:hover,
.colors-custom button:focus,
.colors-custom input[type="button"]:hover,
.colors-custom input[type="button"]:focus,
.colors-custom input[type="submit"]:hover,
.colors-custom input[type="submit"]:focus,
.colors-custom .entry-footer .edit-link a.post-edit-link:hover,
.colors-custom .entry-footer .edit-link a.post-edit-link:focus,
.colors-custom .social-navigation a,
.colors-custom .prev.page-numbers:focus,
.colors-custom .prev.page-numbers:hover,
.colors-custom .next.page-numbers:focus,
.colors-custom .next.page-numbers:hover,
.colors-custom .site-content .wp-playlist-light .wp-playlist-item:hover,
.colors-custom .site-content .wp-playlist-light .wp-playlist-item:focus {
	background: hsl( ' . esc_attr( $hue ) . ', ' . esc_attr( $saturation ) . ', 46% ); /* base: #767676; */
}

.colors-custom button.secondary:hover,
.colors-custom button.secondary:focus,
.colors-custom input[type="reset"]:hover,
.colors-custom input[type="reset"]:focus,
.colors-custom input[type="button"].secondary:hover,
.colors-custom input[type="button"].secondary:focus,
.colors-custom input[type="reset"].secondary:hover,
.colors-custom input[type="reset"].secondary:focus,
.colors-custom input[type="submit"].secondary:hover,
.colors-custom input[type="submit"].secondary:focus,
.colors-custom hr {
	background: hsl( ' . $hue . ', ' . $saturation . ', 73% ); /* base: #bbb; */
}

.colors-custom input[type="text"],
.colors-custom input[type="email"],
.colors-custom input[type="url"],
.colors-custom input[type="password"],
.colors-custom input[type="search"],
.colors-custom input[type="number"],
.colors-custom input[type="tel"],
.colors-custom input[type="range"],
.colors-custom input[type="