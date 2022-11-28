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
          if (src.indexOf(prefixes[i].filter) !== -1) {
            return prefixes[i];
          }
        }
      }
    };

    return {
      getVideoScriptMatch: getVideoScriptMatch
    };
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
  'tinymce.core.Env',
  [
    'global!tinymce.util.Tools.resolve'
  ],
  function (resolve) {
    return resolve('tinymce.Env');
  }
);

/**
 * Nodes.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.plugins.media.core.Nodes',
  [
    'tinymce.plugins.media.core.Sanitize',
    'tinymce.plugins.media.core.VideoScript',
    'tinymce.core.html.Node',
    'tinymce.core.Env'
  ],
  function (Sanitize, VideoScript, Node, Env) {
    var createPlaceholderNode = function (editor, node) {
      var placeHolder;
      var name = node.name;

      placeHolder = new Node('img', 1);
      placeHolder.shortEnded = true;

      retainAttributesAndInnerHtml(editor, node, placeHolder);

      placeHolder.attr({
        width: node.attr('width') || "300",
        height: node.attr('height') || (name === "audio" ? "30" : "150"),
        style: node.attr('style'),
        src: Env.transparentSrc,
        "data-mce-object": name,
        "class": "mce-object mce-object-" + name
      });

      return placeHolder;
    };

    var createPreviewIframeNode = function (editor, node) {
      var previewWrapper;
      var previewNode;
      var shimNode;
      var name = node.name;

      previewWrapper = new Node('span', 1);
      previewWrapper.attr({
        contentEditable: 'false',
        style: node.attr('style'),
        "data-mce-object": name,
        "class": "mce-preview-object mce-object-" + name
      });

      retainAttributesAndInnerHtml(editor, node, previewWrapper);

      previewNode = new Node(name, 1);
      previewNode.attr({
        src: node.attr('src'),
        allowfullscreen: node.attr('allowfullscreen'),
        width: node.attr('width') || "300",
        height: node.attr('height') || (name === "audio" ? "30" : "150"),
        frameborder: '0'
      });

      shimNode = new Node('span', 1);
      shimNode.attr('class', 'mce-shim');

      previewWrapper.append(previewNode);
      previewWrapper.append(shimNode);

      return previewWrapper;
    };

    var retainAttributesAndInnerHtml = function (editor, sourceNode, targetNode) {
      var attrName;
      var attrValue;
      var attribs;
      var ai;
      var innerHtml;

      // Prefix all attributes except width, height and style since we
      // will add these to the placeholder
      attribs = sourceNode.attributes;
      ai = attribs.length;
      while (ai--) {
        attrName = attribs[ai].name;
        attrValue = attribs[ai].value;

        if (attrName !== "width" && attrName !== "height" && attrName !== "style") {
          if (attrName === "data" || attrName === "src") {
            attrValue = editor.convertURL(attrValue, attrName);
          }

          targetNode.attr('data-mce-p-' + attrName, attrValue);
        }
      }

      // Place the inner HTML contents inside an escaped attribute
      // This enables us to copy/paste the fake object
      innerHtml = sourceNode.firstChild && sourceNode.firstChild.value;
      if (innerHtml) {
        targetNode.attr("data-mce-html", escape(Sanitize.sanitize(editor, innerHtml)));
        targetNode.firstChild = null;
      }
    };

    var isWithinEphoxEmbed = function (node) {
      while ((node = node.parent)) {
        if (node.attr('data-ephox-embed-iri')) {
          return true;
        }
      }

      return false;
    };

    var placeHolderConverter = function (editor) {
      return function (nodes) {
        var i = nodes.length;
        var node;
        var videoScript;

        while (i--) {
          node = nodes[i];
          if (!node.parent) {
            continue;
          }

          if (node.parent.attr('data-mce-object')) {
            continue;
          }

          if (node.name === 'script') {
            videoScript = VideoScript.getVideoScriptMatch(editor.settings.media_scripts, node.attr('src'));
            if (!videoScript) {
              continue;
            }
          }

          if (videoScript) {
            if (videoScript.width) {
              node.attr('width', videoScript.width.toString());
            }

            if (videoScript.height) {
              node.attr('height', videoScript.height.toString());
            }
          }

          if (node.name === 'iframe' && editor.settings.media_live_embeds !== false && Env.ceFalse) {
            if (!isWithinEphoxEmbed(node)) {
              node.replace(createPreviewIframeNode(editor, node));
            }
          } else {
            if (!isWithinEphoxEmbed(node)) {
              node.replace(createPlaceholderNode(editor, node));
            }
          }
        }
      };
    };

    return {
      createPreviewIframeNode: createPreviewIframeNode,
      createPlaceholderNode: createPlaceholderNode,
      placeHolderConverter: placeHolderConverter
    };
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
  'tinymce.core.dom.DOMUtils',
  [
    'global!tinymce.util.Tools.resolve'
  ],
  function (resolve) {
    return resolve('tinymce.dom.DOMUtils');
  }
);

/**
 * Size.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.plugins.media.core.Size',
  [
  ],
  function () {
    var trimPx = function (value) {
      return value.replace(/px$/, '');
    };

    var addPx = function (value) {
      return /^[0-9.]+$/.test(value) ? (value + 'px') : value;
    };

    var getSize = function (name) {
      return function (elm) {
        return elm ? trimPx(elm.style[name]) : '';
      };
    };

    var setSize = function (name) {
      return function (elm, value) {
        if (elm) {
          elm.style[name] = addPx(value);
        }
      };
    };

    return {
      getMaxWidth: getSize('maxWidth'),
      getMaxHeight: getSize('maxHeight'),
      setMaxWidth: setSize('maxWidth'),
      setMaxHeight: setSize('maxHeight')
    };
  }
);
/**
 * UpdateHtml.js
 *
 * Released under LGPL License.
 * Copyright (c) 1999-2017 Ephox Corp. All rights reserved
 *
 * License: http://www.tinymce.com/license
 * Contributing: http://www.tinymce.com/contributing
 */

define(
  'tinymce.plugins.media.core.UpdateHtml',
  [
    'tinymce.core.html.Writer',
    'tinymce.core.html.SaxParser',
    'tinymce.core.html.Schema',
    'tinymce.core.dom.DOMUtils',
    'tinymce.plugins.media.core.Size'
  ],
  function (Writer, SaxParser, Schema, DOMUtils, Size) {
    var DOM = DOMUtils.DOM;

    var setAttributes = function (attrs, updatedAttrs) {
      var name;
      var i;
      var value;
      var attr;

      for (name in updatedAttrs) {
        value = "" + updatedAttrs[name];

        if (attrs.map[name]) {
          i = attrs.length;
          while (i--) {
            attr = attrs[i];

            if (attr.name === name) {
              if (value) {
                attrs.map[name] = value;
                attr.value = value;
              } else {
                delete attrs.map[name];
                attrs.splice(i, 1);
              }
            }
          }
        } else if (value) {
          attrs.push({
            name: name,
            value: value
          });

          attrs.map[name] = value;
        }
      }
    };

    var normalizeHtml = function (html) {
      var writer = new Writer();
      var parser = new SaxParser(writer);
      parser.parse(html);
      return writer.getContent();
    };

    var updateHtmlSax = function (html, data, updateAll) {
      var writer = new Writer();
      var sourceCount = 0;
      var hasImage;

      new SaxParser({
        validate: false,
        allow_conditional_comments: true,
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
          switch (name) {
            case "video":
            case "object":
            case "embed":
            case "img":
            case "iframe":
              if (data.height !== undefined && data.width !== undefined) {
                setAttributes(attrs, {
                  width: data.width,
                  height: data.height
                });
              }
              break;
          }

          if (updateAll) {
            switch (name) {
              case "video":
                setAttributes(attrs, {
                  poster: data.poster,
                  src: ""
                });

                if (data.source2) {
                  setAttributes(attrs, {
                    src: ""
                  });
                }
                break;

              case "iframe":
                setAttributes(attrs, {
                  src: data.source1
                });
                break;

              case "source":
                sourceCount++;

                if (sourceCount <= 2) {
                  setAttributes(attrs, {
                    src: data["source" + sourceCount],
                    type: data["source" + sourceCount + "mime"]
                  });

                  if (!data["source" + sourceCount]) {
                    return;
                  }
                }
                break;

              case "img":
                if (!data.poster) {
                  return;
                }

                hasImage = true;
                break;
            }
          }

          writer.start(name, attrs, empty);
        },

        end: function (name) {
          if (name === "video" && updateAll) {
            for (var index = 1; index <= 2; index++) {
              if (data["source" + index]) {
                var attrs = [];
                attrs.map = {};

                if (sourceCount < index) {
                  setAttributes(attrs, {
                    src: data["source" + index],
                    type: data["source" + index + "mime"]
     