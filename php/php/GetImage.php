<?php
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
//GetImage.php

include "enc.php";

if((!isset($_GET["ImgID"])) && (!isset($_GET["Name"]))) {
	header("HTTP/1.0 404 Not Found");
	exit(1);
}

$enc = new ourConstants();

define("DBIPADD", $enc->getDbipadd());
define("DBUNAME", $enc->getDbuname());
define("DBPWORD", $enc->getDbpword());
define("DBNAME", $enc->getDbname());



$link = new mysqli(DBIPADD, DBUNAME, DBPWORD, DBNAME);

if($link->connect_errno) {
	die('Could not connect: ' . $link->connect_errno . ":" . $link->connect_error);
}

		

//ID flag is just used to tell if we have an image.
$IDFlag = false;
$Rvalue = "";
if(isset($_GET["ImgID"])) {
	$IDFlag = true;
	$Rvalue = $_GET["ImgID"];
} else {
	$Rvalue = $_GET["Name"];
}

if(get_magic_quotes_gpc()) {
	$Rvalue = stripslashes($Rvalue);
} 
		
$Rvalue = $link->real_escape_string($Rvalue);

if($IDFlag) {
	$Rvalue = intval($Rvalue);
	$query = "SELECT BImage, PermFlag, ImgID FROM ODOImages WHERE ImgID=" . $Rvalue;
} else {
	$query = "SELECT BImage, PermFlag, ImgID FROM ODOImages WHERE Name='" . $Rvalue . "'";
}

$result = $link->query($query) or trigger_error('Query failed at 103a: Query:' . $query . "Error:" . $link->error, E_USER_ERROR);

if(!mysqli_num_rows($result)) {
	header("HTTP/1.0 404 Not Found");
	$link->close();
	exit(1);
} 

$row = mysqli_fetch_assoc($result);

if($row["PermFlag"] == 1) {
	include "class.php";
	session_start();
	
	if(!session_is_registered('ODOSessionO'))
	{
		//load system objects
		$_SESSION["ODOSessionO"] = new ODOSession;
		$_SESSION["ODOConstantsO"] = new ODOConstants;
		$_SESSION["ODOUserO"] = new ODOUser;
		$_SESSION["ODOLoggingO"] = new ODOLogging;
		$_SESSION["ODOErrorO"] = new ODOError;
		$_SESSION["ODOPageO"] = new ODOPage;
	}

	
	//verify user
	//if verify is on then verify...otherwise we only check the flag
	if(VERIFYUIDPERPAGE)
	{

		if(!$_SESSION["ODOUserO"]->VerifyUser())
		{
			trigger_error("ODOError: Verify Failed", E_USER_ERROR);
			//echo page to log back in.
		}
	}
	
	$query = "SELECT ODOImages.Name FROM ODOImages, GroupsForImages, ODOGroups, ODOUserGID WHERE ODOUserGID.UID=" . $_SESSION["ODOUserO"]->getUID() . " AND ODOUserGID.GID=ODOGroups.GID AND ODOGroups.GID=GroupsForImages.GID AND GroupsForImages.ImgID=" . $row["ImgID"];

	$result2 = $link->query($query) or trigger_error('Query failed at 103a: Query:' . $myquery . "Error:" . $link->error . E_USER_ERROR);

	if(mysqli_num_rows($result2) > 0) {
		header("Content-type: image/jpeg");
		echo $row["BImage"];
	} else {
		
		echo "<html><title>ODOWeb Access denied</title><body><center><H1>ACCESS DENIED</H1></center></body></html>";

	}


} else {
	header("Content-type: image/jpeg");
	echo $row["BImage"];
}

$link->close();

?>