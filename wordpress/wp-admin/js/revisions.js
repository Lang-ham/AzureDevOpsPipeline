/* global isRtl */
/**
 * @file Revisions interface functions, Backbone classes and
 * the revisions.php document.ready bootstrap.
 *
 */

window.wp = window.wp || {};

(function($) {
	var revisions;
	/**
	 * Expose the module in window.wp.revisions.
	 */
	revisions = wp.revisions = { model: {}, view: {}, controller: {} };

	// Link post revisions data served from the back end.
	revisions.settings = window._wpRevisionsSettings || {};

	// For debugging
	revisions.debug = false;

	/**
	 * wp.revisions.log
	 *
	 * A debugging utility for revisions. Works only when a
	 * debug flag is on and the browser supports it.
	 */
	revisions.log = function() {
		if ( window.console && revisions.debug ) {
			window.console.log.apply( window.console, arguments );
		}
	};

	// Handy functions to help with positioning
	$.fn.allOffsets = function() {
		var offset = this.offset() || {top: 0, left: 0}, win = $(window);
		return _.extend( offset, {
			right:  win.width()  - offset.left - this.outerWidth(),
			bottom: win.height() - offset.top  - this.outerHeight()
		});
	};

	$.fn.allPositions = function() {
		var position = this.position() || {top: 0, left: 0}, parent = this.parent();
		return _.extend( position, {
			right:  parent.outerWidth()  - position.left - this.outerWidth(),
			bottom: parent.outerHeight() - position.top  - this.outerHeight()
		});
	};

	/**
	 * ========================================================================
	 * MODELS
	 * ========================================================================
	 */
	revisions.model.Slider = Backbone.Model.extend({
		defaults: {
			value: null,
			values: null,
			min: 0,
			max: 1,
			step: 1,
			range: false,
			compareTwoMode: false
		},

		initialize: function( options ) {
			this.frame = options.frame;
			this.revisions = options.revisions;

			// Listen for changes to the revisions or mode from outside
			this.listenTo( this.frame, 'update:revisions', this.receiveRevisions );
			this.listenTo( this.frame, 'change:compareTwoMode', this.updateMode );

			// Listen for internal changes
			this.on( 'change:from', this.handleLocalChanges );
			this.on( 'change:to', this.handleLocalChanges );
			this.on( 'change:compareTwoMode', this.updateSliderSettings );
			this.on( 'update:revisions', this.updateSliderSettings );

			// Listen for changes to the hovered revision
			this.on( 'change:hoveredRevision', this.hoverRevision );

			this.set({
				max:   this.revisions.length - 1,
				compareTwoMode: this.frame.get('compareTwoMode'),
				from: this.frame.get('from'),
				to: this.frame.get('to')
			});
			this.updateSliderSettings();
		},

		getSliderValue: function( a, b ) {
			return isRtl ? this.revisions.length - this.revisions.indexOf( this.get(a) ) - 1 : this.revisions.indexOf( this.get(b) );
		},

		updateSliderSettings: function() {
			if ( this.get('compareTwoMode') ) {
				this.set({
					values: [
						this.getSliderValue( 'to', 'from' ),
						this.getSliderValue( 'from', 'to' )
					],
					value: null,
					range: true // ensures handles cannot cross
				});
			} else {
				this.set({
					value: this.getSliderValue( 'to', 'to' ),
					values: null,
					range: false
				});
			}
			this.trigger( 'update:slider' );
		},

		// Called when a revision is hovered
		hoverRevision: function( model, value ) {
			this.trigger( 'hovered:revision', value );
		},

		// Called when `compareTwoMode` changes
		updateMode: function( model, value ) {
			this.set({ compareTwoMode: value });
		},

		// Called when `from` or `to` changes in the local model
		handleLocalChanges: function() {
			this.frame.set({
				from: this.get('from'),
				to: this.get('to')
			});
		},

		// Receives revisions changes from outside the model
		receiveRevisions: function( from, to ) {
			// Bail if nothing changed
			if ( this.get('from') === from && this.get('to') === to ) {
				return;
			}

			this.set({ from: from, to: to }, { silent: true });
			this.trigger( 'update:revisions', from, to );
		}

	});

	revisions.model.Tooltip = Backbone.Model.extend({
		defaults: {
			revision: null,
			offset: {},
			hovering: false, // Whether the mouse is hovering
			scrubbing: false // Whether the mouse is scrubbing
		},

		initialize: function( options ) {
			this.frame = options.frame;
			this.revisions = options.revisions;
			this.slider = options.slider;

			this.listenTo( this.slider, 'hovered:revision', this.updateRevision );
			this.listenTo( this.slider, 'change:hovering', this.setHovering );
			this.listenTo( this.slider, 'change:scrubbing', this.setScrubbing );
		},


		updateRevision: function( revision ) {
			this.set({ revision: revision });
		},

		setHovering: function( model, value ) {
			this.set({ hovering: value });
		},

		setScrubbing: function( model, value ) {
			this.set({ scrubbing: value });
		}
	});

	revisions.model.Revision = Backbone.Model.extend({});

	/**
	 * wp.revisions.model.Revisions
	 *
	 * A collection of post revisions.
	 */
	revisions.model.Revisions = Backbone.Collection.extend({
		model: revisions.model.Revision,

		initialize: function() {
			_.bindAll( this, 'next', 'prev' );
		},

		next: function( revision ) {
			var index = this.indexOf( revision );

			if ( index !== -1 && index !== this.length - 1 ) {
				return this.at( index + 1 );
			}
		},

		prev: function( revision ) {
			var index = this.indexOf( revision );

			if ( index !== -1 && index !== 0 ) {
				return this.at( index - 1 );
			}
		}
	});

	revisions.model.Field = Backbone.Model.extend({});

	revisions.model.Fields = Backbone.Collection.extend({
		model: revisions.model.Field
	});

	revisions.model.Diff = Backbone.Model.extend({
		initialize: function() {
			var fields = this.get('fields');
			this.unset('fields');

			this.fields = new revisions.model.Fields( fields );
		}
	});

	revisions.model.Diffs = Backbone.Collection.extend({
		initialize: function( models, options ) {
			_.bindAll( this, 'getClosestUnloaded' );
			this.loadAll = _.once( this._loadAll );
			this.revisions = options.revisions;
			this.postId = options.postId;
			this.requests  = {};
		},

		model: revisions.model.Diff,

		ensure: function( id, context ) {
			var diff     = this.get( id ),
				request  = this.requests[ id ],
				deferred = $.Deferred(),
				ids      = {},
				from     = id.split(':')[0],
				to       = id.split(':')[1];
			ids[id] = true;

			wp.revisions.log( 'ensure', id );

			this.trigger( 'ensure', ids, from, to, deferred.promise() );

			if ( diff ) {
				deferred.resolveWith( context, [ diff ] );
			} else {
				this.trigger( 'ensure:load', ids, from, to, deferred.promise() );
				_.each( ids, _.bind( function( id ) {
					// Remove anything that has an ongoing request
					if ( this.requests[ id ] ) {
						delete ids[ id ];
					}
					// Remove anything we already have
					if ( this.get( id ) ) {
						delete ids[ id ];
					}
				}, this ) );
				if ( ! request ) {
					// Always include the ID that started this ensure
					ids[ id ] = true;
					request   = this.load( _.keys( ids ) );
				}

				request.done( _.bind( function() {
					deferred.resolveWith( context, [ this.get( id ) ] );
				}, this ) ).fail( _.bind( function() {
					deferred.reject();
			