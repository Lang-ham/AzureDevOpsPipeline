<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * PHP Version 5
 * @package PHPMailer
 * @link https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 * @author Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle (original founder)
 * @copyright 2012 - 2014 Marcus Bointon
 * @copyright 2010 - 2012 Jim Jagielski
 * @copyright 2004 - 2009 Andy Prevost
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @note This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * PHPMailer - PHP email creation and transport class.
 * @package PHPMailer
 * @author Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle (original founder)
 */
class PHPMailer
{
    /**
     * The PHPMailer Version number.
     * @var string
     */
    public $Version = '5.2.22';

    /**
     * Email priority.
     * Options: null (default), 1 = High, 3 = Normal, 5 = low.
     * When null, the header is not set at all.
     * @var integer
     */
    public $Priority = null;

    /**
     * The character set of the message.
     * @var string
     */
    public $CharSet = 'iso-8859-1';

    /**
     * The MIME Content-type of the message.
     * @var string
     */
    public $ContentType = 'text/plain';

    /**
     * The message encoding.
     * Options: "8bit", "7bit", "binary", "base64", and "quoted-printable".
     * @var string
     */
    public $Encoding = '8bit';

    /**
     * Holds the most recent mailer error message.
     * @var string
     */
    public $ErrorInfo = '';

    /**
     * The From email address for the message.
     * @var string
     */
    public $From = 'root@localhost';

    /**
     * The From name of the message.
     * @var string
     */
    public $FromName = 'Root User';

    /**
     * The Sender email (Return-Path) of the message.
     * If not empty, will be sent via -f to sendmail or as 'MAIL FROM' in smtp mode.
     * @var string
     */
    public $Sender = '';

    /**
     * The Return-Path of the message.
     * If empty, it will be set to either From or Sender.
     * @var string
     * @deprecated Email senders should never set a return-path header;
     * it's the receiver's job (RFC5321 section 4.4), so this no longer does anything.
     * @link https://tools.ietf.org/html/rfc5321#section-4.4 RFC5321 reference
     */
    public $ReturnPath = '';

    /**
     * The Subject of the message.
     * @var string
     */
    public $Subject = '';

    /**
     * An HTML or plain text message body.
     * If HTML then call isHTML(true).
     * @var string
     */
    public $Body = '';

    /**
     * The plain-text message body.
     * This body can be read by mail clients that do not have HTML email
     * capability such as mutt & Eudora.
     * Clients that can read HTML will view the normal Body.
     * @var string
     */
    public $AltBody = '';

    /**
     * An iCal message part body.
     * Only supported in simple alt or alt_inline message types
     * To generate iCal events, use the bundled extras/EasyPeasyICS.php class or iCalcreator
     * @link http://sprain.ch/blog/downloads/php-class-easypeasyics-create-ical-files-with-php/
     * @link http://kigkonsult.se/iCalcreator/
     * @var string
     */
    public $Ical = '';

    /**
     * The complete compiled MIME message body.
     * @access protected
     * @var string
     */
    protected $MIMEBody = '';

    /**
     * The complete compiled MIME message headers.
     * @var string
     * @access protected
     */
    protected $MIMEHeader = '';

    /**
     * Extra headers that createHeader() doesn't fold in.
     * @var string
     * @access protected
     */
    protected $mailHeader = '';

    /**
     * Word-wrap the message body to this number of chars.
     * Set to 0 to not wrap. A useful value here is 78, for RFC2822 section 2.1.1 compliance.
     * @var integer
     */
    public $WordWrap = 0;

    /**
     * Which method to use to send mail.
     * Options: "mail", "sendmail", or "smtp".
     * @var string
     */
    public $Mailer = 'mail';

    /**
     * The path to the sendmail program.
     * @var string
     */
    public $Sendmail = '/usr/sbin/sendmail';

    /**
     * Whether mail() uses a fully sendmail-compatible MTA.
     * One which supports sendmail's "-oi -f" options.
     * @var boolean
     */
    public $UseSendmailOptions = true;

    /**
     * Path to PHPMailer plugins.
     * Useful if the SMTP class is not in the PHP include path.
     * @var string
     * @deprecated Should not be needed now there is an autoloader.
     */
    public $PluginDir = '';

    /**
     * The email address that a reading confirmation should be sent to, also known as read receipt.
     * @var string
     */
    public $ConfirmReadingTo = '';

    /**
     * The hostname to use in the Message-ID header and as default HELO string.
     * If empty, PHPMailer attempts to find one with, in order,
     * $_SERVER['SERVER_NAME'], gethostname(), php_uname('n'), or the value
     * 'localhost.localdomain'.
     * @var string
     */
    public $Hostname = '';

    /**
     * An ID to be used in the Message-ID header.
     * If empty, a unique id will be generated.
     * You can set your own, but it must be in the format "<id@domain>",
     * as defined in RFC5322 section 3.6.4 or it will be ignored.
     * @see https://tools.ietf.org/html/rfc5322#section-3.6.4
     * @var string
     */
    public $MessageID = '';

    /**
     * The message Date to be used in the Date header.
     * If empty, the current date will be added.
     * @var string
     */
    public $MessageDate = '';

    /**
     * SMTP hosts.
     * Either a single hostname or multiple semicolon-delimited hostnames.
     * You can also specify a different port
     * for each host by using this format: [hostname:port]
     * (e.g. "smtp1.example.com:25;smtp2.example.com").
     * You can also specify encryption type, for example:
     * (e.g. "tls://smtp1.example.com:587;ssl://smtp2.example.com:465").
     * Hosts will be tried in order.
     * @var string
     */
    public $Host = 'localhost';

    /**
     * The default SMTP server port.
     * @var integer
     * @TODO Why is this needed when the SMTP class takes care of it?
     */
    public $Port = 25;

    /**
     * The SMTP HELO of the message.
     * Default is $Hostname. If $Hostname is empty, PHPMailer attempts to find
     * one with the same method described above for $Hostname.
     * @var string
     * @see PHPMailer::$Hostname
     */
    public $Helo = '';

    /**
     * What kind of encryption to use on the SMTP connection.
     * Options: '', 'ssl' or 'tls'
     * @var string
     */
    public $SMTPSecure = '';

    /**
     * Whether to enable TLS encryption automatically if a server supports it,
     * even if `SMTPSecure` is not set to 'tls'.
     * Be aware that in PHP >= 5.6 this requires that the server's certificates are valid.
     * @var boolean
     */
    public $SMTPAutoTLS = true;

    /**
     * Whether to use SMTP authentication.
     * Uses the Username and Password properties.
     * @var boolean
     * @see PHPMailer::$Username
     * @see PHPMailer::$Password
     */
    public $SMTPAuth = false;

    /**
     * Options array passed to stream_context_create when connecting via SMTP.
     * @var array
     */
    public $SMTPOptions = array();

    /**
     * SMTP username.
     * @var string
     */
    public $Username = '';

    /**
     * SMTP password.
     * @var string
     */
    public $Password = '';

    /**
     * SMTP auth type.
     * Options are CRAM-MD5, LOGIN, PLAIN, attempted in that order if not specified
     * @var string
     */
    public $AuthType = '';

    /**
     * SMTP realm.
     * Used for NTLM auth
     * @var string
     */
    public $Realm = '';

    /**
     * SMTP workstation.
     * Used for NTLM auth
     * @var string
     */
    public $Workstation = '';

    /**
     * The SMTP server timeout in seconds.
     * Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2
     * @var integer
     */
    public $Timeout = 300;

    /**
     * SMTP class debug output mode.
     * Debug output level.
     * Options:
     * * `0` No output
     * * `1` Commands
     * * `2` Data and commands
     * * `3` As 2 plus connection status
     * * `4` Low-level data output
     * @var integer
     * @see SMTP::$do_debug
     */
    public $SMTPDebug = 0;

    /**
     * How to handle debug output.
     * Options:
     * * `echo` Output plain-text as-is, appropriate for CLI
     * * `html` Output escaped, line breaks converted to `<br>`, appropriate for browser output
     * * `error_log` Output to error log as configured in php.ini
     *
     * Alternatively, you can provide a callable expecting two params: a message string and the debug level:
     * <code>
     * $mail->Debugoutput = function($str, $level) {echo "debug level $level; message: $str";};
     * </code>
     * @var string|callable
     * @see SMTP::$Debugoutput
     */
    public $Debugoutput = 'echo';

    /**
     * Whether to keep SMTP connection open after each message.
     * If this is set to true then to close the connection
     * requires an explicit call to smtpClose().
     * @var boolean
     */
    public $SMTPKeepAlive = false;

    /**
     * Whether to split multiple to addresses into multiple messages
     * or send them all in one message.
     * Only supported in `mail` and `sendmail` transports, not in SMTP.
     * @var boolean
     */
    public $SingleTo = false;

    /**
     * Storage for addresses when SingleTo is enabled.
     * @var array
     * @TODO This should really not be public
     */
    public $SingleToArray = array();

    /**
     * Whether to generate VERP addresses on send.
     * Only applicable when sending via SMTP.
     * @link https://en.wikipedia.org/wiki/Variable_envelope_return_path
     * @link http://www.postfix.org/VERP_README.html Postfix VERP info
     * @var boolean
     */
    public $do_verp = false;

    /**
     * Whether to allow sending messages with an empty body.
     * @var boolean
     */
    public $AllowEmpty = false;

    /**
     * The default line ending.
     * @note The default remains "\n". We force CRLF where we know
     *        it must be used via self::CRLF.
     * @var string
     */
    public $LE = "\n";

    /**
     * DKIM selector.
     * @var string
     */
    public $DKIM_selector = '';

    /**
     * DKIM Identity.
     * Usually the email address used as the source of the email.
     * @var string
     */
    public $DKIM_identity = '';

    /**
     * DKIM passphrase.
     * Used if your key is encrypted.
     * @var string
     */
    public $DKIM_passphrase = '';

    /**
     * DKIM signing domain name.
     * @example 'example.com'
     * @var string
     */
    public $DKIM_domain = '';

    /**
     * DKIM private key file path.
     * @var string
     */
    public $DKIM_private = '';

    /**
     * DKIM private key string.
     * If set, takes precedence over `$DKIM_private`.
     * @var string
     */
    public $DKIM_private_string = '';

    /**
     * Callback Action function name.
     *
     * The function that handles the result of the send email action.
     * It is called out by send() for each email sent.
     *
     * Value can be any php callable: http://www.php.net/is_callable
     *
     * Parameters:
     *   boolean $result        result of the send action
     *   string  $to            email address of the recipient
     *   string  $cc            cc email addresses
     *   string  $bcc           bcc email addresses
     *   string  $subject       the subject
     *   string  $body          the email body
     *   string  $from          email address of sender
     * @var string
     */
    public $action_function = '';

    /**
     * What to put in the X-Mailer header.
     * Options: An empty string for PHPMailer default, whitespace for none, or a string to use
     * @var string
     */
    public $XMailer = '';

    /**
     * Which validator to use by default when validating email addresses.
     * May be a callable to inject your own validator, but there are several built-in validators.
     * @see PHPMailer::validateAddress()
     * @var string|callable
     * @static
     */
    public static $validator = 'auto';

    /**
     * An instance of the SMTP sender class.
     * @var SMTP
     * @access protected
     */
    protected $smtp = null;

    /**
     * The array of 'to' names and addresses.
     * @var array
     * @access protected
     */
    protected $to = array();

    /**
     * The array of 'cc' names and addresses.
     * @var array
     * @access protected
     */
    protected $cc = array();

    /**
     * The array of 'bcc' names and addresses.
     * @var array
     * @access protected
     */
    protected $bcc = array();

    /**
     * The array of reply-to names and addresses.
     * @var array
     * @access protected
     */
    protected $ReplyTo = array();

    /**
     * An array of all kinds of addresses.
     * Includes all of $to, $cc, $bcc
     * @var array
     * @access protected
     * @see PHPMailer::$to @see PHPMailer::$cc @see PHPMailer::$bcc
     */
    protected $all_recipients = array();

    /**
     * An array of names and addresses queued for validation.
     * In send(), valid and non duplicate entries are moved to $all_recipients
     * and one of $to, $cc, or $bcc.
     * This array is used only for addresses with IDN.
     * @var array
     * @access protected
     * @see PHPMailer::$to @see PHPMailer::$cc @see PHPMailer::$bcc
     * @see PHPMailer::$all_recipients
     */
    protected $RecipientsQueue = array();

    /**
     * An array of reply-to names and addresses queued for validation.
     * In send(), valid and non duplicate entries are moved to $ReplyTo.
     * This array is used only for addresses with IDN.
     * @var array
     * @access protected
     * @see PHPMailer::$ReplyTo
     */
    protected $ReplyToQueue = array();

    /**
     * The array of attachments.
     * @var array
     * @access protected
     */
    protected $attachment = array();

    /**
     * The array of custom headers.
     * @var array
     * @access protected
     */
    protected $CustomHeader = array();

    /**
     * The most recent Message-ID (including angular brackets).
     * @var string
     * @access protected
     */
    protected $lastMessageID = '';

    /**
     * The message's MIME type.
     * @var string
     * @access protected
     */
    protected $message_type = '';

    /**
     * The array of MIME boundary strings.
     * @var array
     * @access protected
     */
    protected $boundary = array();

    /**
     * The array of available languages.
     * @var array
     * @access protected
     */
    protected $language = array();

    /**
     * The number of errors encountered.
     * @var integer
     * @access protected
     */
    protected $error_count = 0;

    /**
     * The S/MIME certificate file path.
     * @var string
     * @access protected
     */
    protected $sign_cert_file = '';

    /**
     * The S/MIME key file path.
     * @var string
     * @access protected
     */
    protected $sign_key_file = '';

    /**
     * The optional S/MIME extra certificates ("CA Chain") file path.
     * @var string
     * @access protected
     */
    protected $sign_extracerts_file = '';

    /**
     * The S/MIME password for the key.
     * Used only if the key is encrypted.
     * @var string
     * @access protected
     */
    protected $sign_key_pass = '';

    /**
     * Whether to throw exceptions for errors.
     * @var boolean
     * @access protected
     */
    protected $exceptions = false;

    /**
     * Unique ID used for message ID and boundaries.
     * @var string
     * @access protected
     */
    protected $uniqueid = '';

    /**
     * Error severity: message only, continue processing.
     */
    const STOP_MESSAGE = 0;

    /**
     * Error severity: message, likely ok to continue processing.
     */
    const STOP_CONTINUE = 1;

    /**
     * Error severity: message, plus full stop, critical error reached.
     */
    const STOP_CRITICAL = 2;

    /**
     * SMTP RFC standard line ending.
     */
    const CRLF = "\r\n";

    /**
     * The maximum line length allowed by RFC 2822 section 2.1.1
     * @var integer
     */
    const MAX_LINE_LENGTH = 998;

    /**
     * Constructor.
     * @param boolean $exceptions Should we throw external exceptions?
     */
    public function __construct($exceptions = null)
    {
        if ($exceptions !== null) {
            $this->exceptions = (boolean)$exceptions;
        }
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        //Close any open SMTP connection nicely
        $this->smtpClose();
    }

    /**
     * Call mail() in a safe_mode-aware fashion.
     * Also, unless sendmail_path points to sendmail (or something that
     * claims to be sendmail), don't pass params (not a perfect fix,
     * but it will do)
     * @param string $to To
     * @param string $subject Subject
     * @param string $body Message Body
     * @param string $header Additional Header(s)
     * @param string $params Params
     * @access private
     * @return boolean
     */
    private function mailPassthru($to, $subject, $body, $header, $params)
    {
        //Check overloading of mail function to avoid double-encoding
        if (ini_get('mbstring.func_overload') & 1) {
            $subject = $this->secureHeader($subject);
        } else {
            $subject = $this->encodeHeader($this->secureHeader($subject));
        }

        //Can't use additional_parameters in safe_mode, calling mail() with null params breaks
        //@link http://php.net/manual/en/function.mail.php
        if (ini_get('safe_mode') or !$this->UseSendmailOptions or is_null($params)) {
            $result = @mail($to, $subject, $body, $header);
        } else {
            $result = @mail($to, $subject, $body, $header, $params);
        }
        return $result;
    }
    /**
     * Output debugging info via user-defined method.
     * Only generates output if SMTP debug output is enabled (@see SMTP::$do_debug).
     * @see PHPMailer::$Debugoutput
     * @see PHPMailer::$SMTPDebug
     * @param string $str
     */
    protected function edebug($str)
    {
        if ($this->SMTPDebug <= 0) {
            return;
        }
        //Avoid clash with built-in function names
        if (!in_array($this->Debugoutput, array('error_log', 'html', 'echo')) and is_callable($this->Debugoutput)) {
            call_user_func($this->Debugoutput, $str, $this->SMTPDebug);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                //Don't output, just log
                error_log($str);
                break;
            case 'html':
                //Cleans up output a bit for a better looking, HTML-safe output
                echo htmlentities(
                    preg_replace('/[\r\n]+/', '', $str),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . "<br>\n";
                break;
            case 'echo':
            default:
                //Normalize line breaks
                $str = preg_replace('/\r\n?/ms', "\n", $str);
                echo gmdate('Y-m-d H:i:s') . "\t" . str_replace(
                    "\n",
                    "\n                   \t                  ",
                    trim($str)
                ) . "\n";
        }
    }

    /**
     * Sets message type to HTML or plain.
     * @param boolean $isHtml True for HTML mode.
     * @return void
     */
    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
    }

    /**
     * Send messages using SMTP.
     * @return void
     */
    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    /**
     * Send messages using PHP's mail() function.
     * @return void
     */
    public function isMail()
    {
        $this->Mailer = 'mail';
    }

    /**
     * Send messages using $Sendmail.
     * @return void
     */
    public function isSendmail()
    {
        $ini_sendmail_path = ini_get('sendmail_path');

        if (!stristr($ini_sendmail_path, 'sendmail')) {
            $this->Sendmail = '/usr/sbin/sendmail';
        } else {
            $this->Sendmail = $ini_sendmail_path;
        }
        $this->Mailer = 'sendmail';
    }

    /**
     * Send messages using qmail.
     * @return void
     */
    public function isQmail()
    {
        $ini_sendmail_path = ini_get('sendmail_path');

        if (!stristr($ini_sendmail_path, 'qmail')) {
            $this->Sendmail = '/var/qmail/bin/qmail-inject';
        } else {
            $this->Sendmail = $ini_sendmail_path;
        }
        $this->Mailer = 'qmail';
    }

    /**
     * Add a "To" address.
     * @param string $address The email address to send to
     * @param string $name
     * @return boolean true on success, false if address already used or invalid in some way
     */
    public function addAddress($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }

    /**
     * Add a "CC" address.
     * @note: This function works with the SMTP mailer on win32, not with the "mail" mailer.
     * @param string $address The email address to send to
     * @param string $name
     * @return boolean true on success, false if address already used or invalid in some way
     */
    public function addCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('cc', $address, $name);
    }

    /**
     * Add a "BCC" address.
     * @note: This function works with the SMTP mailer on win32, not with the "mail" mailer.
     * @param string $address The email address to send to
     * @param string $name
     * @return boolean true on success, false if address already used or invalid in some way
     */
    public function addBCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('bcc', $address, $name);
    }

    /**
     * Add a "Reply-To" address.
     * @param string $address The email address to reply to
     * @param string $name
     * @return boolean true on success, false if address already used or invalid in some way
     */
    public function addReplyTo($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('Reply-To', $address, $name);
    }

    /**
     * Add an address to one of the recipient arrays or to the ReplyTo array. Because PHPMailer
     * can't validate addresses with an IDN without knowing the PHPMailer::$CharSet (that can still
     * be modified after calling this function), addition of such addresses is delayed until send().
     * Addresses that have been added already return false, but do not throw exceptions.
     * @param string $kind One of 'to', 'cc', 'bcc', or 'ReplyTo'
     * @param string $address The email address to send, resp. to reply to
     * @param string $name
     * @throws phpmailerException
     * @return boolean true on success, false if address already used or invalid in some way
     * @access protected
     */
    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
        if (($pos = strrpos($address, '@')) === false) {
            // At-sign is misssing.
            $error_message = $this->lang('invalid_address') . " (addAnAddress $kind): $address";
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new phpmailerException($error_message);
            }
            return false;
        }
        $params = array($kind, $address, $name);
        // Enqueue addresses with IDN until we know the PHPMailer::$CharSet.
        if ($this->has8bitChars(substr($address, ++$pos)) and $this->idnSupported()) {
            if ($kind != 'Reply-To') {
                if (!array_key_exists($address, $this->RecipientsQueue)) {
                    $this->RecipientsQueue[$address] = $params;
                    return true;
                }
            } else {
                if (!array_key_exists($address, $this->ReplyToQueue)) {
                    $this->ReplyToQueue[$address] = $params;
                    return true;
                }
            }
            return false;
        }
        // Immediately add standard addresses without IDN.
        return call_user_func_array(array($this, 'addAnAddress'), $params);
    }

    /**
     * Add an address to one of the recipient arrays or to the ReplyTo array.
     * Addresses that have been added already return false, but do not throw exceptions.
     * @param string $kind One of 'to', 'cc', 'bcc', or 'ReplyTo'
     * @param string $address The email address to send, resp. to reply to
     * @param string $name
     * @throws phpmailerException
     * @return boolean true on success, false if address already used or invalid in some way
     * @access protected
     */
    protected function addAnAddress($kind, $address, $name = '')
    {
        if (!in_array($kind, array('to', 'cc', 'bcc', 'Reply-To'))) {
            $error_message = $this->lang('Invalid recipient kind: ') . $kind;
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new phpmailerException($error_message);
            }
            return false;
        }
        if (!$this->validateAddress($address)) {
            $error_message = $this->lang('invalid_address') . " (addAnAddress $kind): $address";
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new phpmailerException($error_message);
            }
            return false;
        }
        if ($kind != 'Reply-To') {
            if (!array_key_exists(strtolower($address), $this->all_recipients)) {
                array_push($this->$kind, array($address, $name));
                $this->all_recipients[strtolower($address)] = true;
                return true;
            }
        } else {
            if (!array_key_exists(strtolower($address), $this->ReplyTo)) {
                $this->ReplyTo[strtolower($address)] = array($address, $name);
                return true;
            }
        }
        return false;
    }

    /**
     * Parse and validate a string containing one or more RFC822-style comma-separated email addresses
     * of the form "display name <address>" into an array of name/address pairs.
     * Uses the imap_rfc822_parse_adrlist function if the IMAP extension is available.
     * Note that quotes in the name part are removed.
     * @param string $addrstr The address list string
     * @param bool $useimap Whether to use the IMAP extension to parse the list
     * @return array
     * @link http://www.andrew.cmu.edu/user/agreen1/testing/mrbs/web/Mail/RFC822.php A more careful implementation
     */
    public function parseAddresses($addrstr, $useimap = true)
    {
        $addresses = array();
        if ($useimap and function_exists('imap_rfc822_parse_adrlist')) {
            //Use this built-in parser if it's available
            $list = imap_rfc822_parse_adrlist($addrstr, '');
            foreach ($list as $address) {
                if ($address->host != '.SYNTAX-ERROR.') {
                    if ($this->validateAddress($address->mailbox . '@' . $address->host)) {
                        $addresses[] = array(
                            'name' => (property_exists($address, 'personal') ? $address->personal : ''),
                            'address' => $address->mailbox . '@' . $address->host
                        );
                    }
                }
            }
        } else {
            //Use this simpler parser
            $list = explode(',', $addrstr);
            foreach ($list as $address) {
                $address = trim($address);
                //Is there a separate name part?
                if (strpos($address, '<') === false) {
                    //No separate name, just use the whole thing
                    if ($this->validateAddress($address)) {
                        $addresses[] = array(
                            'name' => '',
                            'address' => $address
                        );
                    }
                } else {
                    list($name, $email) = explode('<', $address);
                    $email = trim(str_replace('>', '', $email));
                    if ($this->validateAddress($email)) {
                        $addresses[] = array(
                            'name' => trim(str_replace(array('"', "'"), '', $name)),
                            'address' => $email
                        );
                    }
                }
            }
        }
        return $addresses;
    }

    /**
     * Set the From and FromName properties.
     * @param string $address
     * @param string $name
     * @param boolean $auto Whether to also set the Sender address, defaults to true
     * @throws phpmailerException
     * @return boolean
     */
    public function setFrom($address, $name = '', $auto = true)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name)); //Strip breaks and trim
        // Don't validate now addresses with IDN. Will be done in send().
        if (($pos = strrpos($address, '@')) === false or
            (!$this->has8bitChars(substr($address, ++$pos)) or !$this->idnSupported()) and
            !$this->validateAddress($address)) {
            $error_message = $this->lang('invalid_address') . " (setFrom) $address";
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new phpmailerException($error_message);
            }
            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto) {
            if (empty($this->Sender)) {
                $this->Sender = $address;
            }
        }
        return true;
    }

    /**
     * Return the Message-ID header of the last email.
     * Technically this is the value from the last time the headers were created,
     * but it's also the message ID of the last sent message except in
     * pathological cases.
     * @return string
     */
    public function getLastMessageID()
    {
        return $this->lastMessageID;
    }

    /**
     * Check that a string looks like an email address.
     * @param string $address The email address to check
     * @param string|callable $patternselect A selector for the validation pattern to use :
     * * `auto` Pick best pattern automatically;
     * * `pcre8` Use the squiloople.com