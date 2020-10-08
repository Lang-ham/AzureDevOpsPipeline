<?php
/**
 * The custom header image script.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * The custom header image class.
 *
 * @since 2.1.0
 */
class Custom_Image_Header {

	/**
	 * Callback for administration header.
	 *
	 * @var callable
	 * @since 2.1.0
	 */
	public $admin_header_callback;

	/**
	 * Callback for header div.
	 *
	 * @var callable
	 * @since 3.0.0
	 */
	public $admin_image_div_callback;

	/**
	 * Holds default headers.
	 *
	 * @var array
	 * @since 3.0.0
	 */
	public $default_headers = array();

	/**
	 * Used to trigger a success message when settings updated and set to true.
	 *
	 * @since 3.0.0
	 * @var bool
	 */
	private $updated;

	/**
	 * Constructor - Register administration header callback.
	 *
	 * @since 2.1.0
	 * @param callable $admin_header_callback
	 * @param callable $admin_image_div_callback Optional custom image div output callback.
	 */
	public function __construct($admin_header_callback, $admin_image_div_callback = '') {
		$this->admin_header_callback = $admin_header_callback;
		$this->admin_image_div_callback = $admin_image_div_callback;

		add_action( 'admin_menu', array( $this, 'init' ) );

		add_action( 'customize_save_after',         array( $this, 'customize_set_last_used' ) );
		add_action( 'wp_ajax_custom-header-crop',   array( $this, 'ajax_header_crop'        ) );
		add_action( 'wp_ajax_custom-header-add',    array( $this, 'ajax_header_add'         ) );
		add_action( 'wp_ajax_custom-header-remove', array( $this, 'ajax_header_remove'      ) );
	}

	/**
	 * Set up the hooks for the Custom Header admin page.
	 *
	 * @since 2.1.0
	 */
	public function init() {
		$page = add_theme_page( __( 'Header' ), __( 'Header' ), 'edit_theme_options', 'custom-header', array( $this, 'admin_page' ) );
		if ( ! $page ) {
			return;
		}

		add_action( "admin_print_scripts-$page", array( $this, 'js_includes' ) );
		add_action( "admin_print_styles-$page", array( $this, 'css_includes' ) );
		add_action( "admin_head-$page", array( $this, 'help' ) );
		add_action( "admin_head-$page", array( $this, 'take_action' ), 50 );
		add_action( "admin_head-$page", array( $this, 'js' ), 50 );
		if ( $this->admin_header_callback ) {
			add_action( "admin_head-$page", $this->admin_header_callback, 51 );
		}
	}

	/**
	 * Adds contextual help.
	 *
	 * @since 3.0.0
	 */
	public function help() {
		get_current_screen()->add_help_tab( array(
			'id'      => 'overview',
			'title'   => __('Overview'),
			'content' =>
				'<p>' . __( 'This screen is used to customize the header section of your theme.') . '</p>' .
				'<p>' . __( 'You can choose from the theme&#8217;s default header images, or use one of your own. You can also customize how your Site Title and Tagline are displayed.') . '<p>'
		) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'set-header-image',
			'title'   => __('Header Image'),
			'content' =>
				'<p>' . __( 'You can set a custom image header for your site. Simply upload the image and crop it, and the new header will go live immediately. Alternatively, you can use an image that has already been uploaded to your Media Library by clicking the &#8220;Choose Image&#8221; button.' ) . '</p>' .
				'<p>' . __( 'Some themes come with additional header images bundled. If you see multiple images displayed, select the one you&#8217;d like and click the &#8220;Save Changes&#8221; button.' ) . '</p>' .
				'<p>' . __( 'If your theme has more than one default header image, or you have uploaded more than one custom header image, you have the option of having WordPress display a randomly different image on each page of your site. Click the &#8220;Random&#8221; radio button next to the Uploaded Images or Default Images section to enable this feature.') . '</p>' .
				'<p>' . __( 'If you don&#8217;t want a header image to be displayed on your site at all, click the &#8220;Remove Header Image&#8221; button at the bottom of the Header Image section of this page. If you want to re-enable the header image later, you just have to select one of the other image options and click &#8220;Save Changes&#8221;.') . '</p>'
		) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'set-header-text',
			'title'   => __('Header Text'),
			'content' =>
				'<p>' . sprintf( __( 'For most themes, the header text is your Site Title and Tagline, as defined in the <a href="%1$s">General Settings</a> section.' ), admin_url( 'options-general.php' ) ) . '<p>' .
				'<p>' . __( 'In the Header Text section of this page, you can choose whether to display this text or hide it. You can also choose a color for the text by clicking the Select Color button and either typing in a legitimate HTML hex value, e.g. &#8220;#ff0000&#8221; for red, or by choosing a color using the color picker.' ) . '</p>' .
				'<p>' . __( 'Don&#8217;t forget to click &#8220;Save Changes&#8221; when you&#8217;re done!') . '</p>'
		) );

		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:' ) . '</strong></p>' .
			'<p>' . __( '<a href="https://codex.wordpress.org/Appearance_Header_Screen">Documentation on Custom Header</a>' ) . '</p>' .
			'<p>' . __( '<a href="https://wordpress.org/support/">Support Forums</a>' ) . '</p>'
		);
	}

	/**
	 * Get the current step.
	 *
	 * @since 2.6.0
	 *
	 * @return int Current step
	 */
	public function step() {
		if ( ! isset( $_GET['step'] ) )
			return 1;

		$step = (int) $_GET['step'];
		if ( $step < 1 || 3 < $step ||
			( 2 == $step && ! wp_verify_nonce( $_REQUEST['_wpnonce-custom-header-upload'], 'custom-header-upload' ) ) ||
			( 3 == $step && ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'custom-header-crop-image' ) )
		)
			return 1;

		return $step;
	}

	/**
	 * Set up the enqueue for the JavaScript files.
	 *
	 * @since 2.1.0
	 */
	public function js_includes() {
		$step = $this->step();

		if ( ( 1 == $step || 3 == $step ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'custom-header' );
			if ( current_theme_supports( 'custom-header', 'header-text' ) )
				wp_enqueue_script( 'wp-color-picker' );
		} elseif ( 2 == $step ) {
			wp_enqueue_script('imgareaselect');
		}
	}

	/**
	 * Set up the enqueue for the CSS files
	 *
	 * @since 2.7.0
	 */
	public function css_includes() {
		$step = $this->step();

		if ( ( 1 == $step || 3 == $step ) && current_theme_supports( 'custom-header', 'header-text' ) )
			wp_enqueue_style( 'wp-color-picker' );
		elseif ( 2 == $step )
			wp_enqueue_style('imgareaselect');
	}

	/**
	 * Execute custom header modification.
	 *
	 * @since 2.6.0
	 */
	public function take_action() {
		if ( ! current_user_can('edit_theme_options') )
			return;

		if ( empty( $_POST ) )
			return;

		$this->updated = true;

		if ( isset( $_POST['resetheader'] ) ) {
			check_admin_referer( 'custom-header-options', '_wpnonce-custom-header-options' );
			$this->reset_header_image();
			return;
		}

		if ( isset( $_POST['removeheader'] ) ) {
			check_admin_referer( 'custom-header-options', '_wpnonce-custom-header-options' );
			$this->remove_header_image();
			return;
		}

		if ( isset( $_POST['text-color'] ) && ! isset( $_POST['display-header-text'] ) ) {
			check_admin_referer( 'custom-header-options', '_wpnonce-custom-header-options' );
			set_theme_mod( 'header_textcolor', 'blank' );
		} elseif ( isset( $_POST['text-color'] ) ) {
			check_admin_referer( 'custom-header-options', '_wpnonce-custom-header-options' );
			$_POST['text-color'] = str_replace( '#', '', $_POST['text-color'] );
			$color = preg_replace('/[^0-9a-fA-F]/', '', $_POST['text-color']);
			if ( strlen($color) == 6 || strlen($color) == 3 )
				set_theme_mod('header_textcolor', $color);
			elseif ( ! $color )
				set_theme_mod( 'header_textcolor', 'blank' );
		}

		if ( isset( $_POST['default-header'] ) ) {
			check_admin_referer( 'custom-header-options', '_wpnonce-custom-header-options' );
			$this->set_header_image( $_POST['default-header'] );
			return;
		}
	}

	/**
	 * Process the default headers
	 *
	 * @since 3.0.0
	 *
	 * @global array $_wp_default_headers
	 */
	public function process_default_headers() {
		global $_wp_default_headers;

		if ( !isset($_wp_default_headers) )
			return;

		if ( ! empty( $this->default_headers ) ) {
			return;
		}

		$this->default_headers = $_wp_default_headers;
		$template_directory_uri = get_template_directory_uri();
		$stylesheet_directory_uri = get_stylesheet_directory_uri();
		foreach ( array_keys($this->default_headers) as $header ) {
			$this->default_headers[$header]['url'] =  sprintf( $this->default_headers[$header]['url'], $template_directory_uri, $stylesheet_directory_uri );
			$this->default_headers[$header]['thumbnail_url'] =  sprintf( $this->default_headers[$header]['thumbnail_url'], $template_directory_uri, $stylesheet_directory_uri );
		}
	}

	/**
	 * Display UI for selecting one of several default headers.
	 *
	 * Show the random image option if this theme has multiple header images.
	 * Random image option is on by default if no header has been set.
	 *
	 * @since 3.0.0
	 *
	 * @param string $type The header type. One of 'default' (for the Uploaded Images control)
	 *                     or 'uploaded' (for the Uploaded Images control).
	 */
	public function show_header_selector( $type = 'default' ) {
		if ( 'default' == $type ) {
			$headers = $this->default_headers;
		} else {
			$headers = get_uploaded_header_images();
			$type = 'uploaded';
		}

		if ( 1 < count( $headers ) ) {
			echo '<div class="random-header">';
			echo '<label><input name="default-header" type="radio" value="random-' . $type . '-image"' . checked( is_random_header_image( $type ), true, false ) . ' />';
			_e( '<strong>Random:</strong> Show a different image on each page.' );
			echo '</label>';
			echo '</div>';
		}

		echo '<div class="available-headers">';
		foreach ( $headers as $header_key => $header ) {
			$header_thumbnail = $header['thumbnail_url'];
			$header_url = $header['url'];
			$header_alt_text = empty( $header['alt_text'] ) ? '' : $header['alt_text'];
			echo '<div class="default-header">';
			echo '<label><input name="default-header" type="radio" value="' . esc_attr( $header_key ) . '" ' . checked( $header_url, get_theme_mod( 'header_image' ), false ) . ' />';
			$width = '';
			if ( !empty( $header['attachment_id'] ) )
				$width = ' width="230"';
			echo '<img src="' . set_url_scheme( $header_thumbnail ) . '" alt="' . esc_attr( $header_alt_text ) .'"' . $width . ' /></label>';
			echo '</div>';
		}
		echo '<div class="clear"></div></div>';
	}

	/**
	 * Execute JavaScript depending on step.
	 *
	 * @since 2.1.0
	 */
	public function js() {
		$step = $this->step();
		if ( ( 1 == $step || 3 == $step ) && current_theme_supports( 'custom-header', 'header-text' ) )
			$this->js_1();
		elseif ( 2 == $step )
			$this->js_2();
	}

	/**
	 * Display JavaScript based on Step 1 and 3.
	 *
	 * @since 2.6.0
	 */
	public function js_1() {
		$default_color = '';
		if ( current_theme_supports( 'custom-header', 'default-text-color' ) ) {
			$default_color = get_theme_support( 'custom-header', 'default-text-color' );
			if ( $default_color && false === strpos( $default_color, '#' ) ) {
				$default_color = '#' . $default_color;
			}
		}
		?>
<script type="text/javascript">
(function($){
	var default_color = '<?php echo $default_color; ?>',
		header_text_fields;

	function pickColor(color) {
		$('#name').css('color', color);
		$('#desc').css('color', color);
		$('#text-color').val(color);
	}

	function toggle_text() {
		var checked = $('#display-header-text').prop('checked'),
			text_color;
		header_text_fields.toggle( checked );
		if ( ! checked )
			return;
		text_color = $('#text-color');
		if ( '' == text_color.val().replace('#', '') ) {
			text_color.val( default_color );
			pickColor( default_color );
		} else {
			pickColor( text_color.val() );
		}
	}

	$(document).ready(function() {
		var text_color = $('#text-color');
		header_text_fields = $('.displaying-header-text');
		text_color.wpColorPicker({
			change: function( event, ui ) {
				pickColor( text_color.wpColorPicker('color') );
			},
			clear: function() {
				pickColor( '' );
			}
		});
		$('#display-header-text').click( toggle_text );
		<?php if ( ! display_header_text() ) : ?>
		toggle_text();
		<?php endif; ?>
	});
})(jQuery);
</script>
<?php
	}

	/**
	 * Display JavaScript based on Step 2.
	 *
	 * @since 2.6.0
	 */
	public function js_2() { ?>
<script type="text/javascript">
	function onEndCrop( coords ) {
		jQuery( '#x1' ).val(coords.x);
		jQuery( '#y1' ).val(coords.y);
		jQuery( '#width' ).val(coords.w);
		jQuery( '#height' ).val(coords.h);
	}

	jQuery(document).ready(function() {
		var xinit = <?php echo absint( get_theme_support( 'custom-header', 'width' ) ); ?>;
		var yinit = <?php echo absint( get_theme_support( 'custom-header', 'height' ) ); ?>;
		var ratio = xinit / yinit;
		var ximg = jQuery('img#upload').width();
		var yimg = jQuery('img#upload').height();

		if ( yimg < yinit || ximg < xinit ) {
			if ( ximg / yimg > ratio ) {
				yinit = yimg;
				xinit = yinit * ratio;
			} else {
				xinit = ximg;
				yinit = xinit / ratio;
			}
		}

		jQuery('img#upload').imgAreaSelect({
			handles: true,
			keys: true,
			show: true,
			x1: 0,
			y1: 0,
			x2: xinit,
			y2: yinit,
			<?php
			if ( ! current_theme_supports( 'custom-header', 'flex-height' ) && ! current_theme_supports( 'custom-header', 'flex-width' ) ) {
			?>
			aspectRatio: xinit + ':' + yinit,
			<?php
			}
			if ( ! current_theme_supports( 'custom-header', 'flex-height' ) ) {
			?>
			maxHeight: <?php echo get_theme_support( 'custom-header', 'height' ); ?>,
			<?php
			}
			if ( ! current_theme_supports( 'custom-header', 'flex-width' ) ) {
			?>
			maxWidth: <?php echo get_theme_support( 'custom-header', 'width' ); ?>,
			<?php
			}
			?>
			onInit: function () {
				jQuery('#width').val(xinit);
				jQuery('#height').val(yinit);
			},
			onSelectChange: function(img, c) {
				jQuery('#x1').val(c.x1);
				jQuery('#y1').val(c.y1);
				jQuery('#width').val(c.width);
				jQuery('#height').val(c.height);
			}
		});
	});
</script>
<?php
	}

	/**
	 * Display first step of custom header image page.
	 *
	 * @since 2.1.0
	 */
	public function step_1() {
		$this->process_default_headers();
?>

<div class="wrap">
<h1><?php _e( 'Custom Header' ); ?></h1>

<?php if ( current_user_can( 'customize' ) ) { ?>
<div class="notice notice-info hide-if-no-customize">
	<p>
		<?php
		printf(
			__( 'You can now manage and live-preview Custom Header in the <a href="%1$s">Customizer</a>.' ),
			admin_url( 'customize.php?autofocus[control]=header_image' )
		);
		?>
	</p>
</div>
<?php } ?>

<?php if ( ! empty( $this->updated ) ) { ?>
<div id="message" class="updated">
<p><?php printf( __( 'Header updated. <a href="%s">Visit your site</a> to see how it looks.' ), home_url( '/' ) ); ?></p>
</div>
<?php } ?>

<h3><?php _e( 'Header Image' ); ?></h3>

<table class="form-table">
<tbody>

<?php if ( get_custom_header() || display_header_text() ) : ?>
<tr>
<th scope="row"><?php _e( 'Preview' ); ?></th>
<td>
	<?php
	if ( $this->admin_image_div_callback ) {
		call_user_func( $this->admin_image_div_callback );
	} else {
		$custom_header = get_custom_header();
		$header_image = get_header_image();

		if ( $header_image ) {
			$header_image_style = 'background-image:url(' . esc_url( $header_image ) . ');';
		}  else {
			$header_image_style = '';
		}

		if ( $custom_header->width )
			$header_image_style .= 'max-width:' . $custom_header->width . 'px;';
		if ( $custom_header->height )
			$header_image_style .= 'height:' . $custom_header->height . 'px;';
	?>
	<div id="headimg" style="<?php echo $header_image_style; ?>">
		<?php
		if ( display_header_text() )
			$style = ' style="color:#' . get_header_textcolor() . ';"';
		else
			$style = ' style="display:none;"';
		?>
		<h1><a id="name" class="displaying-header-text" <?php echo $style; ?> onclick="return false;" href="<?php bloginfo('url'); ?>" tabindex="-1"><?php bloginfo( 'name' ); ?></a></h1>
		<div id="desc" class="displaying-header-text" <?php echo $style; ?>><?php bloginfo( 'description' ); ?></div>
	</div>
	<?php } ?>
</td>
</tr>
<?php endif; ?>

<?php if ( current_user_can( 'upload_files' ) && current_theme_supports( 'custom-header', 'uploads' ) ) : ?>
<tr>
<th scope="row"><?php _e( 'Select Image' ); ?></th>
<td>
	<p><?php _e( 'You can select an image to be shown at the top of your site by uploading from your computer or choosing from your media library. After selecting an image you will be able to crop it.' ); ?><br />
	<?php
	if ( ! current_theme_supports( 'custom-header', 'flex-height' ) && ! current_theme_supports( 'custom-header', 'flex-width' ) ) {
		printf( __( 'Images of exactly <strong>%1$d &times; %2$d pixels</strong> will be used as-is.' ) . '<br />', get_theme_support( 'custom-header', 'width' ), get_theme_support( 'custom-header', 'height' ) );
	} elseif ( current_theme_supports( 'custom-header', 'flex-height' ) ) {
		if ( ! current_theme_supports( 'custom-header', 'flex-width' ) )
			printf(
				/* translators: %s: size in pixels */
				__( 'Images should be at least %s wide.' ) . ' ',
				sprintf(
					/* translators: %d: custom header width */
					'<strong>' . __( '%d pixels' ) . '</strong>',
					get_theme_support( 'custom-header', 'width' )
				)
			);
	} elseif ( current_theme_supports( 'custom-header', 'flex-width' ) ) {
		if ( ! current_theme_supports( 'custom-header', 'flex-height' ) )
			printf(
				/* translators: %s: size in pixels */
				__( 'Images should be at least %s tall.' ) . ' ',
				sprintf(
					/* translators: %d: custom header height */
					'<strong>' . __( '%d pixels' ) . '</strong>',
					get_theme_support( 'custom-header', 'height' )
				)
			);
	}
	if ( current_theme_supports( 'custom-header', 'flex-height' ) || current_theme_supports( 'custom-header', 'flex-width' ) ) {
		if ( current_theme_supports( 'custom-header', 'width' ) )
			printf(
				/* translators: %s: size in pixels */
				__( 'Suggested width is %s.' ) . ' ',
				sprintf(
					/* translators: %d: custom header width */
					'<strong>' . __( '%d pixels' ) . '</strong>',
					get_theme_support( 'custom-header', 'width' )
				)
			);
		if ( current_theme_supports( 'custom-header', 'height' ) )
			printf(
				/* translators: %s: size in pixels */
				__( 'Suggested height is %s.' ) . ' ',
				sprintf(
					/* translators: %d: custom header height */
					'<strong>' . __( '%d pixels' ) . '</strong>',
					get_theme_support( 'custom-header', 'height' )
				)
			);
	}
	?></p>
	<form enctype="multipart/form-data" id="upload-form" class="wp-upload-form" method="post" action="<?php echo esc_url( add_query_arg( 'step', 2 ) ) ?>">
	<p>
		<label for="upload"><?php _e( 'Choose an image from your computer:' ); ?></label><br />
		<input type="file" id="upload" name="import" />
		<input type="hidden" name="action" value="save" />
		<?php wp_nonce_field( 'custom-header-upload', '_wpnonce-custom-header-upload' ); ?>
		<?php submit_button( __( 'Upload' ), '', 'submit', false ); ?>
	</p>
	<?php
		$modal_update_href = esc_url( add_query_arg( array(
			'page' => 'custom-header',
			'step' => 2,
			'_wpnonce-custom-header-upload' => wp_create_nonce('custom-header-upload'),
		), admin_url('themes.php') ) );
	?>
	<p>
		<label for="choose-from-library-link"><?php _e( 'Or choose an image from your media library:' ); ?></label><br />
		<button id="choose-from-library-link" class="button"
			data-update-link="<?php echo esc_attr( $modal_update_href ); ?>"
			data-choose="<?php esc_attr_e( 'Choose a Custom Header' ); ?>"
			data-update="<?php esc_attr_e( 'Set as header' ); ?>"><?php _e( 'Choose Image' ); ?></button>
	</p>
	</form>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>

<form method="post" action="<?php echo esc_url( add_query_arg( 'step', 1 ) ) ?>">
<?php submit_button( null, 'screen-reader-text', 'save-header-options', false ); ?>
<table class="form-table">
<tbody>
	<?php if ( get_uploaded_header_images() ) : ?>
<tr>
<th scope="row"><?php _e( 'Uploaded Images' ); ?></th>
<td>
	<p><?php _e( 'You can choose one of your previously uploaded headers, or show a random one.' ) ?></p>
	<?php
		$this->show_header_selector( 'uploaded' );
	?>
</td>
</tr>
	<?php endif;
	if ( ! empty( $this->default_headers ) ) : ?>
<tr>
<th scope="row"><?php _e( 'Default Images' ); ?></th>
<td>
<?php if ( current_theme_supports( 'custom-header', 'uploads' ) ) : ?>
	<p><?php _e( 'If you don&lsquo;t want to upload your own image, you can use one of these cool headers, or show a random one.' ) ?></p>
<?php else: ?>
	<p><?php _e( 'You can use one of these cool headers or show a random one on each page.' ) ?></p>
<?php endif; ?>
	<?php
		$this->show_header_selector( 'default' );
	?>
</td>
</tr>
	<?php endif;
	if ( get_header_image() ) : ?>
<tr>
<th scope="row"><?php _e( 'Remove Image' ); ?></th>
<td>
	<p><?php _e( 'This will remove the header image. You will not be able to restore any customizations.' ) ?></p>
	<?php submit_button( __( 'Remove Header Image' ), '', 'removeheader', false ); ?>
</td>
</tr>
	<?php endif;

	$default_image = sprintf( get_theme_support( 'custom-header', 'default-image' ), get_template_directory_uri(), get_stylesheet_directory_uri() );
	if ( $default_image && get_header_image() != $default_image ) : ?>
<tr>
<th scope="row"><?php _e( 'Reset Image' ); ?></th>
<td>
	<p><?php _e( 'This will restore the original header image. You will not be able to restore any customizations.' ) ?></p>
	<?php submit_button( __( 'Restore Original Header Image' ), '', 'resetheader', false ); ?>
</td>
</tr>
	<?php endif; ?>
</tbody>
</table>

<?php if ( current_theme_supports( 'custom-header', 'header-text' ) ) : ?>

<h3><?php _e( 'Header Text' ); ?></h3>

<table class="form-table">
<tbody>
<tr>
<th scope="row"><?php _e( 'Header Text' ); ?></th>
<td>
	<p>
	<label><input type="checkbox" name="display-header-text" id="display-header-text"<?php checked( display_header_text() ); ?> /> <?php _e( 'Show header text with your image.' ); ?></label>
	</p>
</td>
</tr>

<tr class="displaying-header-text">
<th scope="row"><?php _e( 'Text Color' ); ?></th>
<td>
	<p>
	<?php
	$default_color = '';
	if ( current_theme_supports( 'custom-header', 'default-text-color' ) ) {
		$default_color = get_theme_support( 'custom-header', 'default-text-color' );
		if ( $default_color && false === strpos( $default_color, '#' ) ) {
			$default_color = '#' . $default_color;
		}
	}

	$default_color_attr = $default_color ? ' data-default-color="' . esc_attr( $default_color ) . '"' : '';

	$header_textcolor = display_header_text() ? get_header_textcolor() : get_theme_support( 'custom-header', 'default-text-color' );
	if ( $header_textcolor && false === strpos( $header_textcolor, '#' ) ) {
		$header_textcolor = '#' . $header_textcolor;
	}

	echo '<input type="text" name="text-color" id="text-color" value="' . esc_attr( $header_textcolor ) 