<?php
/**
 * cURL HTTP transport
 *
 * @package Requests
 * @subpackage Transport
 */

/**
 * cURL HTTP transport
 *
 * @package Requests
 * @subpackage Transport
 */
class Requests_Transport_cURL implements Requests_Transport {
	const CURL_7_10_5 = 0x070A05;
	const CURL_7_16_2 = 0x071002;

	/**
	 * Raw HTTP data
	 *
	 * @var string
	 */
	public $headers = '';

	/**
	 * Raw body data
	 *
	 * @var string
	 */
	public $response_data = '';

	/**
	 * Information on the current request
	 *
	 * @var array cURL information array, see {@see https://secure.php.net/curl_getinfo}
	 */
	public $info;

	/**
	 * Version string
	 *
	 * @var long
	 */
	public $version;

	/**
	 * cURL handle
	 *
	 * @var resource
	 */
	protected $handle;

	/**
	 * Hook dispatcher instance
	 *
	 * @var Requests_Hooks
	 */
	protected $hooks;

	/**
	 * Have we finished the headers yet?
	 *
	 * @var boolean
	 */
	protected $done_headers = false;

	/**
	 * If streaming to a file, keep the file pointer
	 *
	 * @var resource
	 */
	protected $stream_handle;

	/**
	 * How many bytes are in the response body?
	 *
	 * @var int
	 */
	protected $response_bytes;

	/**
	 * What's the maximum number of bytes we should keep?
	 *
	 * @var int|bool Byte count, or false if no limit.
	 */
	protected $response_byte_limit;

	/**
	 * Constructor
	 */
	public function __construct() {
		$curl = curl_version();
		$this->version = $curl['version_number'];
		$this->handle = curl_init();

		curl_setopt($this->handle, CURLOPT_HEADER, false);
		curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, 1);
		if ($this->version >= self::CURL_7_10_5) {
			curl_setopt($this->handle, CURLOPT_ENCODING, '');
		}
		if (defined('CURLOPT_PROTOCOLS')) {
			curl_setopt($this->handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		}
		if (defined('CURLOPT_REDIR_PROTOCOLS')) {
			curl_setopt($this->handle, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		}
	}

	/**
	 * Destructor
	 */
	public function __destruct() {
		if (is_resource($this->handle)) {
			curl_close($this->handle);
		}
	}

	/**
	 * Perform a request
	 *
	 * @throws Requests_Exception On a cURL error (`curlerror`)
	 *
	 * @param string $url URL to request
	 * @param array $headers Associative array of request headers
	 * @param string|array $data Data to send either as the POST body, or as parameters in the URL for a GET/HEAD
	 * @param array $options Request options, see {@see Requests::response()} for documentation
	 * @return string Raw HTTP result
	 */
	public function request($url, $headers = array(), $data = array(), $options = array()) {
		$this->hooks = $options['hooks'];

		$this->setup_handle($url, $headers, $data, $options);

		$options['hooks']->dispatch('curl.before_send', array(&$this->handle));

		if ($options['filename'] !== false) {
			$this->stream_handle = fopen($options['filename'], 'wb');
		}

		$this->response_data = '';
		$this->response_bytes = 0;
		$this->response_byte_limit = false;
		if ($options['max_bytes'] !== false) {
			$this->response_byte_limit = $options['max_bytes'];
		}

		if (isset($options['verify'])) {
			if ($options['verify'] === false) {
				curl_setopt($this->handle, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($this->handle, CURLOPT_SSL_VERIFYPEER, 0);
			}
			elseif (is_string($options['verify'])) {
				curl_setopt($this->handle, CURLOPT_CAINFO, $options['verify']);
			}
		}

		if (isset($options['verifyname']) && $options['verifyname'] === false) {
			curl_setopt($this->handle, CURLOPT_SSL_VERIFYHOST, 0);
		}

		curl_exec($this->handle);
		$response = $this->response_data;

		$options['hooks']->dispatch('curl.after_send', array());

		if (curl_errno($this->handle) === 23 || curl_errno($this->handle) === 61) {
			// Reset encoding and try again
			curl_setopt($this->handle, CURLOPT_ENCODING, 'none');

			$this->response_data = '';
			$this->response_bytes = 0;
			curl_exec($this->handle);
			$response = $this->response_data;
		}

		$this->process_response($response, $options);

		// Need to remove the $this reference from the curl handle.
		// Otherwise Requests_Transport_cURL wont be garbage collected and the curl_close() will never be called.
		curl_setopt($this->handle, CURLOPT_HEADERFUNCTION, null);
		curl_setopt($this->handle, CURLOPT_WRITEFUNCTION, null);

		return $this->headers;
	}

	/**
	 * Send multiple requests simultaneously
	 *
	 * @param array $requests Request data
	 * @param array $options Global options
	 * @return array Array of Requests_Response objects (may contain Requests_Exception or string responses as well)
	 */
	public function request_multiple($requests, $options) {
		// If you're not requesting, we can't get any responses ¯\_(ツ)_/¯
		if (empty($requests)) {
			return array();
		}

		$multihandle = curl_multi_init();
		$subrequests = array();
		$subhandles = array();

		$class = get_class($this);
		foreach ($requests as $id => $request) {
			$subrequests[$id] = new $class();
			$subhandles[$id] = $subrequests[$id]->get_subrequest_handle($request['url'], $request['headers'], $request['data'], $request['options']);
			$request['options']['hooks']->dispatch('curl.before_multi_add', array(&$subhandles[$id]));
			curl_multi_add_handle($multihandle, $subhandles[$id]);
		}

		$completed = 0;
		$r