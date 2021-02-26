<?php
/**
 * WordPress Widgets Administration API
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Display list of the available widgets.
 *
 * @since 2.5.0
 *
 * @global array $wp_registered_widgets
 * @global array $wp_registered_widget_controls
 */
function wp_list_widgets() {
	global $wp_registered_widgets, $wp_registered_widget_controls;

	$sort = $wp_registered_widgets;
	usort( $sort, '_sort_name_callback' );
	$done = array();

	foreach ( $sort as $widget ) {
		if ( in_array( $widget['callback'], $done, true ) ) // We already showed this multi-widget
			continue;

		$sidebar = is_active_widget( $widget['callback'], $widget['id'], false, false );
		$done[] = $widget['callback'];

		if ( ! isset( $widget['params'][0] ) )
			$widget['params'][0] = array();

		$args = array( 'widget_id' => $widget['id'], 'widget_name' => $widget['name'], '_display' => 'template' );

		if ( isset($wp_registered_widget_controls[$widget['id']]['id_base']) && isset($widget['params'][0]['number']) ) {
			$id_base = $wp_registered_widget_controls[$widget['id']]['id_base'];
			$args['_temp_id'] = "$id_base-__i__";
			$args['_multi_num'] = next_widget_id_number($id_base);
			$args['_add'] = 'multi';
		} else {
			$args['_add'] = 'single';
			if ( $sidebar )
				$args['_hide'] = '1';
		}

		$args = wp_list_widget_controls_dynamic_sidebar( array( 0 => $args, 1 => $widget['params'][0] ) );
		call_user_func_array( 'wp_widget_control', $args );
	}
}

/**
 * Callback to sort array by a 'name' key.
 *
 * @since 3.1.0
 * @access private
 *
 * @return int
 */
function _sort_name_callback( $a, $b ) {
	return strnatcasecmp( $a['name'], $b['name'] );
}

/**
 * Show the widgets and their settings for a sidebar.
 * Used in the admin widget config screen.
 *
 * @since 2.5.0
 *
 * @param string $sidebar      Sidebar ID.
 * @param string $sidebar_name Optional. Sidebar name. Default empty.
 */
function wp_list_widget_controls( $sidebar, $sidebar_name = '' ) {
	add_filter( 'dynamic_sidebar_params', 'wp_list_widget_controls_dynamic_sidebar' );

	$description = wp_sidebar_description( $sidebar );

	echo '<div id="' . esc_attr( $sidebar ) . '" class="widgets-sortables">';

	if ( $sidebar_name ) {
		?>
		<div class="sidebar-name">
			<button type="button" class="handlediv hide-if-no-js" aria-expanded="true">
				<span class="screen-reader-text"><?php echo esc_html( $sidebar_name ); ?></span>
				<span class="toggle-indicator" aria-hidden="true"></span>
			</button>
			<h2><?php echo esc_html( $sidebar_name ); ?> <span class="spinner"></span></h2>
		</div>
		<?php
	}

	if ( ! empty( $description ) ) {
		?>
		<div class="sidebar-description">
			<p class="description"><?php echo $description; ?></p>
		</div>
		<?php
	}

	dynamic_sidebar( $sidebar );

	echo '</div>';
}

/**
 * Retrieves the widget control arguments.
 *
 * @since 2.5.0
 *
 * @global array $wp_registered_widgets
 *
 * @staticvar int $i
 *
 * @param array $params
 * @return array
 */
function wp_list_widget_controls_dynamic_sidebar( $params ) {
	global $wp_registered_widgets;
	static $i = 0;
	$i++;

	$widget_id = $params[0]['widget_id'];
	$id = isset($params[0]['_temp_id']) ? $params[0]['_temp_id'] : $widget_id;
	$hidden = isset($params[0]['_hide']) ? ' style="display:none;"' : '';

	$params[0]['before_widget'] = "<div id='widget-{$i}_{$id}' class='widget'$hidden>";
	$params[0]['after_widget'] = "</div>";
	$params[0]['before_title'] = "%BEG_OF_TITLE%"; // deprecated
	$params[0]['after_title'] = "%END_OF_TITLE%"; // deprecated
	if ( is_callable( $wp_registered_widgets[$widget_id]['callback'] ) ) {
		$wp_registered_widgets[$widget_id]['_callback'] = $wp_registered_widgets[$widget_id]['callback'];
		$wp_registered_widgets[$widget_id]['callback'] = 'wp_widget_control';
	}

	return $params;
}

/**
 *
 * @global array $wp_registered_widgets
 *
 * @param string $id_base
 * @return int
 */
function next_widget_id_number( $id_base ) {
	global $wp_registered_widgets;
	$number = 1;

	foreach ( $wp_registered_widgets as $widget_id => $widget ) {
		if ( preg_match( '/' . $id_base . '-([0-9]+)$/', $widget_id, $matches ) )
			$number = max($number, $matches[1]);
	}
	$number++;

	return $number;
}

/**
 * Meta widget used to display the control form for a widget.
 *
 * Called from dynamic_sidebar().
 *
 * @since 2.5.0
 *
 * @global array $wp_registered_widgets
 * @global array $wp_registered_widget_controls
 * @global array $sidebars_widgets
 *
 * @param array $sidebar_args
 * @return array
 */
function wp_widget_control( $sidebar_args ) {
	global $wp_registered_widgets, $wp_registered_widget_controls, $sidebars_widgets;

	$widget_id = $sidebar_args['widget_id'];
	$sidebar_id = isset($sidebar_args['id']) ? $sidebar_args['id'] : false;
	$key = $sidebar_id ? array_search( $widget_id, $sidebars_widgets[$sidebar_id] ) : '-1'; // position of widget in sidebar
	$control = isset($wp_registered_widget_controls[$widget_id]) ? $wp_registered_widget_controls[$widget_id] : array();
	$widget = $wp_registered_widgets[$widget_id];

	$id_format = $widget['id'];
	$widget_number = isset($control['params'][0]['number']) ? $control['params'][0]['number'] : '';
	$id_base = isset($control['id_base']) ? $control['id_base'] : $widget_id;
	$multi_number = isset($sidebar_args['_multi_num']) ? $sidebar_args['_multi_num'] : '';
	$add_new = isset($sidebar_args['_add']) ? $sidebar_args['_add'] : '';

	$before_form = isset( $sidebar_args['before_form'] ) ? $sidebar_args['before_form'] : '<form method="post">';
	$after_form = isset( $sidebar_args['after_form'] ) ? $sidebar_args['after_form'] : '</form>';
	$before_widget_content = isset( $sidebar_args['before_widget_content'] ) ? $sidebar_args['before_widget_content'] : '<div class="widget-content">';
	$after_widget_content = isset( $sidebar_args['after_widget_content'] ) ? $sidebar_args['after_widget_content'] : '</div>';

	$query_arg = array( 'editwidge