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
 * IRI parser/serialiser/normaliser
 *
 * @package SimplePie
 * @subpackage HTTP
 * @author Geoffrey Sneddon
 * @author Steve Minutillo
 * @author Ryan McCue
 * @copyright 2007-2012 Geoffrey Sneddon, Steve Minutillo, Ryan McCue
 * @license http://www.opensource.org/licenses/bsd-license.php
 */
class SimplePie_IRI
{
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
			'ipath' => '/'
		),
		'https' => array(
			'port' => 443,
			'ipath' => '/'
		),
	);

	/**
	 * Return the entire IRI when you try and read the object as a string
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->get_iri();
	}

	/**
	 * Overload __set() to provide access via properties
	 *
	 * @param string $name Property name
	 * @param mixed $value Property value
	 */
	public function __set($name, $value)
	{
		if (method_exists($this, 'set_' . $name))
		{
			call_user_func(array($this, 'set_' . $name), $value);
		}
		elseif (
			   $name === 'iauthority'
			|| $name === 'iuserinfo'
			|| $name === 'ihost'
			|| $name === 'ipath'
			|| $name === 'iquery'
			|| $name === 'ifragment'
		)
		{
			call_user_func(array($this, 'set_' . substr($name, 1)), $value);
		}
	}

	/**
	 * Overload __get() to provide access via properties
	 *
	 * @param string $name Property name
	 * @return mixed
	 */
	public function __get($name)
	{
		// isset() returns false for null, we don't want to do that
		