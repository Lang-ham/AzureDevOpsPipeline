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
			$full .= ">$