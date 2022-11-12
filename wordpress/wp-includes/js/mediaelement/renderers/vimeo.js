/*!
 * MediaElement.js
 * http://www.mediaelementjs.com/
 *
 * Wrapper that mimics native HTML5 MediaElement (audio and video)
 * using a variety of technologies (pure JavaScript, Flash, iframe)
 *
 * Copyright 2010-2017, John Dyer (http://j.hn/)
 * License: MIT
 *
 */(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(_dereq_,module,exports){
'use strict';

var VimeoApi = {

	promise: null,

	load: function load(settings) {

		if (typeof Vimeo !== 'undefined') {
			VimeoApi._createPlayer(settings);
		} else {
			VimeoApi.promise = VimeoApi.promise || mejs.Utils.loadScript('https://player.vimeo.com/api/player.js');
			VimeoApi.promise.then(function () {
				VimeoApi._createPlayer(settings);
			});
		}
	},

	_createPlayer: function _createPlayer(settings) {
		var player = new Vimeo.Player(settings.iframe);
		window['__ready__' + settings.id](player);
	},

	getVimeoId: function getVimeoId(url) {
		if (url === undefined || url === null) {
			return null;
		}

		var parts = url.split('?');
		url = parts[0];
		return parseInt(url.substring(url.lastIndexOf('/') + 1), 10);
	}
};

var vimeoIframeRenderer = {

	name: 'vimeo_iframe',
	options: {
		prefix: 'vimeo_iframe'
	},

	canPlayType: function canPlayType(type) {
		return ~['video/vimeo', 'video/x-vimeo'].indexOf(type.toLowerCase());
	},

	create: function create(mediaElement, options, mediaFiles) {
		var apiStack = [],
		    vimeo = {},
		    readyState = 4;

		var paused = true,
		    volume = 1,
		    oldVolume = volume,
		    currentTime = 0,
		    bufferedTime = 0,
		    ended = false,
		    duration = 0,
		    vimeoPlayer = null,
		    url = '';

		vimeo.options = options;
		vimeo.id = mediaElement.id + '_' + options.prefix;
		vimeo.mediaElement = mediaElement;

		var errorHandler = function errorHandler(error, target) {
			var event = mejs.Utils.createEvent('error', target);
			event.message = error.name + ': ' + error.message;
			mediaElement.dispatchEvent(event);
		};

		var props = mejs.html5media.properties,
		    assignGettersSetters = function assignGettersSetters(propName) {

			var capName = '' + propName.substring(0, 1).toUpperCase() + propName.substring(1);

			vimeo['get' + capName] = function () {
				if (vimeoPlayer !== null) {
					var value = null;

					switch (propName) {
						case 'currentTime':
							return currentTime;

						case 'duration':
							return duration;

						case 'volume':
							return volume;
						case 'muted':
							return volume === 0;
						case 'paused':
							return paused;
						case 'ended':
							return ended;

						case 'src':
							vimeoPlayer.getVideoUrl().then(function (_url) {
								url = _url;
							});

							return url;
						case 'buffered':
							return {
								start: function start() {
									return 0;
								},
								end: function end() {
									return bufferedTime * duration;
								},
								length: 1
							};
						case 'readyState':
							return readyState;
					}
					return value;
				} else {
					return null;
				}
			};

			vimeo['set' + capName] = function (value) {
				if (vimeoPlayer !== null) {
					switch (propName) {
						case 'src':
							var _url2 = typeof value === 'string' ? value : value[0].src,
							    videoId = VimeoApi.getVimeoId(_url2);

							vimeoPlayer.loadVideo(videoId).then(function () {
								if (mediaElement.originalNode.autoplay) {
									vimeoPlayer.play();
								}
							}).catch(function (error) {
								errorHandler(error, vimeo);
							});
							break;
						case 'currentTime':
							vimeoPlayer.setCurrentTime(value).then(function () {
								currentTime = value;
								setTimeout(function () {
									var event = mejs.Utils.createEvent('timeupdate', vimeo);
									mediaElement.dispatchEvent(event);
								}, 50);
							}).catch(function (error) {
								errorHandler(error, vimeo);
							});
							break;
						case 'volume':
							vimeoPlayer.setVolume(value).then(function () {
								volume = value;
								oldVolume = volume;
								setTimeout(function () {
									var event = mejs.Utils.createEvent('volumechange', vimeo);
									mediaElement.dispatchEvent(