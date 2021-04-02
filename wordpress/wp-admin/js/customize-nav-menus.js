
/* global _wpCustomizeNavMenusSettings, wpNavMenu, console */
( function( api, wp, $ ) {
	'use strict';

	/**
	 * Set up wpNavMenu for drag and drop.
	 */
	wpNavMenu.originalInit = wpNavMenu.init;
	wpNavMenu.options.menuItemDepthPerLevel = 20;
	wpNavMenu.options.sortableItems         = '> .customize-control-nav_menu_item';
	wpNavMenu.options.targetTolerance       = 10;
	wpNavMenu.init = function() {
		this.jQueryExtensions();
	};

	api.Menus = api.Menus || {};

	// Link settings.
	api.Menus.data = {
		itemTypes: [],
		l10n: {},
		settingTransport: 'refresh',
		phpIntMax: 0,
		defaultSettingValues: {
			nav_menu: {},
			nav_menu_item: {}
		},
		locationSlugMappedToName: {}
	};
	if ( 'undefined' !== typeof _wpCustomizeNavMenusSettings ) {
		$.extend( api.Menus.data, _wpCustomizeNavMenusSettings );
	}

	/**
	 * Newly-created Nav Menus and Nav Menu Items have negative integer IDs which
	 * serve as placeholders until Save & Publish happens.
	 *
	 * @return {number}
	 */
	api.Menus.generatePlaceholderAutoIncrementId = function() {
		return -Math.ceil( api.Menus.data.phpIntMax * Math.random() );
	};

	/**
	 * wp.customize.Menus.AvailableItemModel
	 *
	 * A single available menu item model. See PHP's WP_Customize_Nav_Menu_Item_Setting class.
	 *
	 * @constructor
	 * @augments Backbone.Model
	 */
	api.Menus.AvailableItemModel = Backbone.Model.extend( $.extend(
		{
			id: null // This is only used by Backbone.
		},
		api.Menus.data.defaultSettingValues.nav_menu_item
	) );

	/**
	 * wp.customize.Menus.AvailableItemCollection
	 *
	 * Collection for available menu item models.
	 *
	 * @constructor
	 * @augments Backbone.Model
	 */
	api.Menus.AvailableItemCollection = Backbone.Collection.extend({
		model: api.Menus.AvailableItemModel,

		sort_key: 'order',

		comparator: function( item ) {
			return -item.get( this.sort_key );
		},

		sortByField: function( fieldName ) {
			this.sort_key = fieldName;
			this.sort();
		}
	});
	api.Menus.availableMenuItems = new api.Menus.AvailableItemCollection( api.Menus.data.availableMenuItems );

	/**
	 * Insert a new `auto-draft` post.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param {object} params - Parameters for the draft post to create.
	 * @param {string} params.post_type - Post type to add.
	 * @param {string} params.post_title - Post title to use.
	 * @return {jQuery.promise} Promise resolved with the added post.
	 */
	api.Menus.insertAutoDraftPost = function insertAutoDraftPost( params ) {
		var request, deferred = $.Deferred();

		request = wp.ajax.post( 'customize-nav-menus-insert-auto-draft', {
			'customize-menus-nonce': api.settings.nonce['customize-menus'],
			'wp_customize': 'on',
			'customize_changeset_uuid': api.settings.changeset.uuid,
			'params': params
		} );

		request.done( function( response ) {
			if ( response.post_id ) {
				api( 'nav_menus_created_posts' ).set(
					api( 'nav_menus_created_posts' ).get().concat( [ response.post_id ] )
				);

				if ( 'page' === params.post_type ) {

					// Activate static front page controls as this could be the first page created.
					if ( api.section.has( 'static_front_page' ) ) {
						api.section( 'static_front_page' ).activate();
					}

					// Add new page to dropdown-pages controls.
					api.control.each( function( control ) {
						var select;
						if ( 'dropdown-pages' === control.params.type ) {
							select = control.container.find( 'select[name^="_customize-dropdown-pages-"]' );
							select.append( new Option( params.post_title, response.post_id ) );
						}
					} );
				}
				deferred.resolve( response );
			}
		} );

		request.fail( function( response ) {
			var error = response || '';

			if ( 'undefined' !== typeof response.message ) {
				error = response.message;
			}

			console.error( error );
			deferred.rejectWith( error );
		} );

		return deferred.promise();
	};

	/**
	 * wp.customize.Menus.AvailableMenuItemsPanelView
	 *
	 * View class for the available menu items panel.
	 *
	 * @constructor
	 * @augments wp.Backbone.View
	 * @augments Backbone.View
	 */
	api.Menus.AvailableMenuItemsPanelView = wp.Backbone.View.extend({

		el: '#available-menu-items',

		events: {
			'input #menu-items-search': 'debounceSearch',
			'keyup #menu-items-search': 'debounceSearch',
			'focus .menu-item-tpl': 'focus',
			'click .menu-item-tpl': '_submit',
			'click #custom-menu-item-submit': '_submitLink',
			'keypress #custom-menu-item-name': '_submitLink',
			'click .new-content-item .add-content': '_submitNew',
			'keypress .create-item-input': '_submitNew',
			'keydown': 'keyboardAccessible'
		},

		// Cache current selected menu item.
		selected: null,

		// Cache menu control that opened the panel.
		currentMenuControl: null,
		debounceSearch: null,
		$search: null,
		$clearResults: null,
		searchTerm: '',
		rendered: false,
		pages: {},
		sectionContent: '',
		loading: false,
		addingNew: false,

		initialize: function() {
			var self = this;

			if ( ! api.panel.has( 'nav_menus' ) ) {
				return;
			}

			this.$search = $( '#menu-items-search' );
			this.$clearResults = this.$el.find( '.clear-results' );
			this.sectionContent = this.$el.find( '.available-menu-items-list' );

			this.debounceSearch = _.debounce( self.search, 500 );

			_.bindAll( this, 'close' );

			// If the available menu items panel is open and the customize controls are
			// interacted with (other than an item being deleted), then close the
			// available menu items panel. Also close on back button click.
			$( '#customize-controls, .customize-section-back' ).on( 'click keydown', function( e ) {
				var isDeleteBtn = $( e.target ).is( '.item-delete, .item-delete *' ),
					isAddNewBtn = $( e.target ).is( '.add-new-menu-item, .add-new-menu-item *' );
				if ( $( 'body' ).hasClass( 'adding-menu-items' ) && ! isDeleteBtn && ! isAddNewBtn ) {
					self.close();
				}
			} );

			// Clear the search results and trigger a `keyup` event to fire a new search.
			this.$clearResults.on( 'click', function() {
				self.$search.val( '' ).focus().trigger( 'keyup' );
			} );

			this.$el.on( 'input', '#custom-menu-item-name.invalid, #custom-menu-item-url.invalid', function() {
				$( this ).removeClass( 'invalid' );
			});

			// Load available items if it looks like we'll need them.
			api.panel( 'nav_menus' ).container.bind( 'expanded', function() {
				if ( ! self.rendered ) {
					self.initList();
					self.rendered = true;
				}
			});

			// Load more items.
			this.sectionContent.scroll( function() {
				var totalHeight = self.$el.find( '.accordion-section.open .available-menu-items-list' ).prop( 'scrollHeight' ),
					visibleHeight = self.$el.find( '.accordion-section.open' ).height();

				if ( ! self.loading && $( this ).scrollTop() > 3 / 4 * totalHeight - visibleHeight ) {
					var type = $( this ).data( 'type' ),
						object = $( this ).data( 'object' );

					if ( 'search' === type ) {
						if ( self.searchTerm ) {
							self.doSearch( self.pages.search );
						}
					} else {
						self.loadItems( [
							{ type: type, object: object }
						] );
					}
				}
			});

			// Close the panel if the URL in the preview changes
			api.previewer.bind( 'url', this.close );

			self.delegateEvents();
		},

		// Search input change handler.
		search: function( event ) {
			var $searchSection = $( '#available-menu-items-search' ),
				$otherSections = $( '#available-menu-items .accordion-section' ).not( $searchSection );

			if ( ! event ) {
				return;
			}

			if ( this.searchTerm === event.target.value ) {
				return;
			}

			if ( '' !== event.target.value && ! $searchSection.hasClass( 'open' ) ) {
				$otherSections.fadeOut( 100 );
				$searchSection.find( '.accordion-section-content' ).slideDown( 'fast' );
				$searchSection.addClass( 'open' );
				this.$clearResults.addClass( 'is-visible' );
			} else if ( '' === event.target.value ) {
				$searchSection.removeClass( 'open' );
				$otherSections.show();
				this.$clearResults.removeClass( 'is-visible' );
			}

			this.searchTerm = event.target.value;
			this.pages.search = 1;
			this.doSearch( 1 );
		},

		// Get search results.
		doSearch: function( page ) {
			var self = this, params,
				$section = $( '#available-menu-items-search' ),
				$content = $section.find( '.accordion-section-content' ),
				itemTemplate = wp.template( 'available-menu-item' );

			if ( self.currentRequest ) {
				self.currentRequest.abort();
			}

			if ( page < 0 ) {
				return;
			} else if ( page > 1 ) {
				$section.addClass( 'loading-more' );
				$content.attr( 'aria-busy', 'true' );
				wp.a11y.speak( api.Menus.data.l10n.itemsLoadingMore );
			} else if ( '' === self.searchTerm ) {
				$content.html( '' );
				wp.a11y.speak( '' );
				return;
			}

			$section.addClass( 'loading' );
			self.loading = true;

			params = api.previewer.query( { excludeCustomizedSaved: true } );
			_.extend( params, {
				'customize-menus-nonce': api.settings.nonce['customize-menus'],
				'wp_customize': 'on',
				'search': self.searchTerm,
				'page': page
			} );

			self.currentRequest = wp.ajax.post( 'search-available-menu-items-customizer', params );

			self.currentRequest.done(function( data ) {
				var items;
				if ( 1 === page ) {
					// Clear previous results as it's a new search.
					$content.empty();
				}
				$section.removeClass( 'loading loading-more' );
				$content.attr( 'aria-busy', 'false' );
				$section.addClass( 'open' );
				self.loading = false;
				items = new api.Menus.AvailableItemCollection( data.items );
				self.collection.add( items.models );
				items.each( function( menuItem ) {
					$content.append( itemTemplate( menuItem.attributes ) );
				} );
				if ( 20 > items.length ) {
					self.pages.search = -1; // Up to 20 posts and 20 terms in results, if <20, no more results for either.
				} else {
					self.pages.search = self.pages.search + 1;
				}
				if ( items && page > 1 ) {
					wp.a11y.speak( api.Menus.data.l10n.itemsFoundMore.replace( '%d', items.length ) );
				} else if ( items && page === 1 ) {
					wp.a11y.speak( api.Menus.data.l10n.itemsFound.replace( '%d', items.length ) );
				}
			});

			self.currentRequest.fail(function( data ) {
				// data.message may be undefined, for example when typing slow and the request is aborted.
				if ( data.message ) {
					$content.empty().append( $( '<li class="nothing-found"></li>' ).text( data.message ) );
					wp.a11y.speak( data.message );
				}
				self.pages.search = -1;
			});

			self.currentRequest.always(function() {
				$section.removeClass( 'loading loading-more' );
				$content.attr( 'aria-busy', 'false' );
				self.loading = false;
				self.currentRequest = null;
			});
		},

		// Render the individual items.
		initList: function() {
			var self = this;

			// Render the template for each item by type.
			_.each( api.Menus.data.itemTypes, function( itemType ) {
				self.pages[ itemType.type + ':' + itemType.object ] = 0;
			} );
			self.loadItems( api.Menus.data.itemTypes );
		},

		/**
		 * Load available nav menu items.
		 *
		 * @since 4.3.0
		 * @since 4.7.0 Changed function signature to take list of item types instead of single type/object.
		 * @access private
		 *
		 * @param {Array.<object>} itemTypes List of objects containing type and key.
		 * @param {string} deprecated Formerly the object parameter.
		 * @returns {void}
		 */
		loadItems: function( itemTypes, deprecated ) {
			var self = this, _itemTypes, requestItemTypes = [], params, request, itemTemplate, availableMenuItemContainers = {};
			itemTemplate = wp.template( 'available-menu-item' );

			if ( _.isString( itemTypes ) && _.isString( deprecated ) ) {
				_itemTypes = [ { type: itemTypes, object: deprecated } ];
			} else {
				_itemTypes = itemTypes;
			}

			_.each( _itemTypes, function( itemType ) {
				var container, name = itemType.type + ':' + itemType.object;
				if ( -1 === self.pages[ name ] ) {
					return; // Skip types for which there are no more results.
				}
				container = $( '#available-menu-items-' + itemType.type + '-' + itemType.object );
				container.find( '.accordion-section-title' ).addClass( 'loading' );
				availableMenuItemContainers[ name ] = container;

				requestItemTypes.push( {
					object: itemType.object,
					type: itemType.type,
					page: self.pages[ name ]
				} );
			} );

			if ( 0 === requestItemTypes.length ) {
				return;
			}

			self.loading = true;

			params = api.previewer.query( { excludeCustomizedSaved: true } );
			_.extend( params, {
				'customize-menus-nonce': api.settings.nonce['customize-menus'],
				'wp_customize': 'on',
				'item_types': requestItemTypes
			} );

			request = wp.ajax.post( 'load-available-menu-items-customizer', params );

			request.done(function( data ) {
				var typeInner;
				_.each( data.items, function( typeItems, name ) {
					if ( 0 === typeItems.length ) {
						if ( 0 === self.pages[ name ] ) {
							availableMenuItemContainers[ name ].find( '.accordion-section-title' )
								.addClass( 'cannot-expand' )
								.removeClass( 'loading' )
								.find( '.accordion-section-title > button' )
								.prop( 'tabIndex', -1 );
						}
						self.pages[ name ] = -1;
						return;
					} else if ( ( 'post_type:page' === name ) && ( ! availableMenuItemContainers[ name ].hasClass( 'open' ) ) ) {
						availableMenuItemContainers[ name ].find( '.accordion-section-title > button' ).click();
					}
					typeItems = new api.Menus.AvailableItemCollection( typeItems ); // @todo Why is this collection created and then thrown away?
					self.collection.add( typeItems.models );
					typeInner = availableMenuItemContainers[ name ].find( '.available-menu-items-list' );
					typeItems.each( function( menuItem ) {
						typeInner.append( itemTemplate( menuItem.attributes ) );
					} );
					self.pages[ name ] += 1;
				});
			});
			request.fail(function( data ) {
				if ( typeof console !== 'undefined' && console.error ) {
					console.error( data );
				}
			});
			request.always(function() {
				_.each( availableMenuItemContainers, function( container ) {
					container.find( '.accordion-section-title' ).removeClass( 'loading' );
				} );
				self.loading = false;
			});
		},

		// Adjust the height of each section of items to fit the screen.
		itemSectionHeight: function() {
			var sections, lists, totalHeight, accordionHeight, diff;
			totalHeight = window.innerHeight;
			sections = this.$el.find( '.accordion-section:not( #available-menu-items-search ) .accordion-section-content' );
			lists = this.$el.find( '.accordion-section:not( #available-menu-items-search ) .available-menu-items-list:not(":only-child")' );
			accordionHeight =  46 * ( 1 + sections.length ) + 14; // Magic numbers.
			diff = totalHeight - accordionHeight;
			if ( 120 < diff && 290 > diff ) {
				sections.css( 'max-height', diff );
				lists.css( 'max-height', ( diff - 60 ) );
			}
		},

		// Highlights a menu item.
		select: function( menuitemTpl ) {
			this.selected = $( menuitemTpl );
			this.selected.siblings( '.menu-item-tpl' ).removeClass( 'selected' );
			this.selected.addClass( 'selected' );
		},

		// Highlights a menu item on focus.
		focus: function( event ) {
			this.select( $( event.currentTarget ) );
		},

		// Submit handler for keypress and click on menu item.
		_submit: function( event ) {
			// Only proceed with keypress if it is Enter or Spacebar
			if ( 'keypress' === event.type && ( 13 !== event.which && 32 !== event.which ) ) {
				return;
			}

			this.submit( $( event.currentTarget ) );
		},

		// Adds a selected menu item to the menu.
		submit: function( menuitemTpl ) {
			var menuitemId, menu_item;

			if ( ! menuitemTpl ) {
				menuitemTpl = this.selected;
			}

			if ( ! menuitemTpl || ! this.currentMenuControl ) {
				return;
			}

			this.select( menuitemTpl );

			menuitemId = $( this.selected ).data( 'menu-item-id' );
			menu_item = this.collection.findWhere( { id: menuitemId } );
			if ( ! menu_item ) {
				return;
			}

			this.currentMenuControl.addItemToMenu( menu_item.attributes );

			$( menuitemTpl ).find( '.menu-item-handle' ).addClass( 'item-added' );
		},

		// Submit handler for keypress and click on custom menu item.
		_submitLink: function( event ) {
			// Only proceed with keypress if it is Enter.
			if ( 'keypress' === event.type && 13 !== event.which ) {
				return;
			}

			this.submitLink();
		},

		// Adds the custom menu item to the menu.
		submitLink: function() {
			var menuItem,
				itemName = $( '#custom-menu-item-name' ),
				itemUrl = $( '#custom-menu-item-url' ),
				urlRegex;

			if ( ! this.currentMenuControl ) {
				return;
			}

			/*
			 * Allow URLs including:
			 * - http://example.com/
			 * - //example.com
			 * - /directory/
			 * - ?query-param
			 * - #target
			 * - mailto:foo@example.com
			 *
			 * Any further validation will be handled on the server when the setting is attempted to be saved,
			 * so this pattern does not need to be complete.
			 */
			urlRegex = /^((\w+:)?\/\/\w.*|\w+:(?!\/\/$)|\/|\?|#)/;

			if ( '' === itemName.val() ) {
				itemName.addClass( 'invalid' );
				return;
			} else if ( ! urlRegex.test( itemUrl.val() ) ) {
				itemUrl.addClass( 'invalid' );
				return;
			}

			menuItem = {
				'title': itemName.val(),
				'url': itemUrl.val(),
				'type': 'custom',
				'type_label': api.Menus.data.l10n.custom_label,
				'object': 'custom'
			};

			this.currentMenuControl.addItemToMenu( menuItem );

			// Reset the custom link form.
			itemUrl.val( 'http://' );
			itemName.val( '' );
		},

		/**
		 * Submit handler for keypress (enter) on field and click on button.
		 *
		 * @since 4.7.0
		 * @private
		 *
		 * @param {jQuery.Event} event Event.
		 * @returns {void}
		 */
		_submitNew: function( event ) {
			var container;

			// Only proceed with keypress if it is Enter.
			if ( 'keypress' === event.type && 13 !== event.which ) {
				return;
			}

			if ( this.addingNew ) {
				return;
			}

			container = $( event.target ).closest( '.accordion-section' );

			this.submitNew( container );
		},

		/**
		 * Creates a new object and adds an associated menu item to the menu.
		 *
		 * @since 4.7.0
		 * @private
		 *
		 * @param {jQuery} container
		 * @returns {void}
		 */
		submitNew: function( container ) {
			var panel = this,
				itemName = container.find( '.create-item-input' ),
				title = itemName.val(),
				dataContainer = container.find( '.available-menu-items-list' ),
				itemType = dataContainer.data( 'type' ),
				itemObject = dataContainer.data( 'object' ),
				itemTypeLabel = dataContainer.data( 'type_label' ),
				promise;

			if ( ! this.currentMenuControl ) {
				return;
			}

			// Only posts are supported currently.
			if ( 'post_type' !== itemType ) {
				return;
			}

			if ( '' === $.trim( itemName.val() ) ) {
				itemName.addClass( 'invalid' );
				itemName.focus();
				return;
			} else {
				itemName.removeClass( 'invalid' );
				container.find( '.accordion-section-title' ).addClass( 'loading' );
			}

			panel.addingNew = true;
			itemName.attr( 'disabled', 'disabled' );
			promise = api.Menus.insertAutoDraftPost( {
				post_title: title,
				post_type: itemObject
			} );
			promise.done( function( data ) {
				var availableItem, $content, itemElement;
				availableItem = new api.Menus.AvailableItemModel( {
					'id': 'post-' + data.post_id, // Used for available menu item Backbone models.
					'title': itemName.val(),
					'type': itemType,
					'type_label': itemTypeLabel,
					'object': itemObject,
					'object_id': data.post_id,
					'url': data.url
				} );

				// Add new item to menu.
				panel.currentMenuControl.addItemToMenu( availableItem.attributes );

				// Add the new item to the list of available items.
				api.Menus.availableMenuItemsPanel.collection.add( availableItem );
				$content = container.find( '.available-menu-items-list' );
				itemElement = $( wp.template( 'available-menu-item' )( availableItem.attributes ) );
				itemElement.find( '.menu-item-handle:first' ).addClass( 'item-added' );
				$content.prepend( itemElement );
				$content.scrollTop();

				// Reset the create content form.
				itemName.val( '' ).removeAttr( 'disabled' );
				panel.addingNew = false;
				container.find( '.accordion-section-title' ).removeClass( 'loading' );
			} );
		},

		// Opens the panel.
		open: function( menuControl ) {
			var panel = this, close;

			this.currentMenuControl = menuControl;

			this.itemSectionHeight();

			if ( api.section.has( 'publish_settings' ) ) {
				api.section( 'publish_settings' ).collapse();
			}

			$( 'body' ).addClass( 'adding-menu-items' );

			close = function() {
				panel.close();
				$( this ).off( 'click', close );
			};
			$( '#customize-preview' ).on( 'click', close );

			// Collapse all controls.
			_( this.currentMenuControl.getMenuItemControls() ).each( function( control ) {
				control.collapseForm();
			} );

			this.$el.find( '.selected' ).removeClass( 'selected' );

			this.$search.focus();
		},

		// Closes the panel
		close: function( options ) {
			options = options || {};

			if ( options.returnFocus && this.currentMenuControl ) {
				this.currentMenuControl.container.find( '.add-new-menu-item' ).focus();
			}

			this.currentMenuControl = null;
			this.selected = null;

			$( 'body' ).removeClass( 'adding-menu-items' );
			$( '#available-menu-items .menu-item-handle.item-added' ).removeClass( 'item-added' );

			this.$search.val( '' );
		},

		// Add a few keyboard enhancements to the panel.
		keyboardAccessible: function( event ) {
			var isEnter = ( 13 === event.which ),
				isEsc = ( 27 === event.which ),
				isBackTab = ( 9 === event.which && event.shiftKey ),
				isSearchFocused = $( event.target ).is( this.$search );

			// If enter pressed but nothing entered, don't do anything
			if ( isEnter && ! this.$search.val() ) {
				return;
			}

			if ( isSearchFocused && isBackTab ) {
				this.currentMenuControl.container.find( '.add-new-menu-item' ).focus();
				event.preventDefault(); // Avoid additional back-tab.
			} else if ( isEsc ) {
				this.close( { returnFocus: true } );
			}
		}
	});

	/**
	 * wp.customize.Menus.MenusPanel
	 *
	 * Customizer panel for menus. This is used only for screen options management.
	 * Note that 'menus' must match the WP_Customize_Menu_Panel::$type.
	 *
	 * @constructor
	 * @augments wp.customize.Panel
	 */
	api.Menus.MenusPanel = api.Panel.extend({

		attachEvents: function() {
			api.Panel.prototype.attachEvents.call( this );

			var panel = this,
				panelMeta = panel.container.find( '.panel-meta' ),
				help = panelMeta.find( '.customize-help-toggle' ),
				content = panelMeta.find( '.customize-panel-description' ),
				options = $( '#screen-options-wrap' ),
				button = panelMeta.find( '.customize-screen-options-toggle' );
			button.on( 'click keydown', function( event ) {
				if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
					return;
				}
				event.preventDefault();

				// Hide description
				if ( content.not( ':hidden' ) ) {
					content.slideUp( 'fast' );
					help.attr( 'aria-expanded', 'false' );
				}

				if ( 'true' === button.attr( 'aria-expanded' ) ) {
					button.attr( 'aria-expanded', 'false' );
					panelMeta.removeClass( 'open' );
					panelMeta.removeClass( 'active-menu-screen-options' );
					options.slideUp( 'fast' );
				} else {
					button.attr( 'aria-expanded', 'true' );
					panelMeta.addClass( 'open' );
					panelMeta.addClass( 'active-menu-screen-options' );
					options.slideDown( 'fast' );
				}

				return false;
			} );

			// Help toggle
			help.on( 'click keydown', function( event ) {
				if ( api.utils.isKeydownButNotEnterEvent( event ) ) {
					return;
				}
				event.preventDefault();

				if ( 'true' === button.attr( 'aria-expanded' ) ) {
					button.attr( 'aria-expanded', 'false' );
					help.attr( 'aria-expanded', 'true' );
					panelMeta.addClass( 'open' );
					panelMeta.removeClass( 'active-menu-screen-options' );
					options.slideUp( 'fast' );
					content.slideDown( 'fast' );
				}
			} );
		},

		/**
		 * Update field visibility when clicking on the field toggles.
		 */
		ready: function() {
			var panel = this;
			panel.container.find( '.hide-column-tog' ).click( function() {
				panel.saveManageColumnsState();
			});

			// Inject additional heading into the menu locations section's head container.
			api.section( 'menu_locations', function( section ) {
				section.headContainer.prepend(
					wp.template( 'nav-menu-locations-header' )( api.Menus.data )
				);
			} );
		},

		/**
		 * Save hidden column states.
		 *
		 * @since 4.3.0
		 * @private
		 *
		 * @returns {void}
		 */
		saveManageColumnsState: _.debounce( function() {
			var panel = this;
			if ( panel._updateHiddenColumnsRequest ) {
				panel._updateHiddenColumnsRequest.abort();
			}

			panel._updateHiddenColumnsRequest = wp.ajax.post( 'hidden-columns', {
				hidden: panel.hidden(),
				screenoptionnonce: $( '#screenoptionnonce' ).val(),
				page: 'nav-menus'
			} );
			panel._updateHiddenColumnsRequest.always( function() {
				panel._updateHiddenColumnsRequest = null;
			} );
		}, 2000 ),

		/**
		 * @deprecated Since 4.7.0 now that the nav_menu sections are responsible for toggling the classes on their own containers.
		 */
		checked: function() {},

		/**
		 * @deprecated Since 4.7.0 now that the nav_menu sections are responsible for toggling the classes on their own containers.
		 */
		unchecked: function() {},

		/**
		 * Get hidden fields.
		 *
		 * @since 4.3.0
		 * @private
		 *
		 * @returns {Array} Fields (columns) that are hidden.
		 */
		hidden: function() {
			return $( '.hide-column-tog' ).not( ':checked' ).map( function() {
				var id = this.id;
				return id.substring( 0, id.length - 5 );
			}).get().join( ',' );
		}
	} );

	/**
	 * wp.customize.Menus.MenuSection
	 *
	 * Customizer section for menus. This is used only for lazy-loading child controls.
	 * Note that 'nav_menu' must match the WP_Customize_Menu_Section::$type.
	 *
	 * @constructor
	 * @augments wp.customize.Section
	 */
	api.Menus.MenuSection = api.Section.extend({

		/**
		 * Initialize.
		 *
		 * @since 4.3.0
		 *
		 * @param {String} id
		 * @param {Object} options
		 */
		initialize: function( id, options ) {
			var section = this;
			api.Section.prototype.initialize.call( section, id, options );
			section.deferred.initSortables = $.Deferred();
		},

		/**
		 * Ready.
		 */
		ready: function() {
			var section = this, fieldActiveToggles, handleFieldActiveToggle;

			if ( 'undefined' === typeof section.params.menu_id ) {
				throw new Error( 'params.menu_id was not defined' );
			}

			/*
			 * Since newly created sections won't be registered in PHP, we need to prevent the
			 * preview's sending of the activeSections to result in this control
			 * being deactivated when the preview refreshes. So we can hook onto
			 * the setting that has the same ID and its presence can dictate
			 * whether the section is active.
			 */
			section.active.validate = function() {
				if ( ! api.has( section.id ) ) {
					return false;
				}
				return !! api( section.id ).get();
			};

			section.populateControls();

			section.navMenuLocationSettings = {};
			section.assignedLocations = new api.Value( [] );

			api.each(function( setting, id ) {
				var matches = id.match( /^nav_menu_locations\[(.+?)]/ );
				if ( matches ) {
					section.navMenuLocationSettings[ matches[1] ] = setting;
					setting.bind( function() {
						section.refreshAssignedLocations();
					});
				}
			});

			section.assignedLocations.bind(function( to ) {
				section.updateAssignedLocationsInSectionTitle( to );
			});

			section.refreshAssignedLocations();

			api.bind( 'pane-contents-reflowed', function() {
				// Skip menus that have been removed.
				if ( ! section.contentContainer.parent().length ) {
					return;
				}
				section.container.find( '.menu-item .menu-item-reorder-nav button' ).attr({ 'tabindex': '0', 'aria-hidden': 'false' });
				section.container.find( '.menu-item.move-up-disabled .menus-move-up' ).attr({ 'tabindex': '-1', 'aria-hidden': 'true' });
				section.container.find( '.menu-item.move-down-disabled .menus-move-down' ).attr({ 'tabindex': '-1', 'aria-hidden': 'true' });
				section.container.find( '.menu-item.move-left-disabled .menus-move-left' ).attr({ 'tabindex': '-1', 'aria-hidden': 'true' });
				section.container.find( '.menu-item.move-right-disabled .menus-move-right' ).attr({ 'tabindex': '-1', 'aria-hidden': 'true' });
			} );

			/**
			 * Update the active field class for the content container for a given checkbox toggle.
			 *
			 * @this {jQuery}
			 * @returns {void}
			 */
			handleFieldActiveToggle = function() {
				var className = 'field-' + $( this ).val() + '-active';
				section.contentContainer.toggleClass( className, $( this ).prop( 'checked' ) );
			};
			fieldActiveToggles = api.panel( 'nav_menus' ).contentContainer.find( '.metabox-prefs:first' ).find( '.hide-column-tog' );
			fieldActiveToggles.each( handleFieldActiveToggle );
			fieldActiveToggles.on( 'click', handleFieldActiveToggle );
		},

		populateControls: function() {
			var section = this,
				menuNameControlId,
				menuLocationsControlId,
				menuAutoAddControlId,
				menuDeleteControlId,
				menuControl,
				menuNameControl,
				menuLocationsControl,
				menuAutoAddControl,
				menuDeleteControl;

			// Add the control for managing the menu name.
			menuNameControlId = section.id + '[name]';
			menuNameControl = api.control( menuNameControlId );
			if ( ! menuNameControl ) {
				menuNameControl = new api.controlConstructor.nav_menu_name( menuNameControlId, {
					type: 'nav_menu_name',
					label: api.Menus.data.l10n.menuNameLabel,
					section: section.id,
					priority: 0,
					settings: {
						'default': section.id
					}
				} );
				api.control.add( menuNameControl );
				menuNameControl.active.set( true );
			}

			// Add the menu control.
			menuControl = api.control( section.id );
			if ( ! menuControl ) {
				menuControl = new api.controlConstructor.nav_menu( section.id, {
					type: 'nav_menu',
					section: section.id,
					priority: 998,
					settings: {
						'default': section.id
					},
					menu_id: section.params.menu_id
				} );
				api.control.add( menuControl );
				menuControl.active.set( true );
			}

			// Add the menu locations control.
			menuLocationsControlId = section.id + '[locations]';
			menuLocationsControl = api.control( menuLocationsControlId );
			if ( ! menuLocationsControl ) {
				menuLocationsControl = new api.controlConstructor.nav_menu_locations( menuLocationsControlId, {
					section: section.id,
					priority: 999,
					settings: {
						'default': section.id
					},
					menu_id: section.params.menu_id
				} );
				api.control.add( menuLocationsControl.id, menuLocationsControl );
				menuControl.active.set( true );
			}

			// Add the control for managing the menu auto_add.
			menuAutoAddControlId = section.id + '[auto_add]';
			menuAutoAddControl = api.control( menuAutoAddControlId );
			if ( ! menuAutoAddControl ) {
				menuAutoAddControl = new api.controlConstructor.nav_menu_auto_add( menuAutoAddControlId, {
					type: 'nav_menu_auto_add',
					label: '',
					section: section.id,
					priority: 1000,
					settings: {
						'default': section.id
					}
				} );
				api.control.add( menuAutoAddControl );
				menuAutoAddControl.active.set( true );
			}

			// Add the control for deleting the menu
			menuDeleteControlId = section.id + '[delete]';
			menuDeleteControl = api.control( menuDeleteControlId );
			if ( ! menuDeleteControl ) {
				menuDeleteControl = new api.Control( menuDeleteControlId, {
					section: section.id,
					priority: 1001,
					templateId: 'nav-menu-delete-button'
				} );
				api.control.add( menuDeleteControl.id, menuDeleteControl );
				menuDeleteControl.active.set( true );
				menuDeleteControl.deferred.embedded.done( function () {
					menuDeleteControl.container.find( 'button' ).on( 'click', function() {
						var menuId = section.params.menu_id;
						var menuControl = api.Menus.getMenuControl( menuId );
						menuControl.setting.set( false );
					});
				} );
			}
		},

		/**
		 *
		 */
		refreshAssignedLocations: function() {
			var section = this,
				menuTermId = section.params.menu_id,
				currentAssignedLocations = [];
			_.each( section.navMenuLocationSettings, function( setting, themeLocation ) {
				if ( setting() === menuTermId ) {
					currentAssignedLocations.push( themeLocation );
				}
			});
			section.assignedLocations.set( currentAssignedLocations );
		},

		/**
		 * @param {Array} themeLocationSlugs Theme location slugs.
		 */
		updateAssignedLocationsInSectionTitle: function( themeLocationSlugs ) {
			var section = this,
				$title;

			$title = section.container.find( '.accordion-section-title:first' );
			$title.find( '.menu-in-location' ).remove();
			_.each( themeLocationSlugs, function( themeLocationSlug ) {
				var $label, locationName;
				$label = $( '<span class="menu-in-location"></span>' );
				locationName = api.Menus.data.locationSlugMappedToName[ themeLocationSlug ];
				$label.text( api.Menus.data.l10n.menuLocation.replace( '%s', locationName ) );
				$title.append( $label );
			});

			section.container.toggleClass( 'assigned-to-menu-location', 0 !== themeLocationSlugs.length );

		},

		onChangeExpanded: function( expanded, args ) {
			var section = this, completeCallback;

			if ( expanded ) {
				wpNavMenu.menuList = section.contentContainer;
				wpNavMenu.targetList = wpNavMenu.menuList;

				// Add attributes needed by wpNavMenu
				$( '#menu-to-edit' ).removeAttr( 'id' );
				wpNavMenu.menuList.attr( 'id', 'menu-to-edit' ).addClass( 'menu' );

				_.each( api.section( section.id ).controls(), function( control ) {
					if ( 'nav_menu_item' === control.params.type ) {
						control.actuallyEmbed();
					}
				} );

				// Make sure Sortables is initialized after the section has been expanded to prevent `offset` issues.
				if ( args.completeCallback ) {
					completeCallback = args.completeCallback;
				}
				args.completeCallback = function() {
					if ( 'resolved' !== section.deferred.initSortables.state() ) {
						wpNavMenu.initSortables(); // Depends on menu-to-edit ID being set above.
						section.deferred.initSortables.resolve( wpNavMenu.menuList ); // Now MenuControl can extend the sortable.

						// @todo Note that wp.customize.reflowPaneContents() is debounced, so this immediate change will show a slight flicker while priorities get updated.
						api.control( 'nav_menu[' + String( section.params.menu_id ) + ']' ).reflowMenuItems();
					}
					if ( _.isFunction( completeCallback ) ) {
						completeCallback();
					}
				};
			}
			api.Section.prototype.onChangeExpanded.call( section, expanded, args );
		},

		/**
		 * Highlight how a user may create new menu items.
		 *
		 * This method reminds the user to create new menu items and how.
		 * It's exposed this way because this class knows best which UI needs
		 * highlighted but those expanding this section know more about why and
		 * when the affordance should be highlighted.
		 *
		 * @since 4.9.0
		 *
		 * @returns {void}
		 */
		highlightNewItemButton: function() {
			api.utils.highlightButton( this.contentContainer.find( '.add-new-menu-item' ), { delay: 2000 } );
		}
	});

	/**
	 * Create a nav menu setting and section.
	 *
	 * @since 4.9.0
	 *
	 * @param {string} [name=''] Nav menu name.
	 * @returns {wp.customize.Menus.MenuSection} Added nav menu.
	 */
	api.Menus.createNavMenu = function createNavMenu( name ) {
		var customizeId, placeholderId, setting;
		placeholderId = api.Menus.generatePlaceholderAutoIncrementId();

		customizeId = 'nav_menu[' + String( placeholderId ) + ']';

		// Register the menu control setting.
		setting = api.create( customizeId, customizeId, {}, {
			type: 'nav_menu',
			transport: api.Menus.data.settingTransport,
			previewer: api.previewer
		} );
		setting.set( $.extend(
			{},
			api.Menus.data.defaultSettingValues.nav_menu,
			{
				name: name || ''
			}
		) );

		/*
		 * Add the menu section (and its controls).
		 * Note that this will automatically create the required controls
		 * inside via the Section's ready method.
		 */
		return api.section.add( new api.Menus.MenuSection( customizeId, {
			panel: 'nav_menus',
			title: displayNavMenuName( name ),
			customizeAction: api.Menus.data.l10n.customizingMenus,
			priority: 10,
			menu_id: placeholderId
		} ) );
	};

	/**
	 * wp.customize.Menus.NewMenuSection
	 *
	 * Customizer section for new menus.
	 *
	 * @constructor
	 * @augments wp.customize.Section
	 */
	api.Menus.NewMenuSection = api.Section.extend({

		/**
		 * Add behaviors for the accordion section.
		 *
		 * @since 4.3.0
		 */
		attachEvents: function() {
			var section = this,
				container = section.container,
				contentContainer = section.contentContainer,
				navMenuSettingPattern = /^nav_menu\[/;

			section.headContainer.find( '.accordion-section-title' ).replaceWith(
				wp.template( 'nav-menu-create-menu-section-title' )
			);

			/*
			 * We have to manually handle section expanded because we do not
			 * apply the `accordion-section-title` class to this button-driven section.
			 */
			container.on( 'click', '.customize-add-menu-button', function() {
				section.expand();
			});

			contentContainer.on( 'keydown', '.menu-name-field', function( event ) {
				if ( 13 === event.which ) { // Enter.
					section.submit();
				}
			} );
			contentContainer.on( 'click', '#customize-new-menu-submit', function( event ) {
				section.submit();
				event.stopPropagation();
				event.preventDefault();
			} );

			/**
			 * Get number of non-deleted nav menus.
			 *
			 * @since 4.9.0
			 * @returns {number} Count.
			 */
			function getNavMenuCount() {
				var count = 0;
				api.each( function( setting ) {
					if ( navMenuSettingPattern.test( setting.id ) && false !== setting.get() ) {
						count += 1;
					}
				} );
				return count;
			}

			/**
			 * Update visibility of notice to prompt users to create menus.
			 *
			 * @since 4.9.0
			 * @returns {void}
			 */
			function updateNoticeVisibility() {
				container.find( '.add-new-menu-notice' ).prop( 'hidden', getNavMenuCount() > 0 );
			}

			/**
			 * Handle setting addition.
			 *
			 * @since 4.9.0
			 * @param {wp.customize.Setting} setting - Added setting.
			 * @returns {void}
			 */
			function addChangeEventListener( setting ) {
				if ( navMenuSettingPattern.test( setting.id ) ) {
					setting.bind( updateNoticeVisibility );
					updateNoticeVisibility();
				}
			}

			/**
			 * Handle setting removal.
			 *
			 * @since 4.9.0
			 * @param {wp.customize.Setting} setting - Removed setting.
			 * @returns {void}
			 */
			function removeChangeEventListener( setting ) {
				if ( navMenuSettingPattern.test( setting.id ) ) {
					setting.unbind( updateNoticeVisibility );
					updateNoticeVisibility();
				}
			}

			api.each( addChangeEventListener );
			api.bind( 'add', addChangeEventListener );
			api.bind( 'removed', removeChangeEventListener );
			updateNoticeVisibility();

			api.Section.prototype.attachEvents.apply( section, arguments );
		},

		/**
		 * Set up the control.
		 *
		 * @since 4.9.0
		 */
		ready: function() {
			this.populateControls();
		},

		/**
		 * Create the controls for this section.
		 *
		 * @since 4.9.0
		 */
		populateControls: function() {
			var section = this,
				menuNameControlId,
				menuLocationsControlId,
				newMenuSubmitControlId,
				menuNameControl,
				menuLocationsControl,
				newMenuSubmitControl;

			menuNameControlId = section.id + '[name]';
			menuNameControl = api.control( menuNameControlId );
			if ( ! menuNameControl ) {
				menuNameControl = new api.controlConstructor.nav_menu_name( menuNameControlId, {
					label: api.Menus.data.l10n.menuNameLabel,
					description: api.Menus.data.l10n.newMenuNameDescription,
					section: section.id,
					priority: 0
				} );
				api.control.add( menuNameControl.id, menuNameControl );
				menuNameControl.active.set( true );
			}

			menuLocationsControlId = section.id + '[locations]';
			menuLocationsControl = api.control( menuLocationsControlId );
			if ( ! menuLocationsControl ) {
				menuLocationsControl = new api.controlConstructor.nav_menu_locations( menuLocationsControlId, {
					section: section.id,
					priority: 1,
					menu_id: '',
					isCreating: true
				} );
				api.control.add( menuLocationsControlId, menuLocationsControl );
				menuLocationsControl.active.set( true );
			}

			newMenuSubmitControlId = section.id + '[submit]';
			newMenuSubmitControl = api.control( newMenuSubmitControlId );
			if ( !newMenuSubmitControl ) {
				newMenuSubmitControl = new api.Control( newMenuSubmitControlId, {
					section: section.id,
					priority: 1,
					templateId: 'nav-menu-submit-new-button'
				} );
				api.control.add( newMenuSubmitControlId, newMenuSubmitControl );
				newMenuSubmitControl.active.set( true );
			}
		},

		/**
		 * Create the new menu with name and location supplied by the user.
		 *
		 * @since 4.9.0
		 */
		submit: function() {
			var section = this,
				contentContainer = section.contentContainer,
				nameInput = contentContainer.find( '.menu-name-field' ).first(),
				name = nameInput.val(),
				menuSection;

			if ( ! name ) {
				nameInput.addClass( 'invalid' );
				nameInput.focus();
				return;
			}

			menuSection = api.Menus.createNavMenu( name );

			// Clear name field.
			nameInput.val( '' );
			nameInput.removeClass( 'invalid' );

			contentContainer.find( '.assigned-menu-location input[type=checkbox]' ).each( function() {
				var checkbox = $( this ),
				navMenuLocationSetting;

				if ( checkbox.prop( 'checked' ) ) {
					navMenuLocationSetting = api( 'nav_menu_locations[' + checkbox.data( 'location-id' ) + ']' );
					navMenuLocationSetting.set( menuSection.params.menu_id );

					// Reset state for next new menu
					checkbox.prop( 'checked', false );
				}
			} );

			wp.a11y.speak( api.Menus.data.l10n.menuAdded );

			// Focus on the new menu section.
			menuSection.focus( {
				completeCallback: function() {
					menuSection.highlightNewItemButton();
				}
			} );
		},

		/**
		 * Select a default location.
		 *
		 * This method selects a single location by default so we can support
		 * creating a menu for a specific menu location.
		 *
		 * @since 4.9.0
		 *
		 * @param {string|null} locationId - The ID of the location to select. `null` clears all selections.
		 * @returns {void}
		 */
		selectDefaultLocation: function( locationId ) {
			var locationControl = api.control( this.id + '[locations]' ),
				locationSelections = {};

			if ( locationId !== null ) {
				locationSelections[ locationId ] = true;
			}

			locationControl.setSelections( locationSelections );
		}
	});

	/**
	 * wp.customize.Menus.MenuLocationControl
	 *
	 * Customizer control for menu locations (rendered as a <select>).
	 * Note that 'nav_menu_location' must match the WP_Customize_Nav_Menu_Location_Control::$type.
	 *
	 * @constructor
	 * @augments wp.customize.Control
	 */
	api.Menus.MenuLocationControl = api.Control.extend({
		initialize: function( id, options ) {
			var control = this,
				matches = id.match( /^nav_menu_locations\[(.+?)]/ );
			control.themeLocation = matches[1];
			api.Control.prototype.initialize.call( control, id, options );
		},

		ready: function() {
			var control = this, navMenuIdRegex = /^nav_menu\[(-?\d+)]/;

			// @todo It would be better if this was added directly on the setting itself, as opposed to the control.
			control.setting.validate = function( value ) {
				if ( '' === value ) {
					return 0;
				} else {
					return parseInt( value, 10 );
				}
			};

			// Create and Edit menu buttons.
			control.container.find( '.create-menu' ).on( 'click', function() {
				var addMenuSection = api.section( 'add_menu' );
				addMenuSection.selectDefaultLocation( this.dataset.locationId );
				addMenuSection.focus();
			} );
			control.container.find( '.edit-menu' ).on( 'click', function() {
				var menuId = control.setting();
				api.section( 'nav_menu[' + menuId + ']' ).focus();
			});
			control.setting.bind( 'change', function() {
				var menuIsSelected = 0 !== control.setting();
				control.container.find( '.create-menu' ).toggleClass( 'hidden', menuIsSelected );
				control.container.find( '.edit-menu' ).toggleClass( 'hidden', ! menuIsSelected );
			});

			// Add/remove menus from the available options when they are added and removed.
			api.bind( 'add', function( setting ) {
				var option, menuId, matches = setting.id.match( navMenuIdRegex );
				if ( ! matches || false === setting() ) {
					return;
				}
				menuId = matches[1];
				option = new Option( displayNavMenuName( setting().name ), menuId );
				control.container.find( 'select' ).append( option );
			});
			api.bind( 'remove', function( setting ) {
				var menuId, matches = setting.id.match( navMenuIdRegex );
				if ( ! matches ) {
					return;
				}
				menuId = parseInt( matches[1], 10 );
				if ( control.setting() === menuId ) {
					control.setting.set( '' );
				}
				control.container.find( 'option[value=' + menuId + ']' ).remove();
			});
			api.bind( 'change', function( setting ) {
				var menuId, matches = setting.id.match( navMenuIdRegex );
				if ( ! matches ) {
					return;
				}
				menuId = parseInt( matches[1], 10 );
				if ( false === setting() ) {
					if ( control.setting() === menuId ) {
						control.setting.set( '' );
					}
					control.container.find( 'option[value=' + menuId + ']' ).remove();
				} else {
					control.container.find( 'option[value=' + menuId + ']' ).text( displayNavMenuName( setting().name ) );
				}
			});
		}
	});

	/**
	 * wp.customize.Menus.MenuItemControl
	 *
	 * Customizer control for menu items.
	 * Note that 'menu_item' must match the WP_Customize_Menu_Item_Control::$type.
	 *
	 * @constructor
	 * @augments wp.customize.Control
	 */
	api.Menus.MenuItemControl = api.Control.extend({

		/**
		 * @inheritdoc
		 */
		initialize: function( id, options ) {
			var control = this;
			control.expanded = new api.Value( false );
			control.expandedArgumentsQueue = [];
			control.expanded.bind( function( expanded ) {
				var args = control.expandedArgumentsQueue.shift();
				args = $.extend( {}, control.defaultExpandedArguments, args );
				control.onChangeExpanded( expanded, args );
			});
			api.Control.prototype.initialize.call( control, id, options );
			control.active.validate = function() {
				var value, section = api.section( control.section() );
				if ( section ) {
					value = section.active();
				} else {
					value = false;
				}
				return value;
			};
		},

		/**
		 * Override the embed() method to do nothing,
		 * so that the control isn't embedded on load,
		 * unless the containing section is already expanded.
		 *
		 * @since 4.3.0
		 */
		embed: function() {
			var control = this,
				sectionId = control.section(),
				section;
			if ( ! sectionId ) {
				return;
			}
			section = api.section( sectionId );
			if ( ( section && section.expanded() ) || api.settings.autofocus.control === control.id ) {
				control.actuallyEmbed();
			}
		},

		/**
		 * This function is called in Section.onChangeExpanded() so the control
		 * will only get embedded when the Section is first expanded.
		 *
		 * @since 4.3.0
		 */
		actuallyEmbed: function() {
			var control = this;
			if ( 'resolved' === control.deferred.embedded.state() ) {
				return;
			}
			control.renderContent();
			control.deferred.embedded.resolve(); // This triggers control.ready().
		},

		/**
		 * Set up the control.
		 */
		ready: function() {
			if ( 'undefined' === typeof this.params.menu_item_id ) {
				throw new Error( 'params.menu_item_id was not defined' );
			}

			this._setupControlToggle();
			this._setupReorderUI();
			this._setupUpdateUI();
			this._setupRemoveUI();
			this._setupLinksUI();
			this._setupTitleUI();
		},

		/**
		 * Show/hide the settings when clicking on the menu item handle.
		 */
		_setupControlToggle: function() {
			var control = this;

			this.container.find( '.menu-item-handle' ).on( 'click', function( e ) {
				e.preventDefault();
				e.stopPropagation();
				var menuControl = control.getMenuControl(),
					isDeleteBtn = $( e.target ).is( '.item-delete, .item-delete *' ),
					isAddNewBtn = $( e.target ).is( '.add-new-menu-item, .add-new-menu-item *' );

				if ( $( 'body' ).hasClass( 'adding-menu-items' ) && ! isDeleteBtn && ! isAddNewBtn ) {
					api.Menus.availableMenuItemsPanel.close();
				}

				if ( menuControl.isReordering || menuControl.isSorting ) {
					return;
				}
				control.toggleForm();
			} );
		},

		/**
		 * Set up the menu-item-reorder-nav
		 */
		_setupReorderUI: function() {
			var control = this, template, $reorderNav;

			template = wp.template( 'menu-item-reorder-nav' );

			// Add the menu item reordering elements to the menu item control.
			control.container.find( '.item-controls' ).after( template );

			// Handle clicks for up/down/left-right on the reorder nav.
			$reorderNav = control.container.find( '.menu-item-reorder-nav' );
			$reorderNav.find( '.menus-move-up, .menus-move-down, .menus-move-left, .menus-move-right' ).on( 'click', function() {
				var moveBtn = $( this );
				moveBtn.focus();

				var isMoveUp = moveBtn.is( '.menus-move-up' ),
					isMoveDown = moveBtn.is( '.menus-move-down' ),
					isMoveLeft = moveBtn.is( '.menus-move-left' ),
					isMoveRight = moveBtn.is( '.menus-move-right' );

				if ( isMoveUp ) {
					control.moveUp();
				} else if ( isMoveDown ) {
					control.moveDown();
				} else if ( isMoveLeft ) {
					control.moveLeft();
				} else if ( isMoveRight ) {
					control.moveRight();
				}

				moveBtn.focus(); // Re-focus after the container was moved.
			} );
		},

		/**
		 * Set up event handlers for menu item updating.
		 */
		_setupUpdateUI: function() {
			var control = this,
				settingValue = control.setting(),
				updateNotifications;

			control.elements = {};
			control.elements.url = new api.Element( control.container.find( '.edit-menu-item-url' ) );
			control.elements.title = new api.Element( control.container.find( '.edit-menu-item-title' ) );
			control.elements.attr_title = new api.Element( control.container.find( '.edit-menu-item-attr-title' ) );
			control.elements.target = new api.Element( control.container.find( '.edit-menu-item-target' ) );
			control.elements.classes = new api.Element( control.container.find( '.edit-menu-item-classes' ) );
			control.elements.xfn = new api.Element( control.container.find( '.edit-menu-item-xfn' ) );
			control.elements.description = new api.Element( control.container.find( '.edit-menu-item-description' ) );
			// @todo allow other elements, added by plugins, to be automatically picked up here; allow additional values to be added to setting array.

			_.each( control.elements, function( element, property ) {
				element.bind(function( value ) {
					if ( element.element.is( 'input[type=checkbox]' ) ) {
						value = ( value ) ? element.element.val() : '';
					}

					var settingValue = control.setting();
					if ( settingValue && settingValue[ property ] !== value ) {
						settingValue = _.clone( settingValue );
						settingValue[ property ] = value;
						control.setting.set( settingValue );
					}
				});
				if ( settingValue ) {
					if ( ( property === 'classes' || property === 'xfn' ) && _.isArray( settingValue[ property ] ) ) {
						element.set( settingValue[ property ].join( ' ' ) );
					} else {
						element.set( settingValue[ property ] );
					}
				}
			});

			control.setting.bind(function( to, from ) {
				var itemId = control.params.menu_item_id,
					followingSiblingItemControls = [],
					childrenItemControls = [],
					menuControl;

				if ( false === to ) {
					menuControl = api.control( 'nav_menu[' + String( from.nav_menu_term_id ) + ']' );
					control.container.remove();

					_.each( menuControl.getMenuItemControls(), function( otherControl ) {
						if ( from.menu_item_parent === otherControl.setting().menu_item_parent && otherControl.setting().position > from.position ) {
							followingSiblingItemControls.push( otherControl );
						} else if ( otherControl.setting().menu_item_parent === itemId ) {
							childrenItemControls.push( otherControl );
						}
					});

					// Shift all following siblings by the number of children this item has.
					_.each( followingSiblingItemControls, function( followingSiblingItemControl ) {
						var value = _.clone( followingSiblingItemControl.setting() );
						value.position += childrenItemControls.length;
						followingSiblingItemControl.setting.set( value );
					});

					// Now move the children up to be the new subsequent siblings.
					_.each( childrenItemControls, function( childrenItemControl, i ) {
						var value = _.clone( childrenItemControl.setting() );
						value.position = from.position + i;
						value.menu_item_parent = from.menu_item_parent;
						childrenItemControl.setting.set( value );
					});

					menuControl.debouncedReflowMenuItems();
				} else {
					// Update the elements' values to match the new setting properties.
					_.each( to, function( value, key ) {
						if ( control.elements[ key] ) {
							control.elements[ key ].set( to[ key ] );
						}
					} );
					control.container.find( '.menu-item-data-parent-id' ).val( to.menu_item_parent );

					// Handle UI updates when the position or depth (parent) change.
					if ( to.position !== from.position || to.menu_item_parent !== from.menu_item_parent ) {
						control.getMenuControl().debouncedReflowMenuItems();
					}
				}
			});

			// Style the URL field as invalid when there is an invalid_url notification.
			updateNotifications = function() {
				control.elements.url.element.toggleClass( 'invalid', control.setting.notifications.has( 'invalid_url' ) );
			};
			control.setting.notifications.bind( 'add', updateNotifications );
			control.setting.notifications.bind( 'removed', updateNotifications );
		},

		/**
		 * Set up event handlers for menu item deletion.
		 */
		_setupRemoveUI: function() {
			var control = this, $removeBtn;

			// Configure delete button.
			$removeBtn = control.container.find( '.item-delete' );

			$removeBtn.on( 'click', function() {
				// Find an adjacent element to add focus to when this menu item goes away
				var addingItems = true, $adjacentFocusTarget, $next, $prev;

				if ( ! $( 'body' ).hasClass( 'adding-menu-items' ) ) {
					addingItems = false;
				}

				$next = control.container.nextAll( '.customize-control-nav_menu_item:visible' ).first();
				$prev = control.container.prevAll( '.customize-control-nav_menu_item:visible' ).first();

				if ( $next.length ) {
					$adjacentFocusTarget = $next.find( false === addingItems ? '.item-edit' : '.item-delete' ).first();
				} else if ( $prev.length ) {
					$adjacentFocusTarget = $prev.find( false === addingItems ? '.item-edit' : '.item-delete' ).first();
				} else {
					$adjacentFocusTarget = control.container.nextAll( '.customize-control-nav_menu' ).find( '.add-new-menu-item' ).first();
				}

				control.container.slideUp( function() {
					control.setting.set( false );
					wp.a11y.speak( api.Menus.data.l10n.itemDeleted );
					$adjacentFocusTarget.focus(); // keyboard accessibility
				} );

				control.setting.set( false );
			} );
		},

		_setupLinksUI: function() {
			var $origBtn;

			// Configure original link.
			$origBtn = this.container.find( 'a.original-link' );

			$origBtn.on( 'click', function( e ) {
				e.preventDefault();
				api.previewer.previewUrl( e.target.toString() );
			} );
		},

		/**
		 * Update item handle title when changed.
		 */
		_setupTitleUI: function() {
			var control = this, titleEl;

			// Ensure that whitespace is trimmed on blur so placeholder can be shown.
			control.container.find( '.edit-menu-item-title' ).on( 'blur', function() {
				$( this ).val( $.trim( $( this ).val() ) );
			} );

			titleEl = control.container.find( '.menu-item-title' );
			control.setting.bind( function( item ) {
				var trimmedTitle, titleText;
				if ( ! item ) {
					return;
				}
				trimmedTitle = $.trim( item.title );

				titleText = trimmedTitle || item.original_title || api.Menus.data.l10n.untitled;

				if ( item._invalid ) {
					titleText = api.Menus.data.l10n.invalidTitleTpl.replace( '%s', titleText );
				}

				// Don't update to an empty title.
				if ( trimmedTitle || item.original_title ) {
					titleEl
						.text( titleText )
						.removeClass( 'no-title' );
				} else {
					titleEl
						.text( titleText )
						.addClass( 'no-title' );
				}
			} );
		},

		/**
		 *
		 * @returns {number}
		 */
		getDepth: function() {
			var control = this, setting = control.setting(), depth = 0;
			if ( ! setting ) {
				return 0;
			}
			while ( setting && setting.menu_item_parent ) {
				depth += 1;
				control = api.control( 'nav_menu_item[' + setting.menu_item_parent + ']' );
				if ( ! control ) {
					break;
				}
				setting = control.setting();
			}
			return depth;
		},

		/**
		 * Amend the control's params with the data necessary for the JS template just in time.
		 */
		renderContent: function() {
			var control = this,
				settingValue = control.setting(),
				containerClasses;

			control.params.title = settingValue.title || '';
			control.params.depth = control.getDepth();
			control.container.data( 'item-depth', control.params.depth );
			containerClasses = [
				'menu-item',
				'menu-item-depth-' + String( control.params.depth ),
				'menu-item-' + settingValue.object,
				'menu-item-edit-inactive'
			];

			if ( settingValue._invalid ) {
				containerClasses.push( 'menu-item-invalid' );
				control.params.title = api.Menus.data.l10n.invalidTitleTpl.replace( '%s', control.params.title );
			} else if ( 'draft' === settingValue.status ) {
				containerClasses.push( 'pending' );
				control.params.title = api.Menus.data.pendingTitleTpl.replace( '%s', control.params.title );
			}

			control.params.el_classes = containerClasses.join( ' ' );
			control.params.item_type_label = settingValue.type_label;
			control.params.item_type = settingValue.type;
			control.params.url = settingValue.url;
			control.params.target = settingValue.target;
			control.params.attr_title = settingValue.attr_title;
			control.params.classes = _.isArray( settingValue.classes ) ? settingValue.classes.join( ' ' ) : settingValue.classes;
			control.params.attr_title = settingValue.attr_title;
			control.params.xfn = settingValue.xfn;
			control.params.description = settingValue.description;
			control.params.parent = settingValue.menu_item_parent;
			control.params.original_title = settingValue.original_title || '';

			control.container.addClass( control.params.el_classes );

			api.Control.prototype.renderContent.call( control );
		},

		/***********************************************************************
		 * Begin public API methods
		 **********************************************************************/

		/**
		 * @return {wp.customize.controlConstructor.nav_menu|null}
		 */
		getMenuControl: function() {
			var control = this, settingValue = control.setting();
			if ( settingValue && settingValue.nav_menu_term_id ) {
				return api.control( 'nav_menu[' + settingValue.nav_menu_term_id + ']' );
			} else {
				return null;
			}
		},

		/**
		 * Expand the accordion section containing a control
		 */
		expandControlSection: function() {
			var $section = this.container.closest( '.accordion-section' );
			if ( ! $section.hasClass( 'open' ) ) {
				$section.find( '.accordion-section-title:first' ).trigger( 'click' );
			}
		},

		/**
		 * @since 4.6.0
		 *
		 * @param {Boolean} expanded
		 * @param {Object} [params]
		 * @returns {Boolean} false if state already applied
		 */
		_toggleExpanded: api.Section.prototype._toggleExpanded,

		/**
		 * @since 4.6.0
		 *
		 * @param {Object} [params]
		 * @returns {Boolean} false if already expanded
		 */
		expand: api.Section.prototype.expand,

		/**
		 * Expand the menu item form control.
		 *
		 * @since 4.5.0 Added params.completeCallback.
		 *
		 * @param {Object}   [params] - Optional params.
		 * @param {Function} [params.completeCallback] - Function to call when the form toggle has finished animating.
		 */
		expandForm: function( params ) {
			this.expand( params );
		},

		/**
		 * @since 4.6.0
		 *
		 * @param {Object} [params]
		 * @returns {Boolean} false if already collapsed
		 */
		collapse: api.Section.prototype.collapse,

		/**
		 * Collapse the menu item form control.
		 *
		 * @since 4.5.0 Added params.completeCallback.
		 *
		 * @param {Object}   [params] - Optional params.
		 * @param {Function} [params.completeCallback] - Function to call when the form toggle has finished animating.
		 */
		collapseForm: function( params ) {
			this.collapse( params );
		},

		/**
		 * Expand or collapse the menu item control.
		 *
		 * @deprecated this is poor naming, and it is better to directly set control.expanded( showOrHide )
		 * @since 4.5.0 Added params.completeCallback.
		 *
		 * @param {boolean}  [showOrHide] - If not supplied, will be inverse of current visibility
		 * @param {Object}   [params] - Optional params.
		 * @param {Function} [params.completeCallback] - Function to call when the form toggle has finished animating.
		 */
		toggleForm: function( showOrHide, params ) {
			if ( typeof showOrHide === 'undefined' ) {
				showOrHide = ! this.expanded();
			}
			if ( showOrHide ) {
				this.expand( params );
			} else {
				this.collapse( params );
			}
		},

		/**
		 * Expand or collapse the menu item control.
		 *
		 * @since 4.6.0
		 * @param {boolean}  [showOrHide] - If not supplied, will be inverse of current visibility
		 * @param {Object}   [params] - Optional params.
		 * @param {Function} [params.completeCallback] - Function to call when the form toggle has finished animating.
		 */
		onChangeExpanded: function( showOrHide, params ) {
			var self = this, $menuitem, $inside, complete;

			$menuitem = this.container;
			$inside = $menuitem.find( '.menu-item-settings:first' );
			if ( 'undefined' === typeof showOrHide ) {
				showOrHide = ! $inside.is( ':visible' );
			}

			// Already expanded or collapsed.
			if ( $inside.is( ':visible' ) === showOrHide ) {
				if ( params && params.completeCallback ) {
					params.completeCallback();
				}
				return;
			}

			if ( showOrHide ) {
				// Close all other menu item controls before expanding this one.
				api.control.each( function( otherControl ) {
					if ( self.params.type === otherControl.params.type && self !== otherControl ) {
						otherControl.collapseForm();
					}
				} );

				complete = function() {
					$menuitem
						.removeClass( 'menu-item-edit-inactive' )
						.addClass( 'menu-item-edit-active' );
					self.container.trigger( 'expanded' );

					if ( params && params.completeCallback ) {
						params.completeCallback();
					}
				};

				$menuitem.find( '.item-edit' ).attr( 'aria-expanded', 'true' );
				$inside.slideDown( 'fast', complete );

				self.container.trigger( 'expand' );
			} else {
				complete = function() {
					$menuitem
						.addClass( 'menu-item-edit-inactive' )
						.removeClass( 'menu-item-edit-active' );
					self.container.trigger( 'collapsed' );

					if ( params && params.completeCallback ) {
						params.completeCallback();
					}
				};

				self.container.trigger( 'collapse' );

				$menuitem.find( '.item-edit' ).attr( 'aria-expanded', 'false' );
				$inside.slideUp( 'fast', complete );
			}
		},

		/**
		 * Expand the containing menu section, expand the form, and focus on
		 * the first input in the control.
		 *
		 * @since 4.5.0 Added params.completeCallback.
		 *
		 * @param {Object}   [params] - Params object.
		 * @param {Function} [params.completeCallback] - Optional callback function when focus has completed.
		 */
		focus: function( params ) {
			params = params || {};
			var control = this, originalCompleteCallback = params.completeCallback, focusControl;

			focusControl = function() {
				control.expandControlSection();

				params.completeCallback = function() {
					var focusable;

					// Note that we can't use :focusable due to a jQuery UI issue. See: https://github.com/jquery/jquery-ui/pull/1583
					focusable = control.container.find( '.menu-item-settings' ).find( 'input, select, textarea, button, object, a[href], [tabindex]' ).filter( ':visible' );
					focusable.first().focus();

					if ( originalCompleteCallback ) {
						originalCompleteCallback();
					}
				};

				control.expandForm( params );
			};

			if ( api.section.has( control.section() ) ) {
				api.section( control.section() ).expand( {
					completeCallback: focusControl
				} );
			} else {
				focusControl();
			}
		},

		/**
		 * Move menu item up one in the menu.
		 */
		moveUp: function() {
			this._changePosition( -1 );
			wp.a11y.speak( api.Menus.data.l10n.movedUp );
		},

		/**
		 * Move menu item up one in the menu.
		 */
		moveDown: function() {
			this._changePosition( 1 );
			wp.a11y.speak( api.Menus.data.l10n.movedDown );
		},
		/**
		 * Move menu item and all children up one level of depth.
		 */
		moveLeft: function() {
			this._changeDepth( -1 );
			wp.a11y.speak( api.Menus.data.l10n.movedLeft );
		},

		/**
		 * Move menu item and children one level deeper, as a submenu of the previous item.
		 */
		moveRight: function() {
			this._changeDepth( 1 );
			wp.a11y.speak( api.Menus.data.l10n.movedRight );
		},

		/**
		 * Note that this will trigger a UI update, causing child items to
		 * move as well and cardinal order class names to be updated.
		 *
		 * @private
		 *
		 * @param {Number} offset 1|-1
		 */
		_changePosition: function( offset ) {
			var control = this,
				adjacentSetting,
				settingValue = _.clone( control.setting() ),
				siblingSettings = [],
				realPosition;

			if ( 1 !== offset && -1 !== offset ) {
				throw new Error( 'Offset changes by 1 are only supported.' );
			}

			// Skip moving deleted items.
			if ( ! control.setting() ) {
				return;
			}

			// Locate the other items under the same parent (siblings).
			_( control.getMenuControl().getMenuItemControls() ).each(function( otherControl ) {
				if ( otherControl.setting().menu_item_parent === settingValue.menu_item_parent ) {
					siblingSettings.push( otherControl.setting );
				}
			});
			siblingSettings.sort(function( a, b ) {
				return a().position - b().position;
			});

			realPosition = _.indexOf( siblingSettings, control.setting );
			if ( -1 === realPosition ) {
				throw new Error( 'Expected setting to be among siblings.' );
			}

			// Skip doing anything if the item is already at the edge in the desired direction.
			if ( ( realPosition === 0 && offset < 0 ) || ( realPosition === siblingSettings.length - 1 && offset > 0 ) ) {
				// @todo Should we allow a menu item to be moved up to break it out of a parent? Adopt with previous or following parent?
				return;
			}

			// Update any adjacent menu item setting to take on this item's position.
			adjacentSetting = siblingSettings[ realPosition + offset ];
			if ( adjacentSetting ) {
				adjacentSetting.set( $.extend(
					_.clone( adjacentSetting() ),
					{
						position: settingValue.position
					}
				) );
			}

			settingValue.position += offset;
			control.setting.set( settingValue );
		},

		/**
		 * Note that this will trigger a UI update, causing child items to
		 * move as well and cardinal order class names to be updated.
		 *
		 * @private
		 *
		 * @param {Number} offset 1|-1
		 */
		_changeDepth: function( offset ) {
			if ( 1 !== offset && -1 !== offset ) {
				throw new Error( 'Offset changes by 1 are only supported.' );
			}
			var control = this,
				settingValue = _.clone( control.setting() ),
				siblingControls = [],
				realPosition,
				siblingControl,
				parentControl;

			// Locate the other items under the same parent (siblings).
			_( control.getMenuControl().getMenuItemControls() ).each(function( otherControl ) {
				if ( otherControl.setting().menu_item_parent === settingValue.menu_item_parent ) {
					siblingControls.push( otherControl );
				}
			});
			siblingControls.sort(function( a, b ) {
				return a.setting().position - b.setting().position;
			});

			realPosition = _.indexOf( siblingControls, control );
			if ( -1 === realPosition ) {
				throw new Error( 'Expected control to be among siblings.' );
			}

			if ( -1 === offset ) {
				// Skip moving left an item that is already at the top level.
				if ( ! settingValue.menu_item_parent ) {
					return;
				}

				parentControl = api.control( 'nav_menu_item[' + settingValue.menu_item_parent + ']' );

				// Make this control the parent of all the following siblings.
				_( siblingControls ).chain().slice( realPosition ).each(function( siblingControl, i ) {
					siblingControl.setting.set(
						$.extend(
							{},
							siblingControl.setting(),
							{
								menu_item_parent: control.params.menu_item_id,
								position: i
							}
						)
					);
				});

				// Increase the positions of the parent item's subsequent children to make room for this one.