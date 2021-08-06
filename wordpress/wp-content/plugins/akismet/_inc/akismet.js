jQuery( function ( $ ) {
	var mshotRemovalTimer = null;
	var mshotSecondTryTimer = null
	var mshotThirdTryTimer = null
	
	var mshotEnabledLinkSelector = 'a[id^="author_comment_url"], tr.pingback td.column-author a:first-of-type, td.comment p a';
	
	$('.akismet-status').each(function () {
		var thisId = $(this).attr('commentid');
		$(this).prependTo('#comment-' + thisId + ' .column-comment');
	});
	$('.akismet-user-comment-count').each(function () {
		var thisId = $(this).attr('commentid');
		$(this).insertAfter('#comment-' + thisId + ' .author strong:first').show();
	});

	akismet_enable_comment_author_url_removal();
	
	$( '#the-comment-list' ).on( 'click', '.akismet_remove_url', function () {
		var thisId = $(this).attr('commentid');
		var data = {
			action: 'comment_author_deurl',
			_wpnonce: WPAkismet.comment_author_url_nonce,
			id: thisId
		};
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: data,
			beforeSend: function () {
				// Removes "x" link
				$("a[commentid='"+ thisId +"']").hide();
				// Show temp status
				$("#author_comment_url_"+ thisId).html( $( '<span/>' ).text( WPAkismet.strings['Removing...'] ) );
			},
			success: function (response) {
				if (response) {
					// Show status/undo link
					$("#author_comment_url_"+ thisId)
						.attr('cid', thisId)
						.addClass('akismet_undo_link_removal')
						.html(
							$( '<span/>' ).text( WPAkismet.strings['URL removed'] )
						)
						.append( ' ' )
						.append(
							$( '<span/>' )
								.text( WPAkismet.strings['(undo)'] )
								.addClass( 'akismet-span-link' )
						);
				}
			}
		});

		return false;
	}).on( 'click', '.akismet_undo_link_removal', function () {
		var thisId = $(this).attr('cid');
		var thisUrl = $(this).attr('href');
		var data = {
			action: 'comment_author_reurl',
			_wpnonce: WPAkismet.comment_author_url_nonce,
			id: thisId,
			url: thisUrl
		};
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: data,
			beforeSend: function () {
				// Show temp status
				$("#author_comment_url_"+ thisId).html( $( '<span/>' ).text( WPAkismet.strings['Re-adding...'] ) );
			},
			success: function (response) {
				if (response) {
					// Add "x" link
					$("a[commentid='"+ thisId +"']").show();
					// Show link. Core strips leading http://, so let's do that too.
					$("#author_comment_url_"+ thisId).removeClass('akismet_undo_link_removal').text( thisUrl.replace( /^http:\/\/(www\.)?/ig, '' ) );
				}
			}
		});

		return false;
	});

	// Show a preview image of the hovered URL. Applies to author URLs and URLs inside the comments.
	$( '#the-comment-list' ).on( 'mouseover', mshotEnabledLinkSelector, function () {
		clearTimeout( mshotRemovalTimer );

		if ( $( '.akismet-mshot' ).length > 0 ) {
			if ( $( '.akismet-mshot:first' ).data( 'link' ) == this ) {
				// The preview is already showing for this link.
				return;
			}
			else {
				// A new link is being hovered, so remove the old preview.
				$( '.akismet-mshot' ).remove();
			}
		}

		clearTimeout( mshotSecondTryTimer );
		clearTimeout( mshotThirdTryTimer );

		var thisHref = $( this ).attr( 'href' );

		var mShot = $( '<div class="akismet-mshot mshot-container"><div class="mshot-arrow"></div><img src="' + akismet_mshot_url( thisHref ) + '" width="450" height="338" class="mshot-image" /></div>' );
		mShot.data( 'link', this );

		var offset = $( this ).offset();

		mShot.offset( {
			left : Math.min( $( window ).width() - 475, offset.left + $( this ).width() + 10 ), // Keep it on the screen if the link is near the edge of the window.
			top: offset.top + ( $( this ).height() / 2 ) - 101 // 101 = top offset of the arrow plus the top border thickness
		} );

		// These retries appear to be superfluous if .mshot-image has already loaded, but it's because mShots
		// can return a "Generating thumbnail..." image if it doesn't have a thumbnail ready, so we need
		// to retry to see if we can get the newly generated thumbnail.
		mshotSecondTryTimer = setTimeout( function () {
			mShot.find( '.mshot-image' ).attr( 'src', akismet_mshot_url( thisHref, 2 ) );
		}, 6000 );

		mshotThirdTryTimer = setTimeout( function () {
			mShot.fin