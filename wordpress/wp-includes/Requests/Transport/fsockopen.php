<?php
/**
 * fsockopen HTTP transport
 *
 * @package Requests
 * @subpackage Transport
 */

/**
 * fsockopen HTTP transport
 *
 * @package Requests
 * @subpackage Transport
 */
class Requests_Transport_fsockopen implements Requests_Transport {
	/**
	 * Second to microsecond conversion
	 *
	 * @var integer
	 */
	const SECOND_IN_MICROSECONDS = 1000000;

	/**
	 * Raw HTTP data
	 *
	 * @var string
	 */
	public $headers = '';

	/**
	 * Stream metadata
	 *
	 * @var array Associative array of properties, see {@see https://secure.php.net/stream_get_meta_data}
	 */
	public $info;

	/**
	 * What's the maximum number of bytes we should keep?
	 *
	 * @var int|bool B