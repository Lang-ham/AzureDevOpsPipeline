<?php

/**
 * IXR_Server
 *
 * @package IXR
 * @since 1.5.0
 */
class IXR_Server
{
    var $data;
    var $callbacks = array();
    var $message;
    var $capabilities;

	/**
	 * PHP5 constructor.
	 */
    function __construct( $callbacks = false, $data = false, $wait = false )
    {
        $this->setCapabilities();
        if ($callbacks) {
            $this->callbacks = $callbacks;
        }
        $this->s