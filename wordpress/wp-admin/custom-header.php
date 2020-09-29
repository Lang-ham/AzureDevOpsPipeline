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
		$('#display-header-tex