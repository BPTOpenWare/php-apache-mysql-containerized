<?php
require_once "enc.php";

/**
 * Copyright (C) 2016  Bluff Point Technologies LLC
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


$enc = new ourConstants();

define("DBIPADD", $enc->getDbipadd());
define("DBUNAME", $enc->getDbuname());
define("DBPWORD", $enc->getDbpword());
define("DBNAME", $enc->getDbname());


	//Random String generation for temp password 
	//Found on PHP.net http://www.php.net/manual/en/function.srand.php
	
	function randomstring($len)
	{
		$chars = 'aZb1cAdQKeYB2JfhjC8k9mX3DLnToRME0rNsF4tuUPvG5wHVx6yS7z';
		$str = "";
		$i = 0;
		date_default_timezone_set('UTC');
    		
    		while($i<$len)
     		{
			//0-55
        		$str.=$chars[mt_rand(0,53)];
        		$i++;
    		}
 		return $str;
	}


$txt = randomstring(8);
$img_number = imagecreate(100,25);
$backcolor = imagecolorallocate($img_number,10,10,55);
$textcolor = imagecolorallocate($img_number,255,255,255);
$linecolor = imagecolorallocate($img_number, 128, 255, 0);
$linecolortwo = imagecolorallocate($img_number, 255, 0, 0);
imagefill($img_number,0,0,$backcolor);
imagestring($img_number,5,0,1,$txt,$textcolor);
imagesetthickness($img_number, 1);
imageline($img_number, rand(0,50), rand(0,25), rand(75,100), rand(0,25), $linecolor);
imageline($img_number, rand(25,50), rand(0,25), rand(55,100), rand(0,25), $linecolor);
imageline($img_number, rand(0,50), rand(0,25), rand(1,100), rand(0,25), $linecolor);
imageline($img_number, rand(0,50), rand(0,25), rand(0,50), rand(0,25), $linecolortwo);
session_start();

$link = new mysqli(DBIPADD, DBUNAME, DBPWORD, DBNAME);

if($link->connect_errno) {
	die('Could not connect: ' . $link->connect_errno . ":" . $link->connect_error);
}
		

//first delete all past 24 hours
$curTime = time();
$curTime = $curTime - (24 * 60 * 60);
$myquery = "DELETE FROM NoSpam WHERE tstamp < " . $curTime;

$result = $link->query($myquery);

if(!$result) { 
        trigger_error('Query failed at 502a: Query:' . $myquery . "Error:" . $link->errno . ":" . $link->error, E_USER_ERROR);
}

$myquery = "SELECT * FROM NoSpam WHERE SessionID='" . session_id() . "'";

$result = $link->query($myquery);

if(!$result) { 
        trigger_error('Query failed at 502b: Query:' . $myquery . "Error:" . $link->errno . ":" . $link->error, E_USER_ERROR);
}

if(!mysqli_num_rows($result)) {
	//then insert
	$myquery = "INSERT INTO NoSpam(SessionID, txtSpam, tstamp) values('" . session_id() . "','" . $txt . "'," . time() . ")";
	$result = $link->query($myquery);
	
	if(!$result) { 
        	trigger_error('Query failed at 502c: Query:' . $myquery . "Error:" . $link->errno . ":" . $link->error, E_USER_ERROR);
	}

} else {

	$myquery = "UPDATE NoSpam SET txtSpam='" . $txt . "', tstamp=" . time() . " WHERE SessionID='" . session_id() . "'";
	$result = $link->query($myquery) or trigger_error('Query failed at 502d: Query:' . $myquery . "Error:" . $link->error, E_USER_ERROR);

}

header("Content-type: image/jpeg");
imagejpeg($img_number);
imagecolordeallocate($img_number, $backcolor);
imagecolordeallocate($img_number, $textcolor);
imagecolordeallocate($img_number, $linecolor);
$link->close();
imagedestroy($img_number);
?> 
