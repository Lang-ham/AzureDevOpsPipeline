/* global tinymce */
tinymce.PluginManager.add('wpgallery', function( editor ) {

	function replaceGalleryShortcodes( content ) {
		return content.replace( /\[gallery([^\]]*)\]/g, function( match ) {
			return html( 'wp-gallery', match );
		});
	}

	function html( cls, data ) {
		data = window.encodeURIComponent( data );
		return '<img src="' + tinymce.Env.transparentSrc + '" class="wp-media mceItem ' + cls + '" ' +
			'data-wp-media="' + data + '" data-mce-resize="false" data-mce-placeholder="1" alt="" />';
	}

	function restoreMediaShortcodes( content ) {
		function getAttr( str, name ) {
			name = new RegExp( name + '=\"([^\"]+)\"' ).exec( str );
			return name ? window.decodeURIComponent( name[1] ) : '';
		}

		return content.replace( /(?:<p(?: [^>]+)?>)*(<img [^>]+>)(?:<\/p>)*/g, function( match, image ) {
			var data = getAttr( image, 'data-wp-media' );

			if ( data ) {
				return '<p>' + data + '</p>';
			}

			return match;
		});
	}

	function editMedia( node ) {
		var gallery, frame, data;

		if ( node.nodeName !== 'IMG' ) {
			return;
		}

		// Check if the `wp.media` API exists.
		if ( typeof wp === 'undefined' || ! wp.media ) {
			return;
		}

		data = window.decodeURIComponent( editor.dom.getAttrib( node, 'data-wp-media' ) );

		// Make sure we've selected a gallery node.
		if ( editor.dom.hasClass( node, 'wp-gallery' ) && wp.media.gallery ) {
			gallery = wp.media.gallery;
			frame = gallery.edit( data );

			frame.state('gallery-edit').on( 'update', function( selection ) {
				var shortcode = gallery.shortcode( selection ).string();
				editor.dom.setAttrib( node, 'data-wp-media', window.encodeURIComponent( shortcode ) );
				frame.detach();
			});
		}
	}

	// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('...');
	editor.addCommand( 'WP_Gallery', function() {
		editMedia( editor.selection.getNode() );
	});

	editor.on( 'mouseup', function( event ) {
		var dom = editor.dom,
			node = event.target;

		function unselect() {
			dom.removeClass( dom.select( 'img.wp-media-selected' ), 'wp-media-selected' );
		}

		if ( node.nodeName === 'IMG' && dom.getAttrib( node, 'data-wp-media' )