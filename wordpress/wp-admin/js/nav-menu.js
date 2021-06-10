/**
 * WordPress Administration Navigation Menu
 * Interface JS functions
 *
 * @version 2.0.0
 *
 * @package WordPress
 * @subpackage Administration
 */

/* global menus, postboxes, columns, isRtl, navMenuL10n, ajaxurl */

var wpNavMenu;

(function($) {

	var api;

	api = wpNavMenu = {

		options : {
			menuItemDepthPerLevel : 30, // Do not use directly. Use depthToPx and pxToDepth instead.
			globalMaxDepth:  11,
			sortableItems:   '> *',
			targetTolerance: 0
		},

		menuList : undefined,	// Set in init.
		targetList : undefined, // Set in init.
		menusChanged : false,
		isRTL: !! ( 'undefined' != typeof isRtl && isRtl ),
		negateIfRTL: ( 'undefined' != typeof isRtl && isRtl ) ? -1 : 1,
		lastSearch: '',

		// Functions that run on init.
		init : function() {
			api.menuList = $('#menu-to-edit');
			api.targetList = api.menuList;

			this.jQueryExtensions();

			this.attachMenuEditListeners();

			this.attachQuickSearchListeners();
			this.attachThemeLocationsListeners();
			this.attachMenuSaveSubmitListeners();

			this.attachTabsPanelListeners();

			this.attachUnsavedChangesListener();

			if ( api.menuList.length )
				this.initSortables();

			if ( menus.oneThemeLocationNoMenus )
				$( '#posttype-page' ).addSelectedToMenu( api.addMenuItemToBottom );

			this.initManageLocations();

			this.initAccessibility();

			this.initToggles();

			this.initPreviewing();
		},

		jQueryExtensions : function() {
			// jQuery extensions
			$.fn.extend({
				menuItemDepth : function() {
					var margin = api.isRTL ? this.eq(0).css('margin-right') : this.eq(0).css('margin-left');
					return api.pxToDepth( margin && -1 != margin.indexOf('px') ? margin.slice(0, -2) : 0 );
				},
				updateDepthClass : function(current, prev) {
					return this.each(function(){
						var t = $(this);
						prev = prev || t.menuItemDepth();
						$(this).removeClass('menu-item-depth-'+ prev )
							.addClass('menu-item-depth-'+ current );
					});
				},
				shiftDepthClass : function(change) {
					return this.each(function(){
						var t = $(this),
							depth = t.menuItemDepth(),
							newDepth = depth + change;

						t.removeClass( 'menu-item-depth-'+ depth )
							.addClass( 'menu-item-depth-'+ ( newDepth ) );

						if ( 0 === newDepth ) {
							t.find( '.is-submenu' ).hide();
						}
					});
				},
				childMenuItems : function() {
					var result = $();
					this.each(function(){
						var t = $(this), depth = t.menuItemDepth(), next = t.next( '.menu-item' );
						while( next.length && next.menuItemDepth() > depth ) {
							result = result.add( next );
							next = next.next( '.menu-item' );
						}
					});
					return result;
				},
				shiftHorizontally : function( dir ) {
					return this.each(function(){
						var t = $(this),
							depth = t.menuItemDepth(),
							newDepth = depth + dir;

						// Change .menu-item-depth-n class
						t.moveHorizontally( newDepth, depth );
					});
				},
				moveHorizontally : function( newDepth, depth ) {
					return this.each(function(){
						var t = $(this),
							children = t.childMenuItems(),
							diff = newDepth - depth,
							subItemText = t.find('.is-submenu');

						// Change .menu-item-depth-n class
						t.updateDepthClass( newDepth, depth ).updateParentMenuItemDBId();

						// If it has children, move those too
						if ( children ) {
							children.each(function() {
								var t = $(this),
									thisDepth = t.menuItemDepth(),
									newDepth = thisDepth + diff;
								t.updateDepthClass(newDepth, thisDepth).updateParentMenuItemDBId();
							});
						}

						// Show "Sub item" helper text
						if (0 === newDepth)
							subItemText.hide();
						else
							subItemText.show();
					});
				},
				updateParentMenuItemDBId : function() {
					return this.each(function(){
						var item = $(this),
							input = item.find( '.menu-item-data-parent-id' ),
							depth = parseInt( item.menuItemDepth(), 10 ),
							parentDepth = depth - 1,
							parent = item.prevAll( '.menu-item-depth-' + parentDepth ).first();

						if ( 0 === depth ) { // Item is on the top level, has no parent
							input.val(0);
						} else { // Find the parent item, and retrieve its object id.
							input.val( parent.find( '.menu-item-data-db-id' ).val() );
						}
					});
				},
				hideAdvancedMenuItemFields : function() {
					return this.each(function(){
						var that = $(this);
						$('.hide-column-tog').not(':checked').each(function(){
							that.find('.field-' + $(this).val() ).addClass('hidden-field');
						});
					});
				},
				/**
				 * Adds selected menu items to the menu.
				 *
				 * @param jQuery metabox The metabox jQuery object.
				 */
				addSelectedToMenu : function(processMethod) {
					if ( 0 === $('#menu-to-edit').length ) {
						return false;
					}

					return this.each(function() {
						var t = $(this), menuItems = {},
							checkboxes = ( menus.oneThemeLocationNoMenus && 0 === t.find( '.tabs-panel-active .categorychecklist li input:checked' ).length ) ? t.find( '#page-all li input[type="checkbox"]' ) : t.find( '.tabs-panel-active .categorychecklist li input:checked' ),
							re = /menu-item\[([^\]]*)/;

						processMethod = processMethod || api.addMenuItemToBottom;

						// If no items are checked, bail.
						if ( !checkboxes.length )
							return false;

						// Show the ajax spinner
						t.find( '.button-controls .spinner' ).addClass( 'is-active' );

						// Retrieve menu item data
						$(checkboxes).each(function(){
							var t = $(this),
								listItemDBIDMatch = re.exec( t.attr('name') ),
								listItemDBID = 'undefined' == typeof listItemDBIDMatch[1] ? 0 : parseInt(listItemDBIDMatch[1], 10);

							if ( this.className && -1 != this.className.indexOf('add-to-top') )
								processMethod = api.addMenuItemToTop;
							menuItems[listItemDBID] = t.closest('li').getItemData( 'add-menu-item', listItemDBID );
						});

						// Add the items
						api.addItemToMenu(menuItems, processMethod, function(){
							// Deselect the items and hide the ajax spinner
							checkboxes.removeAttr('checked');
							t.find( '.button-controls .spinner' ).removeClass( 'is-active' );
						});
					});
				},
				getItemData : function( itemType, id ) {
					itemType = itemType || 'menu-item';

					var itemData = {}, i,
					fields = [
						'menu-item-db-id',
						'menu-item-object-id',
						'menu-item-object',
						'menu-item-parent-id',
						'menu-item-position',
						'menu-item-type',
						'menu-item-title',
						'menu-item-url',
						'menu-item-description',
						'menu-item-attr-title',
						'menu-item-target',
						'menu-item-classes',
						'menu-item-xfn'
					];

					if( !id && itemType == 'menu-item' ) {
						id = this.find('.menu-item-data-db-id').val();
					}

					if( !id ) return itemData;

					this.find('input').each(function() {
						var field;
						i = fields.length;
						while ( i-- ) {
							if( itemType == 'menu-item' )
								field = fields[i] + '[' + id + ']';
							else if( itemType == 'add-menu-item' )
								field = 'menu-item[' + id + '][' + fields[i] + ']';

							if (
								this.name &&
								field == this.name
							) {
								itemData[fields[i]] = this.value;
							}
						}
					});

					return itemData;
				},
				setItemData : function( itemData, itemType, id ) { // Can take a type, such as 'menu-item', or an id.
					itemType = itemType || 'menu-item';

					if( !id && itemType == 'menu-item' ) {
						id = $('.menu-item-data-db-id', this).val();
					}

					if( !id ) return this;

					this.find('input').each(function() {
						var t = $(this), field;
						$.each( itemData, function( attr, val ) {
							if( itemType == 'menu-item' )
								field = attr + '[' + id + ']';
							else if( itemType == 'add-menu-item' )
								field = 'menu-item[' + id + '][' + attr + ']';

							if ( field == t.attr('name') ) {
								t.val( val );
							}
						});
					});
					return this;
				}
			});
		},

		countMenuItems : function( depth ) {
			return $( '.menu-item-depth-' + depth ).length;
		},

		moveMenuItem : function( $this, dir ) {

			var items, newItemPosition, newDepth,
				menuItems = $( '#menu-to-edit li' ),
				menuItemsCount = menuItems.length,
				thisItem = $this.parents( 'li.menu-item' ),
				thisItemChildren = thisItem.childMenuItems(),
				thisItemData = thisItem.getItemData(),
				thisItemDepth = parseInt( thisItem.menuItemDepth(), 10 ),
				thisItemPosition = parseInt( thisItem.index(), 10 ),
				nextItem = thisItem.next(),
				nextItemChildren = nextItem.childMenuItems(),
				nextItemDepth = parseInt( nextItem.menuItemDepth(), 10 ) + 1,
				prevItem = thisItem.prev(),
				prevItemDepth = parseInt( prevItem.menuItemDepth(), 10 ),
				prevItemId = prevItem.getItemData()['menu-item-db-id'];

			switch ( dir ) {
			case 'up':
				newItemPosition = thisItemPosition - 1;

				// Already at top
				if ( 0 === thisItemPosition )
					break;

				// If a sub item is moved to top, shift it to 0 depth
				if ( 0 === newItemPosition && 0 !== thisItemDepth )
					thisItem.moveHorizontally( 0, thisItemDepth );

				// If prev item is sub item, shift to match depth
				if ( 0 !== prevItemDepth )
					thisItem.moveHorizontally( prevItemDepth, thisItemDepth );

				// Does this item have sub items?
				if ( thisItemChildren ) {
					items = thisItem.add( thisItemChildren );
					// Move the entire block
					items.detach().insertBefore( menuItems.eq( newItemPosition ) ).updateParentMenuItemDBId();
				} else {
					thisItem.detach().insertBefore( menuItems.eq( newItemPosition ) ).updateParentMenuItemDBId();
				}
				break;
			case 'down':
				// Does this item have sub items?
				if ( thisItemChildren ) {
					items = thisItem.add( thisItemChildren ),
						nextItem = menuItems.eq( items.length + thisItemPosition ),
						nextItemChildren = 0 !== nextItem.childMenuItems().length;

					if ( nextItemChildren ) {
						newDepth = parseInt( nextItem.menuItemDepth(), 10 ) + 1;
						thisItem.moveHorizontally( newDepth, thisItemDepth );
					}

					// Have we reached the bottom?
					if ( menuItemsCount === thisItemPosition + items.length )
						break;

					items.detach().insertAfter( menuItems.eq( thisItemPosition + items.length ) ).updateParentMenuItemDBId();
				} else {
					// If next item has sub items, shift depth
					if ( 0 !== nextItemChildren.length )
						thisItem.moveHorizontally( nextItemDepth, thisItemDepth );

					// Have we reached the bottom
					if ( menuItemsCount === thisItemPosition + 1 )
						break;
					thisItem.detach().insertAfter( menuItems.eq( thisItemPosition + 1 ) ).updateParentMenuItemDBId();
				}
				break;
			case 'top':
				// Already at top
				if ( 0 === thisItemPosition )
					break;
				// Does this item have sub items?
				if ( thisItemChildren ) {
					items = thisItem.add( thisItemChildren );
					// Move the entire block
					items.detach().insertBefore( menuItems.eq( 0 ) ).updateParentMenuItemDBId();
				} else {
					thisItem.detach().insertBefore( menuItems.eq( 0 ) ).updateParentMenuItemDBId();
				}
				break;
			case 'left':
				// As far left as possible
				if ( 0 === thisItemDepth )
					break;
				thisItem.shiftHorizontally( -1 );
				break;
			case 'right':
				// Can't be sub item at top
				if ( 0 === thisItemPosition )
					break;
				// Already sub item of prevItem
				if ( thisItemData['menu-item-parent-id'] === prevItemId )
					break;
				thisItem.shiftHorizontally( 1 );
				break;
			}
			$this.focus();
			api.registerChange();
			api.refreshKeyboardAccessibility();
			api.refreshAdvancedAccessibility();
		},

		initAccessibility : function() {
			var menu = $( '#menu-to-edit' );

			api.refreshKeyboardAccessibility();
			api.refreshAdvancedAccessibility();

			// Refresh the accessibility when the user comes close to the item in any way
			menu.on( 'mouseenter.refreshAccessibility focus.refreshAccessibility touchstart.refreshAccessibility' , '.menu-item' , function(){
				api.refreshAdvancedAccessibilityOfItem( $( this ).find( 'a.item-edit' ) );
			} );

			// We have to update on click as well because we might hover first, change the item, and then click.
			menu.on( 'click', 'a.item-edit', function() {
				api.refreshAdvancedAccessibilityOfItem( $( this ) );
			} );

			// Links for moving items
			menu.on( 'click', '.menus-move', function () {
				var $this = $( this ),
					dir = $this.data( 'dir' );

				if ( 'undefined' !== typeof dir ) {
					api.moveMenuItem( $( this ).parents( 'li.menu-item' ).find( 'a.item-edit' ), dir );
				}
			});
		},

		/**
		 * refreshAdvancedAccessibilityOfItem( [itemToRefresh] )
		 *
		 * Refreshes advanced accessibility buttons for one menu item.
		 * Shows or hides buttons based on the location of the menu item.
		 *
		 * @param  {object} itemToRefresh The menu item that might need its advanced accessibility buttons refreshed
		 */
		refreshAdvancedAccessibilityOfItem : function( itemToRefresh ) {

			// Only refresh accessibility when necessary
			if ( true !== $( itemToRefresh ).data( 'needs_accessibility_refresh' ) ) {
				return;
			}

			var thisLink, thisLinkText, primaryItems, itemPosition, title,
				parentItem, parentItemId, parentItemName, subItems,
				$this = $( itemToRefresh ),
				menuItem = $this.closest( 'li.menu-item' ).first(),
				depth = menuItem.menuItemDepth(),
				isPrimaryMenuItem = ( 0 === depth ),
				itemName = $this.closest( '.menu-item-handle' ).find( '.menu-item-title' ).text(),
				position = parseInt( menuItem.index(), 10 ),
				prevItemDepth = ( isPrimaryMenuItem ) ? depth : parseInt( depth - 1, 10 ),
				prevItemNameLeft = menuItem.prevAll('.menu-item-depth-' + prevItemDepth).first().find( '.menu-item-title' ).text(),
				prevItemNameRight = menuItem.prevAll('.menu-item-depth-' + depth).first().find( '.menu-item-title' ).text(),
				totalMenuItems = $('#menu-to-edit li').length,
				hasSameDepthSibling = menuItem.nextAll( '.menu-item-depth-' + depth ).length;

				menuItem.find( '.field-move' ).toggle( totalMenuItems > 1 );

			// Where can they move this menu item?
			if ( 0 !== position ) {
				thisLink = menuItem.find( '.menus-move-up' );
				thisLink.attr( 'aria-label', menus.moveUp ).css( 'display', 'inline' );
			}

			if ( 0 !== position && isPrimaryMenuItem ) {
				thisLink = menuItem.find( '.menus-move-top' );
				thisLink.attr( 'aria-label', menus.moveToTop ).css( 'display', 'inline' );
			}

			if ( position + 1 !== totalMenuItems && 0 !== position ) {
				thisLink = menuItem.find( '.menus-move-down' );
				thisLink.attr( 'aria-label', menus.moveDown ).css( 'display', 'inline' );
			}

			if ( 0 === position && 0 !== hasSameDepthSibling ) {
				thisLink = menuItem.find( '.menus-move-down' );
				thisLink.attr( 'aria-label', menus.moveDown ).css( 'display', 'inline' );
			}

			if ( ! isPrimaryMenuItem ) {
				thisLink = menuItem.find( '.menus-move-left' ),
				thisLinkText = menus.outFrom.replace( '%s', prevItemNameLeft );
				thisLink.attr( 'aria-label', menus.moveOutFrom.replace( '%s', prevItemNameLeft ) ).text( thisLinkText ).css( 'display', 'inline' );
			}

			if ( 0 !== position ) {
				if ( menuItem.find( '.menu-item-data-parent-id' ).val() !== menuItem.prev().find( '.menu-item-data-db-id' ).val() ) {
					thisLink = menuItem.find( '.menus-move-right' ),
					thisLinkText = menus.under.replace( '%s', prevItemNameRight );
					thisLink.attr( 'aria-label', menus.moveUnder.replace( '%s', prevItemNameRight ) ).text( thisLinkText ).css( 'display', 'inline' );
				}
			}

			if ( isPrimaryMenuItem ) {
				primaryItems = $( '.menu-item-depth-0' ),
				itemPosition = primaryItems.index( menuItem ) + 1,
				totalMenuItems = primaryItems.length,

				// String together help text for primary menu items
				title = menus.menuFocus.replace( '%1$s', itemName ).replace( '%2$d', itemPosition ).replace( '%3$d', totalMenuItems );
			} else {
				parentItem = menuItem.prevAll( '.menu-item-depth-' + parseInt( depth - 1, 10 ) ).first(),
				parentItemId = parentItem.find( '.menu-item-data-db-id' ).val(),
				parentItemName = parentItem.find( '.menu-item-title' ).text(),
				subItems = $( '.menu-item .menu-item-data-parent-id[value="' + parentItemId + '"]' ),
				itemPosition = $( subItems.parents('.menu-item').get().reverse() ).index( menuItem ) + 1;

				// String together help text for sub menu items
				title = menus.subMenuFocus.replace( '%1$s', itemName ).replace( '%2$d', itemPosition ).replace( '%3$s', parentItemName );
			}

			$this.attr( 'aria-label', title );

			// Mark this item's accessibility as refreshed
			$this.data( 'needs_accessibility_refresh', false );
		},

		/**
		 * refreshAdvancedAccessibility
		 *
		 * 