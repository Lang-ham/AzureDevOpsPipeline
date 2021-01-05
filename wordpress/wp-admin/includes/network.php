<?php
/**
 * WordPress Network Administration API.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 4.4.0
 */

/**
 * Check for an existing network.
 *
 * @since 3.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return Whether a network exists.
 */
function network_domain_check() {
	global $wpdb;

	$sql = $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $wpdb->site ) );
	if ( $wpdb->get_var( $sql ) ) {
		return $wpdb->get_var( "SELECT domain FROM $wpdb->site ORDER BY id ASC LIMIT 1" );
	}
	return false;
}

/**
 * Allow subdomain installation
 *
 * @since 3.0.0
 * @return bool Whether subdomain installation is allowed
 */
function allow_subdomain_install() {
	$domain = preg_replace( '|https?://([^/]+)|', '$1', get_option( 'home' ) );
	if ( parse_url( get_option( 'home' ), PHP_URL_PATH ) || 'localhost' == $domain || preg_match( '|^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$|', $domain ) )
		return false;

	return true;
}

/**
 * Allow subdirectory installation.
 *
 * @since 3.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @return bool Whether subdirectory installation is allowed
 */
function allow_subdirectory_install() {
	global $wpdb;
        /**
         * Filters whether to enable the subdirectory installation feature in Multisite.
         *
         * @since 3.0.0
         *
         * @param bool $allow Whether to enable the subdirectory installation feature in Multisite. Default is false.
         */
	if ( apply_filters( 'allow_subdirectory_install', false ) )
		return true;

	if ( defined( 'ALLOW_SUBDIRECTORY_INSTALL' ) && ALLOW_SUBDIRECTORY_INSTALL )
		return true;

	$post = $wpdb->get_row( "SELECT ID FROM $wpdb->posts WHERE post_date < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND post_status = 'publish'" );
	if ( empty( $post ) )
		return true;

	return false;
}

/**
 * Get base domain of network.
 *
 * @since 3.0.0
 * @return string Base domain.
 */
function get_clean_basedomain() {
	if ( $existing_domain = network_domain_check() )
		return $existing_domain;
	$domain = preg_replace( '|https?://|', '', get_option( 'siteurl' ) );
	if ( $slash = strpos( $domain, '/' ) )
		$domain = substr( $domain, 0, $slash );
	return $domain;
}

/**
 * Prints step 1 for Network installation process.
 *
 * @todo Realistically, step 1 should be a welcome screen explaining what a Network is and such. Navigating to Tools > Network
 * 	should not be a sudden "Welcome to a new install process! Fill this out and click here." See also contextual help todo.
 *
 * @since 3.0.0
 *
 * @global bool $is_apache
 *
 * @param WP_Error $errors
 */
function network_step1( $errors = false ) {
	global $is_apache;

	if ( defined('DO_NOT_UPGRADE_GLOBAL_TABLES') ) {
		echo '<div class="error"><p><strong>' . __( 'ERROR:' ) . '</strong> ' . sprintf(
			/* translators: %s: DO_NOT_UPGRADE_GLOBAL_TABLES */
			__( 'The constant %s cannot be defined when creating a network.' ),
			'<code>DO_NOT_UPGRADE_GLOBAL_TABLES</code>'
		) . '</p></div>';
		echo '</div>';
		include( ABSPATH . 'wp-admin/admin-footer.php' );
		die();
	}

	$active_plugins = get_option( 'active_plugins' );
	if ( ! empty( $active_plugins ) ) {
		echo '<div class="updated"><p><strong>' . __( 'Warning:' ) . '</strong> ' . sprintf(
			/* translators: %s: Plugins screen URL */
			__( 'Please <a href="%s">deactivate your plugins</a> before enabling the Network feature.' ),
			admin_url( 'plugins.php?plugin_status=active' )
		) . '</p></div>';
		echo '<p>' . __( 'Once the network is created, you may reactivate your plugins.' ) . '</p>';
		echo '</div>';
		include( ABSPATH . 'wp-admin/admin-footer.php' );
		die();
	}

	$hostname = get_clean_basedomain();
	$has_ports = strstr( $hostname, ':' );
	if ( ( false !== $has_ports && ! in_array( $has_ports, array( ':80', ':443' ) ) ) ) {
		echo '<div class="error"><p><strong>' . __( 'ERROR:' ) . '</strong> ' . __( 'You cannot install a network of sites with your server address.' ) . '</p></div>';
		echo '<p>' . sprintf(
			/* translators: %s: port number */
			__( 'You cannot use port numbers such as %s.' ),
			'<code>' . $has_ports . '</code>'
		) . '</p>';
		echo '<a href="' . esc_url( admin_url() ) . '">' . __( 'Return to Dashboard' ) . '</a>';
		echo '</div>';
		include( ABSPATH . 'wp-admin/admin-footer.php' );
		die();
	}

	echo '<form method="post">';

	wp_nonce_field( 'install-network-1' );

	$error_codes = array();
	if ( is_wp_error( $errors ) ) {
		echo '<div class="error"><p><strong>' . __( 'ERROR: The network could not be created.' ) . '</strong></p>';
		foreach ( $errors->get_error_messages() as $error )
			echo "<p>$error</p>";
		echo '</div>';
		$error_codes = $errors->get_error_codes();
	}

	if ( ! empty( $_POST['sitename'] ) && ! in_array( 'empty_sitename', $error_codes ) ) {
		$site_name = $_POST['sitename'];
	} else {
		/* translators: %s: Default network name */
		$site_name = sprintf( __( '%s Sites' ), get_option( 'blogname' ) );
	}

	if ( ! empty( $_POST['email'] ) && ! in_array( 'invalid_email', $error_codes ) ) {
		$admin_email = $_POST['email'];
	} else {
		$admin_email = get_option( 'admin_email' );
	}
	?>
	<p><?php _e( 'Welcome to the Network installation process!' ); ?></p>
	<p><?php _e( 'Fill in the information below and you&#8217;ll be on your way to creating a network of WordPress sites. We will create configuration files in the next step.' ); ?></p>
	<?php

	if ( isset( $_POST['subdomain_install'] ) ) {
		$subdomain_install = (bool) $_POST['subdomain_install'];
	} elseif ( apache_mod_loaded('mod_rewrite') ) { // assume nothing
		$subdomain_install = true;
	} elseif ( !allow_subdirectory_install() ) {
		$subdomain_install = true;
	} else {
		$subdomain_install = false;
		if ( $got_mod_rewrite = got_mod_rewrite() ) { // dangerous assumptions
			echo '<div class="updated inline"><p><strong>' . __( 'Note:' ) . '</strong> ';
			/* translators: %s: mod_rewrite */
			printf( __( 'Please make sure the Apache %s module is installed as it will be used at the end of this installation.' ),
				'<code>mod_rewrite</code>'
			);
			echo '</p>';
		} elseif ( $is_apache ) {
			echo '<div class="error inline"><p><strong>' . __( 'Warning:' ) . '</strong> ';
			/* translators: %s: mod_rewrite */
			printf( __( 'It looks like the Apache %s module is not installed.' ),
				'<code>mod_rewrite</code>'
			);
			echo '</p>';
		}

		if ( $got_mod_rewrite || $is_apache ) { // Protect against mod_rewrite mimicry (but ! Apache)
			echo '<p>';
			/* translators: 1: mod_rewrite, 2: mod_rewrite documentation URL, 3: Google search for mod_rewrite */
			printf( __( 'If %1$s is disabled, ask your administrator to enable that module, or look at the <a href="%2$s">Apache documentation</a> or <a href="%3$s">elsewhere</a> for help setting it up.' ),
				'<code>mod_rewrite</code>',
				'https://httpd.apache.org/docs/mod/mod_rewrite.html',
				'https://www.google.com/search?q=apache+mod_rewrite'
			);
			echo '</p></div>';
		}
	}

	if ( allow_subdomain_install() && allow_subdirectory_install() ) : ?>
		<h3><?php esc_html_e( 'Addresses of Sites in your Network' ); ?></h3>
		<p><?php _e( 'Please choose whether you would like sites in your WordPress network to use sub-domains or sub-directories.' ); ?>
			<strong><?php _e( 'You cannot change this later.' ); ?></strong></p>
		<p><?php _e( 'You will need a wildcard DNS record if you are going to use the virtual host (sub-domain) functionality.' ); ?></p>
		<?php // @todo: Link to an MS readme? ?>
		<table class="form-table">
			<tr>
				<th><label><input type="radio" name="subdomain_install" value="1"<?php checked( $subdomain_install ); ?> /> <?php _e( 'Sub-domains' ); ?></label></th>
				<td><?php printf(
					/* translators: 1: hostname */
					_x( 'like <code>site1.%1$s</code> and <code>site2.%1$s</code>', 'subdomain examples' ),
					$hostname
				); ?></td>
			</tr>
			<tr>
				<th><label><input type="radio" name="subdomain_install" value="0"<?php checked( ! $subdomain_install ); ?> /> <?php _e( 'Sub-directories' ); ?></label></th>
				<td><?php printf(
					/* translators: 1: hostname */
					_x( 'like <code>%1$s/site1</code> and <code>%1$s/site2</code>', 'subdirectory examples' ),
					$hostname
				); ?></td>
			</tr>
		</table>

<?php
	endif;

		if ( WP_CONTENT_DIR != ABSPATH . 'wp-content' && ( allow_subdirectory_install() || ! allow_subdomain_install() ) )
			echo '<div class="error inline"><p><strong>' . __( 'Warning:' ) . '</strong> ' . __( 'Subdirectory networks may not be fully compatible with custom wp-content directories.' ) . '</p></div>';

		$is_www = ( 0 === strpos( $hostname, 'www.' ) );
		if ( $is_www ) :
		?>
		<h3><?php esc_html_e( 'Server Address' ); ?></h3>
		<p><?php printf(
			/* translators: 1: site url 2: host name 3. www */
			__( 'We recommend you change your siteurl to %1$s before enabling the network feature. It will still be possible to visit your site using the %3$s prefix with an address like %2$s but any links will not have the %3$s prefix.' ),
			'<code>' . substr( $hostname, 4 ) . '</code>',
			'<code>' . $hostname . '</code>',
			'<code>www</code>'
		); ?></p>
		<table class="form-table">
			<tr>
				<th scope='row'><?php esc_html_e( 'Server Address' ); ?></th>
				<td>
					<?php printf(
						/* translators: %s: host name */
						__( 'The internet address of your network will be %s.' ),
						'<code>' . $hostname . '</code>'
					); ?>
				</td>
			</tr>
		</table>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Network Details' ); ?></h3>
		<table class="form-table">
		<?php if ( 'localhost' == $hostname ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sub-directory Installati