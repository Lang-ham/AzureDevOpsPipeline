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
		//        ID3v2 size             4 * %0x