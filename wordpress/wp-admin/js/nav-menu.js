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
						})