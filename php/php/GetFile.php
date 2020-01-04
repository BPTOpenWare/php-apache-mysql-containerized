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
require_once "enc.php";

if((!isset($_GET["FID"]))&&(!isset($_POST["FID"])) ) {
	header("HTTP/1.0 404 Not Found");
	echo("404 File Not Found!");
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



//ID flag is just used to tell if we have an ID.

$Rvalue = "";
if(isset($_GET["FID"])) {
	$Rvalue = $_GET["FID"];
} elseif($_POST["FID"]) {
	$Rvalue = $_POST["FID"];
}

if(get_magic_quotes_gpc()) {
	$Rvalue = stripslashes($Rvalue);
}

$Rvalue = $link->real_escape_string($Rvalue);
$Rvalue = intval($Rvalue);

$query = "SELECT FID, BFile, PermFlag, Type, Name, Size FROM ODOFiles, FileTypes WHERE ODOFiles.FTypeID=FileTypes.FTypeID AND ODOFiles.FID=" . $Rvalue;


$result = $link->query($query) or trigger_error('Query failed at 103a: Query:' . $myquery . "Error:" . $link->error, E_USER_ERROR);

if(!mysqli_num_rows($result)) {
	header("HTTP/1.0 404 Not Found");
	echo("404 File Not Found!");
	$link->close();
	exit(1);
}

$row = mysqli_fetch_assoc($result);

if($row["PermFlag"] == 1) {
	include "class.php";
	//Create ODODB object
	$ODODBO = new ODODB;

	//establish connection
	$ODODBO->Connect();
	session_start();

	if(!isset($_SESSION["ODOUserO"]))
	{

		$_SESSION["ODOUserO"] = new ODOUser;

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

	$query = "SELECT ODOFiles.Name FROM ODOFiles, GroupsForFiles, ODOGroups, ODOUserGID WHERE ODOUserGID.UID=" . $_SESSION["ODOUserO"]->getUID() . " AND ODOUserGID.GID=ODOGroups.GID AND ODOGroups.GID=GroupsForFiles.GID AND GroupsForFiles.FID=" . $row["FID"];

	$result2 = $link->query($query) or trigger_error('Query failed at 103a: Query:' . $myquery . "Error:" . $link->error, E_USER_ERROR);

	if(mysqli_num_rows($result2) > 0) {
		header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
		header('Content-Transfer-Encoding: binary');
		//header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . $row["Name"] . '";\n\n');
		//header("Content-Disposition: inline; filename=$name");
		header("Pragma: cache");
		header("Cache-Control: private");
		header('Content-Length: ' . $row["Size"]);
		header('Content-type: ' . $row["Type"]);


		print $row["BFile"];


	} else {

		echo "<html><title>ODOWeb Access denied</title><body><center><H1>ACCESS DENIED</H1></center></body></html>";

	}

} else {

	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	header('Content-Transfer-Encoding: binary');
	//header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $row["Name"] . '";\n\n');
	//header("Content-Disposition: inline; filename=$name");
	header("Pragma: cache");
	header("Cache-Control: private");
	header('Content-Length: ' . $row["Size"]);
	header('Content-type: ' . $row["Type"]);

	print $row["BFile"];

}

mysqli_close($link);

?>