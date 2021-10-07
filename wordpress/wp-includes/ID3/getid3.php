<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
//                                                             //
// Please see readme.txt for more information                  //
//                                                            ///
/////////////////////////////////////////////////////////////////

// define a constant rather than looking up every time it is needed
if (!defined('GETID3_OS_ISWINDOWS')) {
	define('GETID3_OS_ISWINDOWS', (stripos(PHP_OS, 'WIN') === 0));
}
// Get base path of getID3() - ONCE
if (!defined('GETID3_INCLUDEPATH')) {
	define('GETID3_INCLUDEPATH', dirname(__FILE__).DIRECTORY_SEPARATOR);
}
// Workaround Bug #39923 (https://bugs.php.net/bug.php?id=39923)
if (!defined('IMG_JPG') && defined('IMAGETYPE_JPEG')) {
	define('IMG_JPG', IMAGETYPE_JPEG);
}
if (!defined('ENT_SUBSTITUTE')) { // PHP5.3 adds ENT_IGNORE, PHP5.4 adds ENT_SUBSTITUTE
	define('ENT_SUBSTITUTE', (defined('ENT_IGNORE') ? ENT_IGNORE : 8));
}

// attempt to define temp dir as something flexible but reliable
$temp_dir = ini_get('upload_tmp_dir');
if ($temp_dir && (!is_dir($temp_dir) || !is_readable($temp_dir))) {
	$temp_dir = '';
}
if (!$temp_dir && function_exists('sys_get_temp_dir')) { // sys_get_temp_dir added in PHP v5.2.1
	// sys_get_temp_dir() may give inaccessible temp dir, e.g. with open_basedir on virtual hosts
	$temp_dir = sys_get_temp_dir();
}
$temp_dir = @realpath($temp_dir); // see https://github.com/JamesHeinrich/getID3/pull/10
$open_basedir = ini_get('open_basedir');
if ($open_basedir) {
	// e.g. "/var/www/vhosts/getid3.org/httpdocs/:/tmp/"
	$temp_dir     = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $temp_dir);
	$open_basedir = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $open_basedir);
	if (substr($temp_dir, -1, 1) != DIRECTORY_SEPARATOR) {
		$temp_dir .= DIRECTORY_SEPARATOR;
	}
	$found_valid_tempdir = false;
	$open_basedirs = explode(PATH_SEPARATOR, $open_basedir);
	foreach ($open_basedirs as $basedir) {
		if (substr($basedir, -1, 1) != DIRECTORY_SEPARATOR) {
			$basedir .= DIRECTORY_SEPARATOR;
		}
		if (preg_match('#^'.preg_quote($basedir).'#', $temp_dir)) {
			$found_valid_tempdir = true;
			break;
		}
	}
	if (!$found_valid_tempdir) {
		$temp_dir = '';
	}
	unset($open_basedirs, $found_valid_tempdir, $basedir);
}
if (!$temp_dir) {
	$temp_dir = '*'; // invalid directory name should force tempnam() to use system default temp dir
}
// $temp_dir = '/something/else/';  // feel free to override temp dir here if it works better for your system
if (!defined('GETID3_TEMP_DIR')) {
	define('GETID3_TEMP_DIR', $temp_dir);
}
unset($open_basedir, $temp_dir);

// End: Defines


class getID3
{
	// public: Settings
	public $encoding        = 'UTF-8';        // CASE SENSITIVE! - i.e. (must be supported by iconv()). Examples:  ISO-8859-1  UTF-8  UTF-16  UTF-16BE
	public $encoding_id3v1  = 'ISO-8859-1';   // Should always be 'ISO-8859-1', but some tags may be written in other encodings such as 'EUC-CN' or 'CP1252'

	// public: Optional tag checks - disable for speed.
	public $option_tag_id3v1         = true;  // Read and process ID3v1 tags
	public $option_tag_id3v2         = true;  // Read and process ID3v2 tags
	public $option_tag_lyrics3       = true;  // Read and process Lyrics3 tags
	public $option_tag_apetag        = true;  // Read and process APE tags
	public $option_tags_process      = true;  // Copy tags to root key 'tags' and encode to $this->encoding
	public $option_tags_html         = true;  // Copy tags to root key 'tags_html' properly translated from various encodings to HTML entities

	// public: Optional tag/comment calucations
	public $option_extra_info        = true;  // Calculate additional info such as bitrate, channelmode etc

	// public: Optional handling of embedded attachments (e.g. images)
	public $option_save_attachments  = true; // defaults to true (ATTACHMENTS_INLINE) for backward compatibility

	// public: Optional calculations
	public $option_md5_data          = false; // Get MD5 sum of data part - slow
	public $option_md5_data_source   = false; // Use MD5 of source file if availble - only FLAC and OptimFROG
	public $option_sha1_data         = false; // Get SHA1 sum of data part - slow
	public $option_max_2gb_check     = null;  // Check whether file is larger than 2GB and thus not supported by 32-bit PHP (null: auto-detect based on PHP_INT_MAX)

	// public: Read buffer size in bytes
	public $option_fread_buffer_size = 32768;

	// Public variables
	public $filename;                         // Filename of file being analysed.
	public $fp;                               // Filepointer to file being analysed.
	public $info;                             // Result array.
	public $tempdir = GETID3_TEMP_DIR;
	public $memory_limit = 0;

	// Protected variables
	protected $startup_error   = '';
	protected $startup_warning = '';

	const VERSION           = '1.9.14-201706111222';
	const FREAD_BUFFER_SIZE = 32768;

	const ATTACHMENTS_NONE   = false;
	const ATTACHMENTS_INLINE = true;

	// public: constructor
	public function __construct() {

		// Check memory
		$this->memory_limit = ini_get('memory_limit');
		if (preg_match('#([0-9]+) ?M#i', $this->memory_limit, $matches)) {
			// could be stored as "16M" rather than 16777216 for example
			$this->memory_limit = $matches[1] * 1048576;
		} elseif (preg_match('#([0-9]+) ?G#i', $this->memory_limit, $matches)) { // The 'G' modifier is available since PHP 5.1.0
			// could be stored as "2G" rather than 2147483648 for example
			$this->memory_limit = $matches[1] * 1073741824;
		}
		if ($this->memory_limit <= 0) {
			// memory limits probably disabled
		} elseif ($this->memory_limit <= 4194304) {
			$this->startup_error .= 'PHP has less than 4MB available memory and will very likely run out. Increase memory_limit in php.ini'."\n";
		} elseif ($this->memory_limit <= 12582912) {
			$this->startup_warning .= 'PHP has less than 12MB available memory and might run out if all modules are loaded. Increase memory_limit in php.ini'."\n";
		}

		// Check safe_mode off
		if (preg_match('#(1|ON)#i', ini_get('safe_mode'))) {
			$this->warning('WARNING: Safe mode is on, shorten support disabled, md5data/sha1data for ogg vorbis disabled, ogg vorbos/flac tag writing disabled.');
		}

		if (($mbstring_func_overload = ini_get('mbstring.func_overload')) && ($mbstring_func_overload & 0x02)) {
			// http://php.net/manual/en/mbstring.overload.php
			// "mbstring.func_overload in php.ini is a positive value that represents a combination of bitmasks specifying the categories of functions to be overloaded. It should be set to 1 to overload the mail() function. 2 for string functions, 4 for regular expression functions"
			// getID3 cannot run when string functions are overloaded. It doesn't matter if mail() or ereg* functions are overloaded since getID3 does not use those.
			$this->startup_error .= 'WARNING: php.ini contains "mbstring.func_overload = '.ini_get('mbstring.func_overload').'", getID3 cannot run with this setting (bitmask 2 (string functions) cannot be set). Recommended to disable entirely.'."\n";
		}

		// Check for magic_quotes_runtime
		if (function_exists('get_magic_quotes_runtime')) {
			if (get_magic_quotes_runtime()) {
				$this->startup_error .= 'magic_quotes_runtime must be disabled before running getID3(). Surround getid3 block by set_magic_quotes_runtime(0) and set_magic_quotes_runtime(1).'."\n";
			}
		}

		// Check for magic_quotes_gpc
		if (function_exists('magic_quotes_gpc')) {
			if (get_magic_quotes_gpc()) {
				$this->startup_error .= 'magic_quotes_gpc must be disabled before running getID3(). Surround getid3 block by set_magic_quotes_gpc(0) and set_magic_quotes_gpc(1).'."\n";
			}
		}

		// Load support library
		if (!include_once(GETID3_INCLUDEPATH.'getid3.lib.php')) {
			$this->startup_error .= 'getid3.lib.php is missing or corrupt'."\n";
		}

		if ($this->option_max_2gb_check === null) {
			$this->option_max_2gb_check = (PHP_INT_MAX <= 2147483647);
		}


		// Needed for Windows only:
		// Define locations of helper applications for Shorten, VorbisComment, MetaFLAC
		//   as well as other helper functions such as head, tail, md5sum, etc
		// This path cannot contain spaces, but the below code will attempt to get the
		//   8.3-equivalent path automatically
		// IMPORTANT: This path must include the trailing slash
		if (GETID3_OS_ISWINDOWS && !defined('GETID3_HELPERAPPSDIR')) {

			$helperappsdir = GETID3_INCLUDEPATH.'..'.DIRECTORY_SEPARATOR.'helperapps'; // must not have any space in this path

			if (!is_dir($helperappsdir)) {
				$this->startup_warning .= '"'.$helperappsdir.'" cannot be defined as GETID3_HELPERAPPSDIR because it does not exist'."\n";
			} elseif (strpos(realpath($helperappsdir), ' ') !== false) {
				$DirPieces = explode(DIRECTORY_SEPARATOR, realpath($helperappsdir));
				$path_so_far = array();
				foreach ($DirPieces as $key => $value) {
					if (strpos($value, ' ') !== false) {
						if (!empty($path_so_far)) {
							$commandline = 'dir /x '.escapeshellarg(implode(DIRECTORY_SEPARATOR, $path_so_far));
							$dir_listing = `$commandline`;
							$lines = explode("\n", $dir_listing);
							foreach ($lines as $line) {
								$line = trim($line);
								if (preg_match('#^([0-9/]{10}) +([0-9:]{4,5}( [AP]M)?) +(<DIR>|[0-9,]+) +([^ ]{0,11}) +(.+)$#', $line, $matches)) {
									list($dummy, $date, $time, $ampm, $filesize, $shortname, $filename) = $matches;
									if ((strtoupper($filesize) == '<DIR>') && (strtolower($filename) == strtolower($value))) {
										$value = $shortname;
									}
								}
							}
						} else {
							$this->startup_warning .= 'GETID3_HELPERAPPSDIR must not have any spaces in it - use 8dot3 naming convention if neccesary. You can run "dir /x" from the commandline to see the correct 8.3-style names.'."\n";
						}
					}
					$path_so_far[] = $value;
				}
				$helperappsdir = implode(DIRECTORY_SEPARATOR, $path_so_far);
			}
			define('GETID3_HELPERAPPSDIR', $helperappsdir.DIRECTORY_SEPARATOR);
		}

		if (!empty($this->startup_error)) {
			echo $this->startup_error;
			throw new getid3_exception($this->startup_error);
		}

		return true;
	}

	public function version() {
		return self::VERSION;
	}

	public function fread_buffer_size() {
		return $this->option_fread_buffer_size;
	}


	// public: setOption
	public function setOption($optArray) {
		if (!is_array($optArray) || empty($optArray)) {
			return false;
		}
		foreach ($optArray as $opt => $val) {
			if (isset($this->$opt) === false) {
				continue;
			}
			$this->$opt = $val;
		}
		return true;
	}


	public function openfile($filename, $filesize=null) {
		try {
			if (!empty($this->startup_error)) {
				throw new getid3_exception($this->startup_error);
			}
			if (!empty($this->startup_warning)) {
				foreach (explode("\n", $this->startup_warning) as $startup_warning) {
					$this->warning($startup_warning);
				}
			}

			// init result array and set parameters
			$this->filename = $filename;
			$this->info = array();
			$this->info['GETID3_VERSION']   = $this->version();
			$this->info['php_memory_limit'] = (($this->memory_limit > 0) ? $this->memory_limit : false);

			// remote files not supported
			if (preg_match('#^(ht|f)tp://#', $filename)) {
				throw new getid3_exception('Remote files are not supported - please copy the file locally first');
			}

			$filename = str_replace('/', DIRECTORY_SEPARATOR, $filename);
			$filename = preg_replace('#(?<!gs:)('.preg_quote(DIRECTORY_SEPARATOR).'{2,})#', DIRECTORY_SEPARATOR, $filename);

			// open local file
			//if (is_readable($filename) && is_file($filename) && ($this->fp = fopen($filename, 'rb'))) { // see http://www.getid3.org/phpBB3/viewtopic.php?t=1720
			if ((is_readable($filename) || file_exists($filename)) && is_file($filename) && ($this->fp = fopen($filename, 'rb'))) {
				// great
			} else {
				$errormessagelist = array();
				if (!is_readable($filename)) {
					$errormessagelist[] = '!is_readable';
				}
				if (!is_file($filename)) {
					$errormessagelist[] = '!is_file';
				}
				if (!file_exists($filename)) {
					$errormessagelist[] = '!file_exists';
				}
				if (empty($errormessagelist)) {
					$errormessagelist[] = 'fopen failed';
				}
				throw new getid3_exception('Could not open "'.$filename.'" ('.implode('; ', $errormessagelist).')');
			}

			$this->info['filesize'] = (!is_null($filesize) ? $filesize : filesize($filename));
			// set redundant parameters - might be needed in some include file
			// filenames / filepaths in getID3 are always expressed with forward slashes (unix-style) for both Windows and other to try and minimize confusion
			$filename = str_replace('\\', '/', $filename);
			$this->info['filepath']     = str_replace('\\', '/', realpath(dirname($filename)));
			$this->info['filename']     = getid3_lib::mb_basename($filename);
			$this->info['filenamepath'] = $this->info['filepath'].'/'.$this->info['filename'];

			// set more parameters
			$this->info['avdataoffset']        = 0;
			$this->info['avdataend']           = $this->info['filesize'];
			$this->info['fileformat']          = '';                // filled in later
			$this->info['audio']['dataformat'] = '';                // filled in later, unset if not used
			$this->info['video']['dataformat'] = '';                // filled in later, unset if not used
			$this->info['tags']                = array();           // filled in later, unset if not used
			$this->info['error']               = array();           // filled in later, unset if not used
			$this->info['warning']             = array();           // filled in later, unset if not used
			$this->info['comments']            = array();           // filled in later, unset if not used
			$this->info['encod