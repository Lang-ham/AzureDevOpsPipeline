
/* global _wpCustomizeHeader, _wpCustomizeBackground, _wpMediaViewsL10n, MediaElementPlayer, console, confirm */
(function( exports, $ ){
	var Container, focus, normalizedTransitionendEventName, api = wp.customize;

	/**
	 * A notification that is displayed in a full-screen overlay.
	 *
	 * @since 4.9.0
	 * @class
	 * @augments wp.customize.Notification
	 */
	api.OverlayNotification = api.Notification.extend({

		/**
		 * Whether the notification should show a loading spinner.
		 *
		 * @since 4.9.0
		 * @var {boolean}
		 */
		loading: false,

		/**
		 * Initialize.
		 *
		 * @since 4.9.0
		 *
		 * @param {string} code - Code.
		 * @param {object} params - Params.
		 */
		initialize: function( code, params ) {
			var notification = this;
			api.Notification.prototype.initialize.call( notification, code, params );
			notification.containerClasses += ' notification-overlay';
			if ( notification.loading ) {
				notification.containerClasses += ' notification-loading';
			}
		},

		/**
		 * Render notification.
		 *
		 * @since 4.9.0
		 *
		 * @return {jQuery} Notification container.
		 */
		render: function() {
			var li = api.Notification.prototype.render.call( this );
			li.on( 'keydown', _.bind( this.handleEscape, this ) );
			return li;
		},

		/**
		 * Stop propagation on escape key presses, but also dismiss notification if it is dismissible.
		 *
		 * @since 4.9.0
		 *
		 * @param {jQuery.Event} event - Event.
		 * @returns {void}
		 */
		handleEscape: function( event ) {
			var notification = this;
			if ( 27 === event.which ) {
				event.stopPropagation();
				if ( notification.dismissible && notification.parent ) {
					notification.parent.remove( notification.code );
				}
			}
		}
	});

	/**
	 * A collection of observable notifications.
	 *
	 * @since 4.9.0
	 * @class
	 * @augments wp.customize.Values
	 */
	api.Notifications = api.Values.extend({

		/**
		 * Whether the alternative style should be used.
		 *
		 * @since 4.9.0
		 * @type {boolean}
		 */
		alt: false,

		/**
		 * The default constructor for items of the collection.
		 *
		 * @since 4.9.0
		 * @type {object}
		 */
		defaultConstructor: api.Notification,

		/**
		 * Initialize notifications area.
		 *
		 * @since 4.9.0
		 * @constructor
		 * @param {object}  options - Options.
		 * @param {jQuery}  [options.container] - Container element for notifications. This can be injected later.
		 * @param {boolean} [options.alt] - Whether alternative style should be used when rendering notifications.
		 * @returns {void}
		 * @this {wp.customize.Notifications}
		 */
		initialize: function( options ) {
			var collection = this;

			api.Values.prototype.initialize.call( collection, options );

			_.bindAll( collection, 'constrainFocus' );

			// Keep track of the order in which the notifications were added for sorting purposes.
			collection._addedIncrement = 0;
			collection._addedOrder = {};

			// Trigger change event when notification is added or removed.
			collection.bind( 'add', function( notification ) {
				collection.trigger( 'change', notification );
			});
			collection.bind( 'removed', function( notification ) {
				collection.trigger( 'change', notification );
			});
		},

		/**
		 * Get the number of notifications added.
		 *
		 * @since 4.9.0
		 * @return {number} Count of notifications.
		 */
		count: function() {
			return _.size( this._value );
		},

		/**
		 * Add notification to the collection.
		 *
		 * @since 4.9.0
		 *
		 * @param {string|wp.customize.Notification} notification - Notification object to add. Alternatively code may be supplied, and in that case the second notificationObject argument must be supplied.
		 * @param {wp.customize.Notification} [notificationObject] - Notification to add when first argument is the code string.
		 * @returns {wp.customize.Notification} Added notification (or existing instance if it was already added).
		 */
		add: function( notification, notificationObject ) {
			var collection = this, code, instance;
			if ( 'string' === typeof notification ) {
				code = notification;
				instance = notificationObject;
			} else {
				code = notification.code;
				instance = notification;
			}
			if ( ! collection.has( code ) ) {
				collection._addedIncrement += 1;
				collection._addedOrder[ code ] = collection._addedIncrement;
			}
			return api.Values.prototype.add.call( collection, code, instance );
		},

		/**
		 * Add notification to the collection.
		 *
		 * @since 4.9.0
		 * @param {string} code - Notification code to remove.
		 * @return {api.Notification} Added instance (or existing instance if it was already added).
		 */
		remove: function( code ) {
			var collection = this;
			delete collection._addedOrder[ code ];
			return api.Values.prototype.remove.call( this, code );
		},

		/**
		 * Get list of notifications.
		 *
		 * Notifications may be sorted by type followed by added time.
		 *
		 * @since 4.9.0
		 * @param {object}  args - Args.
		 * @param {boolean} [args.sort=false] - Whether to return the notifications sorted.
		 * @return {Array.<wp.customize.Notification>} Notifications.
		 * @this {wp.customize.Notifications}
		 */
		get: function( args ) {
			var collection = this, notifications, errorTypePriorities, params;
			notifications = _.values( collection._value );

			params = _.extend(
				{ sort: false },
				args
			);

			if ( params.sort ) {
				errorTypePriorities = { error: 4, warning: 3, success: 2, info: 1 };
				notifications.sort( function( a, b ) {
					var aPriority = 0, bPriority = 0;
					if ( ! _.isUndefined( errorTypePriorities[ a.type ] ) ) {
						aPriority = errorTypePriorities[ a.type ];
					}
					if ( ! _.isUndefined( errorTypePriorities[ b.type ] ) ) {
						bPriority = errorTypePriorities[ b.type ];
					}
					if ( aPriority !== bPriority ) {
						return bPriority - aPriority; // Show errors first.
					}
					return collection._addedOrder[ b.code ] - collection._addedOrder[ a.code ]; // Show newer notifications higher.
				});
			}

			return notifications;
		},

		/**
		 * Render notifications area.
		 *
		 * @since 4.9.0
		 * @returns {void}
		 * @this {wp.customize.Notifications}
		 */
		render: function() {
			var collection = this,
				notifications, hadOverlayNotification = false, hasOverlayNotification, overlayNotifications = [],
				previousNotificationsByCode = {},
				listElement, focusableElements;

			// Short-circuit if there are no container to render into.
			if ( ! collection.container || ! collection.container.length ) {
				return;
			}

			notifications = collection.get( { sort: true } );
			collection.container.toggle( 0 !== notifications.length );

			// Short-circuit if there are no changes to the notifications.
			if ( collection.container.is( collection.previousContainer ) && _.isEqual( notifications, collection.previousNotifications ) ) {
				return;
			}

			// Make sure list is part of the container.
			listElement = collection.container.children( 'ul' ).first();
			if ( ! listElement.length ) {
				listElement = $( '<ul></ul>' );
				collection.container.append( listElement );
			}

			// Remove all notifications prior to re-rendering.
			listElement.find( '> [data-code]' ).remove();

			_.each( collection.previousNotifications, function( notification ) {
				previousNotificationsByCode[ notification.code ] = notification;
			});

			// Add all notifications in the sorted order.
			_.each( notifications, function( notification ) {
				var notificationContainer;
				if ( wp.a11y && ( ! previousNotificationsByCode[ notification.code ] || ! _.isEqual( notification.message, previousNotificationsByCode[ notification.code ].message ) ) ) {
					wp.a11y.speak( notification.message, 'assertive' );
				}
				notificationContainer = $( notification.render() );
				notification.container = notificationContainer;
				listElement.append( notificationContainer ); // @todo Consider slideDown() as enhancement.

				if ( notification.extended( api.OverlayNotification ) ) {
					overlayNotifications.push( notification );
				}
			});
			hasOverlayNotification = Boolean( overlayNotifications.length );

			if ( collection.previousNotifications ) {
				hadOverlayNotification = Boolean( _.find( collection.previousNotifications, function( notification ) {
					return notification.extended( api.OverlayNotification );
				} ) );
			}

			if ( hasOverlayNotification !== hadOverlayNotification ) {
				$( document.body ).toggleClass( 'customize-loading', hasOverlayNotification );
				collection.container.toggleClass( 'has-overlay-notifications', hasOverlayNotification );
				if ( hasOverlayNotification ) {
					collection.previousActiveElement = document.activeElement;
					$( document ).on( 'keydown', collection.constrainFocus );
				} else {
					$( document ).off( 'keydown', collection.constrainFocus );
				}
			}

			if ( hasOverlayNotification ) {
				collection.focusContainer = overlayNotifications[ overlayNotifications.length - 1 ].container;
				collection.focusContainer.prop( 'tabIndex', -1 );
				focusableElements = collection.focusContainer.find( ':focusable' );
				if ( focusableElements.length ) {
					focusableElements.first().focus();
				} else {
					collection.focusContainer.focus();
				}
			} else if ( collection.previousActiveElement ) {
				$( collection.previousActiveElement ).focus();
				collection.previousActiveElement = null;
			}

			collection.previousNotifications = notifications;
			collection.previousContainer = collection.container;
			collection.trigger( 'rendered' );
		},

		/**
		 * Constrain focus on focus container.
		 *
		 * @since 4.9.0
		 *
		 * @param {jQuery.Event} event - Event.
		 * @returns {void}
		 */
		constrainFocus: function constrainFocus( event ) {
			var collection = this, focusableElements;

			// Prevent keys from escaping.
			event.stopPropagation();

			if ( 9 !== event.which ) { // Tab key.
				return;
			}

			focusableElements = collection.focusContainer.find( ':focusable' );
			if ( 0 === focusableElements.length ) {
				focusableElements = collection.focusContainer;
			}

			if ( ! $.contains( collection.focusContainer[0], event.target ) || ! $.contains( collection.focusContainer[0], document.activeElement ) ) {
				event.preventDefault();
				focusableElements.first().focus();
			} else if ( focusableElements.last().is( event.target ) && ! event.shiftKey ) {
				event.preventDefault();
				focusableElements.first().focus();
			} else if ( focusableElements.first().is( event.target ) && event.shiftKey ) {
				event.preventDefault();
				focusableElements.last().focus();
			}
		}
	});

	/**
	 * A Customizer Setting.
	 *
	 * A setting is WordPress data (theme mod, option, menu, etc.) that the user can
	 * draft changes to in the Customizer.
	 *
	 * @see PHP class WP_Customize_Setting.
	 *
	 * @since 3.4.0
	 * @class
	 * @augments wp.customize.Value
	 * @augments wp.customize.Class
	 */
	api.Setting = api.Value.extend({

		/**
		 * Default params.
		 *
		 * @since 4.9.0
		 * @var {object}
		 */
		defaults: {
			transport: 'refresh',
			dirty: false
		},

		/**
		 * Initialize.
		 *
		 * @since 3.4.0
		 *
		 * @param {string}  id                          - The setting ID.
		 * @param {*}       value                       - The initial value of the setting.
		 * @param {object}  [options={}]                - Options.
		 * @param {string}  [options.transport=refresh] - The transport to use for previewing. Supports 'refresh' and 'postMessage'.
		 * @param {boolean} [options.dirty=false]       - Whether the setting should be considered initially dirty.
		 * @param {object}  [options.previewer]         - The Previewer instance to sync with. Defaults to wp.customize.previewer.
		 */
		initialize: function( id, value, options ) {
			var setting = this, params;
			params = _.extend(
				{ previewer: api.previewer },
				setting.defaults,
				options || {}
			);

			api.Value.prototype.initialize.call( setting, value, params );

			setting.id = id;
			setting._dirty = params.dirty; // The _dirty property is what the Customizer reads from.
			setting.notifications = new api.Notifications();

			// Whenever the setting's value changes, refresh the preview.
			setting.bind( setting.preview );
		},

		/**
		 * Refresh the preview, respective of the setting's refresh policy.
		 *
		 * If the preview hasn't sent a keep-alive message and is likely
		 * disconnected by having navigated to a non-allowed URL, then the
		 * refresh transport will be forced when postMessage is the transport.
		 * Note that postMessage does not throw an error when the recipient window
		 * fails to match the origin window, so using try/catch around the
		 * previewer.send() call to then fallback to refresh will not work.
		 *
		 * @since 3.4.0
		 * @access public
		 *
		 * @returns {void}
		 */
		preview: function() {
			var setting = this, transport;
			transport = setting.transport;

			if ( 'postMessage' === transport && ! api.state( 'previewerAlive' ).get() ) {
				transport = 'refresh';
			}

			if ( 'postMessage' === transport ) {
				setting.previewer.send( 'setting', [ setting.id, setting() ] );
			} else if ( 'refresh' === transport ) {
				setting.previewer.refresh();
			}
		},

		/**
		 * Find controls associated with this setting.
		 *
		 * @since 4.6.0
		 * @returns {wp.customize.Control[]} Controls associated with setting.
		 */
		findControls: function() {
			var setting = this, controls = [];
			api.control.each( function( control ) {
				_.each( control.settings, function( controlSetting ) {
					if ( controlSetting.id === setting.id ) {
						controls.push( control );
					}
				} );
			} );
			return controls;
		}
	});

	/**
	 * Current change count.
	 *
	 * @since 4.7.0
	 * @type {number}
	 * @protected
	 */
	api._latestRevision = 0;

	/**
	 * Last revision that was saved.
	 *
	 * @since 4.7.0
	 * @type {number}
	 * @protected
	 */
	api._lastSavedRevision = 0;

	/**
	 * Latest revisions associated with the updated setting.
	 *
	 * @since 4.7.0
	 * @type {object}
	 * @protected
	 */
	api._latestSettingRevisions = {};

	/*
	 * Keep track of the revision associated with each updated setting so that
	 * requestChangesetUpdate knows which dirty settings to include. Also, once
	 * ready is triggered and all initial settings have been added, increment
	 * revision for each newly-created initially-dirty setting so that it will
	 * also be included in changeset update requests.
	 */
	api.bind( 'change', function incrementChangedSettingRevision( setting ) {
		api._latestRevision += 1;
		api._latestSettingRevisions[ setting.id ] = api._latestRevision;
	} );
	api.bind( 'ready', function() {
		api.bind( 'add', function incrementCreatedSettingRevision( setting ) {
			if ( setting._dirty ) {
				api._latestRevision += 1;
				api._latestSettingRevisions[ setting.id ] = api._latestRevision;
			}
		} );
	} );

	/**
	 * Get the dirty setting values.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param {object} [options] Options.
	 * @param {boolean} [options.unsaved=false] Whether only values not saved yet into a changeset will be returned (differential changes).
	 * @returns {object} Dirty setting values.
	 */
	api.dirtyValues = function dirtyValues( options ) {
		var values = {};
		api.each( function( setting ) {
			var settingRevision;

			if ( ! setting._dirty ) {
				return;
			}

			settingRevision = api._latestSettingRevisions[ setting.id ];

			// Skip including settings that have already been included in the changeset, if only requesting unsaved.
			if ( api.state( 'changesetStatus' ).get() && ( options && options.unsaved ) && ( _.isUndefined( settingRevision ) || settingRevision <= api._lastSavedRevision ) ) {
				return;
			}

			values[ setting.id ] = setting.get();
		} );
		return values;
	};

	/**
	 * Request updates to the changeset.
	 *
	 * @since 4.7.0
	 * @access public
	 *
	 * @param {object}  [changes] - Mapping of setting IDs to setting params each normally including a value property, or mapping to null.
	 *                             If not provided, then the changes will still be obtained from unsaved dirty settings.
	 * @param {object}  [args] - Additional options for the save request.
	 * @param {boolean} [args.autosave=false] - Whether changes will be stored in autosave revision if the changeset has been promoted from an auto-draft.
	 * @param {boolean} [args.force=false] - Send request to update even when there are no changes to submit. This can be used to request the latest status of the changeset on the server.
	 * @param {string}  [args.title] - Title to update in the changeset. Optional.
	 * @param {string}  [args.date] - Date to update in the changeset. Optional.
	 * @returns {jQuery.Promise} Promise resolving with the response data.
	 */
	api.requestChangesetUpdate = function requestChangesetUpdate( changes, args ) {
		var deferred, request, submittedChanges = {}, data, submittedArgs;
		deferred = new $.Deferred();

		// Prevent attempting changeset update while request is being made.
		if ( 0 !== api.state( 'processing' ).get() ) {
			deferred.reject( 'already_processing' );
			return deferred.promise();
		}

		submittedArgs = _.extend( {
			title: null,
			date: null,
			autosave: false,
			force: false
		}, args );

		if ( changes ) {
			_.extend( submittedChanges, changes );
		}

		// Ensure all revised settings (changes pending save) are also included, but not if marked for deletion in changes.
		_.each( api.dirtyValues( { unsaved: true } ), function( dirtyValue, settingId ) {
			if ( ! changes || null !== changes[ settingId ] ) {
				submittedChanges[ settingId ] = _.extend(
					{},
					submittedChanges[ settingId ] || {},
					{ value: dirtyValue }
				);
			}
		} );

		// Allow plugins to attach additional params to the settings.
		api.trigger( 'changeset-save', submittedChanges, submittedArgs );

		// Short-circuit when there are no pending changes.
		if ( ! submittedArgs.force && _.isEmpty( submittedChanges ) && null === submittedArgs.title && null === submittedArgs.date ) {
			deferred.resolve( {} );
			return deferred.promise();
		}

		// A status would cause a revision to be made, and for this wp.customize.previewer.save() should be used. Status is also disallowed for revisions regardless.
		if ( submittedArgs.status ) {
			return deferred.reject( { code: 'illegal_status_in_changeset_update' } ).promise();
		}

		// Dates not beung allowed for revisions are is a technical limitation of post revisions.
		if ( submittedArgs.date && submittedArgs.autosave ) {
			return deferred.reject( { code: 'illegal_autosave_with_date_gmt' } ).promise();
		}

		// Make sure that publishing a changeset waits for all changeset update requests to complete.
		api.state( 'processing' ).set( api.state( 'processing' ).get() + 1 );
		deferred.always( function() {
			api.state( 'processing' ).set( api.state( 'processing' ).get() - 1 );
		} );

		// Ensure that if any plugins add data to save requests by extending query() that they get included here.
		data = api.previewer.query( { excludeCustomizedSaved: true } );
		delete data.customized; // Being sent in customize_changeset_data instead.
		_.extend( data, {
			nonce: api.settings.nonce.save,
			customize_theme: api.settings.theme.stylesheet,
			customize_changeset_data: JSON.stringify( submittedChanges )
		} );
		if ( null !== submittedArgs.title ) {
			data.customize_changeset_title = submittedArgs.title;
		}
		if ( null !== submittedArgs.date ) {
			data.customize_changeset_date = submittedArgs.date;
		}
		if ( false !== submittedArgs.autosave ) {
			data.customize_changeset_autosave = 'true';
		}

		// Allow plugins to modify the params included with the save request.
		api.trigger( 'save-request-params', data );

		request = wp.ajax.post( 'customize_save', data );

		request.done( function requestChangesetUpdateDone( data ) {
			var savedChangesetValues = {};

			// Ensure that all settings updated subsequently will be included in the next changeset update request.
			api._lastSavedRevision = Math.max( api._latestRevision, api._lastSavedRevision );

			api.state( 'changesetStatus' ).set( data.changeset_status );

			if ( data.changeset_date ) {
				api.state( 'changesetDate' ).set( data.changeset_date );
			}

			deferred.resolve( data );
			api.trigger( 'changeset-saved', data );

			if ( data.setting_validities ) {
				_.each( data.setting_validities, function( validity, settingId ) {
					if ( true === validity && _.isObject( submittedChanges[ settingId ] ) && ! _.isUndefined( submittedChanges[ settingId ].value ) ) {
						savedChangesetValues[ settingId ] = submittedChanges[ settingId ].value;
					}
				} );
			}

			api.previewer.send( 'changeset-saved', _.extend( {}, data, { saved_changeset_values: savedChangesetValues } ) );
		} );
		request.fail( function requestChangesetUpdateFail( data ) {
			deferred.reject( data );
			api.trigger( 'changeset-error', data );
		} );
		request.always( function( data ) {
			if ( data.setting_validities ) {
				api._handleSettingValidities( {
					settingValidities: data.setting_validities
				} );
			}
		} );

		return deferred.promise();
	};

	/**
	 * Watch all changes to Value properties, and bubble changes to parent Values instance
	 *
	 * @since 4.1.0
	 *
	 * @param {wp.customize.Class} instance
	 * @param {Array}              properties  The names of the Value instances to watch.
	 */
	api.utils.bubbleChildValueChanges = function ( instance, properties ) {
		$.each( properties, function ( i, key ) {
			instance[ key ].bind( function ( to, from ) {
				if ( instance.parent && to !== from ) {
					instance.parent.trigger( 'change', instance );
				}
			} );
		} );
	};

	/**
	 * Expand a panel, section, or control and focus on the first focusable element.
	 *
	 * @since 4.1.0
	 *
	 * @param {Object}   [params]
	 * @param {Function} [params.completeCallback]
	 */
	focus = function ( params ) {
		var construct, completeCallback, focus, focusElement;
		construct = this;
		params = params || {};
		focus = function () {
			var focusContainer;
			if ( ( construct.extended( api.Panel ) || construct.extended( api.Section ) ) && construct.expanded && construct.expanded() ) {
				focusContainer = construct.contentContainer;
			} else {
				focusContainer = construct.container;
			}

			focusElement = focusContainer.find( '.control-focus:first' );
			if ( 0 === focusElement.length ) {
				// Note that we can't use :focusable due to a jQuery UI issue. See: https://github.com/jquery/jquery-ui/pull/1583
				focusElement = focusContainer.find( 'input, select, textarea, button, object, a[href], [tabindex]' ).filter( ':visible' ).first();
			}
			focusElement.focus();
		};
		if ( params.completeCallback ) {
			completeCallback = params.completeCallback;
			params.completeCallback = function () {
				focus();
				completeCallback();
			};
		} else {
			params.completeCallback = focus;
		}

		api.state( 'paneVisible' ).set( true );
		if ( construct.expand ) {
			construct.expand( params );
		} else {
			params.completeCallback();
		}
	};

	/**
	 * Stable sort for Panels, Sections, and Controls.
	 *
	 * If a.priority() === b.priority(), then sort by their respective params.instanceNumber.
	 *
	 * @since 4.1.0
	 *
	 * @param {(wp.customize.Panel|wp.customize.Section|wp.customize.Control)} a
	 * @param {(wp.customize.Panel|wp.customize.Section|wp.customize.Control)} b
	 * @returns {Number}
	 */
	api.utils.prioritySort = function ( a, b ) {
		if ( a.priority() === b.priority() && typeof a.params.instanceNumber === 'number' && typeof b.params.instanceNumber === 'number' ) {
			return a.params.instanceNumber - b.params.instanceNumber;
		} else {
			return a.priority() - b.priority();
		}
	};
