<?php
/**
 * mail_fetch/setup.php
 *
 * Copyright (c) 1999-2011 CDI (cdi@thewebmasters.net) All Rights Reserved
 * Modified by Philippe Mingo 2001-2009 mingo@rotedic.com
 * An RFC 1939 compliant wrapper class for the POP3 protocol.
 *
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * POP3 class
 *
 * @copyright 1999-2011 The SquirrelMail Project Team
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @package plugins
 * @subpackage mail_fetch
 */

class POP3 {
    var $ERROR      = '';       //  Error string.

    var $TIMEOUT    = 60;       //  Default timeout before giving up on a
                                //  network operation.

    var $COUNT      = -1;       //  Mailbox msg count

    var $BUFFER     = 512;      //  Socket buffer for socket fgets() calls.
                                //  Per RFC 1939 the returned line a POP3
                                //  server can send is 512 bytes.

    var $FP         = '';       //  The connection to the server's
                                //  file descriptor

    var $MAILSERVER = '';       // Set this to hard code the server name

    var $DEBUG      = FALSE;    // set to true to echo pop3
                                // commands and responses to error_log
                                // this WILL log passwords!

    var $BANNER     = '';       //  Holds the banner returned by the
                                //  pop server - used for apop()

    var $ALLOWAPOP  = FALSE;    //  Allow or disallow apop()
                                //  This must be set to true
                                //  manually

	/**
	 * PHP5 constructor.
	 */
    function __construct ( $server = '', $timeout = '' ) {
        settype($this->BUFFER,"integer");
        if( !empty($server) ) {
            // Do not allow programs to alter MAILSERVER
            // if it is already specified. They can get around
            // this if they -really- want to, so don't count on it.
            if(empty($this->MAILSERVER))
                $this->MAILSERVER = $server;
        }
        if(!empty($timeout)) {
            settype($timeout,"integer");
            $this->TIMEOUT = $timeout;
            if (!ini_get('safe_mode'))
                set_time_limit($timeout);
        }
        return true;
    }

	/**
	 * PHP4 constructor.
	 */
	public function POP3( $server = '', $timeout = '' ) {
		self::__construct( $server, $timeout );
	}

    function update_timer () {
        if (!ini_get('safe_mode'))
            set_t