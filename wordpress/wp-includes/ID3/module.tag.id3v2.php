<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
///                                                            //
// module.tag.id3v2.php                                        //
// module for analyzing ID3v2 tags                             //
// dependencies: module.tag.id3v1.php                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'module.tag.id3v1.php', __FILE__, true);

class getid3_id3v2 extends getid3_handler
{
	public $StartingOffset = 0;

	public function Analyze() {
		$info = &$this->getid3->info;

		//    Overall tag structure:
		//        +-----------------------------+
		//        |      Header (10 bytes)      |
		//        +-----------------------------+
		//        |       Extended Header       |
		//        | (variable length, OPTIONAL) |
		//        +-----------------------------+
		//        |   Frames (variable length)  |
		//        +-----------------------------+
		//        |           Padding           |
		//        | (variable length, OPTIONAL) |
		//        +-----------------------------+
		//        | Footer (10 bytes, OPTIONAL) |
		//        +-----------------------------+

		//    Header
		//        ID3v2/file identifier      "ID3"
		//        ID3v2 version              $04 00
		//        ID3v2 flags                (%ab000000 in v2.2, %abc00000 in v2.3, %abcd0000 in v2.4.x)
		//        ID3v2 size             4 * %0xxxxxxx


		// shortcuts
		$info['id3v2']['header'] = true;
		$thisfile_id3v2                  = &$info['id3v2'];
		$thisfile_id3v2['flags']         =  array();
		$thisfile_id3v2_flags            = &$thisfile_id3v2['flags'];


		$this->fseek($this->StartingOffset);
		$header = $this->fread(10);
		if (substr($header, 0, 3) == 'ID3'  &&  strlen($header) == 10) {

			$thisfile_id3v2['majorversion'] = ord($header{3});
			$thisfile_id3v2['minorversion'] = ord($header{4});

			// shortcut
			$id3v2_majorversion = &$thisfile_id3v2['majorversion'];

		} else {

			unset($info['id3v2']);
			return false;

		}

		if ($id3v2_majorversion > 4) { // this script probably won't correctly parse ID3v2.5.x and above (if it ever exists)

			$this->error('this script only parses up to ID3v2.4.x - this tag is ID3v2.'.$id3v2_majorversion.'.'.$thisfile_id3v2['minorversion']);
			return false;

		}

		$id3_flags = ord($header{5});
		switch ($id3v2_majorversion) {
			case 2:
				// %ab000000 in v2.2
				$thisfile_id3v2_flags['unsynch']     = (bool) ($id3_flags & 0x80); // a - Unsynchronisation
				$thisfile_id3v2_flags['compression'] = (bool) ($id3_flags & 0x40); // b - Compression
				break;

			case 3:
				// %abc00000 in v2.3
				$thisfile_id3v2_flags['unsynch']     = (bool) ($id3_flags & 0x80); // a - Unsynchronisation
				$thisfile_id3v2_flags['exthead']     = (bool) ($id3_flags & 0x40); // b - Extended header
				$thisfile_id3v2_flags['experim']     = (bool) ($id3_flags & 0x20); // c - Experimental indicator
				break;

			case 4:
				// %abcd0000 in v2.4
				$thisfile_id3v2_flags['unsynch']     = (bool) ($id3_flags & 0x80); // a - Unsynchronisation
				$thisfile_id3v2_flags['exthead']     = (bool) ($id3_flags & 0x40); // b - Extended header
				$thisfile_id3v2_flags['experim']     = (bool) ($id3_flags & 0x20); // c - Experimental indicator
				$thisfile_id3v2_flags['isfooter']    = (bool) ($id3_flags & 0x10); // d - Footer present
				break;
		}

		$thisfile_id3v2['headerlength'] = getid3_lib::BigEndian2Int(substr($header, 6, 4), 1) + 10; // length of ID3v2 tag in 10-byte header doesn't include 10-byte header length

		$thisfile_id3v2['tag_offset_start'] = $this->StartingOffset;
		$thisfile_id3v2['tag_offset_end']   = $thisfile_id3v2['tag_offset_start'] + $thisfile_id3v2['headerlength'];



		// create 'encoding' key - used by getid3::HandleAllTags()
		// in ID3v2 every field can have it's own encoding type
		// so force everything to UTF-8 so it can be handled consistantly
		$thisfile_id3v2['encoding'] = 'UTF-8';


	//    Frames

	//        All ID3v2 frames consists of one frame header followed by one or more
	//        fields containing the actual information. The header is always 10
	//        bytes and laid out as follows:
	//
	//        Frame ID      $xx xx xx xx  (four characters)
	//        Size      4 * %0xxxxxxx
	//        Flags         $xx xx

		$sizeofframes = $thisfile_id3v2['headerlength'] - 10; // not including 10-byte initial header
		if (!empty($thisfile_id3v2['exthead']['length'])) {
			$sizeofframes -= ($thisfile_id3v2['exthead']['length'] + 4);
		}
		if (!empty($thisfile_id3v2_flags['isfooter'])) {
			$sizeofframes -= 10; // footer takes last 10 bytes of ID3v2 header, after frame data, before audio
		}
		if ($sizeofframes > 0) {

			$framedata = $this->fread($sizeofframes); // read all frames from file into $framedata variable

			//    if entire frame data is unsynched, de-unsynch it now (ID3v2.3.x)
			if (!empty($thisfile_id3v2_flags['unsynch']) && ($id3v2_majorversion <= 3)) {
				$framedata = $this->DeUnsynchronise($framedata);
			}
			//        [in ID3v2.4.0] Unsynchronisation [S:6.1] is done on frame level, instead
			//        of on tag level, making it easier to skip frames, increasing the streamability
			//        of the tag. The unsynchronisation flag in the header [S:3.1] indicates that
			//        there exists an unsynchronised frame, while the new unsynchronisation flag in
			//        the frame header [S:4.1.2] indicates unsynchronisation.


			//$framedataoffset = 10 + ($thisfile_id3v2['exthead']['length'] ? $thisfile_id3v2['exthead']['length'] + 4 : 0); // how many bytes into the stream - start from after the 10-byte header (and extended header length+4, if present)
			$framedataoffset = 10; // how many bytes into the stream - start from after the 10-byte header


			//    Extended Header
			if (!empty($thisfile_id3v2_flags['exthead'])) {
				$extended_header_offset = 0;

				if ($id3v2_majorversion == 3) {

					// v2.3 definition:
					//Extended header size  $xx xx xx xx   // 32-bit integer
					//Extended Flags        $xx xx
					//     %x0000000 %00000000 // v2.3
					//     x - CRC data present
					//Size of padding       $xx xx xx xx

					$thisfile_id3v2['exthead']['length'] = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, 4), 0);
					$extended_header_offset += 4;

					$thisfile_id3v2['exthead']['flag_bytes'] = 2;
					$thisfile_id3v2['exthead']['flag_raw'] = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, $thisfile_id3v2['exthead']['flag_bytes']));
					$extended_header_offset += $thisfile_id3v2['exthead']['flag_bytes'];

					$thisfile_id3v2['exthead']['flags']['crc'] = (bool) ($thisfile_id3v2['exthead']['flag_raw'] & 0x8000);

					$thisfile_id3v2['exthead']['padding_size'] = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, 4));
					$extended_header_offset += 4;

					if ($thisfile_id3v2['exthead']['flags']['crc']) {
						$thisfile_id3v2['exthead']['flag_data']['crc'] = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, 4));
						$extended_header_offset += 4;
					}
					$extended_header_offset += $thisfile_id3v2['exthead']['padding_size'];

				} elseif ($id3v2_majorversion == 4) {

					// v2.4 definition:
					//Extended header size   4 * %0xxxxxxx // 28-bit synchsafe integer
					//Number of flag bytes       $01
					//Extended Flags             $xx
					//     %0bcd0000 // v2.4
					//     b - Tag is an update
					//         Flag data length       $00
					//     c - CRC data present
					//         Flag data length       $05
					//         Total frame CRC    5 * %0xxxxxxx
					//     d - Tag restrictions
					//         Flag data length       $01

					$thisfile_id3v2['exthead']['length'] = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, 4), true);
					$extended_header_offset += 4;

					$thisfile_id3v2['exthead']['flag_bytes'] = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, 1)); // should always be 1
					$extended_header_offset += 1;

					$thisfile_id3v2['exthead']['flag_raw'] = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, $thisfile_id3v2['exthead']['flag_bytes']));
					$extended_header_offset += $thisfile_id3v2['exthead']['flag_bytes'];

					$thisfile_id3v2['exthead']['flags']['update']       = (bool) ($thisfile_id3v2['exthead']['flag_raw'] & 0x40);
					$thisfile_id3v2['exthead']['flags']['crc']          = (bool) ($thisfile_id3v2['exthead']['flag_raw'] & 0x20);
					$thisfile_id3v2['exthead']['flags']['restrictions'] = (bool) ($thisfile_id3v2['exthead']['flag_raw'] & 0x10);

					if ($thisfile_id3v2['exthead']['flags']['update']) {
						$ext_header_chunk_length = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, 1)); // should be 0
						$extended_header_offset += 1;
					}

					if ($thisfile_id3v2['exthead']['flags']['crc']) {
						$ext_header_chunk_length = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, 1)); // should be 5
						$extended_header_offset += 1;
						$thisfile_id3v2['exthead']['flag_data']['crc'] = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, $ext_header_chunk_length), true, false);
						$extended_header_offset += $ext_header_chunk_length;
					}

					if ($thisfile_id3v2['exthead']['flags']['restrictions']) {
						$ext_header_chunk_length = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, 1)); // should be 1
						$extended_header_offset += 1;

						// %ppqrrstt
						$restrictions_raw = getid3_lib::BigEndian2Int(substr($framedata, $extended_header_offset, 1));
						$extended_header_offset += 1;
						$thisfile_id3v2['exthead']['flags']['restrictions']['tagsize']  = ($restrictions_raw & 0xC0) >> 6; // p - Tag size restrictions
						$thisfile_id3v2['exthead']['flags']['restrictions']['textenc']  = ($restrictions_raw & 0x20) >> 5; // q - Text encoding restrictions
						$thisfile_id3v2['exthead']['flags']['restrictions']['textsize'] = ($restrictions_raw & 0x18) >> 3; // r - Text fields size restrictions
						$thisfile_id3v2['exthead']['flags']['restrictions']['imgenc']   = ($restrictions_raw & 0x04) >> 2; // s - Image encoding restrictions
						$thisfile_id3v2['exthead']['flags']['restrictions']['imgsize']  = ($restrictions_raw & 0x03) >> 0; // t - Image size restrictions

						$thisfile_id3v2['exthead']['flags']['restrictions_text']['tagsize']  = $this->LookupExtendedHeaderRestrictionsTagSizeLimits($thisfile_id3v2['exthead']['flags']['restrictions']['tagsize']);
						$thisfile_id3v2['exthead']['flags']['restrictions_text']['textenc']  = $this->LookupExtendedHeaderRestrictionsTextEncodings($thisfile_id3v2['exthead']['flags']['restrictions']['textenc']);
						$thisfile_id3v2['exthead']['flags']['restrictions_text']['textsize'] = $this->LookupExtendedHeaderRestrictionsTextFieldSize($thisfile_id3v2['exthead']['flags']['restrictions']['textsize']);
						$thisfile_id3v2['exthead']['flags']['restrictions_text']['imgenc']   = $this->LookupExtendedHeaderRestrictionsImageEncoding($thisfile_id3v2['exthead']['flags']['restrictions']['imgenc']);
						$thisfile_id3v2['exthead']['flags']['restrictions_text']['imgsize']  = $this->LookupExtendedHeaderRestrictionsImageSizeSize($thisfile_id3v2['exthead']['flags']['restrictions']['imgsize']);
					}

					if ($thisfile_id3v2['exthead']['length'] != $extended_header_offset) {
						$this->warning('ID3v2.4 extended header length mismatch (expecting '.intval($thisfile_id3v2['exthead']['length']).', found '.intval($extended_header_offset).')');
					}
				}

				$framedataoffset += $extended_header_offset;
				$framedata = substr($framedata, $extended_header_offset);
			} // end extended header


			while (isset($framedata) && (strlen($framedata) > 0)) { // cycle through until no more frame data is left to parse
				if (strlen($framedata) <= $this->ID3v2HeaderLength($id3v2_majorversion)) {
					// insufficient room left in ID3v2 header for actual data - must be padding
					$thisfile_id3v2['padding']['start']  = $framedataoffset;
					$thisfile_id3v2['paddi