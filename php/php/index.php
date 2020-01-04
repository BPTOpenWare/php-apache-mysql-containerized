<?php
include "class.php";
//Create ODODB object
global $ODODBO;
$ODODBO = new ODODB();

//establish connection
$ODODBO->Connect();

//to enable database stored sessions
//uncomment this include


//start session if not already started.
session_start();

//define default page to load
$DEFAULTPAGE = "Home";

//if not loaded before then load constants and system objects.
//the session object acts as a flag here.

if(!isset($_SESSION["ODOSessionO"]))
{
	//load system objects
	$_SESSION["ODOSessionO"] = new ODOSession();
	$_SESSION["ODOConstantsO"] = new ODOConstants();
	$_SESSION["ODOUserO"] = new ODOUser();
	$_SESSION["ODOLoggingO"] = new ODOLogging();
	$_SESSION["ODOErrorO"] = new ODOError();
	$_SESSION["ODOPageO"] = new ODOPage();
	$_SESSION["ODOUtil"] = new ODOUtil();
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

//the login system can log a user in from any page. The page itself though decides on if a guest
//is allowed to view a page or not. If a user is not logged in then they will be logged in as a guest
//by the system. A user created page must issue the request to the User object to log a user in. Alternative 
//methods are to use the seperate login page.
if(!$_SESSION["ODOUserO"]->getLoggedIn())
{
	//we are going to try and log the user in as guest. 
	//if the guest userid is not in the system then we 
	//will have to produce the login page
	//This is a potential area where a developer may want a custom
	//login page but can not get one. 

	if(!$_SESSION["ODOUserO"]->LoginGuest())
	{
		//replace me with your specific login page code
		//this will only be called if guest userid is removed
		//default is we fail and simply let the script throw an
		//access violation	
	}
}

if((!isset($_POST["pg"])) && (!isset($_GET["pg"]))) {
	$_POST["pg"]=$DEFAULTPAGE;
}

//execute
$_SESSION["ODOSessionO"]->OpenPage();
$ODODBO->CloseDBCon();

?>