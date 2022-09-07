/* global jQuery, JSON, _customizePartialRefreshExports, console */

/** @namespace wp.customize.selectiveRefresh */
wp.customize.selectiveRefresh = ( function( $, api ) {
	'use strict';
	var self, Partial, Placement;

	self = {
		ready: $.Deferred(),
		editShortcutVisibility: new api.Value(),
		data: {
			partials: {},
			renderQueryVar: '',
			l10n: {
				shiftClickToEdit: ''
			}
		},
		currentRequest: null
	};

	_.extend( self, api.Events );

	/**
	 * A Customizer Partial.
	 *
	 * A partial provides a rendering of one or more settings according to a template.
	 *
	 * @memberOf wp.customize.selectiveRefresh
	 *
	 * @see PHP class WP_Customize_Partial.
	 *
	 * @class
	 * @augments wp.customize.Class
	 * @since 4.5.0
	 */
	Partial = self.Partial = api.Class.extend(/** @lends wp.customize.SelectiveRefresh.Partial.prototype */{

		id: null,

		/**
		 * Default params.
		 *
		 * @since 4.9.0
		 * @var {object}
		 */
		defaults: {
			selector: null,
			primarySetting: null,
			containerInclusive: false,
			fallbackRefresh: true // Note this needs to be false in a front-end editing context.
		},

		/**
		 * Constructor.
		 *
		 * @since 4.5.0
		 *
		 * @param {string} id                      - Unique identifier for the partial instance.
		 * @param {object} options                 - Options hash for the partial instance.
		 * @param {string} options.type            - Type of partial (e.g. nav_menu, widget, etc)
		 * @param {string} options.selector        - jQuery selector to find the container element in the page.
		 * @param {array}  options.settings        - The IDs for the settings the partial relates to.
		 * @param {string} options.primarySetting  - The ID for the primary setting the partial renders.
		 * @param {bool}   options.fallbackRefresh - Whether to refresh the entire preview in case of a partial refresh failure.
		 * @param {object} [options.params]        - Deprecated wrapper for the above properties.
		 */
		initialize: function( id, options ) {
			var partial = this;
			options = options || {};
			partial.id = id;

			partial.params = _.extend(
				{
					settings: []
				},
				partial.defaults,
				options.params || options
			);

			partial.deferred = {};
			partial.deferred.ready = $.Deferred();

			partial.deferred.ready.done( function() {
				partial.ready();
			} );
		},

		/**
		 * Set up the partial.
		 *
		 * @since 4.5.0
		 */
		ready: function() {
			var partial = this;
			_.each( partial.placements(), function( placement ) {
				$( placement.container ).attr( 'title', self.data.l10n.shiftClickToEdit );
				partial.createEditShortcutForPlacement( placement );
			} );
			$( document ).on( 'click', partial.params.selector, function( e ) {
				if ( ! e.shiftKey ) {
					return;
				}
				e.preventDefault();
				_.each( partial.placements(), function( placement ) {
					if ( $( placement.container ).is( e.currentTarget ) ) {
						partial.showControl();
					}
				} );
			} );
		},

		/**
		 * Create and show the edit shortcut for a given partial placement container.
		 *
		 * @since 4.7.0
		 * @access public
		 *
		 * @param {Placement} placement The placement container element.
		 * @returns {void}
		 */
		createEditShortcutForPlacement: function( placement ) {
			var partial = this, $shortcut, $placementContainer, illegalAncestorSelector, illegalContainerSelector;
			if ( ! placement.container ) {
				return;
			}
			$placementContainer = $( placement.container );
			illegalAncestorSelector = 'head';
			illegalContainerSelector = 'area, audio, base, bdi, bdo, br, button, canvas, col, colgroup, command, datalist, embed, head, hr, html, iframe, img, input, keygen, label, link, map, math, menu, meta, noscript, object, optgroup, option, param, progress, rp, rt, ruby, script, select, source, style, svg, table, tbody, textarea, tfoot, thead, title, tr, track, video, wbr';
			if ( ! $placementContainer.length || $placementContainer.is( illegalContainerSelector ) || $placementContainer.closest( illegalAncestorSelector ).length ) {
				return;
			}
			$shortcut = partial.createEditShortcut();
			$shortcut.on( 'click', function( event ) {
				event.preventDefault();
				event.stopPropagation();
				partial.showControl();
			} );
			partial.addEditShortcutToPlacement( placement, $shortcut );
		},

		/**
		 * Add an edit shortcut to the placement container.
		 *
		 * @since 4.7.0
		 * @access public
		 *
		 * @param {Placement} placement The placement for the partial.
		 * @param {jQuery} $editShortcut The shortcut element as a jQuery object.
		 * @returns {void}
		 */
		addEditShortcutToPlacement: function( placement, $editShortcut ) {
			var $placementContainer = $( placement.container );
			$placementContainer.prepend( $editShortcut );
			if ( ! $placementContainer.is( ':visible' ) || 'none' === $placementContainer.css( 'display' ) ) {
				$editShortcut.addClass( 'customize-partial-edit-shortcut-hidden' );
			}
		},

		/**
		 * Return the unique class name for the edit shortcut button for this partial.
		 *
		 * @since 4.7.0
		 * @access public
		 *
		 * @return {string} Partial ID converted into a class name for use in shortcut.
		 */
		getEditShortcutClassName: function() {
			var partial = this, cleanId;
			cleanId = partial.id.replace( /]/g, '' ).replace( /\[/g, '-' );
			return 'customize-partial-edit-shortcut-' + cleanId;
		},

		/**
		 * Return the appropriate translated string for the edit shortcut button.
		 *
		 * @since 4.7.0
		 * @access public
		 *
		 * @return {string} Tooltip for edit shortcut.
		 */
		getEditShortcutTitle: function() {
			var partial = this, l10n = self.data.l10n;
			switch ( partial.getType() ) {
				case 'widget':
					return l10n.clickEditWidget;
				case 'blogname':
					return l10n.clickEditTitle;
				case 'blogdescription':
					return l10n.clickEditTitle;
				case 'nav_menu':
					return l10n.clickEditMenu;
				default:
					return l10n.clickEditMisc;
			}
		},

		/**
		 * Return the type of this partial
		 *
		 * Will use `params.type` if set, but otherwise will try to infer type from settingId.
		 *
		 * @since 4.7.0
		 * @access public
		 *
		 * @return {string} Type of partial derived from type param or the related setting ID.
		 */
		getType: function() {
			var partial = this, settingId;
			settingId = partial.params.primarySetting || _.first( partial.settings() ) || 'unknown';
			if ( partial.params.type ) {
				return partial.params.type;
			}
			if ( settingId.match( /^nav_menu_instance\[/ ) ) {
				return 'nav_menu';
			}
			if ( settingId.match( /^widget_.+\[\d+]$/ ) ) {
				return 'widget';
			}
			return settingId;
		},

		/**
		 * Create an edit shortcut button for this partial.
		 *
		 * @since 4.7.0
		 * @access public
		 *
		 * @return {jQuery} The edit shortcut button element.
		 */
		createEditShortcut: function() {
			var partial = this, shortcutTitle, $buttonContainer, $button, $image;
			shortcutTitle = partial.getEditShortcutTitle();
			$buttonContainer = $( '<span>', {
				'class': 'customize-partial-edit-shortcut ' + partial.getEditShortcutClassName()
			} );
			$button = $( '<button>', {
				'aria-label': shortcutTitle,
				'title': shortcutTitle,
				'class': 'customize-partial-edit-shortcut-button'
			} );
			$image = $( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M13.89 3.39l2.71 2.72c.46.46.42 1.24.03 1.64l-8.01 8.02-5.56 1.16 1.16-5.58s7.6-7.63 7.99-8.03c.39-.39 1.22-.39 1.68.07zm-2.73 2.79l-5.59 5.61 1.11 1.11 5.54-5.65zm-2.97 8.23l5.58-5.6-1.07-1.08-5.59 5.6z"/></svg>' );
			$button.append( $image );
			$buttonContainer.append( $button );
			return $buttonContainer;
		},

		/**
		 * Find all placements for this partial int he document.
		 *
		 * @since 4.5.0
		 *
		 * @return {Array.<Placement>}
		 */
		placements: function() {
			var partial = this, selector;

			selector = partial.params.selector || '';
			if ( selector ) {
				selector += ', ';
			}
			selector += '[data-customize-partial-id="' + partial.id + '"]'; // @todo Consider injecting customize-partial-id-${id} classnames instead.

			return $( selector ).map( function() {
				var container = $( this ), context;

				context = container.data( 'customize-partial-placement-context' );
				if ( _.isString( context ) && '{' === context.substr( 0, 1 ) ) {
					throw new Error( 'context JSON parse error' );
				}

				return new Placement( {
					partial: partial,
					container: container,
					context: context
				} );
			} ).get();
		},

		/**
		 * Get list of setting IDs related to this partial.
		 *
		 * @since 4.5.0
		 *
		 * @return {String[]}
		 */
		settings: function() {
			var partial = this;
			if ( partial.params.settings && 0 !== partial.params.settings.length ) {
				return partial.params.settings;
			} else if ( partial.params.primarySetting ) {
				return [ partial.params.primarySetting ];
			} else {
				return [ partial.id ];
			}
		},

		/**
		 * Return whether the setting is related to the partial.
		 *
		 * @since 4.5.0
		 *
		 * @param {wp.customize.Value|string} setting  ID or object for setting.
		 * @return {boolean} Whether the setting is related to the partial.
		 */
		isRelatedSetting: function( setting /*... newValue, oldValue */ ) {
			var partial = this;
			if ( _.isString( setting ) ) {
				setting = api( setting );
			}
			if ( ! setting ) {
				return false;
			}
			return -1 !== _.indexOf( partial.settings(), setting.id );
		},

		/**
		 * Show the control to modify this partial's setting(s).
		 *
		 * This may be overridden for inline editing.
		 *
		 * @since 4.5.0
		 */
		showControl: function() {
			var partial = this, settingId = partial.params.primarySetting;
			if ( ! settingId ) {
				settingId = _.first( partial.settings() );
			}
			if ( partial.getType() === 'nav_menu' ) {
				if ( partial.params.navMenuArgs.theme_location ) {
					settingId = 'nav_menu_locations[' + partial.params.navMenuArgs.theme_location + ']';
				} else if ( partial.params.navMenuArgs.menu )   {
					settingId = 'nav_menu[' + String( partial.params.navMenuArgs.menu ) + ']';
				}
			}
			api.preview.send( 'focus-control-for-setting', settingId );
		},

		/**
		 * Prepare container for selective refresh.
		 *
		 * @since 4.5.0
		 *
		 * @param {Placement} placement
		 */
		preparePlacement: function( placement ) {
			$( placement.container ).addClass( 'customize-partial-refreshing' );
		},

		/**
		 * Reference to the pending promise returned from self.requestPartial().
		 *
		 * @since 4.5.0
		 * @private
		 */
		_pendingRefreshPromise: null,

		/**
		 * Request the new partial and render it into the placements.
		 *
		 * @since 4.5.0
		 *
		 * @this {wp.customize.selectiveRefresh.Partial}
		 * @return {jQuery.Promise}
		 */
		refresh: function() {
			var partial = this, refreshPromise;

			refreshPromise = self.requestPartial( partial );

			if ( ! partial._pendingRefreshPromise ) {
				_.each( partial.placements(), function( placement ) {
					partial.preparePlacement( placement );
				} );

				refreshPromise.done( function( placements ) {
					_.each( placements, function( placement ) {
						partial.renderContent( placement );
					} );
				} );

				refreshPromise.fail( function( data, placements ) {
					partial.fallback( data, placements );
				} );

				// Allow new request when this one finishes.
				partial._pendingRefreshPromise = refreshPromise;
				refreshPromise.always( function() {
					partial._pendingRefreshPromise = null;
				} );
			}

			return refreshPromise;
		},

		/**
		 * Apply the addedContent in the placement to the document.
		 *
		 * Note the placement object will have its container and removedNodes
		 * properties updated.
		 *
		 * @since 4.5.0
		 *
		 * @param {Placement}             placement
		 * @param {Element|jQuery}        [placement.container]  - This param will be empty if there was no element matching the selector.
		 * @param {string|object|boolean} placement.addedContent - Rendered HTML content, a data object for JS templates to render, or false if no render.
		 * @param {object}                [placement.context]    - Optional context information about the container.
		 * @returns {boolean} Whether the rendering was successful and the fallback was not invoked.
		 */
		renderContent: function( placement ) {
			var partial = this, content, newContainerElement;
			if ( ! placement.container ) {
				partial.fallback( new Error( 'no_container' ), [ placement ] );
				return false;
			}
			placement.container = $( placement.container );
			if ( false === placement.addedContent ) {
				partial.fallback( new Error( 'missing_render' ), [ placement ] );
				return false;
			}

			// Currently a subclass needs to override renderContent to handle partials returning data object.
			if ( ! _.isString( placement.addedContent ) ) {
				partial.fallback( new Error( 'non_string_content' ), [ placement ] );
				return false;
			}

			/* jshint ignore:start */
			self.orginalDocumentWrite = document.write;
			document.write = function() {
				throw new Error( self.data.l10n.badDocumentWrite );
			};
			/* jshint ignore:end */
			try {
				content = placement.addedContent;
				if ( wp.emoji && wp.emoji.parse && ! $.contains( document.head, placement.container[0] ) ) {
					content = wp.emoji.parse( content );
				}

				if ( partial.params.containerInclusive ) {

					// Note that content may be an empty string, and in this case jQuery will just remove the oldContainer
					newContainerElement = $( content );

					// Merge the new context on top of the old context.
					placement.context = _.extend(
						placement.context,
						newContainerElement.data( 'customize-partial-placement-context' ) || {}
					);
					newContainerElement.data( 'customize-partial-placement-context', placement.context );

					placement.removedNodes = placement.container;
					placement.container = newContainerElement;
					placement.removedNodes.replaceWith( placement.container );
					placement.container.attr( 'title', self.data.l10n.shiftClickToEdit );
				} else {
					placement.removedNodes = document.createDocumentFragment();
					while ( placement.container[0].firstChild ) {
						placement.removedNodes.appendChild( placement.container[0].firstChild );
					}

					placement.container.html( content );
				}

				placement.container.removeClass( 'customize-render-content-error' );
			} catch ( error ) {
				if ( 'undefined' !== typeof console && console.error ) {
					console.error( partial.id, error );
				}
				partial.fallback( error, [ placement ] );
			}
			/* jshint ignore:start */
			document.write = self.orginalDocumentWrite;
			self.orginalDocumentWrite = null;
			/* jshint ignore:end */

			partial.createEditShortcutForPlacement( placement );
			placement.container.removeClass( 'customize-partial-refreshing' );

			// Prevent placement container from being re-triggered as being rendered among nested partials.
			placement.container.data( 'customize-partial-content-rendered', true );

			/*
			 * Note that the 'wp_audio_shortcode_library' and 'wp_video_shortcode_library' filters
			 * will determine whether or not wp.mediaelement is loaded and whether it will
			 * initialize audio and video respectively. See also https://core.trac.wordpress.org/ticket/40144
			 */
			if ( wp.mediaelement ) {
				wp.mediaelement.initialize();
			}

			if ( wp.playlist ) {
				wp.playlist.initialize();
			}

			/**
			 * Announce when a partial's placement has been rendered so that dynamic elements can be re-built.
			 */
			self.trigger( 'partial-content-rendered', placement );
			return true;
		},

		/**
		 * Handle fail to render partial.
		 *
		 * The first argument is either the failing jqXHR or an Error object, and the second argument is the array of containers.
		 *
		 * @since 4.5.0
		 */
		fallback: function() {
			var partial = this;
			if ( partial.params.fallbackRefresh ) {
				self.requestFullRefresh();
			}
		}
	} );

	/**
	 * A Placement for a Partial.
	 *
	 * A partial placement is the actual physical representation of a partial for a given context.
	 * It also may have information in relation to how a placement may have just changed.
	 * The placement is conceptually similar to a DOM Range or MutationRecord.
	 *
	 * @memberOf wp.customize.selectiveRefresh
	 *
	 * @class Placement
	 * @augments wp.customize.Class
	 * @since 4.5.0
	 */
	self.Placement = Placement = api.Class.extend(/** @lends wp.customize.selectiveRefresh.prototype */{

		/**
		 * The partial with which the container is associated.
		 *
		 * @param {wp.customize.selectiveRefresh.Partial}
		 */
		partial: null,

		/**
		 * DOM element which contains the placement's contents.
		 *
		 * This will be null if the startNode and endNode do not point to the same
		 * DOM element, such as in the case of a sidebar partial.
		 * This container element itself will be replaced for partials that
		 * have containerInclusive param defined as true.
		 */
		container: null,

		/**
		 * DOM node for the initial boundary of the placement.
		 *
		 * This will normally be the same as endNode since most placements appear as elements.
		 * This is primarily useful for widget sidebars which do not have intrinsic containers, but
		 * for which an HTML comment is output before to mark the starting position.
		 */
		startNode: null,

		/**
		 * DOM node for the terminal boundary of the placement.
		 *
		 * This will normally be the same as startNode since most placements appear as elements.
		 * This is primarily useful for widget sidebars which do not have intrinsic containers, but
		 * for which an HTML comment is output before to mark the ending position.
		 */
		endNode: null,

		/**
		 * Context data.
		 *
		 * This provides information about the placement which is included in the request
		 * in order to render the partial properly.
		 *
		 * @param {object}
		 */
		context: null,

		/**
		 * The content for the partial when refreshed.
		 *
		 * @param {string}
		 */
		addedContent: null,

		/**
		 * DOM node(s) removed when the partial is refreshed.
		 *
		 * If the partial is containerInclusive, then the removedNodes will be
		 * the single Element that was the partial's former placement. If the
		 * partial is not containerInclusive, then the removedNodes will be a
		 * documentFragment containing the nodes removed.
		 *
		 * @param {Element|DocumentFragment}
		 */
		removedNodes: null,

		/**
		 * Constructor.
		 *
		 * @since 4.5.0
		 *
		 * @param {object}                   args
		 * @param {Partial}                  args.partial
		 * @param {jQuery|Element}           [args.container]
		 * @param {Node}                     [args.startNode]
		 * @param {Node}                     [args.endNode]
		 * @param {object}                   [args.context]
		 * @param {string}                   [args.addedContent]
		 * @param {jQuery|DocumentFragment}  [args.removedNodes]
		 */
		initialize: function( args ) {
			var placement = this;

			args = _.extend( {}, args || {} );
			if ( ! args.partial || ! args.partial.extended( Partial ) ) {
				throw new Error( 'Missing partial' );
			}
			args.context = args.context || {};
			if ( args.container ) {
				args.container = $( args.container );
			}

			_.extend( placement, args );
		}

	});

	/**
	 * Mapping of type names to Partia