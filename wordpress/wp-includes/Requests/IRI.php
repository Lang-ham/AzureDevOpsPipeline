<?php
/**
 * IRI parser/serialiser/normaliser
 *
 * @package Requests
 * @subpackage Utilities
 */

/**
 * IRI parser/serialiser/normaliser
 *
 * Copyright (c) 2007-2010, Geoffrey Sneddon and Steve Minutillo.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *  * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *  * Neither the name of the SimplePie Team nor the names of its contributors
 *       may be used to endorse or promote products derived from this software
 *       without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package Requests
 * @subpackage Utilities
 * @author Geoffrey Sneddon
 * @author Steve Minutillo
 * @copyright 2007-2009 Geoffrey Sneddon and Steve Minutillo
 * @license http://www.opensource.org/licenses/bsd-license.php
 * @link http://hg.gsnedders.com/iri/
 *
 * @property string $iri IRI we're working with
 * @property-read string $uri IRI in URI form, {@see to_uri}
 * @property string $scheme Scheme part of the IRI
 * @property string $authority Authority part, formatted for a URI (userinfo + host + port)
 * @property string $iauthority Authority part of the IRI (userinfo + host + port)
 * @property string $userinfo Userinfo part, formatted for a URI (after '://' and before '@')
 * @property string $iuserinfo Userinfo part of the IRI (after '://' and before '@')
 * @property string $host Host part, formatted for a URI
 * @property string $ihost Host part of the IRI
 * @property string $port Port part of the IRI (after ':')
 * @property string $path Path part, formatted for a URI (after first '/')
 * @property string $ipath Path part of the IRI (after first '/')
 * @property string $query Query part, formatted for a URI (after '?')
 * @property string $iquery Query part of the IRI (after '?')
 * @property string $fragment Fragment, formatted for a URI (after '#')
 * @property string $ifragment Fragment part of the IRI (after '#')
 */
class Requests_IRI {
	/**
	 * Scheme
	 *
	 * @var string
	 */
	protected $scheme = null;

	/**
	 * User Information
	 *
	 * @var string
	 */
	protected $iuserinfo = null;

	/**
	 * ihost
	 *
	 * @var string
	 */
	protected $ihost = null;

	/**
	 * Port
	 *
	 * @var string
	 */
	protected $port = null;

	/**
	 * ipath
	 *
	 * @var string
	 */
	protected $ipath = '';

	/**
	 * iquery
	 *
	 * @var string
	 */
	protected $iquery = null;

	/**
	 * ifragment
	 *
	 * @var string
	 */
	protected $ifragment = null;

	/**
	 * Normalization database
	 *
	 * Each key is the scheme, each value is an array with each key as the IRI
	 * part and value as the default value for that part.
	 */
	protected $normalization = array(
		'acap' => array(
			'port' => 674
		),
		'dict' => array(
			'port' => 2628
		),
		'file' => array(
			'ihost' => 'localhost'
		),
		'http' => array(
			'port' => 80,
		),
		'https' => array(
			'port' => 443,
		),
	);

	/**
	 * Return the entire IRI when you try and read the object as a string
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->get_iri();
	}

	/**
	 * Overload __set() to provide access via properties
	 *
	 * @param string $name Property name
	 * @param mixed $value Property value
	 */
	public function __set($name, $value) {
		if (method_exists($this, 'set_' . $name)) {
			call_user_func(array($this, 'set_' . $name), $value);
		}
		elseif (
			   $name === 'iauthority'
			|| $name === 'iuserinfo'
			|| $name === 'ihost'
			|| $name === 'ipath'
			|| $name === 'iquery'
			|| $name === 'ifragment'
		) {
			call_user_func(array($this, 'set_' . substr($name, 1)), $value);
		}
	}

	/**
	 * Overload __get() to provide access via properties
	 *
	 * @param string $name Property name
	 * @return mixed
	 */
	public function __get($name) {
		// isset() returns false for null, we don't want to do that
		// Also why we use array_key_exists below instead of isset()
		$props = get_object_vars($this);

		if (
			$name === 'iri' ||
			$name === 'uri' ||
			$name === 'iauthority' ||
			$name === 'authority'
		) {
			$method = 'get_' . $name;
			$return = $this->$method();
		}
		elseif (array_key_exists($name, $props)) {
			$return = $this->$name;
		}
		// host -> ihost
		elseif (($prop = 'i' . $name) && array_key_exists($prop, $props)) {
			$name = $prop;
			$return = $this->$prop;
		}
		// ischeme -> scheme
		elseif (($prop = substr($name, 1)) && array_key_exists($prop, $props)) {
			$name = $prop;
			$return = $this->$prop;
		}
		else {
			trigger_error('Undefined property: ' . get_class($this) . '::' . $name, E_USER_NOTICE);
			$return = null;
		}

		if ($return === null && isset($this->normalization[$this->scheme][$name])) {
			return $this->normalization[$this->scheme][$name];
		}
		else {
			return $return;
		}
	}

	/**
	 * Overload __isset() to provide access via properties
	 *
	 * @param string $name Property name
	 * @return bool
	 */
	public function __isset($name) {
		return (method_exists($this, 'get_' . $name) || isset($this->$name));
	}

	/**
	 * Overload __unset() to provide access via properties
	 *
	 * @param string $name Property name
	 */
	public function __unset($name) {
		if (method_exists($this, 'set_' . $name)) {
			call_user_func(array($this, 'set_' . $name), '');
		}
	}

	/**
	 * Create a new IRI object, from a specified string
	 *
	 * @param string|null $iri
	 */
	public function __construct($iri = null) {
		$this->set_iri($iri);
	}

	/**
	 * Create a new IRI object by resolving a relative IRI
	 *
	 * Returns false if $base is not absolute, otherwise an IRI.
	 *
	 * @param IRI|string $base (Absolute) Base IRI
	 * @param IRI|string $relative Relative IRI
	 * @return IRI|false
	 */
	public static function absolutize($base, $relative) {
		if (!($relative instanceof Requests_IRI)) {
			$relative = new Requests_IRI($relative);
		}
		if (!$relative->is_valid()) {
			return false;
		}
		elseif ($relative->scheme !== null) {
			return clone $relative;
		}

		if (!($base instanceof Requests_IRI)) {
			$base = new Requests_IRI($base);
		}
		if ($base->scheme === null || !$base->is_valid()) {
			return false;
		}

		if ($relative->get_iri() !== '') {
			if ($relative->iuserinfo !== null || $relative->ihost !== null || $relative->port !== null) {
				$target = clone $relative;
				$target->scheme = $base->scheme;
			}
			else {
				$target = new Requests_IRI;
				$target->scheme = $base->scheme;
				$target->iuserinfo = $base->iuserinfo;
				$target->ihost = $base->ihost;
				$target->port = $base->port;
				if ($relative->ipath !== '') {
					if ($relative->ipath[0] === '/') {
						$target->ipath = $relative->ipath;
					}
					elseif (($base->iuserinfo !== null || $base->ihost !== null || $base->port !== null) && $base->ipath === '') {
						$target->ipath = '/' . $relative->ipath;
					}
					elseif (($last_segment = strrpos($base->ipath, '/')) !== false) {
						$target->ipath = substr($base->ipath, 0, $last_segment + 1) . $relative->ipath;
					}
					else {
						$target->ipath = $relative->ipath;
					}
					$target->ipath = $target->remove_dot_segments($target->ipath);
					$target->iquery = $relative->iquery;
				}
				else {
					$target->ipath = $base->ipath;
					if ($relative->iquery !== null) {
						$target->iquery = $relative->iquery;
					}
					elseif ($base->iquery !== null) {
						$target->iquery = $base->iquery;
					}
				}
				$target->ifragment = $relative->ifragment;
			}
		}
		else {
			$target = clone $base;
			$target->ifragment = null;
		}
		$target->scheme_normalization();
		return $target;
	}

	/**
	 * Parse an IRI into scheme/authority/path/query/fragment segments
	 *
	 * @param string $iri
	 * @return array
	 */
	protected function parse_iri($iri) {
		$iri = trim($iri, "\x20\x09\x0A\x0C\x0D");
		$has_match = preg_match('/^((?P<scheme>[^:\/?#]+):)?(\/\/(?P<authority>[^\/?#]*))?(?P<path>[^?#]*)(\?(?P<query>[^#]*))?(#(?P<fragment>.*))?$/', $iri, $match);
		if (!$has_match) {
			throw new Requests_Exception('Cannot parse supplied IRI', 'iri.cannot_parse', $iri);
		}

		if ($match[1] === '') {
			$match['scheme'] = null;
		}
		if (!isset($match[3]) || $match[3] === '') {
			$match['authority'] = null;
		}
		if (!isset($match[5])) {
			$match['path'] = '';
		}
		if (!isset($match[6]) || $match[6] === '') {
			$match['query'] = null;
		}
		if (!isset($match[8]) || $match[8] === '') {
			$match['fragment'] = null;
		}
		return $match;
	}

	/**
	 * Remove dot segments from a path
	 *
	 * @param string $input
	 * @return string
	 */
	protected function remove_dot_segments($input) {
		$output = '';
		while (strpos($input, './') !== false || strpos($input, '/.') !== false || $input === '.' || $input === '..') {
			// A: If the input buffer begins with a prefix of "../" or "./",
			// then remove that prefix from the input buffer; otherwise,
			if (strpos($input, '../') === 0) {
				$input = substr($input, 3);
			}
			elseif (strpos($input, './') === 0) {
				$input = substr($input, 2);
			}
			// B: if the input buffer begins with a prefix of "/./" or "/.",
			// where "." is a complete path segment, then replace that prefix
			// with "/" in the input buffer; otherwise,
			elseif (strpos($input, '/./') === 0) {
				$input = substr($input, 2);
			}
			elseif ($input === '/.') {
				$input = '/';
			}
			// C: if the input buffer begins with a prefix of "/../" or "/..",
			// where ".." is a complete path segment, then replace that prefix
			// with "/" in the input buffer and remove the last segment and its
			// preceding "/" (if any) from the output buffer; otherwise,
			elseif (strpos($input, '/../') === 0) {
				$input = substr($input, 3);
				$output = substr_replace($output, '', strrpos($output, '/'));
			}
			elseif ($input === '/..') {
				$input = '/';
				$output = substr_replace($output, '', strrpos($output, '/'));
			}
			// D: if the input buffer consists only of "." or "..", then remove
			// that from the input buffer; otherwise,
			elseif ($input === '.' || $input === '..') {
				$input = '';
			}
			// E: move the first path segment in the input buffer to the end of
			// the output buffer, including the initial "/" character (if any)
			// and any subsequent characters up to, but not including, the next
			// "/" character or the end of the input buffer
			elseif (($pos = strpos($input, '/', 1)) !== false) {
				$output .= substr($input, 0, $pos);
				$input = substr_replace($input, '', 0, $pos);
			}
			else {
				$output .= $input;
				$input = '';
			}
		}
		return $output . $input;
	}

	/**
	 * Replace invalid character with percent encoding
	 *
	 * @param string $string Input string
	 * @param string $extra_chars Valid characters not in iunreserved or
	 *                            iprivate (this is ASCII-only)
	 * @param bool $iprivate Allow iprivate
	 * @return string
	 */
	protected function replace_invalid_with_pct_encoding($string, $extra_chars, $iprivate = false) {
		// Normalize as many pct-encoded sections as possible
		$string = preg_replace_callback('/(?:%[A-Fa-f0-9]{2})+/', array(&$this, 'remove_iunreserved_percent_encoded'), $string);

		// Replace invalid percent characters
		$string = preg_replace('/%(?![A-Fa-f0-9]{2})/', '%25', $string);

		// Add unreserved and % to $extra_chars (the latter is safe because all
		// pct-encoded sections are now valid).
		$extra_chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~%';

		// Now replace any bytes that aren't allowed with their pct-encoded versions
		$position = 0;
		$strlen = strlen($string);
		while (($position += strspn($string, $extra_chars, $position)) < $strlen) {
			$value = ord($string[$position]);

			// Start position
			$start = $position;

			// By default we are valid
			$valid = true;

			// No one byte sequences are valid due to the while.
			// Two byte sequence:
			if (($value & 0xE0) === 0xC0) {
				$character = ($value & 0x1F) << 6;
				$length = 2;
				$remaining = 1;
			}
			// Three byte sequence:
			elseif (($value & 0xF0) === 0xE0) {
				$character = ($value & 0x0F) << 12;
				$length = 3;
				$remaining = 2;
			}
			// Four byte sequence:
			elseif (($value & 0xF8) === 0xF0) {
				$character = ($value & 0x07) << 18;
				$length = 4;
				$remaining = 3;
			}
			// Invalid byte:
			else {
				$valid = false;
				$length = 1;
				$remaining = 0;
			}

			if ($remaining) {
				if ($position + $length <= $strlen) {
					for ($position++; $remaining; $position++) {
						$value = ord($string[$position]);

						// Check that the byte is valid, then add it to the character:
						if (($value & 0xC0) === 0x80) {
							$character |= ($value & 0x3F) << (--$remaining * 6);
						}
						// If it is invalid, count the sequence as invalid and reprocess the current byte:
						else {
							$valid = false;
							$position--;
							break;
						}
					}
				}
				else {
					$position = $strlen - 1;
					$valid = false;
				}
			}

			// Percent encode anything invalid or not in ucschar
			if (
				// Invalid sequences
				!$valid
				// Non-shortest form sequences are invalid
				|| $length > 1 && $character <= 0x7F
				|| $length > 2 && $character <= 0x7FF
				|| $length > 3 && $character <= 0xFFFF
				// Outside of range of ucschar codepoints
				// Noncharacters
				|| ($character & 0xFFFE) === 0xFFFE
				|| $character >= 0xFDD0 && $character <= 0xFDEF
				|| (
					// Everything else not in ucschar
					   $character > 0xD7FF && $character < 0xF900
					|| $character < 0xA0
					|| $character > 0xEFFFD
				)
				&& (
					// Everything not in iprivate, if it applies
					   !$iprivate
					|| $character < 0xE000
					|| $character > 0x10FFFD
				)
			) {
				// If we were a character, pretend we weren't, but rather an error.
				if ($valid) {
					$position--;
				}

				for ($j = $start; $j <= $position; $j++) {
					$string = substr_replace($string, sprintf('%%%02X', ord($string[$j])), $j, 1);
					$j += 2;
					$position += 2;
					$strlen += 2;
				}
			}
		}

		return $string;
	}

	/**
	 * Callback function for preg_replace_callback.
	 *
	 * Removes sequences of percent encoded bytes that represent UTF-8
	 * encoded characters in iunreserved
	 *
	 * @param array $match PCRE match
	 * @return string Replacement
	 */
	protected function remove_iunreserved_percent_encoded($match) {
		// As we just have valid percent encoded sequences we can just explode
		// and ignore the first member of the returned array (an empty string).
		$bytes = explode('%', $match[0]);

		// Initialize the new string (this is what will be returned) and that
		// there are no bytes remaining in the current sequence (unsurprising
		// at the first byte!).
		$string = '';
		$remaining = 0;

		// Loop over each and every byte, and set $value to its value
		for ($i = 1, $len = count($bytes); $i < $len; $i++) {
			$value = hexdec($bytes[$i]);

			// If we're the first byte of sequence:
			if (!$remaining) {
				// Start position
				$start = $i;

				// By default we are valid
				$valid = true;

				// One byte sequence:
				if ($value <= 0x7F) {
					$character = $value;
					$length = 1;
				}
				// Two byte sequence:
				elseif (($value & 0xE0) === 0xC0) {
					$character = ($value & 0x1F) << 6;
					$length = 2;
					$remaining = 1;
				}
				// Three byte sequence:
				elseif (($value & 0xF0) === 0xE0) {
					$character = ($value & 0x0F) << 12;
					$length = 3;
					$r