/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, {
/******/ 				configurable: false,
/******/ 				enumerable: true,
/******/ 				get: getter
/******/ 			});
/******/ 		}
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 0);
/******/ })
/************************************************************************/
/******/ ([
/* 0 */
/***/ (function(module, exports, __webpack_require__) {

var media = wp.media,
	baseSettings = window._wpmejsSettings || {},
	l10n = window._wpMediaViewsL10n || {};

/**
 *
 * @summary Defines the wp.media.mixin object.
 *
 * @mixin
 *
 * @since 4.2.0
 */
wp.media.mixin = {
	mejsSettings: baseSettings,

	/**
	 * @summary Pauses and removes all players.
	 *
	 * @since 4.2.0
	 *
	 * @return {void}
	 */
	removeAllPlayers: function() {
		var p;

		if ( window.mejs && window.mejs.players ) {
			for ( p in window.mejs.players ) {
				window.mejs.players[p].pause();
				this.removePlayer( window.mejs.players[p] );
			}
		}
	},

	/**
	 * @summary Removes the player.
	 *
	 * Override the MediaElement method for removing a player.
	 * MediaElement tries to pull the audio/video tag out of
	 * its container and re-add it to the DOM.
	 *
	 * @since 4.2.0
	 *
	 * @return {void}
	 */
	removePlayer: function(t) {
		var featureIndex, feature;

		if ( ! t.options ) {
			return;
		}

		// invoke features cleanup
		for ( featureIndex in t.options.features ) {
			feature = t.options.features[featureIndex];
			if ( t['clean' + feature] ) {
				try {
					t['clean' + feature](t);
				} catch (e) {}
			}
		}

		if ( ! t.isDynamic ) {
			t.node.remove();
		}

		if ( 'html5' !== t.media.rendererName ) {
			t.media.remove();
		}

		delete window.mejs.players[t.id];

		t.container.remove();
		t.globalUnbind('resize', t.globalResizeCallback);
		t.globalUnbind('keydown', t.globalKeydownCallback);
		t.globalUnbind('click', t.globalClickCallback);
		delete t.media.player;
	},

	/**
	 *
	 * @summary Removes and resets all players.
	 *
	 * Allows any class that has set 'player' to a MediaElementPlayer
	 * instance to remove the player when listening to events.
	 *
	 * Examples: modal closes, shortcode properties are removed, etc.
	 *
	 * @since 4.2.0
	 */
	unsetPlayers : function() {
		if ( this.players && this.players.length ) {
			_.each( this.players, function (player) {
				player.pause();
				wp.media.mixin.removePlayer( player );
			} );
			this.players = [];
		}
	}
};

/**
 * @summary Shortcode modeling for playlists.
 *
 * @since 4.2.0
 */
wp.media.playlist = new wp.media.collection({
	tag: 'playlist',
	editTitle : l10n.editPlaylistTitle,
	defaults : {
		id: wp.media.view.settings.post.id,
		style: 'light',
		tracklist: true,
		tracknumbers: true,
		images: true,
		artists: true,
		type: 'audio'
	}
});

/**
 * @summary Shortcode modeling for audio.
 *
 * `edit()` prepares the shortcode for the media modal.
 * `shortcode()` builds the new shortcode after an update.
 *
 * @namespace
 *
 * @since 4.2.0
 */
wp.media.audio = {
	coerce : wp.media.coerce,

	defaults : {
		id : wp.media.view.settings.post.id,
		src : '',
		loop : false,
		autoplay : false,
		preload : 'none',
		width : 400
	},

	/**
	 * @summary Instantiates a new media object with the next matching shortcode.
	 *
	 * @since 4.2.0
	 *
	 * @param {string} data The text to apply the shortcode on.
	 * @returns {wp.media} The media object.
	 */
	edit : function( data ) {
		var frame, shortcode = wp.shortcode.next( 'audio', data ).shortcode;

		frame = wp.media({
			frame: 'audio',
			state: 'audio-details',
			metadata: _.defaults( shortcode.attrs.named, this.defaults )
		});

		return frame;
	},

	/**
	 * @summary Generates an audio shortcode.
	 *
	 * @since 4.2.0
	 *
	 * @param {Array} model Array with attributes for the shortcode.
	 * @returns {wp.shortcode} The audio shortcode object.
	 */
	shortcode : function( model ) {
		var content;

		_.each( this.defaults, function( value, key ) {
			model[ key ] = this.coerce( model, key );

			if ( value === model[ key ] ) {
				delete model[ key ];
			}
		}, this );

		content = model.content;
		delete model.content;

		return new wp.shortcode({
			tag: 'audio',
			attrs: model,
			content: content
		});
	}
};

/**
 * @summary Shortcode modeling for video.
 *
 *  `edit()` prepares the shortcode for the media modal.
 *  `shortcode()` builds the new shortcode after update.
 *
 * @since 4.2.0
 *
 * @namespace
 */
wp.media.video = {
	coerce : wp.media.coerce,

	defaults : {
		id : wp.media.view.settings.post.id,
		src : '',
		poster : '',
		loop : false,
		autoplay : false,
		preload : 'metadata',
		content : '',
		width : 640,
		height : 360
	},

	/**
	 * @summary Instantiates a new media object with the next matching shortcode.
	 *
	 * @since 4.2.0
	 *
	 * @param {string} data The text to apply the shortcode on.
	 * @returns {wp.media} The media object.
	 */
	edit : function( data ) {
		var frame,
			shortcode = wp.shortcode.next( 'video', data ).shortcode,
			attrs;

		attrs = shortcode.attrs.named;
		attrs.content = shortcode.content;

		frame = wp.media({
			frame: 'video',
			state: 'video-details',
			metadata: _.defaults( attrs, this.defaults )
		});

		return frame;
	},

	/**
	 * @summary Generates an video shortcode.
	 *
	 * @since 4.2.0
	 *
	 * @param {Array} model Array with attributes for the shortcode.
	 * @returns {wp.shortcode} The video shortcode object.
	 */
	shortcode : function( model ) {
		var content;

		_.each( this.defaults, function( value, key ) {
			model[ key ] = this.coerce( model, key );

			if ( value === model[ key ] ) {
				delete model[ key ];
			}
		}, this );

		content = model.content;
		delete model.content;

		return new wp.shortcode({
			tag: 'video',
			attrs: model,
			content: content
		});
	}
};

media.model.PostMedia = __webpack_require__( 1 );
media.controller.AudioDetails = __webpack_require__( 2 );
media.controller.VideoDetails = __webpack_require__( 3 );
media.view.MediaFrame.MediaDetails = __webpack_require__( 4 );
media.view.MediaFrame.AudioDetails = __webpack_require__( 5 );
media.view.MediaFrame.VideoDetails = __webpack_require__( 6 );
media.view.MediaDetails = __webpack_require__( 7 );
media.view.AudioDetails = __webpack_require__( 8 );
media.view.VideoDetails = __webpack_require__( 9 );


/***/ }),
/* 1 */
/***/ (function(module, exports) {

/**
 * wp.media.model.PostMedia
 *
 * Shared model class for audio and video. Updates the model after
 *   "Add Audio|Video Source" and "Replace Audio|Video" states return
 *
 * @memberOf wp.media.model
 *
 * @class
 * @augments Backbone.Model
 */
var PostMedia = Backbone.Model.extend(/** @lends wp.media.model.PostMedia.prototype */{
	initialize: function() {
		this.attachment = false;
	},

	setSource: function( attachment ) {
		this.attachment = attachment;
		this.extension = attachment.get( 'filename' ).split('.').pop();

		if ( this.get( 'src' ) && this.extension === this.get( 'src' ).split('.').pop() ) {
			this.unset( 'src' );
		}

		if ( _.contains( wp.media.view.settings.embedExts, this.extension ) ) {
			this.set( this.extension, this.attachment.get( 'url' ) );
		} else {
			this.unset( this.extension );
		}
	},

	changeAttachment: function( attachment ) {
		this.setSource( attachment );

		this.unset( 'src' );
		_.each( _.without( wp.media.view.settings.embedExts, this.extension ), function( ext ) {
			this.unset( ext );
		}, this );
	}
});

module.exports = PostMedia;


/***/ }),
/* 2 */
/***/ (function(module, exports) {

var State = wp.media.controller.State,
	l10n = wp.media.view.l10n,
	AudioDetails;

/**
 * wp.media.controller.AudioDetails
 *
 * The controller for the Audio Details state
 *
 * @memberOf wp.media.controller
 *
 * @class
 * @augments wp.media.controller.State
 * @augments Backbone.Model
 */
AudioDetails = State.extend(/** @lends wp.media.controller.AudioDetails.prototype */{
	defaults: {
		id: 'audio-details',
		toolbar: 'audio-details',
		title: l10n.audioDetailsTitle,
		content: 'audio-details',
		menu: 'audio-details',
		router: false,
		priority: 60
	},

	initialize: function( options ) {
		this.media = options.media;
		State.prototype.initialize.apply( this, arguments );
	}
});

module.exports = AudioDetails;


/***/ }),
/* 3 */
/***/ (function(module, exports) {

/**
 * wp.media.controller.VideoDetails
 *
 * The controller for the Video Det