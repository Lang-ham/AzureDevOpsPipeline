<?php
/**
 * General template tags that can go anywhere in a template.
 *
 * @package WordPress
 * @subpackage Template
 */

/**
 * Load header template.
 *
 * Includes the header template for a theme or if a name is specified then a
 * specialised header will be included.
 *
 * For the parameter, if the file is called "header-special.php" then specify
 * "special".
 *
 * @since 1.5.0
 *
 * @param string $name The name of the specialised header.
 */
function get_header( $name = null ) {
	/**
	 * Fires before the header template file is loaded.
	 *
	 * @since 2.1.0
	 * @since 2.8.0 $name parameter added.
	 *
	 * @param string|null $name Name of the specific header file to use. null for the default header.
	 */
	do_action( 'get_header', $name );

	$templates = array();
	$name = (string) $name;
	if ( '' !== $name ) {
		$templates[] = "header-{$name}.php";
	}

	$templates[] = 'header.php';

	locate_template( $templates, true );
}

/**
 * Load footer template.
 *
 * Includes the footer template for a theme or if a name is specified then a
 * specialised footer will be included.
 *
 * For the parameter, if the file is called "footer-special.php" then specify
 * "special".
 *
 * @since 1.5.0
 *
 * @param string $name The name of the specialised footer.
 */
function get_footer( $name = null ) {
	/**
	 * Fires before the footer template file is loaded.
	 *
	 * @since 2.1.0
	 * @since 2.8.0 $name parameter added.
	 *
	 * @param string|null $name Name of the specific footer file to use. null for the default footer.
	 */
	do_action( 'get_footer', $name );

	$templates = array();
	$name = (string) $name;
	if ( '' !== $name ) {
		$templates[] = "footer-{$name}.php";
	}

	$templates[]    = 'footer.php';

	locate_template( $templates, true );
}

/**
 * Load sidebar template.
 *
 * Includes the sidebar template for a theme or if a name is specified then a
 * specialised sidebar will be included.
 *
 * For the parameter, if the file is called "sidebar-special.php" then specify
 * "special".
 *
 * @since 1.5.0
 *
 * @param string $name The name of the specialised sidebar.
 */
function get_sidebar( $name = null ) {
	/**
	 * Fires before the sidebar template file is loaded.
	 *
	 * @since 2.2.0
	 * @since 2.8.0 $name parameter added.
	 *
	 * @param string|null $name Name of the specific sidebar file to use. null for the default sidebar.
	 */
	do_action( 'get_sidebar', $name );

	$templates = array();
	$name = (string) $name;
	if ( '' !== $name )
		$templates[] = "sidebar-{$name}.php";

	$templates[] = 'sidebar.php';

	locate_template( $templates, true );
}

/**
 * Loads a template part into a template.
 *
 * Provides a simple mechanism for child themes to overload reusable sections of code
 * in the theme.
 *
 * Includes the named template part for a theme or if a name is specified then a
 * specialised part will be included. If the theme contains no {slug}.php file
 * then no template will be included.
 *
 * The template is included using require, not require_once, so you may include the
 * same template part multiple times.
 *
 * For the $name parameter, if the file is called "{slug}-special.php" then specify
 * "special".
 *
 * @since 3.0.0
 *
 * @param string $slug The slug name for the generic template.
 * @param string $name The name of the specialised template.
 */
function get_template_part( $slug, $name = null ) {
	/**
	 * Fires before the specified template part file is loaded.
	 *
	 * The dynamic portion of the hook name, `$slug`, refers to the slug name
	 * for the generic template part.
	 *
	 * @since 3.0.0
	 *
	 * @param string      $slug The slug name for the generic template.
	 * @param string|null $name The name of the specialized template.
	 */
	do_action( "get_template_part_{$slug}", $slug, $name );

	$templates = array();
	$name = (string) $name;
	if ( '' !== $name )
		$templates[] = "{$slug}-{$name}.php";

	$templates[] = "{$slug}.php";

	locate_template($templates, true, false);
}

/**
 * Display search form.
 *
 * Will first attempt to locate the searchform.php file in either the child or
 * the parent, then load it. If it doesn't exist, then the default search form
 * will be displayed. The default search form is HTML, which will be displayed.
 * There is a filter applied to the search form HTML in order to edit or replace
 * it. The filter is {@see 'get_search_form'}.
 *
 * This function is primarily used by themes which want to hardcode the search
 * form into the sidebar and also by the search widget in WordPress.
 *
 * There is also an action that is called whenever the function is run called,
 * {@see 'pre_get_search_form'}. This can be useful for outputting JavaScript that the
 * search relies on or various formatting that applies to the beginning of the
 * search. To give a few examples of what it can be used for.
 *
 * @since 2.7.0
 *
 * @param bool $echo Default to echo and not return the form.
 * @return string|void String when $echo is false.
 */
function get_search_form( $echo = true ) {
	/**
	 * Fires before the search form is retrieved, at the start of get_search_form().
	 *
	 * @since 2.7.0 as 'get_search_form' action.
	 * @since 3.6.0
	 *
	 * @link https://core.trac.wordpress.org/ticket/19321
	 */
	do_action( 'pre_get_search_form' );

	$format = current_theme_supports( 'html5', 'search-form' ) ? 'html5' : 'xhtml';

	/**
	 * Filters the HTML format of the search form.
	 *
	 * @since 3.6.0
	 *
	 * @param string $format The type of markup to use in the search form.
	 *                       Accepts 'html5', 'xhtml'.
	 */
	$format = apply_filters( 'search_form_format', $format );

	$search_form_template = locate_template( 'searchform.php' );
	if ( '' != $search_form_template ) {
		ob_start();
		require( $search_form_template );
		$form = ob_get_clean();
	} else {
		if ( 'html5' == $format ) {
			$form = '<form role="search" method="get" class="search-form" action="' . esc_url( home_url( '/' ) ) . '">
				<label>
					<span class="screen-reader-text">' . _x( 'Search for:', 'label' ) . '</span>
					<input type="search" class="search-field" placeholder="' . esc_attr_x( 'Search &hellip;', 'placeholder' ) . '" value="' . get_search_query() . '" name="s" />
				</label>
				<input type="submit" class="search-submit" value="'. esc_attr_x( 'Search', 'submit button' ) .'" />
			</form>';
		} else {
			$form = '<form role="search" method="get" id="searchform" class="searchform" action="' . esc_url( home_url( '/' ) ) . '">
				<div>
					<label class="screen-reader-text" for="s">' . _x( 'Search for:', 'label' ) . '</label>
					<input type="text" value="' . get_search_query() . '" name="s" id="s" />
					<input type="submit" id="searchsubmit" value="'. esc_attr_x( 'Search', 'submit button' ) .'" />
				</div>
			</form>';
		}
	}

	/**
	 * Filters the HTML output of the search form.
	 *
	 * @since 2.7.0
	 *
	 * @param string $form The search form HTML output.
	 */
	$result = apply_filters( 'get_search_form', $form );

	if ( null === $result )
		$result = $form;

	if ( $echo )
		echo $result;
	else
		return $result;
}

/**
 * Display the Log In/Out link.
 *
 * Displays a link, which allows users to navigate to the Log In page to log in
 * or log out depending on whether they are currently logged in.
 *
 * @since 1.5.0
 *
 * @param string $redirect Optional path to redirect to on login/logout.
 * @param bool   $echo     Default to echo and not return the link.
 * @return string|void String when retrieving.
 */
function wp_loginout($redirect = '', $echo = true) {
	if ( ! is_user_logged_in() )
		$link = '<a href="' . esc_url( wp_login_url($redirect) ) . '">' . __('Log in') . '</a>';
	else
		$link = '<a href="' . esc_url( wp_logout_url($redirect) ) . '">' . __('Log out') . '</a>';

	if ( $echo ) {
		/**
		 * Filters the HTML output for the Log In/Log Out link.
		 *
		 * @since 1.5.0
		 *
		 * @param string $link The HTML link content.
		 */
		echo apply_filters( 'loginout', $link );
	} else {
		/** This filter is documented in wp-includes/general-template.php */
		return apply_filters( 'loginout', $link );
	}
}

/**
 * Retrieves the logout URL.
 *
 * Returns the URL that allows the user to log out of the site.
 *
 * @since 2.7.0
 *
 * @param string $redirect Path to redirect to on logout.
 * @return string The logout URL. Note: HTML-encoded via esc_html() in wp_nonce_url().
 */
function wp_logout_url($redirect = '') {
	$args = array( 'action' => 'logout' );
	if ( !empty($redirect) ) {
		$args['redirect_to'] = urlencode( $redirect );
	}

	$logout_url = add_query_arg($args, site_url('wp-login.php', 'login'));
	$logout_url = wp_nonce_url( $logout_url, 'log-out' );

	/**
	 * Filters the logout URL.
	 *
	 * @since 2.8.0
	 *
	 * @param string $logout_url The HTML-encoded logout URL.
	 * @param string $redirect   Path to redirect to on logout.
	 */
	return apply_filters( 'logout_url', $logout_url, $redirect );
}

/**
 * Retrieves the login URL.
 *
 * @since 2.7.0
 *
 * @param string $redirect     Path to redirect to on log in.
 * @param bool   $force_reauth Whether to force reauthorization, even if a cookie is present.
 *                             Default false.
 * @return string The login URL. Not HTML-encoded.
 */
function wp_login_url($redirect = '', $force_reauth = false) {
	$login_url = site_url('wp-login.php', 'login');

	if ( !empty($redirect) )
		$login_url = add_query_arg('redirect_to', urlencode($redirect), $login_url);

	if ( $force_reauth )
		$login_url = add_query_arg('reauth', '1', $login_url);

	/**
	 * Filters the login URL.
	 *
	 * @since 2.8.0
	 * @since 4.2.0 The `$force_reauth` parameter was added.
	 *
	 * @param string $login_url    The login URL. Not HTML-encoded.
	 * @param string $redirect     The path to redirect to on login, if supplied.
	 * @param bool   $force_reauth Whether to force reauthorization, even if a cookie is present.
	 */
	return apply_filters( 'login_url', $login_url, $redirect, $force_reauth );
}

/**
 * Returns the URL that allows the user to register on the site.
 *
 * @since 3.6.0
 *
 * @return string User registration URL.
 */
function wp_registration_url() {
	/**
	 * Filters the user registration URL.
	 *
	 * @since 3.6.0
	 *
	 * @param string $register The user registration URL.
	 */
	return apply_filters( 'register_url', site_url( 'wp-login.php?action=register', 'login' ) );
}

/**
 * Provides a simple login form for use anywhere within WordPress.
 *
 * The login format HTML is echoed by default. Pass a false