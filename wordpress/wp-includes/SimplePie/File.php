<?php
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
 * Used for fetching remote files and reading local files
 *
 * Supports HTTP 1.0 via cURL or fsockopen, with spotty HTTP 1.1 support
 *
 * This class can be overloaded with {@see SimplePie::set_file_class()}
 *
 * @package SimplePie
 * @subpackage HTTP
 * @todo Move to properly supporting RFC2616 (HTTP/1.1)
 */
class SimplePie_File
{
	var $url;
	var $useragent;
	var $success = true;
	var $headers = array();
	var $body;
	var $status_code;
	var $redirects = 0;
	var $error;
	var $method = SIMPLEPIE_FILE_SOURCE_NONE;

	public function __construct($url, $timeout = 10, $redirects = 5, $headers = null, $useragent = null, $force_fsockopen = false)
	{
		if (class_exists('idna_convert'))
		{
			$idn = new idna_convert();
			$parsed = SimplePie_Misc::parse_url($url);
			$url = SimplePie_Misc::compress_parse_url($parsed['scheme'], $idn->encode($parsed['authority']), $parsed['path'], $parsed['query'], $parsed['fragment']);
		}
		$this->url = $url;
		$this->useragent = $useragent;
		if (preg_match('/^http(s)?:\/\//i', $url))
		{
			if ($useragent === null)
			{
				$useragent = ini_get('user_agent');
				$this->useragent = $useragent;
			}
			if (!is_array($headers))
			{
				$headers = array();
			}
			if (!$force_fsockopen && function_exists('curl_exec'))
			{
				$this->method = SIMPLEPIE_FILE_SOURCE_REMOTE | SIMPLEPIE_FILE_SOURCE_CURL;
				$fp = curl_init();
				$headers2 = array();
				foreach ($headers as $key => $value)
				{
					$headers2[] = "$key: $value";
				}
				if (version_compare(SimplePie_Misc::get_curl_version(), '7.10.5', '>='))
				{
					curl_setopt($fp, CURLOPT_ENCODING, '');
				}
				curl_setopt($fp, CURLOPT_URL, $url);
				curl_set