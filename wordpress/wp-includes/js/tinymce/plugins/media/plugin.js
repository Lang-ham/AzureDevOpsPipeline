(function () {

var defs = {}; // id -> {dependencies, definition, instance (possibly undefined)}

// Used when there is no 'main' module.
// The name is probably (hopefully) unique so minification removes for releases.
var register_3795 = function (id) {
  var module = dem(id);
  var fragments = id.split('.');
  var target = Function('return this;')();
  for (var i = 0; i < fragments.length - 1; ++i) {
    if (target[fragments[i]] === undefined)
      target[fragments[i]] = {};
    target = target[fragments[i]];
  }
  target[fragments[fragments.length - 1]] = module;
};

var instantiate = function (id) {
  var actual = defs[id];
  var dependencies = actual.deps;
  var definition = actual.defn;
  var len = dependencies.length;
  var instances = new Array(len);
  for (var i = 0; i < len; ++i)
    instances[i] = dem(dependencies[i]);
  var defResult = definition.apply(null, instances);
  if (defResult === undefined)
     throw 'module [' + id + '] returned undefined';
  actual.instance = defResult;
};

var def = function (id, dependencies, definition) {
  if (typeof id !== 'string')
    throw 'module id must be a string';
  else if (dependencies === undefined)
    throw 'no dependencies for ' + id;
  else if (definition === undefined)
    throw 'no definition function for ' + id;
  defs[id] = {
    deps: dependencies,
    defn: definition,
    instance: undefined
  };
};

var dem = function (id) {
  var actual = defs[id];
  if (actual === undefined)
    throw 'module [' + id + '] was undefined';
  else if (actual.instance === undefined)
    instantiate(id);
  return actual.instance;
};

var req = function (ids, callback) {
  var len = ids.length;
  var instances = new Array(len);
  for (var i = 0; i < len; ++i)
    instances.push(dem(ids[i]));
  callback.apply(null, callback);
};

var ephox = {};

ephox.bolt = {
  module: {
    api: {
      define: def,
      require: req,
      demand: dem
    }
  }
};

var define = def;
var require = req;
var demand = dem;
// this helps with minificiation when using a lot of global references
var defineGlobal = function (id, ref) {
  define(id, [], function () { return ref; });
};
/*jsc
["tinymce.plugins.media.Plugin","tinymce.core.html.Node","tinymce.core.PluginManager","tinymce.core.util.Tools","tinymce.plugins.media.core.Nodes","tinymce.plugins.media.core.Sanitize","tinymce.plugins.media.core.UpdateHtml","tinymce.plugins.media.ui.Dialog","global!tinymce.util.Tools.resolve","tinymce.core.html.Writer","tinymce.core.html.SaxParser","tinymce.core.html.Schema","tinymce.plugins.media.core.VideoScript","tinymce.core.Env","tinymce.core.dom.DOMUtils","tinymce.plugins.media.core.Size","tinymce.core.util.Delay","tinymce.plugins.media.core.HtmlToData","tinymce.plugins.media.core.Service","tinymce.plugins.media.ui.SizeManager","tinymce.plugins.media.core.DataToHtml","tinymce.core.util.Promise","tinymce.plugins.media.core.Mime","tinymce.plugins.media.core.UrlPatterns"]
jsc*/
defineGlobal("global!tinymce.util.Tools.resolve", tinymce.util.Tools.resolve);
/**
 * ResolveGlobal.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.core.html.Node',
  [
    'global!tinymce.util.Tools.resolve'
  ],
  function (resolve) {
    return resolve('tinymce.html.Node');
  }
);

/**
 * ResolveGlobal.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.core.PluginManager',
  [
    'global!tinymce.util.Tools.resolve'
  ],
  function (resolve) {
    return resolve('tinymce.PluginManager');
  }
);

/**
 * ResolveGlobal.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.core.util.Tools',
  [
    'global!tinymce.util.Tools.resolve'
  ],
  function (resolve) {
    return resolve('tinymce.util.Tools');
  }
);

/**
 * ResolveGlobal.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.core.html.Writer',
  [
    'global!tinymce.util.Tools.resolve'
  ],
  function (resolve) {
    return resolve('tinymce.html.Writer');
  }
);

/**
 * ResolveGlobal.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.core.html.SaxParser',
  [
    'global!tinymce.util.Tools.resolve'
  ],
  function (resolve) {
    return resolve('tinymce.html.SaxParser');
  }
);

/**
 * ResolveGlobal.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.core.html.Schema',
  [
    'global!tinymce.util.Tools.resolve'
  ],
  function (resolve) {
    return resolve('tinymce.html.Schema');
  }
);

/**
 * Sanitize.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.plugins.media.core.Sanitize',
  [
    'tinymce.core.util.Tools',
    'tinymce.core.html.Writer',
    'tinymce.core.html.SaxParser',
    'tinymce.core.html.Schema'
  ],
  function (Tools, Writer, SaxParser, Schema) {
    var sanitize = function (editor, html) {
      if (editor.settings.media_filter_html === false) {
        return html;
      }

      var writer = new Writer();
      var blocked;

      new SaxParser({
        validate: false,
        allow_conditional_comments: false,
        special: 'script,noscript',

        comment: function (text) {
          writer.comment(text);
        },

        cdata: function (text) {
          writer.cdata(text);
        },

        text: function (text, raw) {
          writer.text(text, raw);
        },

        start: function (name, attrs, empty) {
          blocked = true;

          if (name === 'script' || name === 'noscript') {
            return;
          }

          for (var i = 0; i < attrs.length; i++) {
            if (attrs[i].name.indexOf('on') === 0) {
              return;
            }

            if (attrs[i].name === 'style') {
              attrs[i].value = editor.dom.serializeStyle(editor.dom.parseStyle(attrs[i].value), name);
            }
          }

          writer.start(name, attrs, empty);
          blocked = false;
        },

        end: function (name) {
          if (blocked) {
            return;
          }

          writer.end(name);
        }
      }, new Schema({})).parse(html);

      return writer.getContent();
    };

    return {
      sanitize: sanitize
    };
  }
);
/**
 * VideoScript.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.plugins.media.core.VideoScript',
  [
  ],
  function () {
    var getVideoScriptMatch = function (prefixes, src) {
      // var prefixes = editor.settings.media_scripts;
      if (prefixes) {
        for (var i = 0; i < prefixes.length; i++) {
          if (src.index