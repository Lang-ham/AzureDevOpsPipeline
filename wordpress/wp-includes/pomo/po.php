<?php
/**
 * Class for working with PO files
 *
 * @version $Id: po.php 1158 2015-11-20 04:31:23Z dd32 $
 * @package pomo
 * @subpackage po
 */

require_once dirname(__FILE__) . '/translations.php';

if ( ! defined( 'PO_MAX_LINE_LEN' ) ) {
	define('PO_MAX_LINE_LEN', 79);
}

ini_set('auto_detect_line_endings', 1);

/**
 * Routines for working with PO files
 */
if ( ! class_exists( 'PO', false ) ):
class PO extends Gettext_Translations {

	var $comments_before_headers = '';

	/**
	 * Exports headers to a PO entry
	 *
	 * @return string msgid/msgstr PO entry for this PO file headers, doesn't contain newline at the end
	 */
	function export_headers() {
		$header_string = '';
		foreach($this->headers as $header => $value) {
			$header_string.= "$header: $value\n";
		}
		$poified = PO::poify($header_string);
		if ($this->comments_before_headers)
			$before_headers = $this->prepend_each_line(rtrim($this->comments_before_headers)."\n", '# ');
		else
			$before_headers = '';
		return rtrim("{$before_headers}msgid \"\"\nmsgstr $poified");
	}

	/**
	 * Exports all entries to PO format
	 *
	 * @return string sequence of mgsgid/msgstr PO strings, doesn't containt newline at the end
	 */
	function export_entries() {
		//TODO sorting
		return implode("\n\n", array_map(array('PO', 'export_entry'), $this->entries));
	}

	/**
	 * Exports the whole PO file as a string
	 *
	 * @param bool $include_headers whether to include the headers in the export
	 * @return string ready for inclusion in PO file string for headers and all the enrtries
	 */
	function export($include_headers = true) {
		$res = '';
		if ($include_headers) {
			$res .= $this->export_headers();
			$res .= "\n\n";
		}
		$res .= $this->export_entries();
		return $res;
	}

	/**
	 * Same as {@link export}, but writes the result to a file
	 *
	 * @param string $filename where to write the PO string
	 * @param bool $include_headers whether to include tje headers in the export
	 * @return bool true on success, false on error
	 */
	function export_to_file($filename, $include_headers = true) {
		$fh = fopen($filename, 'w');
		if (false === $fh) return false;
		$export = $this->export($include_headers);
		$res = fwrite($fh, $export);
		if (false === $res) return false;
		return fclose($fh);
	}

	/**
	 * Text to include as a com