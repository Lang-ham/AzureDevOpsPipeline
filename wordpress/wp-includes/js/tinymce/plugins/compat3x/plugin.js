/**
 * plugin.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

/*global tinymce:true, console:true */
/*eslint no-console:0, new-cap:0 */

/**
 * This plugin adds missing events form the 4.x API back. Not every event is
 * properly supported but most things should work.
 *
 * Unsupported things:
 *  - No editor.onEvent
 *  - Can't cancel execCommands with beforeExecCommand
 */
(function (tinymce) {
  var reported;

  function noop() {
  }

  function log(apiCall) {
    if (!reported && window && window.console) {
      reported =