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
					$thisfile_id3v2['padding']['length'] = strlen($framedata);
					$thisfile_id3v2['padding']['valid']  = true;
					for ($i = 0; $i < $thisfile_id3v2['padding']['length']; $i++) {
						if ($framedata{$i} != "\x00") {
							$thisfile_id3v2['padding']['valid'] = false;
							$thisfile_id3v2['padding']['errorpos'] = $thisfile_id3v2['padding']['start'] + $i;
							$this->warning('Invalid ID3v2 padding found at offset '.$thisfile_id3v2['padding']['errorpos'].' (the remaining '.($thisfile_id3v2['padding']['length'] - $i).' bytes are considered invalid)');
							break;
						}
					}
					break; // skip rest of ID3v2 header
				}
				if ($id3v2_majorversion == 2) {
					// Frame ID  $xx xx xx (three characters)
					// Size      $xx xx xx (24-bit integer)
					// Flags     $xx xx

					$frame_header = substr($framedata, 0, 6); // take next 6 bytes for header
					$framedata    = substr($framedata, 6);    // and leave the rest in $framedata
					$frame_name   = substr($frame_header, 0, 3);
					$frame_size   = getid3_lib::BigEndian2Int(substr($frame_header, 3, 3), 0);
					$frame_flags  = 0; // not used for anything in ID3v2.2, just set to avoid E_NOTICEs

				} elseif ($id3v2_majorversion > 2) {

					// Frame ID  $xx xx xx xx (four characters)
					// Size      $xx xx xx xx (32-bit integer in v2.3, 28-bit synchsafe in v2.4+)
					// Flags     $xx xx

					$frame_header = substr($framedata, 0, 10); // take next 10 bytes for header
					$framedata    = substr($framedata, 10);    // and leave the rest in $framedata

					$frame_name = substr($frame_header, 0, 4);
					if ($id3v2_majorversion == 3) {
						$frame_size = getid3_lib::BigEndian2Int(substr($frame_header, 4, 4), 0); // 32-bit integer
					} else { // ID3v2.4+
						$frame_size = getid3_lib::BigEndian2Int(substr($frame_header, 4, 4), 1); // 32-bit synchsafe integer (28-bit value)
					}

					if ($frame_size < (strlen($framedata) + 4)) {
						$nextFrameID = substr($framedata, $frame_size, 4);
						if ($this->IsValidID3v2FrameName($nextFrameID, $id3v2_majorversion)) {
							// next frame is OK
						} elseif (($frame_name == "\x00".'MP3') || ($frame_name == "\x00\x00".'MP') || ($frame_name == ' MP3') || ($frame_name == 'MP3e')) {
							// MP3ext known broken frames - "ok" for the purposes of this test
						} elseif (($id3v2_majorversion == 4) && ($this->IsValidID3v2FrameName(substr($framedata, getid3_lib::BigEndian2Int(substr($frame_header, 4, 4), 0), 4), 3))) {
							$this->warning('ID3v2 tag written as ID3v2.4, but with non-synchsafe integers (ID3v2.3 style). Older versions of (Helium2; iTunes) are known culprits of this. Tag has been parsed as ID3v2.3');
							$id3v2_majorversion = 3;
							$frame_size = getid3_lib::BigEndian2Int(substr($frame_header, 4, 4), 0); // 32-bit integer
						}
					}


					$frame_flags = getid3_lib::BigEndian2Int(substr($frame_header, 8, 2));
				}

				if ((($id3v2_majorversion == 2) && ($frame_name == "\x00\x00\x00")) || ($frame_name == "\x00\x00\x00\x00")) {
					// padding encountered

					$thisfile_id3v2['padding']['start']  = $framedataoffset;
					$thisfile_id3v2['padding']['length'] = strlen($frame_header) + strlen($framedata);
					$thisfile_id3v2['padding']['valid']  = true;

					$len = strlen($framedata);
					for ($i = 0; $i < $len; $i++) {
						if ($framedata{$i} != "\x00") {
							$thisfile_id3v2['padding']['valid'] = false;
							$thisfile_id3v2['padding']['errorpos'] = $thisfile_id3v2['padding']['start'] + $i;
							$this->warning('Invalid ID3v2 padding found at offset '.$thisfile_id3v2['padding']['errorpos'].' (the remaining '.($thisfile_id3v2['padding']['length'] - $i).' bytes are considered invalid)');
							break;
						}
					}
					break; // skip rest of ID3v2 header
				}

				if ($iTunesBrokenFrameNameFixed = self::ID3v22iTunesBrokenFrameName($frame_name)) {
					$this->warning('error parsing "'.$frame_name.'" ('.$framedataoffset.' bytes into the ID3v2.'.$id3v2_majorversion.' tag). (ERROR: IsValidID3v2FrameName("'.str_replace("\x00", ' ', $frame_name).'", '.$id3v2_majorversion.'))). [Note: this particular error has been known to happen with tags edited by iTunes (versions "X v2.0.3", "v3.0.1", "v7.0.0.70" are known-guilty, probably others too)]. Translated frame name from "'.str_replace("\x00", ' ', $frame_name).'" to "'.$iTunesBrokenFrameNameFixed.'" for parsing.');
					$frame_name = $iTunesBrokenFrameNameFixed;
				}
				if (($frame_size <= strlen($framedata)) && ($this->IsValidID3v2FrameName($frame_name, $id3v2_majorversion))) {

					unset($parsedFrame);
					$parsedFrame['frame_name']      = $frame_name;
					$parsedFrame['frame_flags_raw'] = $frame_flags;
					$parsedFrame['data']            = substr($framedata, 0, $frame_size);
					$parsedFrame['datalength']      = getid3_lib::CastAsInt($frame_size);
					$parsedFrame['dataoffset']      = $framedataoffset;

					$this->ParseID3v2Frame($parsedFrame);
					$thisfile_id3v2[$frame_name][] = $parsedFrame;

					$framedata = substr($framedata, $frame_size);

				} else { // invalid frame length or FrameID

					if ($frame_size <= strlen($framedata)) {

						if ($this->IsValidID3v2FrameName(substr($framedata, $frame_size, 4), $id3v2_majorversion)) {

							// next frame is valid, just skip the current frame
							$framedata = substr($framedata, $frame_size);
							$this->warning('Next ID3v2 frame is valid, skipping current frame.');

						} else {

							// next frame is invalid too, abort processing
							//unset($framedata);
							$framedata = null;
							$this->error('Next ID3v2 frame is also invalid, aborting processing.');

						}

					} elseif ($frame_size == strlen($framedata)) {

						// this is the last frame, just skip
						$this->warning('This was the last ID3v2 frame.');

					} else {

						// next frame is invalid too, abort processing
						//unset($framedata);
						$framedata = null;
						$this->warning('Invalid ID3v2 frame size, aborting.');

					}
					if (!$this->IsValidID3v2FrameName($frame_name, $id3v2_majorversion)) {

						switch ($frame_name) {
							case "\x00\x00".'MP':
							case "\x00".'MP3':
							case ' MP3':
							case 'MP3e':
							case "\x00".'MP':
							case ' MP':
							case 'MP3':
								$this->warning('error parsing "'.$frame_name.'" ('.$framedataoffset.' bytes into the ID3v2.'.$id3v2_majorversion.' tag). (ERROR: !IsValidID3v2FrameName("'.str_replace("\x00", ' ', $frame_name).'", '.$id3v2_majorversion.'))). [Note: this particular error has been known to happen with tags edited by "MP3ext (www.mutschler.de/mp3ext/)"]');
								break;

							default:
								$this->warning('error parsing "'.$frame_name.'" ('.$framedataoffset.' bytes into the ID3v2.'.$id3v2_majorversion.' tag). (ERROR: !IsValidID3v2FrameName("'.str_replace("\x00", ' ', $frame_name).'", '.$id3v2_majorversion.'))).');
								break;
						}

					} elseif (!isset($framedata) || ($frame_size > strlen($framedata))) {

						$this->error('error parsing "'.$frame_name.'" ('.$framedataoffset.' bytes into the ID3v2.'.$id3v2_majorversion.' tag). (ERROR: $frame_size ('.$frame_size.') > strlen($framedata) ('.(isset($framedata) ? strlen($framedata) : 'null').')).');

					} else {

						$this->error('error parsing "'.$frame_name.'" ('.$framedataoffset.' bytes into the ID3v2.'.$id3v2_majorversion.' tag).');

					}

				}
				$framedataoffset += ($frame_size + $this->ID3v2HeaderLength($id3v2_majorversion));

			}

		}


	//    Footer

	//    The footer is a copy of the header, but with a different identifier.
	//        ID3v2 identifier           "3DI"
	//        ID3v2 version              $04 00
	//        ID3v2 flags                %abcd0000
	//        ID3v2 size             4 * %0xxxxxxx

		if (isset($thisfile_id3v2_flags['isfooter']) && $thisfile_id3v2_flags['isfooter']) {
			$footer = $this->fread(10);
			if (substr($footer, 0, 3) == '3DI') {
				$thisfile_id3v2['footer'] = true;
				$thisfile_id3v2['majorversion_footer'] = ord($footer{3});
				$thisfile_id3v2['minorversion_footer'] = ord($footer{4});
			}
			if ($thisfile_id3v2['majorversion_footer'] <= 4) {
				$id3_flags = ord(substr($footer{5}));
				$thisfile_id3v2_flags['unsynch_footer']  = (bool) ($id3_flags & 0x80);
				$thisfile_id3v2_flags['extfoot_footer']  = (bool) ($id3_flags & 0x40);
				$thisfile_id3v2_flags['experim_footer']  = (bool) ($id3_flags & 0x20);
				$thisfile_id3v2_flags['isfooter_footer'] = (bool) ($id3_flags & 0x10);

				$thisfile_id3v2['footerlength'] = getid3_lib::BigEndian2Int(substr($footer, 6, 4), 1);
			}
		} // end footer

		if (isset($thisfile_id3v2['comments']['genre'])) {
			$genres = array();
			foreach ($thisfile_id3v2['comments']['genre'] as $key => $value) {
				foreach ($this->ParseID3v2GenreString($value) as $genre) {
					$genres[] = $genre;
				}
			}
			$thisfile_id3v2['comments']['genre'] = array_unique($genres);
			unset($key, $value, $genres, $genre);
		}

		if (isset($thisfile_id3v2['comments']['track'])) {
			foreach ($thisfile_id3v2['comments']['track'] as $key => $value) {
				if (strstr($value, '/')) {
					list($thisfile_id3v2['comments']['tracknum'][$key], $thisfile_id3v2['comments']['totaltracks'][$key]) = explode('/', $thisfile_id3v2['comments']['track'][$key]);
				}
			}
		}

		if (!isset($thisfile_id3v2['comments']['year']) && !empty($thisfile_id3v2['comments']['recording_time'][0]) && preg_match('#^([0-9]{4})#', trim($thisfile_id3v2['comments']['recording_time'][0]), $matches)) {
			$thisfile_id3v2['comments']['year'] = array($matches[1]);
		}


		if (!empty($thisfile_id3v2['TXXX'])) {
			// MediaMonkey does this, maybe others: write a blank RGAD frame, but put replay-gain adjustment values in TXXX frames
			foreach ($thisfile_id3v2['TXXX'] as $txxx_array) {
				switch ($txxx_array['description']) {
					case 'replaygain_track_gain':
						if (empty($info['replay_gain']['track']['adjustment']) && !empty($txxx_array['data'])) {
							$info['replay_gain']['track']['adjustment'] = floatval(trim(str_replace('dB', '', $txxx_array['data'])));
						}
						break;
					case 'replaygain_track_peak':
						if (empty($info['replay_gain']['track']['peak']) && !empty($txxx_array['data'])) {
							$info['replay_gain']['track']['peak'] = floatval($txxx_array['data']);
						}
						break;
					case 'replaygain_album_gain':
						if (empty($info['replay_gain']['album']['adjustment']) && !empty($txxx_array['data'])) {
							$info['replay_gain']['album']['adjustment'] = floatval(trim(str_replace('dB', '', $txxx_array['data'])));
						}
						break;
				}
			}
		}


		// Set avdataoffset
		$info['avdataoffset'] = $thisfile_id3v2['headerlength'];
		if (isset($thisfile_id3v2['footer'])) {
			$info['avdataoffset'] += 10;
		}

		return true;
	}


	public function ParseID3v2GenreString($genrestring) {
		// Parse genres into arrays of genreName and genreID
		// ID3v2.2.x, ID3v2.3.x: '(21)' or '(4)Eurodisco' or '(51)(39)' or '(55)((I think...)'
		// ID3v2.4.x: '21' $00 'Eurodisco' $00
		$clean_genres = array();

		// hack-fixes for some badly-written ID3v2.3 taggers, while trying not to break correctly-written tags
		if (($this->getid3->info['id3v2']['majorversion'] == 3) && !preg_match('#[\x00]#', $genrestring)) {
			// note: MusicBrainz Picard incorrectly stores plaintext genres separated by "/" when writing in ID3v2.3 mode, hack-fix here:
			// replace / with NULL, then replace back the two ID3v1 genres that legitimately have "/" as part of the single genre name
			if (preg_match('#/#', $genrestring)) {
				$genrestring = str_replace('/', "\x00", $genrestring);
				$genrestring = str_replace('Pop'."\x00".'Funk', 'Pop/Funk', $genrestring);
				$genrestring = str_replace('Rock'."\x00".'Rock', 'Folk/Rock', $genrestring);
			}

			// some other taggers separate multiple genres with semicolon, e.g. "Heavy Metal;Thrash Metal;Metal"
			if (preg_match('#;#', $genrestring)) {
				$genrestring = str_replace(';', "\x00", $genrestring);
			}
		}


		if (strpos($genrestring, "\x00") === false) {
			$genrestring = preg_replace('#\(([0-9]{1,3})\)#', '$1'."\x00", $genrestring);
		}

		$genre_elements = explode("\x00", $genrestring);
		foreach ($genre_elements as $element) {
			$element = trim($element);
			if ($element) {
				if (preg_match('#^[0-9]{1,3}#', $element)) {
					$clean_genres[] = getid3_id3v1::LookupGenreName($element);
				} else {
					$clean_genres[] = str_replace('((', '(', $element);
				}
			}
		}
		return $clean_genres;
	}


	public function ParseID3v2Frame(&$parsedFrame) {

		// shortcuts
		$info = &$this->getid3->info;
		$id3v2_majorversion = $info['id3v2']['majorversion'];

		$parsedFrame['framenamelong']  = $this->FrameNameLongLookup($parsedFrame['frame_name']);
		if (empty($parsedFrame['framenamelong'])) {
			unset($parsedFrame['framenamelong']);
		}
		$parsedFrame['framenameshort'] = $this->FrameNameShortLookup($parsedFrame['frame_name']);
		if (empty($parsedFrame['framenameshort'])) {
			unset($parsedFrame['framenameshort']);
		}

		if ($id3v2_majorversion >= 3) { // frame flags are not part of the ID3v2.2 standard
			if ($id3v2_majorversion == 3) {
				//    Frame Header Flags
				//    %abc00000 %ijk00000
				$parsedFrame['flags']['TagAlterPreservation']  = (bool) ($parsedFrame['frame_flags_raw'] & 0x8000); // a - Tag alter preservation
				$parsedFrame['flags']['FileAlterPreservation'] = (bool) ($parsedFrame['frame_flags_raw'] & 0x4000); // b - File alter preservation
				$parsedFrame['flags']['ReadOnly']              = (bool) ($parsedFrame['frame_flags_raw'] & 0x2000); // c - Read only
				$parsedFrame['flags']['compression']           = (bool) ($parsedFrame['frame_flags_raw'] & 0x0080); // i - Compression
				$parsedFrame['flags']['Encryption']            = (bool) ($parsedFrame['frame_flags_raw'] & 0x0040); // j - Encryption
				$parsedFrame['flags']['GroupingIdentity']      = (bool) ($parsedFrame['frame_flags_raw'] & 0x0020); // k - Grouping identity

			} elseif ($id3v2_majorversion == 4) {
				//    Frame Header Flags
				//    %0abc0000 %0h00kmnp
				$parsedFrame['flags']['TagAlterPreservation']  = (bool) ($parsedFrame['frame_flags_raw'] & 0x4000); // a - Tag alter preservation
				$parsedFrame['flags']['FileAlterPreservation'] = (bool) ($parsedFrame['frame_flags_raw'] & 0x2000); // b - File alter preservation
				$parsedFrame['flags']['ReadOnly']              = (bool) ($parsedFrame['frame_flags_raw'] & 0x1000); // c - Read only
				$parsedFrame['flags']['GroupingIdentity']      = (bool) ($parsedFrame['frame_flags_raw'] & 0x0040); // h - Grouping identity
				$parsedFrame['flags']['compression']           = (bool) ($parsedFrame['frame_flags_raw'] & 0x0008); // k - Compression
				$parsedFrame['flags']['Encryption']            = (bool) ($parsedFrame['frame_flags_raw'] & 0x0004); // m - Encryption
				$parsedFrame['flags']['Unsynchronisation']     = (bool) ($parsedFrame['frame_flags_raw'] & 0x0002); // n - Unsynchronisation
				$parsedFrame['flags']['DataLengthIndicator']   = (bool) ($parsedFrame['frame_flags_raw'] & 0x0001); // p - Data length indicator

				// Frame-level de-unsynchronisation - ID3v2.4
				if ($parsedFrame['flags']['Unsynchronisation']) {
					$parsedFrame['data'] = $this->DeUnsynchronise($parsedFrame['data']);
				}

				if ($parsedFrame['flags']['DataLengthIndicator']) {
					$parsedFrame['data_length_indicator'] = getid3_lib::BigEndian2Int(substr($parsedFrame['data'], 0, 4), 1);
					$parsedFrame['data']                  =                           substr($parsedFrame['data'], 4);
				}
			}

			//    Frame-level de-compression
			if ($parsedFrame['flags']['compression']) {
				$parsedFrame['decompressed_size'] = getid3_lib::BigEndian2Int(substr($parsedFrame['data'], 0, 4));
				if (!function_exists('gzuncompress')) {
					$this->warning('gzuncompress() support required to decompress ID3v2 frame "'.$parsedFrame['frame_name'].'"');
				} else {
					if ($decompresseddata = @gzuncompress(substr($parsedFrame['data'], 4))) {
					//if ($decompresseddata = @gzuncompress($parsedFrame['data'])) {
						$parsedFrame['data'] = $decompresseddata;
						unset($decompresseddata);
					} else {
						$this->warning('gzuncompress() failed on compressed contents of ID3v2 frame "'.$parsedFrame['frame_name'].'"');
					}
				}
			}
		}

		if (!empty($parsedFrame['flags']['DataLengthIndicator'])) {
			if ($parsedFrame['data_length_indicator'] != strlen($parsedFrame['data'])) {
				$this->warning('ID3v2 frame "'.$parsedFrame['frame_name'].'" should be '.$parsedFrame['data_length_indicator'].' bytes long according to DataLengthIndicator, but found '.strlen($parsedFrame['data']).' bytes of data');
			}
		}

		if (isset($parsedFrame['datalength']) && ($parsedFrame['datalength'] == 0)) {

			$warning = 'Frame "'.$parsedFrame['frame_name'].'" at offset '.$parsedFrame['dataoffset'].' has no data portion';
			switch ($parsedFrame['frame_name']) {
				case 'WCOM':
					$warning .= ' (this is known to happen with files tagged by RioPort)';
					break;

				default:
					break;
			}
			$this->warning($warning);

		} elseif ((($id3v2_majorversion >= 3) && ($parsedFrame['frame_name'] == 'UFID')) || // 4.1   UFID Unique file identifier
			(($id3v2_majorversion == 2) && ($parsedFrame['frame_name'] == 'UFI'))) {  // 4.1   UFI  Unique file identifier
			//   There may be more than one 'UFID' frame in a tag,
			//   but only one with the same 'Owner identifier'.
			// <Header for 'Unique file identifier', ID: 'UFID'>
			// Owner identifier        <text string> $00
			// Identifier              <up to 64 bytes binary data>
			$exploded = explode("\x00", $parsedFrame['data'], 2);
			$parsedFrame['ownerid'] = (isset($exploded[0]) ? $exploded[0] : '');
			$parsedFrame['data']    = (isset($exploded[1]) ? $exploded[1] : '');

		} elseif ((($id3v2_majorversion >= 3) && ($parsedFrame['frame_name'] == 'TXXX')) || // 4.2.2 TXXX User defined text information frame
				(($id3v2_majorversion == 2) && ($parsedFrame['frame_name'] == 'TXX'))) {    // 4.2.2 TXX  User defined text information frame
			//   There may be more than one 'TXXX' frame in each tag,
			//   but only one with the same description.
			// <Header for 'User defined text information frame', ID: 'TXXX'>
			// Text encoding     $xx
			// Description       <text string according to encoding> $00 (00)
			// Value             <text string according to encoding>

			$frame_offset = 0;
			$frame_textencoding = ord(substr($parsedFrame['data'], $frame_offset++, 1));
			$frame_textencoding_terminator = $this->TextEncodingTerminatorLookup($frame_textencoding);
			if ((($id3v2_majorversion <= 3) && ($frame_textencoding > 1)) || (($id3v2_majorversion == 4) && ($frame_textencoding > 3))) {
				$this->warning('Invalid text encoding byte ('.$frame_textencoding.') in frame "'.$parsedFrame['frame_name'].'" - defaulting to ISO-8859-1 encoding');
				$frame_textencoding_terminator = "\x00";
			}
			$frame_terminatorpos = strpos($parsedFrame['data'], $frame_textencoding_terminator, $frame_offset);
			if (ord(substr($parsedFrame['data'], $frame_terminatorpos + strlen($frame_textencoding_terminator), 1)) === 0) {
				$frame_terminatorpos++; // strpos() fooled because 2nd byte of Unicode chars are often 0x00
			}
			$frame_description = substr($parsedFrame['data'], $frame_offset, $frame_terminatorpos - $frame_offset);
			if (in_array($frame_description, array("\x00", "\x00\x00", "\xFF\xFE", "\xFE\xFF"))) {
				// if description only contains a BOM or terminator then make it blank
				$frame_description = '';
			}
			$parsedFrame['encodingid']  = $frame_textencoding;
			$parsedFrame['encoding']    = $this->TextEncodingNameLookup($frame_textencoding);

			$parsedFrame['description'] = trim(getid3_lib::iconv_fallback($parsedFrame['encoding'], $info['id3v2']['encoding'], $frame_description));
			$parsedFrame['data'] = substr($parsedFrame['data'], $frame_terminatorpos + strlen($frame_textencoding_terminator));
			if (!empty($parsedFrame['framenameshort']) && !empty($parsedFrame['data'])) {
				$commentkey = ($parsedFrame['description'] ? $parsedFrame['description'] : (isset($info['id3v2']['comments'][$parsedFrame['framenameshort']]) ? count($info['id3v2']['comments'][$parsedFrame['framenameshort']]) : 0));
				if (!isset($info['id3v2']['comments'][$parsedFrame['framenameshort']]) || !array_key_exists($commentkey, $info['id3v2']['comments'][$parsedFrame['framenameshort']])) {
					$info['id3v2']['comments'][$parsedFrame['framenameshort']][$commentkey] = trim(getid3_lib::iconv_fallback($parsedFrame['encoding'], $info['id3v2']['encoding'], $parsedFrame['data']));
				} else {
					$info['id3v2']['comments'][$parsedFrame['framenameshort']][]            = trim(getid3_lib::iconv_fallback($parsedFrame['encoding'], $info['id3v2']['encoding'], $parsedFrame['data']));
				}
			}
			//unset($parsedFrame['data']); do not unset, may be needed elsewhere, e.g. for replaygain


		} elseif ($parsedFrame['frame_name']{0} == 'T') { // 4.2. T??[?] Text information frame
			//   There may only be one text information frame of its kind in an tag.
			// <Header for 'Text information frame', ID: 'T000' - 'TZZZ',
			// excluding 'TXXX' described in 4.2.6.>
			// Text encoding                $xx
			// Information                  <text string(s) according to encoding>

			$frame_offset = 0;
			$frame_textencoding = ord(substr($parsedFrame['data'], $frame_offset++, 1));
			if ((($id3v2_majorversion <= 3) && ($frame_textencoding > 1)) || (($id3v2_majorversion == 4) && ($frame_textencoding > 3))) {
				$this->warning('Invalid tex