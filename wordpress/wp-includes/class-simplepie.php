<?php
if ( ! class_exists( 'SimplePie', false ) ) :

// Load classes we will need.
require ABSPATH . WPINC . '/SimplePie/Misc.php';
require ABSPATH . WPINC . '/SimplePie/Cache.php';
require ABSPATH . WPINC . '/SimplePie/File.php';
require ABSPATH . WPINC . '/SimplePie/Sanitize.php';
require ABSPATH . WPINC . '/SimplePie/Registry.php';
require ABSPATH . WPINC . '/SimplePie/IRI.php';
require ABSPATH . WPINC . '/SimplePie/Locator.php';
require ABSPATH . WPINC . '/SimplePie/Content/Type/Sniffer.php';
require ABSPATH . WPINC . '/SimplePie/XML/Declaration/Parser.php';
require ABSPATH . WPINC . '/SimplePie/Parser.php';
require ABSPATH . WPINC . '/SimplePie/Item.php';
require ABSPATH . WPINC . '/SimplePie/Parse/Date.php';
require ABSPATH . WPINC . '/SimplePie/Author.php';

/**
 * WordPress autoloader for SimplePie.
 *
 * @since 3.5.0
 */
function wp_simplepie_autoload( $class ) {
	if ( 0 !== strpos( $class, 'SimplePie_' ) )
		return;

	$file = ABSPATH . WPINC . '/' . str_replace( '_', '/', $class ) . '.php';
	include( $file );
}

/**
 * We autoload classes we may not need.
 */
spl_autoload_register( 'wp_simplepie_autoload' );

/**
 * SimplePie
 *
 * A PHP-Based RSS and Atom Feed Framework.
 * Takes the hard work out of managing a complete RSS/Atom solution.
 *
 * Copyright (c) 2004-2012, Ryan Parman, Geoffrey Sneddon, Ryan McCue, and contributors
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are
 * permitted provided that the following conditions are met:
 *
 * 	* Redistributions of source code must retain the above copyright notice, this list of
 * 	  conditions and the following disclaimer.
 *
 * 	* Redistributions in binary form must reproduce the above copyright notice, this list
 * 	  of conditions and the following disclaimer in the documentation and/or other materials
 * 	  provided with the distribution.
 *
 * 	* Neither the name of the SimplePie Team nor the names of its contributors may be used
 * 	  to endorse or promote products derived from this software without specific prior
 * 	  written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
 * OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS
 * AND CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package SimplePie
 * @version 1.3.1
 * @copyright 2004-2012 Ryan Parman, Geoffrey Sneddon, Ryan McCue
 * @author Ryan Parman
 * @author Geoffrey Sneddon
 * @author Ryan McCue
 * @link http://simplepie.org/ SimplePie
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

/**
 * SimplePie Name
 */
define('SIMPLEPIE_NAME', 'SimplePie');

/**
 * SimplePie Version
 */
define('SIMPLEPIE_VERSION', '1.3.1');

/**
 * SimplePie Build
 * @todo Hardcode for release (there's no need to have to call SimplePie_Misc::get_build() only every load of simplepie.inc)
 */
define('SIMPLEPIE_BUILD', gmdate('YmdHis', SimplePie_Misc::get_build()));

/**
 * SimplePie Website URL
 */
define('SIMPLEPIE_URL', 'http://simplepie.org');

/**
 * SimplePie Useragent
 * @see SimplePie::set_useragent()
 */
define('SIMPLEPIE_USERAGENT', SIMPLEPIE_NAME . '/' . SIMPLEPIE_VERSION . ' (Feed Parser; ' . SIMPLEPIE_URL . '; Allow like Gecko) Build/' . SIMPLEPIE_BUILD);

/**
 * SimplePie Linkback
 */
define('SIMPLEPIE_LINKBACK', '<a href="' . SIMPLEPIE_URL . '" title="' . SIMPLEPIE_NAME . ' ' . SIMPLEPIE_VERSION . '">' . SIMPLEPIE_NAME . '</a>');

/**
 * No Autodiscovery
 * @see SimplePie::set_autodiscovery_level()
 */
define('SIMPLEPIE_LOCATOR_NONE', 0);

/**
 * Feed Link Element Autodiscovery
 * @see SimplePie::set_autodiscovery_level()
 */
define('SIMPLEPIE_LOCATOR_AUTODISCOVERY', 1);

/**
 * Local Feed Extension Autodiscovery
 * @see SimplePie::set_autodiscovery_level()
 */
define('SIMPLEPIE_LOCATOR_LOCAL_EXTENSION', 2);

/**
 * Local Feed Body Autodiscovery
 * @see SimplePie::set_autodiscovery_level()
 */
define('SIMPLEPIE_LOCATOR_LOCAL_BODY', 4);

/**
 * Remote Feed Extension Autodiscovery
 * @see SimplePie::set_autodiscovery_level()
 */
define('SIMPLEPIE_LOCATOR_REMOTE_EXTENSION', 8);

/**
 * Remote Feed Body Autodiscovery
 * @see SimplePie::set_autodiscovery_level()
 */
define('SIMPLEPIE_LOCATOR_REMOTE_BODY', 16);

/**
 * All Feed Autodiscovery
 * @see SimplePie::set_autodiscovery_level()
 */
define('SIMPLEPIE_LOCATOR_ALL', 31);

/**
 * No known feed type
 */
define('SIMPLEPIE_TYPE_NONE', 0);

/**
 * RSS 0.90
 */
define('SIMPLEPIE_TYPE_RSS_090', 1);

/**
 * RSS 0.91 (Netscape)
 */
define('SIMPLEPIE_TYPE_RSS_091_NETSCAPE', 2);

/**
 * RSS 0.91 (Userland)
 */
define('SIMPLEPIE_TYPE_RSS_091_USERLAND', 4);

/**
 * RSS 0.91 (both Netscape and Userland)
 */
define('SIMPLEPIE_TYPE_RSS_091', 6);

/**
 * RSS 0.92
 */
define('SIMPLEPIE_TYPE_RSS_092', 8);

/**
 * RSS 0.93
 */
define('SIMPLEPIE_TYPE_RSS_093', 16);

/**
 * RSS 0.94
 */
define('SIMPLEPIE_TYPE_RSS_094', 32);

/**
 * RSS 1.0
 */
define('SIMPLEPIE_TYPE_RSS_10', 64);

/**
 * RSS 2.0
 */
define('SIMPLEPIE_TYPE_RSS_20', 128);

/**
 * RDF-based RSS
 */
define('SIMPLEPIE_TYPE_RSS_RDF', 65);

/**
 * Non-RDF-based RSS (truly intended as syndication format)
 */
define('SIMPLEPIE_TYPE_RSS_SYNDICATION', 190);

/**
 * All RSS
 */
define('SIMPLEPIE_TYPE_RSS_ALL', 255);

/**
 * Atom 0.3
 */
define('SIMPLEPIE_TYPE_ATOM_03', 256);

/**
 * Atom 1.0
 */
define('SIMPLEPIE_TYPE_ATOM_10', 512);

/**
 * All Atom
 */
define('SIMPLEPIE_TYPE_ATOM_ALL', 768);

/**
 * All feed types
 */
define('SIMPLEPIE_TYPE_ALL', 1023);

/**
 * No construct
 */
define('SIMPLEPIE_CONSTRUCT_NONE', 0);

/**
 * Text construct
 */
define('SIMPLEPIE_CONSTRUCT_TEXT', 1);

/**
 * HTML construct
 */
define('SIMPLEPIE_CONSTRUCT_HTML', 2);

/**
 * XHTML construct
 */
define('SIMPLEPIE_CONSTRUCT_XHTML', 4);

/**
 * base64-encoded construct
 */
define('SIMPLEPIE_CONSTRUCT_BASE64', 8);

/**
 * IRI construct
 */
define('SIMPLEPIE_CONSTRUCT_IRI', 16);

/**
 * A construct that might be HTML
 */
define('SIMPLEPIE_CONSTRUCT_MAYBE_HTML', 32);

/**
 * All constructs
 */
define('SIMPLEPIE_CONSTRUCT_ALL', 63);

/**
 * Don't change case
 */
define('SIMPLEPIE_SAME_CASE', 1);

/**
 * Change to lowercase
 */
define('SIMPLEPIE_LOWERCASE', 2);

/**
 * Change to uppercase
 */
define('SIMPLEPIE_UPPERCASE', 4);

/**
 * PCRE for HTML attributes
 */
define('SIMPLEPIE_PCRE_HTML_ATTRIBUTE', '((?:[\x09\x0A\x0B\x0C\x0D\x20]+[^\x09\x0A\x0B\x0C\x0D\x20\x2F\x3E][^\x09\x0A\x0B\x0C\x0D\x20\x2F\x3D\x3E]*(?:[\x09\x0A\x0B\x0C\x0D\x20]*=[\x09\x0A\x0B\x0C\x0D\x20]*(?:"(?:[^"]*)"|\'(?:[^\']*)\'|(?:[^\x09\x0A\x0B\x0C\x0D\x20\x22\x27\x3E][^\x09\x0A\x0B\x0C\x0D\x20\x3E]*)?))?)*)[\x09\x0A\x0B\x0C\x0D\x20]*');

/**
 * PCRE for XML attributes
 */
define('SIMPLEPIE_PCRE_XML_ATTRIBUTE', '((?:\s+(?:(?:[^\s:]+:)?[^\s:]+)\s*=\s*(?:"(?:[^"]*)"|\'(?:[^\']*)\'))*)\s*');

/**
 * XML Namespace
 */
define('SIMPLEPIE_NAMESPACE_XML', 'http://www.w3.org/XML/1998/namespace');

/**
 * Atom 1.0 Namespace
 */
define('SIMPLEPIE_NAMESPACE_ATOM_10', 'http://www.w3.org/2005/Atom');

/**
 * Atom 0.3 Namespace
 */
define('SIMPLEPIE_NAMESPACE_ATOM_03', 'http://purl.org/atom/ns#');

/**
 * RDF Namespace
 */
define('SIMPLEPIE_NAMESPACE_RDF', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

/**
 * RSS 0.90 Namespace
 */
define('SIMPLEPIE_NAMESPACE_RSS_090', 'http://my.netscape.com/rdf/simple/0.9/');

/**
 * RSS 1.0 Namespace
 */
define('SIMPLEPIE_NAMESPACE_RSS_10', 'http://purl.org/rss/1.0/');

/**
 * RSS 1.0 Content Module Namespace
 */
define('SIMPLEPIE_NAMESPACE_RSS_10_MODULES_CONTENT', 'http://purl.org/rss/1.0/modules/content/');

/**
 * RSS 2.0 Namespace
 * (Stupid, I know, but I'm certain it will confuse people less with support.)
 */
define('SIMPLEPIE_NAMESPACE_RSS_20', '');

/**
 * DC 1.0 Namespace
 */
define('SIMPLEPIE_NAMESPACE_DC_10', 'http://purl.org/dc/elements/1.0/');

/**
 * DC 1.1 Namespace
 */
define('SIMPLEPIE_NAMESPACE_DC_11', 'http://purl.org/dc/elements/1.1/');

/**
 * W3C Basic Geo (WGS84 lat/long) Vocabulary Namespace
 */
define('SIMPLEPIE_NAMESPACE_W3C_BASIC_GEO', 'http://www.w3.org/2003/01/geo/wgs84_pos#');

/**
 * GeoRSS Namespace
 */
define('SIMPLEPIE_NAMESPACE_GEORSS', 'http://www.georss.org/georss');

/**
 * Media RSS Namespace
 */
define('SIMPLEPIE_NAMESPACE_MEDIARSS', 'http://search.yahoo.com/mrss/');

/**
 * Wrong Media RSS Namespace. Caused by a long-standing typo in the spec.
 */
define('SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG', 'http://search.yahoo.com/mrss');

/**
 * Wrong Media RSS Namespace #2. New namespace introduced in Media RSS 1.5.
 */
define('SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG2', 'http://video.search.yahoo.com/mrss');

/**
 * Wrong Media RSS Namespace #3. A possible typo of the Media RSS 1.5 namespace.
 */
define('SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG3', 'http://video.search.yahoo.com/mrss/');

/**
 * Wrong Media RSS Namespace #4. New spec location after the RSS Advisory Board takes it over, but not a valid namespace.
 */
define('SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG4', 'http://www.rssboard.org/media-rss');

/**
 * Wrong Media RSS Namespace #5. A possible typo of the RSS Advisory Board URL.
 */
define('SIMPLEPIE_NAMESPACE_MEDIARSS_WRONG5', 'http://www.rssboard.org/media-rss/');

/**
 * iTunes RSS Namespace
 */
define('SIMPLEPIE_NAMESPACE_ITUNES', 'http://www.itunes.com/dtds/podcast-1.0.dtd');

/**
 * XHTML Namespace
 */
define('SIMPLEPIE_NAMESPACE_XHTML', 'http://www.w3.org/1999/xhtml');

/**
 * IANA Link Relations Registry
 */
define('SIMPLEPIE_IANA_LINK_RELATIONS_REGISTRY', 'http://www.iana.org/assignments/relation/');

/**
 * No file source
 */
define('SIMPLEPIE_FILE_SOURCE_NONE', 0);

/**
 * Remote file source
 */
define('SIMPLEPIE_FILE_SOURCE_REMOTE', 1);

/**
 * Local file source
 */
define('SIMPLEPIE_FILE_SOURCE_LOCAL', 2);

/**
 * fsockopen() file source
 */
define('SIMPLEPIE_FILE_SOURCE_FSOCKOPEN', 4);

/**
 * cURL file source
 */
define('SIMPLEPIE_FILE_SOURCE_CURL', 8);

/**
 * file_get_contents() file source
 */
define('SIMPLEPIE_FILE_SOURCE_FILE_GET_CONTENTS', 16);



/**
 * SimplePie
 *
 * @package SimplePie
 * @subpackage API
 */
class SimplePie
{
	/**
	 * @var array Raw data
	 * @access private
	 */
	public $data = array();

	/**
	 * @var mixed Error string
	 * @access private
	 */
	public $error;

	/**
	 * @var object Instance of SimplePie_Sanitize (or other class)
	 * @see SimplePie::set_sanitize_class()
	 * @access private
	 */
	public $sanitize;

	/**
	 * @var string SimplePie Useragent
	 * @see SimplePie::set_useragent()
	 * @access private
	 */
	public $useragent = SIMPLEPIE_USERAGENT;

	/**
	 * @var string Feed URL
	 * @see SimplePie::set_feed_url()
	 * @access private
	 */
	public $feed_url;

	/**
	 * @var object Instance of SimplePie_File to use as a feed
	 * @see SimplePie::set_file()
	 * @access private
	 */
	public $file;

	/**
	 * @var string Raw feed data
	 * @see SimplePie::set_raw_data()
	 * @access private
	 */
	public $raw_data;

	/**
	 * @var int Timeout for fetching remote files
	 * @see SimplePie::set_timeout()
	 * @access private
	 */
	public $timeout = 10;

	/**
	 * @var bool Forces fsockopen() to be used for remote files instead
	 * of cURL, even if a new enough version is installed
	 * @see SimplePie::force_fsockopen()
	 * @access private
	 */
	public $force_fsockopen = false;

	/**
	 * @var bool Force the given data/URL to be treated as a feed no matter what
	 * it appears like
	 * @see SimplePie::force_feed()
	 * @access private
	 */
	public $force_feed = false;

	/**
	 * @var bool Enable/Disable Caching
	 * @see SimplePie::enable_cache()
	 * @access private
	 */
	public $cache = true;

	/**
	 * @var int Cache duration (in seconds)
	 * @see SimplePie::set_cache_duration()
	 * @access private
	 */
	public $cache_duration = 3600;

	/**
	 * @var int Auto-discovery cache duration (in seconds)
	 * @see SimplePie::set_autodiscovery_cache_duration()
	 * @access private
	 */
	public $autodiscovery_cache_duration = 604800; // 7 Days.

	/**
	 * @var string Cache location (relative to executing script)
	 * @see SimplePie::set_cache_location()
	 * @access private
	 */
	public $cache_location = './cache';

	/**
	 * @var string Function that creates the cache filename
	 * @see SimplePie::set_cache_name_function()
	 * @access private
	 */
	public $cache_name_function = 'md5';

	/**
	 * @var bool Reorder feed by date descending
	 * @see SimplePie::enable_order_by_date()
	 * @access private
	 */
	public $order_by_date = true;

	/**
	 * @var mixed Force input encoding to be set to the follow value
	 * (false, or anything type-cast to false, disables this feature)
	 * @see SimplePie::set_input_encoding()
	 * @access private
	 */
	public $input_encoding = false;

	/**
	 * @var int Feed Autodiscovery Level
	 * @see SimplePie::set_autodiscovery_level()
	 * @access private
	 */
	public $autodiscovery = SIMPLEPIE_LOCATOR_ALL;

	/**
	 * Class registry object
	 *
	 * @var SimplePie_Registry
	 */
	public $registry;

	/**
	 * @var int Maximum number of feeds to check with autodiscovery
	 * @see SimplePie::set_max_checked_feeds()
	 * @access private
	 */
	public $max_checked_feeds = 10;

	/**
	 * @var array All the feeds found during the autodiscovery process
	 * @see SimplePie::get_all_discovered_feeds()
	 * @access private
	 */
	public $all_discovered_feeds = array();

	/**
	 * @var string Web-accessible path to the handler_image.php file.
	 * @see SimplePie::set_image_handler()
	 * @access private
	 */
	public $image_handler = '';

	/**
	 * @var array Stores the URLs when multiple feeds are being initialized.
	 * @see SimplePie::set_feed_url()
	 * @access private
	 */
	public $multifeed_url = array();

	/**
	 * @var array Stores SimplePie objects when multiple feeds initialized.
	 * @access private
	 */
	public $multifeed_objects = array();

	/**
	 * @var array Stores the get_object_vars() array for use with multifeeds.
	 * @see SimplePie::set_feed_url()
	 * @access private
	 */
	public $config_settings = null;

	/**
	 * @var integer Stores the number of items to return per-feed with multifeeds.
	 * @see SimplePie::set_item_limit()
	 * @access private
	 */
	public $item_limit = 0;

	/**
	 * @var array Stores the default attributes to be stripped by strip_attributes().
	 * @see SimplePie::strip_attributes()
	 * @access private
	 */
	public $strip_attributes = array('bgsound', 'class', 'expr', 'id', 'style', 'onclick', 'onerror', 'onfinish', 'onmouseover', 'onmouseout', 'onfocus', 'onblur', 'lowsrc', 'dynsrc');

	/**
	 * @var array Stores the default tags to be stripped by strip_htmltags().
	 * @see SimplePie::strip_htmltags()
	 * @access private
	 */
	public $strip_htmltags = array('base', 'blink', 'body', 'doctype', 'embed', 'font', 'form', 'frame', 'frameset', 'html', 'iframe', 'input', 'marquee', 'meta', 'noscript', 'object', 'param', 'script', 'style');

	/**
	 * The SimplePie class contains feed level data and options
	 *
	 * To use SimplePie, create the SimplePie object with no parameters. You can
	 * then set configuration options using the provided methods. After setting
	 * them, you must initialise the feed using $feed->init(). At that point the
	 * object's methods and properties will be available to you.
	 *
	 * Previously, it was possible to pass in the feed URL along with cache
	 * options directly into the constructor. This has been removed as of 1.3 as
	 * it caused a lot of confusion.
	 *
	 * @since 1.0 Preview Release
	 */
	public function __construct()
	{
		if (version_compare(PHP_VERSION, '5.2', '<'))
		{
			trigger_error('PHP 4.x, 5.0 and 5.1 are no longer supported. Please upgrade to PHP 5.2 or newer.');
			die();
		}

		// Other objects, instances created here so we can set options on them
		$this->sanitize = new SimplePie_Sanitize();
		$this->registry = new SimplePie_Registry();

		if (func_num_args() > 0)
		{
			$level = defined('E_USER_DEPRECATED') ? E_USER_DEPRECATED : E_USER_WARNING;
			trigger_error('Passing parameters to the constructor is no longer supported. Please use set_feed_url(), set_cache_location(), and set_cache_location() directly.', $level);

			$args = func_get_args();
			switch (count($args)) {
				case 3:
					$this->set_cache_duration($args[2]);
				case 2:
					$this->set_cache_location($args[1]);
				case 1:
					$this->set_feed_url($args[0]);
					$this->init();
			}
		}
	}

	/**
	 * Used for converting object to a string
	 */
	public function __toString()
	{
		return md5(serialize($this->data));
	}

	/**
	 * Remove items that link back to this before destroying this object
	 */
	public function __destruct()
	{
		if ((version_compare(PHP_VERSION, '5.3', '<') || !gc_enabled()) && !ini_get('zend.ze1_compatibility_mode'))
		{
			if (!empty($this->data['items']))
			{
				foreach ($this->data['items'] as $item)
				{
					$item->__destruct();
				}
				unset($item, $this->data['items']);
			}
			if (!empty($this->data['ordered_items']))
			{
				foreach ($this->data['ordered_items'] as $item)
				{
					$item->__destruct();
				}
				unset($item, $this->data['ordered_items']);
			}
		}
	}

	/**
	 * Force the given data/URL to be treated as a feed
	 *
	 * This tells SimplePie to ignore the content-type provided by the server.
	 * Be careful when using this option, as it will also disable autodiscovery.
	 *
	 * @since 1.1
	 * @param bool $enable Force the given data/URL to be treated as a feed
	 */
	public function force_feed($enable = false)
	{
		$this->force_feed = (bool) $enable;
	}

	/**
	 * Set the URL of the feed you want to parse
	 *
	 * This allows you to enter the URL of the feed you want to parse, or the
	 * website you want to try to use auto-discovery on. This takes priority
	 * over any set raw data.
	 *
	 * You can set multiple feeds to mash together by passing an array instead
	 * of a string for the $url. Remember that with each additional feed comes
	 * additional processing and resources.
	 *
	 * @since 1.0 Preview Release
	 * @see set_raw_data()
	 * @param string|array $url This is the URL (or array of URLs) that you want to parse.
	 */
	public function set_feed_url($url)
	{
		$this->multifeed_url = array();
		if (is_array($url))
		{
			foreach ($url as $value)
			{
				$this->multifeed_url[] = $this->registry->call('Misc', 'fix_protocol', array($value, 1));
			}
		}
		else
		{
			$this->feed_url = $this->registry->call('Misc', 'fix_protocol', array($url, 1));
		}
	}

	/**
	 * Set an instance of {@see SimplePie_File} to use as a feed
	 *
	 * @param SimplePie_File &$file
	 * @return bool True on success, false on failure
	 */
	public function set_file(&$file)
	{
		if ($file instanceof SimplePie_File)
		{
			$this->feed_url = $file->url;
			$this->file =& $file;
			return true;
		}
		return false;
	}

	/**
	 * Set the raw XML data to parse
	 *
	 * Allows you to use a string of RSS/Atom data instead of a remote feed.
	 *
	 * If you have a feed available as a string in PHP, you can tell SimplePie
	 * to parse that data string instead of a remote feed. Any set feed URL
	 * takes precedence.
	 *
	 * @since 1.0 Beta 3
	 * @param string $data RSS or Atom data as a string.
	 * @see set_feed_url()
	 */
	public function set_raw_data($data)
	{
		$this->raw_data = $data;
	}

	/**
	 * Set the the default timeout for fetching remote feeds
	 *
	 * This allows you to change the maximum time the feed's server to respond
	 * and send the feed back.
	 *
	 * @since 1.0 Beta 3
	 * @param int $timeout The maximum number of seconds to spend waiting to retrieve a feed.
	 */
	public function set_timeout($timeout = 10)
	{
		$this->timeout = (int) $timeout;
	}

	/**
	 * Force SimplePie to use fsockopen() instead of cURL
	 *
	 * @since 1.0 Beta 3
	 * @param bool $enable Force fsockopen() to be used
	 */
	public function force_fsockopen($enable = false)
	{
		$this->force_fsockopen = (bool) $enable;
	}

	/**
	 * Enable/disable caching in SimplePie.
	 *
	 * This option allows you to disable caching all-together in SimplePie.
	 * However, disabling the cache can lead to longer load times.
	 *
	 * @since 1.0 Preview Release
	 * @param bool $enable Enable caching
	 */
	public function enable_cache($enable = true)
	{
		$this->cache = (bool) $enable;
	}

	/**
	 * Set the length of time (in seconds) that the contents of a feed will be
	 * cached
	 *
	 * @param int $seconds The feed content cache duration
	 */
	public function set_cache_duration($seconds = 3600)
	{
		$this->cache_duration = (int) $seconds;
	}

	/**
	 * Set the length of time (in seconds) that the autodiscovered feed URL will
	 * be cached
	 *
	 * @param int $seconds The autodiscovered feed URL cache duration.
	 */
	public function set_autodiscovery_cache_duration($seconds = 604800)
	{
		$this->autodiscovery_cache_duration = (int) $seconds;
	}

	/**
	 * Set the file system location where the cached files should be stored
	 *
	 * @param string $location The file system location.
	 */
	public function set_cache_location($location = './cache')
	{
		$this->cache_location = (string) $location;
	}

	/**
	 * Set whether feed items should be sorted into reverse chronological order
	 *
	 * @param bool $enable Sort as reverse chronological order.
	 */
	public function enable_order_by_date($enable = true)
	{
		$this->order_by_date = (bool) $enable;
	}

	/**
	 * Set the character encoding used to parse the feed
	 *
	 * This overrides the encoding reported by the feed, however it will fall
	 * back to the normal encoding detection if the override fails
	 *
	 * @param string $encoding Character encoding
	 */
	public function set_input_encoding($encoding = false)
	{
		if ($encoding)
		{
			$this->input_encoding = (string) $encoding;
		}
		else
		{
			$this->input_encoding = false;
		}
	}

	/**
	 * Set how much feed autodiscovery to do
	 *
	 * @see SIMPLEPIE_LOCATOR_NONE
	 * @see SIMPLEPIE_LOCATOR_AUTODISCOVERY
	 * @see SIMPLEPIE_LOCATOR_LOCAL_EXTENSION
	 * @see SIMPLEPIE_LOCATOR_LOCAL_BODY
	 * @see SIMPLEPIE_LOCATOR_REMOTE_EXTENSION
	 * @see SIMPLEPIE_LOCATOR_REMOTE_BODY
	 * @see SIMPLEPIE_LOCATOR_ALL
	 * @param int $level Feed Autodiscovery Level (level can be a combination of the above constants, see bitwise OR operator)
	 */
	public function set_autodiscovery_level($level = SIMPLEPIE_LOCATOR_ALL)
	{
		$this->autodiscovery = (int) $level;
	}

	/**
	 * Get the class registry
	 *
	 * Use this to override SimplePie's default classes
	 * @see SimplePie_Registry
	 * @return SimplePie_Registry
	 */
	public function &get_registry()
	{
		return $this->registry;
	}

	/**#@+
	 * Useful when you are overloading or extending SimplePie's default classes.
	 *
	 * @deprecated Use {@see get_registry()} instead
	 * @link http://php.net/manual/en/language.oop5.basic.php#language.oop5.basic.extends PHP5 extends documentation
	 * @param string $class Name of custom class
	 * @return boolean True on success, false otherwise
	 */
	/**
	 * Set which class SimplePie uses for caching
	 */
	public function set_cache_class