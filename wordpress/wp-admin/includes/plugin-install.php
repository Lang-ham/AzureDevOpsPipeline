<?php
/**
 * WordPress Plugin Install Administration API
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * Retrieves plugin installer pages from the WordPress.org Plugins API.
 *
 * It is possible for a plugin to override the Plugin API result with three
 * filters. Assume this is for plugins, which can extend on the Plugin Info to
 * offer more choices. This is very powerful and must be used with care when
 * overriding the filters.
 *
 * The first filter, {@see 'plugins_api_args'}, is for the args and gives the action
 * as the second parameter. The hook for {@see 'plugins_api_args'} must ensure that
 * an object is returned.
 *
 * The second filter, {@see 'plugins_api'}, allows a plugin to override the WordPress.org
 * Plugin Installation API entirely. If `$action` is 'query_plugins' or 'plugin_information',
 * an object MUST be passed. If `$action` is 'hot_tags' or 'hot_categories', an array MUST
 * be passed.
 *
 * Finally, the third filter, {@see 'plugins_api_result'}, makes it possible to filter the
 * response object or array, depending on the `$action` type.
 *
 * Supported arguments per action:
 *
 * | Argument Name        | query_plugins | plugin_information | hot_tags | hot_categories |
 * | -------------------- | :-----------: | :----------------: | :------: | :------------: |
 * | `$slug`              | No            |  Yes               | No       | No             |
 * | `$per_page`          | Yes           |  No                | No       | No             |
 * | `$page`              | Yes           |  No                | No       | No             |
 * | `$number`            | No            |  No                | Yes      | Yes            |
 * | `$search`            | Yes           |  No                | No       | No             |
 * | `$tag`               | Yes           |  No                | No       | No             |
 * | `$author`            | Yes           |  No                | No       | No             