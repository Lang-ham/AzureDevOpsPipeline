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
 * Miscellanous utilities
 *
 * @package SimplePie
 */
class SimplePie_Misc
{
	public static function time_hms($seconds)
	{
		$time = '';

		$hours = floor($seconds / 3600);
		$remainder = $seconds % 3600;
		if ($hours > 0)
		{
			$time .= $hours.':';
		}

		$minutes = floor($remainder / 60);
		$seconds = $remainder % 60;
		if ($minutes < 10 && $hours > 0)
		{
			$minutes = '0' . $minutes;
		}
		if ($seconds < 10)
		{
			$seconds = '0' . $seconds;
		}

		$time .= $minutes.':';
		$time .= $seconds;

		return $time;
	}

	public static function absolutize_url($relative, $base)
	{
		$iri = SimplePie_IRI::absolutize(new SimplePie_IRI($base), $relative);
		if ($iri === false)
		{
			return false;
		}
		return $iri->get_uri();
	}

	/**
	 * Get a HTML/XML element from a HTML string
	 *
	 * @deprecated Use DOMDocument instead (parsing HTML with regex is bad!)
	 * @param string $realname Element name (including namespace prefix if applicable)
	 * @param string $string HTML document
	 * @return array
	 */
	public static function get_element($realname, $string)
	{
		$return = array();
		$name = preg_quote($realname, '/');
		if (preg_match_all("/<($name)" . SIMPLEPIE_PCRE_HTML_ATTRIBUTE . "(>(.*)<\/$name>|(\/)?>)/siU", $string, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
		{
			for ($i = 0, $total_matches = count($matches); $i < $total_matches; $i++)
			{
				$return[$i]['tag'] = $realname;
				$return[$i]['full'] = $matches[$i][0][0];
				$return[$i]['offset'] = $matches[$i][0][1];
				if (strlen($matches[$i][3][0]) <= 2)
				{
					$return[$i]['self_closing'] = true;
				}
				else
				{
					$return[$i]['self_closing'] = false;
					$return[$i]['content'] = $matches[$i][4][0];
				}
				$return[$i]['attribs'] = array();
				if (isset($matches[$i][2][0]) && preg_match_all('/[\x09\x0A\x0B\x0C\x0D\x20]+([^\x09\x0A\x0B\x0C\x0D\x20\x2F\x3E][^\x09\x0A\x0B\x0C\x0D\x20\x2F\x3D\x3E]*)(?:[\x09\x0A\x0B\x0C\x0D\x20]*=[\x09\x0A\x0B\x0C\x0D\x20]*(?:"([^"]*)"|\'([^\']*)\'|([^\x09\x0A\x0B\x0C\x0D\x20\x22\x27\x3E][^\x09\x0A\x0B\x0C\x0D\x20\x3E]*)?))?/', ' ' . $matches[$i][2][0] . ' ', $attribs, PREG_SET_ORDER))
				{
					for ($j = 0, $total_attribs = count($attribs); $j < $total_attribs; $j++)
					{
						if (count($attribs[$j]) === 2)
						{
							$attribs[$j][2] = $attribs[$j][1];
						}
						$return[$i]['attribs'][strtolower($attribs[$j][1])]['data'] = SimplePie_Misc::entities_decode(end($attribs[$j]));
					}
				}
			}
		}
		return $return;
	}

	public static function element_implode($element)
	{
		$full = "<$element[tag]";
		foreach ($element['attribs'] as $key => $value)
		{
			$key = strtolower($key);
			$full .= " $key=\"" . htmlspecialchars($value['data']) . '"';
		}
		if ($element['self_closing'])
		{
			$full .= ' />';
		}
		else
		{
			$full .= ">$element[content]</$element[tag]>";
		}
		return $full;
	}

	public static function error($message, $level, $file, $line)
	{
		if ((ini_get('error_reporting') & $level) > 0)
		{
			switch ($level)
			{
				case E_USER_ERROR:
					$note = 'PHP Error';
					break;
				case E_USER_WARNING:
					$note = 'PHP Warning';
					break;
				case E_USER_NOTICE:
					$note = 'PHP Notice';
					break;
				default:
					$note = 'Unknown Error';
					break;
			}

			$log_error = true;
			if (!function_exists('error_log'))
			{
				$log_error = false;
			}

			$log_file = @ini_get('error_log');
			if (!empty($log_file) && ('syslog' !== $log_file) && !@is_writable($log_file))
			{
				$log_error = false;
			}

			if ($log_error)
			{
				@error_log("$note: $message in $file on line $line", 0);
			}
		}

		return $message;
	}

	public static function fix_protocol($url, $http = 1)
	{
		$url = SimplePie_Misc::normalize_url($url);
		$parsed = SimplePie_Misc::parse_url($url);
		if ($parsed['scheme'] !== '' && $parsed['scheme'] !== 'http' && $parsed['scheme'] !== 'https')
		{
			return SimplePie_Misc::fix_protocol(SimplePie_Misc::compress_parse_url('http', $parsed['authority'], $parsed['path'], $parsed['query'], $parsed['fragment']), $http);
		}

		if ($parsed['scheme'] === '' && $parsed['authority'] === '' && !file_exists($url))
		{
			return SimplePie_Misc::fix_protocol(SimplePie_Misc::compress_parse_url('http', $parsed['path'], '', $parsed['query'], $parsed['fragment']), $http);
		}

		if ($http === 2 && $parsed['scheme'] !== '')
		{
			return "feed:$url";
		}
		elseif ($http === 3 && strtolower($parsed['scheme']) === 'http')
		{
			return substr_replace($url, 'podcast', 0, 4);
		}
		elseif ($http === 4 && strtolower($parsed['scheme']) === 'http')
		{
			return substr_replace($url, 'itpc', 0, 4);
		}
		else
		{
			return $url;
		}
	}

	public static function parse_url($url)
	{
		$iri = new SimplePie_IRI($url);
		return array(
			'scheme' => (string) $iri->scheme,
			'authority' => (string) $iri->authority,
			'path' => (string) $iri->path,
			'query' => (string) $iri->query,
			'fragment' => (string) $iri->fragment
		);
	}

	public static function compress_parse_url($scheme = '', $authority = '', $path = '', $query = '', $fragment = '')
	{
		$iri = new SimplePie_IRI('');
		$iri->scheme = $scheme;
		$iri->authority = $authority;
		$iri->path = $path;
		$iri->query = $query;
		$iri->fragment = $fragment;
		return $iri->get_uri();
	}

	public static function normalize_url($url)
	{
		$iri = new SimplePie_IRI($url);
		return $iri->get_uri();
	}

	public static function percent_encoding_normalization($match)
	{
		$integer = hexdec($match[1]);
		if ($integer >= 0x41 && $integer <= 0x5A || $integer >= 0x61 && $integer <= 0x7A || $integer >= 0x30 && $integer <= 0x39 || $integer === 0x2D || $integer === 0x2E || $integer === 0x5F || $integer === 0x7E)
		{
			return chr($integer);
		}
		else
		{
			return strtoupper($match[0]);
		}
	}

	/**
	 * Converts a Windows-1252 encoded string to a UTF-8 encoded string
	 *
	 * @static
	 * @param string $string Windows-1252 encoded string
	 * @return string UTF-8 encoded string
	 */
	public static function windows_1252_to_utf8($string)
	{
		static $convert_table = array("\x80" => "\xE2\x82\xAC", "\x81" => "\xEF\xBF\xBD", "\x82" => "\xE2\x80\x9A", "\x83" => "\xC6\x92", "\x84" => "\xE2\x80\x9E", "\x85" => "\xE2\x80\xA6", "\x86" => "\xE2\x80\xA0", "\x87" => "\xE2\x80\xA1", "\x88" => "\xCB\x86", "\x89" => "\xE2\x80\xB0", "\x8A" => "\xC5\xA0", "\x8B" => "\xE2\x80\xB9", "\x8C" => "\xC5\x92", "\x8D" => "\xEF\xBF\xBD", "\x8E" => "\xC5\xBD", "\x8F" => "\xEF\xBF\xBD", "\x90" => "\xEF\xBF\xBD", "\x91" => "\xE2\x80\x98", "\x92" => "\xE2\x80\x99", "\x93" => "\xE2\x80\x9C", "\x94" => "\xE2\x80\x9D", "\x95" => "\xE2\x80\xA2", "\x96" => "\xE2\x80\x93", "\x97" => "\xE2\x80\x94", "\x98" => "\xCB\x9C", "\x99" => "\xE2\x84\xA2", "\x9A" => "\xC5\xA1", "\x9B" => "\xE2\x80\xBA", "\x9C" => "\xC5\x93", "\x9D" => "\xEF\xBF\xBD", "\x9E" => "\xC5\xBE", "\x9F" => "\xC5\xB8", "\xA0" => "\xC2\xA0", "\xA1" => "\xC2\xA1", "\xA2" => "\xC2\xA2", "\xA3" => "\xC2\xA3", "\xA4" => "\xC2\xA4", "\xA5" => "\xC2\xA5", "\xA6" => "\xC2\xA6", "\xA7" => "\xC2\xA7", "\xA8" => "\xC2\xA8", "\xA9" => "\xC2\xA9", "\xAA" => "\xC2\xAA", "\xAB" => "\xC2\xAB", "\xAC" => "\xC2\xAC", "\xAD" => "\xC2\xAD", "\xAE" => "\xC2\xAE", "\xAF" => "\xC2\xAF", "\xB0" => "\xC2\xB0", "\xB1" => "\xC2\xB1", "\xB2" => "\xC2\xB2", "\xB3" => "\xC2\xB3", "\xB4" => "\xC2\xB4", "\xB5" => "\xC2\xB5", "\xB6" => "\xC2\xB6", "\xB7" => "\xC2\xB7", "\xB8" => "\xC2\xB8", "\xB9" => "\xC2\xB9", "\xBA" => "\xC2\xBA", "\xBB" => "\xC2\xBB", "\xBC" => "\xC2\xBC", "\xBD" => "\xC2\xBD", "\xBE" => "\xC2\xBE", "\xBF" => "\xC2\xBF", "\xC0" => "\xC3\x80", "\xC1" => "\xC3\x81", "\xC2" => "\xC3\x82", "\xC3" => "\xC3\x83", "\xC4" => "\xC3\x84", "\xC5" => "\xC3\x85", "\xC6" => "\xC3\x86", "\xC7" => "\xC3\x87", "\xC8" => "\xC3\x88", "\xC9" => "\xC3\x89", "\xCA" => "\xC3\x8A", "\xCB" => "\xC3\x8B", "\xCC" => "\xC3\x8C", "\xCD" => "\xC3\x8D", "\xCE" => "\xC3\x8E", "\xCF" => "\xC3\x8F", "\xD0" => "\xC3\x90", "\xD1" => "\xC3\x91", "\xD2" => "\xC3\x92", "\xD3" => "\xC3\x93", "\xD4" => "\xC3\x94", "\xD5" => "\xC3\x95", "\xD6" => "\xC3\x96", "\xD7" => "\xC3\x97", "\xD8" => "\xC3\x98", "\xD9" => "\xC3\x99", "\xDA" => "\xC3\x9A", "\xDB" => "\xC3\x9B", "\xDC" => "\xC3\x9C", "\xDD" => "\xC3\x9D", "\xDE" => "\xC3\x9E", "\xDF" => "\xC3\x9F", "\xE0" => "\xC3\xA0", "\xE1" => "\xC3\xA1", "\xE2" => "\xC3\xA2", "\xE3" => "\xC3\xA3", "\xE4" => "\xC3\xA4", "\xE5" => "\xC3\xA5", "\xE6" => "\xC3\xA6", "\xE7" => "\xC3\xA7", "\xE8" => "\xC3\xA8", "\xE9" => "\xC3\xA9", "\xEA" => "\xC3\xAA", "\xEB" => "\xC3\xAB", "\xEC" => "\xC3\xAC", "\xED" => "\xC3\xAD", "\xEE" => "\xC3\xAE", "\xEF" => "\xC3\xAF", "\xF0" => "\xC3\xB0", "\xF1" => "\xC3\xB1", "\xF2" => "\xC3\xB2", "\xF3" => "\xC3\xB3", "\xF4" => "\xC3\xB4", "\xF5" => "\xC3\xB5", "\xF6" => "\xC3\xB6", "\xF7" => "\xC3\xB7", "\xF8" => "\xC3\xB8", "\xF9" => "\xC3\xB9", "\xFA" => "\xC3\xBA", "\xFB" => "\xC3\xBB", "\xFC" => "\xC3\xBC", "\xFD" => "\xC3\xBD", "\xFE" => "\xC3\xBE", "\xFF" => "\xC3\xBF");

		return strtr($string, $convert_table);
	}

	/**
	 * Change a string from one encoding to another
	 *
	 * @param string $data Raw data in $input encoding
	 * @param string $input Encoding of $data
	 * @param string $output Encoding you want
	 * @return string|boolean False if we can't convert it
	 */
	public static function change_encoding($data, $input, $output)
	{
		$input = SimplePie_Misc::encoding($input);
		$output = SimplePie_Misc::encoding($output);

		// We fail to fail on non US-ASCII bytes
		if ($input === 'US-ASCII')
		{
			static $non_ascii_octects = '';
			if (!$non_ascii_octects)
			{
				for ($i = 0x80; $i <= 0xFF; $i++)
				{
					$non_ascii_octects .= chr($i);
				}
			}
			$data = substr($data, 0, strcspn($data, $non_ascii_octects));
		}

		// This is first, as behaviour of this is completely predictable
		if ($input === 'windows-1252' && $output === 'UTF-8')
		{
			return SimplePie_Misc::windows_1252_to_utf8($data);
		}
		// This is second, as behaviour of this varies only with PHP version (the middle part of this expression checks the encoding is supported).
		elseif (function_exists('mb_convert_encoding') && ($return = SimplePie_Misc::change_encoding_mbstring($data, $input, $output)))
		{
			return $return;
 		}
		// This is last, as behaviour of this varies with OS userland and PHP version
		elseif (function_exists('iconv') && ($return = SimplePie_Misc::change_encoding_iconv($data, $input, $output)))
		{
			return $return;
		}
		// If we can't do anything, just fail
		else
		{
			return false;
		}
	}

	protected static function change_encoding_mbstring($data, $input, $output)
	{
		if ($input === 'windows-949')
		{
			$input = 'EUC-KR';
		}
		if ($output === 'windows-949')
		{
			$output = 'EUC-KR';
		}
		if ($input === 'Windows-31J')
		{
			$input = 'SJIS';
		}
		if ($output === 'Windows-31J')
		{
			$output = 'SJIS';
		}

		// Check that the encoding is supported
		if (@mb_convert_encoding("\x80", 'UTF-16BE', $input) === "\x00\x80")
		{
			return false;
		}
		if (!in_array($input, mb_list_encodings()))
		{
			return false;
		}

		// Let's do some conversion
		if ($return = @mb_convert_encoding($data, $output, $input))
		{
			return $return;
		}

		return false;
	}

	protected static function change_encoding_iconv($data, $input, $output)
	{
		return @iconv($input, $output, $data);
	}

	/**
	 * Normalize an encoding name
	 *
	 * This is automatically generated by create.php
	 *
	 * To generate it, run `php create.php` on the command line, and copy the
	 * output to replace this function.
	 *
	 * @param string $charset Character set to standardise
	 * @return string Standardised name
	 */
	public static function encoding($charset)
	{
		// Normalization from UTS #22
		switch (strtolower(preg_replace('/(?:[^a-zA-Z0-9]+|([^0-9])0+)/', '\1', $charset)))
		{
			case 'adobestandardencoding':
			case 'csadobestandardencoding':
				return 'Adobe-Standard-Encoding';

			case 'adobesymbolencoding':
			case 'cshppsmath':
				return 'Adobe-Symbol-Encoding';

			case 'ami1251':
			case 'amiga1251':
				return 'Amiga-1251';

			case 'ansix31101983':
			case 'csat5001983':
			case 'csiso99naplps':
			case 'isoir99':
			case 'naplps':
				return 'ANSI_X3.110-1983';

			case 'arabic7':
			case 'asmo449':
			case 'csiso89asmo449':
			case 'iso9036':
			case 'isoir89':
				return 'ASMO_449';

			case 'big5':
			case 'csbig5':
				return 'Big5';

			case 'big5hkscs':
				return 'Big5-HKSCS';

			case 'bocu1':
			case 'csbocu1':
				return 'BOCU-1';

			case 'brf':
			case 'csbrf':
				return 'BRF';

			case 'bs4730':
			case 'csiso4unitedkingdom':
			case 'gb':
			case 'iso646gb':
			case 'isoir4':
			case 'uk':
				return 'BS_4730';

			case 'bsviewdata':
			case 'csiso47bsviewdata':
			case 'isoir47':
				return 'BS_viewdata';

			case 'cesu8':
			case 'cscesu8':
				return 'CESU-8';

			case 'ca':
			case 'csa71':
			case 'csaz243419851':
			case 'csiso121canadian1':
			case 'iso646ca':
			case 'isoir121':
				return 'CSA_Z243.4-1985-1';

			case 'csa72':
			case 'csaz243419852':
			case 'csiso122canadian2':
			case 'iso646ca2':
			case 'isoir122':
				return 'CSA_Z243.4-1985-2';

			case 'csaz24341985gr':
			case 'csiso123csaz24341985gr':
			case 'isoir123':
				return 'CSA_Z243.4-1985-gr';

			case 'csiso139csn369103':
			case 'csn369103':
			case 'isoir139':
				return 'CSN_369103';

			case 'csdecmcs':
			case 'dec':
			case 'decmcs':
				return 'DEC-MCS';

			case 'csiso21german':
			case 'de':
			case 'din66003':
			case 'iso646de':
			case 'isoir21':
				return 'DIN_66003';

			case 'csdkus':
			case 'dkus':
				return 'dk-us';

			case 'csiso646danish':
			case 'dk':
			case 'ds2089':
			case 'iso646dk':
				return 'DS_2089';

			case 'csibmebcdicatde':
			case 'ebcdicatde':
				return 'EBCDIC-AT-DE';

			case 'csebcdicatdea':
			case 'ebcdicatdea':
				return 'EBCDIC-AT-DE-A';

			case 'csebcdiccafr':
			case 'ebcdiccafr':
				return 'EBCDIC-CA-FR';

			case 'csebcdicdkno':
			case 'ebcdicdkno':
				return 'EBCDIC-DK-NO';

			case 'csebcdicdknoa':
			case 'ebcdicdknoa':
				return 'EBCDIC-DK-NO-A';

			case 'csebcdices':
			case 'ebcdices':
				return 'EBCDIC-ES';

			case 'csebcdicesa':
			case 'ebcdicesa':
				return 'EBCDIC-ES-A';

			case 'csebcdicess':
			case 'ebcdicess':
				return 'EBCDIC-ES-S';

			case 'csebcdicfise':
			case 'ebcdicfise':
				return 'EBCDIC-FI-SE';

			case 'csebcdicfisea':
			case 'ebcdicfisea':
				return 'EBCDIC-FI-SE-A';

			case 'csebcdicfr':
			case 'ebcdicfr':
				return 'EBCDIC-FR';

			case 'csebcdicit':
			case 'ebcdicit':
				return 'EBCDIC-IT';

			case 'csebcdicpt':
			case 'ebcdicpt':
				return 'EBCDIC-PT';

			case 'csebcdicuk':
			case 'ebcdicuk':
				return 'EBCDIC-UK';

			case 'csebcdicus':
			case 'ebcdicus':
				return 'EBCDIC-US';

			case 'csiso111ecmacyrillic':
			case 'ecmacyrillic':
			case 'isoir111':
			case 'koi8e':
				return 'ECMA-cyrillic';

			case 'csiso17spanish':
			case 'es':
			case 'iso646es':
			case 'isoir17':
				return 'ES';

			case 'csiso85spanish2':
			case 'es2':
			case 'iso646es2':
			case 'isoir85':
				return 'ES2';

			case 'cseucpkdfmtjapanese':
			case 'eucjp':
			case 'extendedunixcodepackedformatforjapanese':
				return 'EUC-JP';

			case 'cseucfixwidjapanese':
			case 'extendedunixcodefixedwidthforjapanese':
				return 'Extended_UNIX_Code_Fixed_Width_for_Japanese';

			case 'gb18030':
				return 'GB18030';

			case 'chinese':
			case 'cp936':
			case 'csgb2312':
			case 'csiso58gb231280':
			case 'gb2312':
			case 'gb231280':
			case 'gbk':
			case 'isoir58':
			case 'ms936':
			case 'windows936':
				return 'GBK';

			case 'cn':
			case 'csiso57gb1988':
			case 'gb198880':
			case 'iso646cn':
			case 'isoir57':
				return 'GB_1988-80';

			case 'csiso153gost1976874':
			case 'gost1976874':
			case 'isoir153':
			case 'stsev35888':
				return 'GOST_19768-74';

			case 'csiso150':
			case 'csiso150greekccitt':
			case 'greekccitt':
			case 'isoir150':
				return 'greek-ccitt';

			case 'csiso88greek7':
			case 'greek7':
			case 'isoir88':
				return 'greek7';

			case 'csiso18greek7old':
			case 'greek7old':
			case 'isoir18':
				return 'greek7-old';

			case 'cshpdesktop':
			case 'hpdesktop':
				return 'HP-DeskTop';

			case 'cshplegal':
			case 'hplegal':
				return 'HP-Legal';

			case 'cshpmath8':
			case 'hpmath8':
				return 'HP-Math8';

			case 'cshppifont':
			case 'hppifont':
				return 'HP-Pi-font';

			case 'cshproman8':
			case 'hproman8':
			case 'r8':
			case 'roman8':
				return 'hp-roman8';

			case 'hzgb2312':
				return 'HZ-GB-2312';

			case 'csibmsymbols':
			case 'ibmsymbols':
				return 'IBM-Symbols';

			case 'csibmthai':
			case 'ibmthai':
				return 'IBM-Thai';

			case 'cp37':
			case 'csibm37':
			case 'ebcdiccpca':
			case 'ebcdiccpnl':
			case 'ebcdiccpus':
			case 'ebcdiccpwt':
			case 'ibm37':
				return 'IBM037';

			case 'cp38':
			case 'csibm38':
			case 'ebcdicint':
			case 'ibm38':
				return 'IBM038';

			case 'cp273':
			case 'csibm273':
			case 'ibm273':
				return 'IBM273';

			case 'cp274':
			case 'csibm274':
			case 'ebcdicbe':
			case 'ibm274':
				return 'IBM274';

			case 'cp275':
			case 'csibm275':
			case 'ebcdicbr':
			case 'ibm275':
				return 'IBM275';

			case 'csibm277':
			case 'ebcdiccpdk':
			case 'ebcdiccpno':
			case 'ibm277':
				return 'IBM277';

			case 'cp278':
			case 'csibm278':
			case 'ebcdiccpfi':
			case 'ebcdiccpse':
			case 'ibm278':
				return 'IBM278';

			case 'cp280':
			case 'csibm280':
			case 'ebcdiccpit':
			case 'ibm280':
				return 'IBM280';

			case 'cp281':
			case 'csibm281':
			case 'ebcdicjpe':
			case 'ibm281':
				return 'IBM281';

			case 'cp284':
			case 'csibm284':
			case 'ebcdiccpes':
			case 'ibm284':
				return 'IBM284';

			case 'cp285':
			case 'csibm285':
			case 'ebcdiccpgb':
			case 'ibm285':
				return 'IBM285';

			case 'cp290':
			case 'csibm290':
			case 'ebcdicjpkana':
			case 'ibm290':
				return 'IBM290';

			case 'cp297':
			case 'csibm297':
			case 'ebcdiccpfr':
			case 'ibm297':
				return 'IBM297';

			case 'cp420':
			case 'csibm420':
			case 'ebcdiccpar1':
			case 'ibm420':
				return 'IBM420';

			case 'cp423':
			case 'csibm423':
			case 'ebcdiccpgr':
			case 'ibm423':
				return 'IBM423';

			case 'cp424':
			case 'csibm424':
			case 'ebcdiccphe':
			case 'ibm424':
				return 'IBM424';

			case '437':
			case 'cp437':
			case 'cspc8codepage437':
			case 'ibm437':
				return 'IBM437';

			case 'cp500':
			case 'csibm500':
			case 'ebcdiccpbe':
			case 'ebcdiccpch':
			case 'ibm500':
				return 'IBM500';

			case 'cp775':
			case 'cspc775baltic':
			case 'ibm775':
				return 'IBM775';

			case '850':
			case 'cp850':
			case 'cspc850multilingual':
			case 'ibm850':
				return 'IBM850';

			case '851':
			case 'cp851':
			case 'csibm851':
			case 'ibm851':
				return 'IBM851';

			case '852':
			case 'cp852':
			case 'cspcp852':
			case 'ibm852':
				return 'IBM852';

			case '855':
			case 'cp855':
			case 'csibm855':
			case 'ibm855':
				return 'IBM855';

			case '857':
			case 'cp857':
			case 'csibm857':
			case 'ibm857':
				return 'IBM857';

			case 'ccsid858':
			case 'cp858':
			case 'ibm858':
			case 'pcmultilingual850euro':
				return 'IBM00858';

			case '860':
			case 'cp860':
			case 'csibm860':
			case 'ibm860':
				return 'IBM860';

			case '861':
			case 'cp861':
			case 'cpis':
			case 'csibm861':
			case 'ibm861':
				return 'IBM861';

			case '862':
			case 'cp862':
			case 'cspc862latinhebrew':
			case 'ibm862':
				return 'IBM862';

			case '863':
			case 'cp863':
			case 'csibm863':
			case 'ibm863':
				return 'IBM863';

			case 'cp864':
			case 'csibm864':
			case 'ibm864':
				return 'IBM864';

			case '865':
			case 'cp865':
			case 'csibm865':
			case 'ibm865':
				return 'IBM865';

			case '866':
			case 'cp866':
			case 'csibm866':
			case 'ibm866':
				return 'IBM866';

			case 'cp868':
			case 'cpar':
			case 'csibm868':
			case 'ibm868':
				return 'IBM868';

			case '869':
			case 'cp869':
			case 'cpgr':
			case 'csibm869':
			case 'ibm869':
				return 'IBM869';

			case 'cp870':
			case 'csibm870':
			case 'ebcdiccproece':
			case 'ebcdiccpyu':
			case 'ibm870':
				return 'IBM870';

			case 'cp871':
			case 'csibm871':
			case 'ebcdiccpis':
			case 'ibm871':
				return 'IBM871';

			case 'cp880':
			case 'csibm880':
			case 'ebcdiccyrillic':
			case 'ibm880':
				return 'IBM880';

			case 'cp891':
			case 'csibm891':
			case 'ibm891':
				return 'IBM891';

			case 'cp903':
			case 'csibm903':
			case 'ibm903':
				return 'IBM903';

			case '904':
			case 'cp904':
			case 'csibbm904':
			case 'ibm904':
				return 'IBM904';

			case 'cp905':
			case 'csibm905':
			case 'ebcdiccptr':
			case 'ibm905':
				return 'IBM905';

			case 'cp918':
			case 'csibm918':
			case 'ebcdiccpar2':
			case 'ibm918':
				return 'IBM918';

			case 'ccsid924':
			case 'cp924':
			case 'ebcdiclatin9euro':
			case 'ibm924':
				return 'IBM00924';

			case 'cp1026':
			case 'csibm1026':
			case 'ibm1026':
				return 'IBM1026';

			case 'ibm1047':
				return 'IBM1047';

			case 'ccsid1140':
			case 'cp1140':
			case 'ebcdicus37euro':
			case 'ibm1140':
				return 'IBM01140';

			case 'ccsid1141':
			case 'cp1141':
			case 'ebcdicde273euro':
			case 'ibm1141':
				return 'IBM01141';

			case 'ccsid1142':
			case 'cp1142':
			case 'ebcdicdk277euro':
			case 'ebcdicno277euro':
			case 'ibm1142':
				return 'IBM01142';

			case 'ccsid1143':
			case 'cp1143':
			case 'ebcdicfi278euro':
			case 'ebcdicse278euro':
			case 'ibm1143':
				return 'IBM01143';

			case 'ccsid1144':
			case 'cp1144':
			case 'ebcdicit280euro':
			case 'ibm1144':
				return 'IBM01144';

			case 'ccsid1145':
			case 'cp1145':
			case 'ebcdices284euro':
			case 'ibm1145':
				return 'IBM01145';

			case 'ccsid1146':
			case 'cp1146':
			case 'ebcdicgb285euro':
			case 'ibm1146':
				return 'IBM01146';

			case 'ccsid1147':
			case 'cp1147':
			case 'ebcdicfr297euro':
			case 'ibm1147':
				return 'IBM01147';

			case 'ccsid1148':
			case 'cp1148':
			case 'ebcdicinternational500euro':
			case 'ibm1148':
				return 'IBM01148';

			case 'ccsid1149':
			case 'cp1149':
			case 'ebcdicis871euro':
			case 'ibm1149':
				return 'IBM01149';

			case 'csiso143iecp271':
			case 'iecp271':
			case 'isoir143':
				return 'IEC_P27-1';

			case 'csiso49inis':
			case 'inis':
			case 'isoir49':
				return 'INIS';

			case 'csiso50inis8':
			case 'inis8':
			case 'isoir50':
				return 'INIS-8';

			case 'csiso51iniscyrillic':
			case 'iniscyrillic':
			case 'isoir51':
				return 'INIS-cyrillic';

			case 'csinvariant':
			case 'invariant':
				return 'INVARIANT';

			case 'iso2022cn':
				return 'ISO-2022-CN';

			case 'iso2022cnext':
				return 'ISO-2022-CN-EXT';

			case 'csiso2022jp':
			case 'iso2022jp':
				return 'ISO-2022-JP';

			case 'csiso2022jp2':
			case 'iso2022jp2':
				return 'ISO-2022-JP-2';

			case 'csiso2022kr':
			case 'iso2022kr':
				return 'ISO-2022-KR';

			case 'cswindows30latin1':
			case 'iso88591windows30latin1':
				return 'ISO-8859-1-Windows-3.0-Latin-1';

			case 'cswindows31latin1':
			case 'iso88591windows31latin1':
				return 'ISO-8859-1-Windows-3.1-Latin-1';

			case 'csisolatin2':
			case 'iso88592':
			case 'iso885921987':
			case 'isoir101':
			case 'l2':
			case 'latin2':
				return 'ISO-8859-2';

			case 'cswindows31latin2':
			case 'iso88592windowslatin2':
				return 'ISO-8859-2-Windows-Latin-2';

			case 'csisolatin3':
			case 'iso88593':
			case 'iso885931988':
			case 'isoir109':
			case 'l3':
			case 'latin3':
				return 'ISO-8859-3';

			case 'csisolatin4':
			case 'iso88594':
			case 'iso885941988':
			case 'isoir110':
			case 'l4':
			case 'latin4':
				return 'ISO-8859-4';

			case 'csisolatincyrillic':
			case 'cyrillic':
			case 'iso88595':
			case 'iso885951988':
			case 'isoir144':
				return 'ISO-8859-5';

			case 'arabic':
			case 'asmo708':
			case 'csisolatinarabic':
			case 'ecma114':
			case 'iso88596':
			case 'iso885961987':
			case 'isoir127':
				return 'ISO-8859-6';

			case 'csiso88596e':
			case 'iso88596e':
				return 'ISO-8859-6-E';

			case '