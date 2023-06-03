<?php
/**
 * Widget API: WP_Widget_Text class
 *
 * @package WordPress
 * @subpackage Widgets
 * @since 4.4.0
 */

/**
 * Core class used to implement a Text widget.
 *
 * @since 2.8.0
 *
 * @see WP_Widget
 */
class WP_Widget_Text extends WP_Widget {

	/**
	 * Whether or not the widget has been registered yet.
	 *
	 * @since 4.8.1
	 * @var bool
	 */
	protected $registered = false;

	/**
	 * Sets up a new Text widget instance.
	 *
	 * @since 2.8.0
	 */
	public function __construct() {
		$widget_ops = array(
			'classname' => 'widget_text',
			'description' => __( 'Arbitrary text.' ),
			'customize_selective_refresh' => true,
		);
		$control_ops = array(
			'width' => 400,
			'height' => 350,
		);
		parent::__construct( 'text', __( 'Text' ), $widget_ops, $control_ops );
	}

	/**
	 * Add hooks for enqueueing assets when registering all widget instances of this widget class.
	 *
	 * @param integer $number Optional. The unique order number of this widget instance
	 *                        compared to other instances of the same class. Default -1.
	 */
	public function _register_one( $number = -1 ) {
		parent::_register_one( $number );
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;

		wp_add_inline_script( 'text-widgets', sprintf( 'wp.textWidgets.idBases.push( %s );', wp_json_encode( $this->id_base ) ) );

		if ( $this->is_preview() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_preview_scripts' ) );
		}

		// Note that the widgets component in the customizer will also do the 'admin_print_scripts-widgets.php' action in WP_Customize_Widgets::print_scripts().
		add_action( 'admin_print_scripts-widgets.php', array( $this, 'enqueue_admin_scripts' ) );

		// Note that the widgets component in the customizer will also do the 'admin_footer-widgets.php' action in WP_Customize_Widgets::print_footer_scripts().
		add_action( 'admin_footer-widgets.php', array( 'WP_Widget_Text', 'render_control_template_scripts' ) );
	}

	/**
	 * Determines whether a given instance is legacy and should bypass using TinyMCE.
	 *
	 * @since 4.8.1
	 *
	 * @param array $instance {
	 *     Instance data.
	 *
	 *     @type string      $text   Content.
	 *     @type bool|string $filter Whether autop or content filters should apply.
	 *     @type bool        $legacy Whether widget is in legacy mode.
	 * }
	 * @return bool Whether Text widget instance contains legacy data.
	 */
	public function is_legacy_instance( $instance ) {

		// Legacy mode when not in visual mode.
		if ( isset( $instance['visual'] ) ) {
			return ! $instance['visual'];
		}

		// Or, the widget has been added/updated in 4.8.0 then filter prop is 'content' and it is no longer legacy.
		if ( isset( $instance['filter'] ) && 'content' === $instance['filter'] ) {
			return false;
		}

		// If the text is empty, then nothing is preventing migration to TinyMCE.
		if ( empty( $instance['text'] ) ) {
			return false;
		}

		$wpautop = ! empty( $instance['filter'] );
		$has_line_breaks = ( false !== strpos( trim( $instance['text'] ), "\n" ) );

		// If auto-paragraphs are not enabled and there are line breaks, then ensure legacy mode.
		if ( ! $wpautop && $has_line_breaks ) {
			return true;
		}

		// If an HTML comment is present, assume legacy mode.
		if ( false !== strpos( $instance['text'], '<!--' ) ) {
			return true;
		}

		// In the rare case that DOMDocument is not available we cannot reliably sniff content and so we assume legacy.
		if ( ! class_exists( 'DOMDocument' ) ) {
			// @codeCoverageIgnoreStart
			return true;
			// @codeCoverageIgnoreEnd
		}

		$doc = new DOMDocument();
		@$doc->loadHTML( sprintf(
			'<!DOCTYPE html><html><head><meta charset="%s"></head><body>%s</body></html>',
			esc_attr( get_bloginfo( 'charset' ) ),
			$instance['text']
		) );
		$body = $doc->getElementsByTagName( 'body' )->item( 0 );

		// See $allowedposttags.
		$safe_elements_attributes = array(
			'strong' => array(),
			'em' => array(),
			'b' => array(),
			'i' => array(),
			'u' => array(),
			's' => array(),
			'ul' => array(),
			'ol' => array(),
			'li' => array(),
			'hr' => array(),
			'abbr' => array(),
			'acronym' => array(),
			'code' => array(),
			'dfn' => array(),
			'a' => array(
				'href' => true,
			),
			'img' => array(
				'src' => true,
				'alt' => true,
			),
		);
		$safe_empty_elements = array( 'img', 'hr', 'iframe' );

		foreach ( $body->getElementsByTagName( '*' ) as $element ) {
			/** @var DOMElement $element */
			$tag_name = strtolower( $element->nodeName );

			// If the element is not safe, then the instance is legacy.
			if ( ! isset( $safe_elements_attributes[ $tag_name ] ) ) {
				return true;
			}

			// If the element is not safely empty and it has empty contents, then legacy mode.
			if ( ! in_array( $tag_name, $safe_empty_elements, true ) && '' === trim( $element->textContent ) ) {
				return true;
			}

			// If an attribute is not recognized as safe, then the instance is legacy.
			foreach ( $element->attributes as $attribute ) {
				/** @var DOMAttr $attribute */
				$attribute_name = strtolower( $attribute->nodeName );

				if ( ! isset( $safe_elements_attributes[ $tag_name ][ $attribute_name ] ) ) {
					return true;
				}
			}
		}

		// Otherwise, the text contains no elements/attributes that TinyMCE could drop, and therefore the widget does not need legacy mode.
		return false;
	}

	/**
	 * Filter gallery shortcode attributes.
	 *
	 * Prevents all of a site's attachments from being shown in a gallery displayed on a
	 * non-singular template where a $post context is not available.
	 *
	 * @since 4.9.0
	 *
	 * @param array $attrs Attributes.
	 * @return array Attributes.
	 */
	public function _filter_gallery_shortcode_attrs( $attrs ) {
		if ( ! is_singular() && empty( $attrs['id'] ) && empty( $attrs['include'] ) ) {
			$attrs['id'] = -1;
		}
		return $attrs;
	}

	/**
	 * Outputs the content for the current Text widget instance.
	 *
	 * @since 2.8.0
	 *
	 * @global WP_Post $post
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current Text widget instance.
	 */
	public function widget( $args, $instance ) {
		global $post;

		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$text = ! empty( $instance['text'] ) ? $instance['text'] : '';
		$is_visual_text_widget = ( ! empty( $instance['visual'] ) && ! empty( $instance['filter'] ) );

		// In 4.8.0 only, visual Text widgets get filter=content, without visual prop; upgrade instance props just-in-time.
		if ( ! $is_visual_text_widget ) {
			$is_visual_text_widget = ( isset( $instance['filter'] ) && 'content' === $instance['filter'] );
		}
		if ( $is_visual_text_widget ) {
			$instance['filter'] = true;
			$instance['visual'] = true;
		}

		/*
		 * Suspend legacy plugin-supplied do_shortcode() for 'widget_text' filter for the visual Text widget to prevent
		 * shortcodes being processed twice. Now do_shortcode() is added to the 'widget_text_content' filter in core itself
		 * and it applies after wpautop() to prevent corrupting HTML output added by the shortcode. When do_shortcode() is
		 * added to 'widget_text_content' then do_shortcode() will be manually called when in legacy mode as well.
		 */
		$widget_text_do_shortcode_priority = has_filter( 'widget_text', 'do_shortcode' );
		$should_suspend_legacy_shortcode_support = ( $is_visual_text_widget && false !== $widget_text_do_shortcode_priority );
		if ( $should_suspend_legacy_shortcode_support ) {
			remove_filter( 'widget_text', 'do_shortcode', $widget_text_do_shortcode_priority );
		}

		// Override global $post so filters (and shortcodes) apply in a consistent context.
		$original_post = $post;
		if ( is_singular() ) {
			// Make sure post is always the queried object on singular queries (not from another sub-query that failed to clean up the global $post).
			$post = get_queried_object();
		} else {
			// Nullify the $post global during widget rendering to prevent shortcodes from running with the unexpected context on archive queries.
			$post = null;
		}

		// Prevent dumping out all attachments from the media library.
		add_filter( 'shortcode_atts_gallery', array( $this, '_filter_gallery_shortcode_attrs' ) );

		/**
		 * Filters the content of the Text widget.
		 *
		 * @since 2.3.0
		 * @since 4.4.0 Added the `$this` parameter.
		 * @since 4.8.1 The `$this` param may now be a `WP_Widget_Custom_HTML` object in addition to a `WP_Widget_Text` object.
		 *
		 * @param string                               $text     The widget content.
		 * @param array                                $instance Array of settings for the current widget.
		 * @param WP_Widget_Text|WP_Widget_Custom_HTML $this     Current Text widget instance.
		 */
		$text = apply_filters( 'widget_text', $text, $instance, $this );

		if ( $is_visual_text_widget ) {

			/**
			 * Filters the content of the Text widget to apply changes expected from the visual (TinyMCE) editor.
			 *
			 * By default a subset of the_content filters are applied, including wpautop and wptexturize.
			 *
			 * @since 4.8.0
			 *
			 * @param stri