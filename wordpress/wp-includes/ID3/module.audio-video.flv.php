<?php
/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
//                                                             //
//  FLV module by Seth Kaufman <sethØwhirl-i-gig*com>          //
//                                                             //
//  * version 0.1 (26 June 2005)                               //
//                                                             //
//                                                             //
//  * version 0.1.1 (15 July 2005)                             //
//  minor modifications by James Heinrich <info@getid3.org>    //
//                                                             //
//  * version 0.2 (22 February 2006)                           //
//  Support for On2 VP6 codec and meta information             //
//    by Steve Webster <steve.websterØfeaturecreep*com>        //
//                                                             //
//  * version 0.3 (15 June 2006)                               //
//  Modified to not read entire file into memory               //
//    by James Heinrich <info@getid3.org>                      //
//                                                             //
//  * version 0.4 (07 December 2007)                           //
//  Bugfixes for incorrectly parsed FLV dimensions             //
//    and incorrect parsing of onMetaTag                       //
//    by Evgeny Moysevich <moysevichØgmail*com>                //
//                                                             //
//  * version 0.5 (21 May 2009)                                //
//  Fixed parsing of audio tags and added additional codec     //
//    details. The duration is now read from onMetaTag (if     //
//    exists), rather than parsing whole file                  //
//    by Nigel Barnes <ngbarnesØhotmail*com>                   //
//                                                             //
//  * version 0.6 (24 May 2009)                                //
//  Better parsing of files with h264 video                    //
//    by Evgeny Moysevich <moysevichØgmail*com>                //
//                                                             //
//  * version 0.6.1 (30 May 2011)                              //
//    prevent infinite loops in expGolombUe()                  //
//                                                             //
//  * version 0.7.0 (16 Jul 2013)                              //
//  handle GETID3_FLV_VIDEO_VP6FLV_ALPHA                       //
//  improved AVCSequenceParameterSetReader::readData()         //
//    by Xander Schouwerwou <schouwerwouØgmail*com>            //
//                                                             //
/////////////////////////////////////////////////////////////////
//                                                             //
// module.audio-video.flv.php                                  //
// module for analyzing Shockwave Flash Video files            //
// dependencies: NONE                                          //
//                                                            ///
/////////////////////////////////////////////////////////////////

define('GETID3_FLV_TAG_AUDIO',          8);
define('GETID3_FLV_TAG_VIDEO',          9);
define('GETID3_FLV_TAG_META',          18);

define('GETID3_FLV_VIDEO_H263',         2);
define('GETID3_FLV_VIDEO_SCREEN',       3);
define('GETID3_FLV_VIDEO_VP6FLV',       4);
define('GETID3_FLV_VIDEO_VP6FLV_ALPHA', 5);
define('GETID3_FLV_VIDEO_SCREENV2',     6);
define('GETID3_FLV_VIDEO_H264',         7);

define('H264_AVC_SEQUENCE_HEADER',          0);
define('H264_PROFILE_BASELINE',            66);
define('H264_PROFILE_MAIN',                77);
define('H264_PROFILE_EXTENDED',            88);
define('H264_PROFILE_HIGH',               100);
define('H264_PROFILE_HIGH10',             110);
define('H264_PROFILE_HIGH422',            122);
define('H264_PROFILE_HIGH444',            144);
define('H264_PROFILE_HIGH444_PREDICTIVE', 244);

class getid3_flv extends getid3_handler {

	const magic = 'FLV';

	public $max_frames = 100000; // break out of the loop if too many frames have been scanned; only scan this many if meta frame does not contain useful duration

	public function Analyze() {
		$info = &$this->getid3->info;

		$this->fseek($info['avdataoffset']);

		$FLVdataLength = $info['avdataend'] - $info['avdataoffset'];
		$FLVheader = $this->fread(5);

		$info['fileformat'] = 'flv';
		$info['flv']['header']['signature'] =                           substr($FLVheader, 0, 3);
		$info['flv']['header']['version']   = getid3_lib::BigEndian2Int(substr($FLVheader, 3, 1));
		$TypeFlags                          = getid3_lib::BigEndian2Int(substr($FLVheader, 4, 1));

		if ($info['flv']['header']['signature'] != self::magic) {
			$this->error('Expecting "'.getid3_lib::PrintHexBytes(self::magic).'" at offset '.$info['avdataoffset'].', found "'.getid3_lib::PrintHexBytes($info['flv']['header']['signature']).'"');
			unset($info['flv'], $info['fileformat']);
			return false;
		}

		$info['flv']['header']['hasAudio'] = (bool) ($TypeFlags & 0x04);
		$info['flv']['header']['hasVideo'] = (bool) ($TypeFlags & 0x01);

		$FrameSizeDataLength = getid3_lib::BigEndian2Int($this->fread(4));
		$FLVheaderFrameLength = 9;
		if ($FrameSizeDataLength > $FLVheaderFrameLength) {
			$this->fseek($FrameSizeDataLength - $FLVheaderFrameLength, SEEK_CUR);
		}
		$Duration = 0;
		$found_video = false;
		$found_audio = false;
		$found_meta  = false;
		$found_valid_meta_playtime = false;
		$tagParseCount = 0;
		$info['flv']['framecount'] = array('total'=>0, 'audio'=>0, 'video'=>0);
		$flv_framecount = &$info['flv']['framecount'];
		while ((($this->ftell() + 16) < $info['avdataend']) && (($tagParseCount++ <= $this->max_frames) || !$found_valid_meta_playtime))  {
			$ThisTagHeader = $this->fread(16);

			$PreviousTagLength = getid3_lib::BigEndian2Int(substr($ThisTagHeader,  0, 4));
			$TagType           = getid3_lib::BigEndian2Int(substr($ThisTagHeader,  4, 1));
			$DataLength        = getid3_lib::BigEndian2Int(substr($ThisTagHeader,  5, 3));
			$Timestamp         = getid3_lib::BigEndian2Int(substr($ThisTagHeader,  8, 3));
			$LastHeaderByte    = getid3_lib::BigEndian2Int(substr($ThisTagHeader, 15, 1));
			$NextOffset = $this->ftell() - 1 + $DataLength;
			if ($Timestamp > $Duration) {
				$Duration = $Timestamp;
			}

			$flv_framecount['total']++;
			switch ($TagType) {
				case GETID3_FLV_TAG_AUDIO:
					$flv_framecount['audio']++;
					if (!$found_audio) {
						$found_audio = true;
						$info['flv']['audio']['audioFormat']     = ($LastHeaderByte >> 4) & 0x0F;
						$info['flv']['audio']['audioRate']       = ($LastHeaderByte >> 2) & 0x03;
						$info['flv']['audio']['audioSampleSize'] = ($LastHeaderByte >> 1) & 0x01;
						$info['flv']['audio']['audioType']       =  $LastHeaderByte       & 0x01;
					}
					break;

				case GETID3_FLV_TAG_VIDEO:
					$flv_framecount['video']++;
					if (!$found_video) {
						$found_video = true;
						$info['flv']['video']['videoCodec'] = $LastHeaderByte & 0