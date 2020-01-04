<?php

include "../class.php";
//Create ODODB object
$ODODBO = new ODODB;

//establish connection
$ODODBO->Connect();

//start session if not already started.
session_start();

//define default page to load
$DEFAULTPAGE = "Home";

//if not loaded before then load constants and system objects.
//the session object acts as a flag here.

if(!isset($_SESSION["ODOSessionO"]))
{
	//load system objects
	$_SESSION["ODOSessionO"] = new ODOSession;
	$_SESSION["ODOConstantsO"] = new ODOConstants;
	$_SESSION["ODOUserO"] = new ODOUser;
	$_SESSION["ODOLoggingO"] = new ODOLogging;
	$_SESSION["ODOErrorO"] = new ODOError;
	$_SESSION["ODOPageO"] = new ODOPage;
	$_SESSION["ODOUtil"] = new ODOUtil;
}


if((!isset($_POST["pg"])) && (!isset($_GET["pg"]))) {
	$_POST["pg"]=$DEFAULTPAGE;
}


$_SESSION["ODOSessionO"]->ConvertPostGetVars(true);

//Random String generation for temp password
//Found on PHP.net http://www.php.net/manual/en/function.srand.php
//Random String generation for temp password
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



function SelfPasswordResetPublic() {
	//this function allows a user to reset a password if they have completed the
	//question answer section.

	//after checks show form

	if((isset($GLOBALS['globalref'][0]->EscapedVars["ResetA"])) && (isset($GLOBALS['globalref'][0]->EscapedVars["UNAME"]))) {
		//check reset Answer and do a random reset sending e-mail to user e-mail addy
			
		$query = "select * from ODOUsers where user='" . $GLOBALS['globalref'][0]->EscapedVars["UNAME"] . "'";
		$result = $GLOBALS['globalref'][1]->Query($query);

		$ResetQ = 0;
		$ResetA = "";
		$email = "";
		$mode = 3;
		$IsLocked = 1;
		$intTries = 100;

		if(mysqli_num_rows($result)>0) {
				
			$row = mysqli_fetch_assoc($result);

			$ResetQ = $row["ResetQID"];
			$mode = $row["HashedMode"];
			$IsLocked = $row["IsLocked"];
			$intTries = $row["FailedLogins"];

			if($row["IsEncrypted"] == 1) {
				if($GLOBALS['globalref'][7]->isMcryptAvailable()) {

					$encData = new EncryptedData();

					$encData->iv = $row["EncIV"];

					$encData->encArray["emailadd"] = $row["emailadd"];
					$encData->encArray["rnameLast"] = $row["rnameLast"];
					$encData->encArray["rnameFirst"] = $row["rnameFirst"];

					$encData = $GLOBALS['globalref'][7]->ODODecrypt($encData);

					if((isset($row["ResetA"]))&&(!is_null($row["ResetA"]))) {
						$ResetA = $row["ResetA"]; //hashed
					}

					$email = $encData->decArray["emailadd"];

				} else {

					$email = "";
					$ResetQ = "";
					$ResetA = "";
					$GLOBALS['globalref'][4]->LogEvent("DECRYPTERROR", "User info for " . $UID . " is encrypted but mcrypt is not available on server.", 3);
						
				}
			} else {

				$email = $row["emailadd"];
				$ResetA = $row["ResetA"];
					
			}
		}
			
		//include check to see if e-mail, ResetQ, and ResetA are not null

		if((strlen($email) < 5)||($ResetQ==0)||(strlen($ResetA)<1)) {
			$PageOut = "<html><head><title>" . SERVERNAME . " Error</title>";
			$PageOut .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<meta name=\"description\" content=\"ODOMuse is a live song linking system\">\n";
			$PageOut .= "<meta name=\"keywords\" content=\"Online Music, Live song tagging, ODOMuse, ODOMuse Live\">\n<meta name=\"author\" content=\"Bluff Point Technologies LLC\">\n";
			$PageOut .= "<meta charset=\"UTF-8\">\n<link rel='stylesheet' type='text/css' href='css/OdoMuse.min.css'>\n<link rel='stylesheet' type='text/css' href='css/jquery.mobile.icons.min.css'>\n";
			$PageOut .= "<link rel=\"stylesheet\" href=\"http://code.jquery.com/mobile/1.4.5/jquery.mobile.structure-1.4.5.min.css\" />\n<script src=\"http://code.jquery.com/jquery-1.11.1.min.js\"></script>\n<script src=\"http://code.jquery.com/mobile/1.4.5/jquery.mobile-1.4.5.min.js\"></script>";

			$PageOut .= "<script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"index.php?pg=";
			$PageOut = $PageOut . $GLOBALS['globalref'][0]->pg . "\"\n\n\n}\n//-->\n</script></head><body onLoad=\"setTimeout('delayer()', 5000)\">\n";
			$PageOut .= "<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">\n";
			$PageOut= $PageOut . "<div id=\"loginerror\" class=\"loginerror\">You have not setup your password reset question, reset answer, or e-mail address! Please Contact System Admin to reset your password.</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

			echo $PageOut;
			return false;
				
		}

		if($_SESSION["ODOUserO"]->ODOHash($GLOBALS['globalref'][0]->EscapedVars["ResetA"], $mode) == $ResetA)
		{
			//we have a valid responce so reset and send password

			$temppass = randomstring(10);
			$tempemail = $email;

			$query = "UPDATE ODOUsers SET pass='" . $GLOBALS['globalref'][1]->EscapeMe($GLOBALS['globalref'][3]->ODOHash($temppass, $_SESSION["ODOUserO"]->GetHashMode())) . "', Pistemp=1, IsLocked=0, FailedLogins=0 where user='" . $GLOBALS['globalref'][0]->EscapedVars["UNAME"] . "'";

			$result = $GLOBALS['globalref'][1]->Query($query);

			$headers  = 'MIME-Version: 1.0' . "\n";
			$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\n";
			$headers .= 'From: ' . REGREPLYEMAIL . "\n";
			$headers .= 'Reply-To:  ' . REGREPLYEMAIL . "\n";
			$headers .= 'X-Mailer: ODOWeb' . "\n";

			$message = "There was a password reset requested for " . SERVERNAME . ". at time=" . date(DATE_RFC822) . " from address=" . $_SERVER['REMOTE_ADDR'] . ". Please use the password found below to log back into your account. \n \n Password: " . $temppass . "\n\n";

			mail($tempemail, "Password reset on " . SERVERNAME, $message, $headers) or $GLOBALS['globalref'][4]->LogEvent("EMAILERROR", "Mail sending failed for password reset!", 4);

			$GLOBALS['globalref'][4]->LogEvent("FORGOTPASSWORDRESET", "Reseting of password using Q&A for username " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], 3);


			$PageOut = "<html><head><title>" . SERVERNAME . " Password Reset</title><link rel='stylesheet' type='text/css' href='css/global.css'><script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"login.php?pg=";
			$PageOut = $PageOut . $GLOBALS['globalref'][0]->pg . "\"\n\n\n}\n//-->\n</script></head><body onLoad=\"setTimeout('delayer()', 5000)\">\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">\n";
			$PageOut= $PageOut . "<div id=\"loginmessage\" class=\"loginmessage\">Success! You have successfully reset your password. An e-mail has been sent to the e-mail address you have recorded with your new temporary password. You will be prompted to change your password on your first login. Please wait to be redirected to the login page.</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

			echo $PageOut;

		} else {
			$PageOut = "<html><head><title>" . SERVERNAME . " Password Reset</title><link rel='stylesheet' type='text/css' href='css/global.css'><script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"login.php?pg=";
			$PageOut = $PageOut . $GLOBALS['globalref'][0]->pg . "\"\n\n\n}\n//-->\n</script></head><body onLoad=\"setTimeout('delayer()', 5000)\">\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">\n";
			$PageOut= $PageOut . "<div id=\"loginerror\" class=\"loginerror\">Access Denied! You have answered the question wrong. Please wait to be redirected to the login page.</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";


			//log event
			$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Invalid Answer/Question pair for userid: " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], WRONGPASSWORDSEVLEVEL);
				
			//Incr locked out count
			if(LOCKOUTONSELFPASSRESET) {

				$intTries = $intTries + 1;

				if($IsLocked == 0)
				{
						
					$query = "update ODOUsers SET FailedLogins=" . $intTries;
					//update IsLocked field
					if($intTries > NUMBEROFVALIDPWORDTRIES)
					{
							
						$query = $query . ", IsLocked=1";
					}

					$query = $query . " where UID=" . $row["UID"];
					$result = $GLOBALS['globalref'][1]->Query($query);

				}
			}
				

			echo $PageOut;

		}


	} else {

		$PageOut = "<html><Head><title>Self Password Reset</title><link rel='stylesheet' type='text/css' href='css/global.css'></head><body>\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">\n";

		//if no UNAME then prompt.
		if(isset($GLOBALS['globalref'][0]->EscapedVars["UNAME"])) {
			$query = "select ResetQ from ODOUsers, ResetQuestions where user='" . $GLOBALS['globalref'][0]->EscapedVars["UNAME"] . "' AND ODOUsers.ResetQID=ResetQuestions.ResetQID";
			$result = $GLOBALS['globalref'][1]->Query($query);

			$row = mysqli_fetch_assoc($result);

			if(mysqli_num_rows($result)>0) {
				$ResetQ = $row["ResetQ"];
					
				$PageOut = $PageOut . $_SESSION["ODOUserO"]->GetResetQuestionForm($GLOBALS['globalref'][0]->EscapedVars["UNAME"], $ResetQ);
					
				$PageOut = $PageOut . "</div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

			} else {
				$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Question for user not setup but user requested password reset:" . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], WRONGPASSWORDSEVLEVEL);

				$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">You have not setup your password reset question or answer! Please return to the home page <a href=\"index.php?pg=Home\">here</a>.</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";
			}

		} else {

			$PageOut = $PageOut . $_SESSION["ODOUserO"]->GetResetQuestionForm("", "") . "</div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

		}
			
		echo $PageOut;

	}

}


function ChangePassword() {

	if(!$_SESSION["ODOUserO"]->getLoggedIn())
	{
		return false;

	} else {
			
		if($_SESSION["ODOUserO"]->VerifyUser()) {

			if(isset($GLOBALS['globalref'][0]->EscapedVars["OldPword"]) && isset($GLOBALS['globalref'][0]->EscapedVars["NewPword"]) && isset($GLOBALS['globalref'][0]->EscapedVars["ConfirmPword"])) {

				//Confirm Old password
				if(($_SESSION["ODOUserO"]->getLoggedIn()) && (!$_SESSION["ODOUserO"]->getIsGuest())) {

					//load the refresh this page script
					$PageOut = "<html><head><title>" . SERVERNAME . " Login</title><link rel='stylesheet' type='text/css' href='css/global.css'><script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"index.php?pg=";

					$PageOut = $PageOut . $GLOBALS['globalref'][0]->EscapedVars["pg"] . "\"\n\n\n}\n//-->\n</script></head><body onLoad=\"setTimeout('delayer()', 5000)\">\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">\n";

					$query = "SELECT pass, HashedMode, IsLocked, FailedLogins, Pistemp FROM ODOUsers WHERE UID=" . $_SESSION["ODOUserO"]->getUID();

					$result = $GLOBALS['globalref'][1]->Query($query);
					if(!mysqli_num_rows($result)) {
						//do nothing just exit
						$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">ACCESS DENIED</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";
						//log event
						$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Invalid username: " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], WRONGUSERNAMESEVLEVEL);
							
						echo $PageOut;
						return;
					}

					$row = mysqli_fetch_assoc($result);

					$oldmode = $row["HashedMode"];
						
					$curmode =  $_SESSION["ODOUserO"]->GetHashMode() ;
					$pwd = $_SESSION["ODOUserO"]->ODOHash($GLOBALS['globalref'][0]->EscapedVars["OldPword"], $oldmode);
					$Locked = $row["IsLocked"];
					$FailedLogins = $row["FailedLogins"];


					if($pwd!=$row["pass"]) {
							

						$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">Invalid Old Password</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";
						echo $PageOut;
						return false;

					} else {

						//Confirm Matched Passwords
						if($GLOBALS['globalref'][0]->EscapedVars["NewPword"] != $GLOBALS['globalref'][0]->EscapedVars["ConfirmPword"]) {
							$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">Your new passwords do not match!</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

							echo $PageOut;
							return false;
						}

						if((strlen($GLOBALS['globalref'][0]->EscapedVars["NewPword"]) < 3) || (strlen($GLOBALS['globalref'][0]->EscapedVars["NewPword"]) > 12)) {
							$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">Your new password is either to short or to long. Please choose a password at least 4 characters long and no more than 12 characters.</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

							echo $PageOut;
							return false;

						}

						//update User Info and clear Temp flag
						$query = "update ODOUsers set Pistemp=0, FailedLogins=0, pass='" .  $GLOBALS['globalref'][1]->EscapeMe($_SESSION["ODOUserO"]->ODOHash($GLOBALS['globalref'][0]->EscapedVars["NewPword"], $curmode)) . "'";
							
						if($curmode != $oldmode) {
							$query = $query . ", HashedMode=" . $curmode;
						}
							
						$query = $query . " where UID=" . $_SESSION["ODOUserO"]->getUID();

						$result = $GLOBALS['globalref'][1]->Query($query);
						if($GLOBALS['globalref'][1]->GetNumRowsAffected() < 1) {
							trigger_error("Could not Reset Password!");
						} else {
							$PageOut = $PageOut . "<div id=\"loginmessage\" class=\"loginmessage\">Your password has been changed.</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

							echo $PageOut;
							return true;

						}
					}

				} else {

					//we shouldn't be here. Just return if we are
					return false;
				}
			} else {
					
				//else we return false or output form
				//how do we handle html segment output?
					
				$ReturnValue = "<html><head><title>" . SERVERNAME . " Reset Password</title><link rel='stylesheet' type='text/css' href='css/global.css'></head><body>\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">\n";
					

				$ReturnValue = $ReturnValue . $_SESSION["ODOUserO"]->GetChangePassForm() . "</div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

				echo $ReturnValue;

					
			}

		} else {

			return false;
		}

	}

}


//ObjectOnlyOutput is no longer an issue because this script will output html regardless
function Login() {
	//must check AllowUserAnyPageLogin before running

	//all we do is create form and wait for responce.
	//check if they are logged in first.

		

	if($_SESSION["ODOUserO"]->getLoggedIn())
	{
		//Log user off first

		$_SESSION["ODOUserO"]->Logout(0);

	}
		
	$tempPage = $_SESSION['ODOSessionO']->pg;

	if(defined("DEFAULTAFTERLOGINPAGE")) {
		$tempPage = DEFAULTAFTERLOGINPAGE;
	}

	//load the refresh this page script
	$PageOut = "<html><head><title>" . SERVERNAME . " Login</title><link rel='stylesheet' type='text/css' href='css/global.css'><script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"index.php?pg=";
	$PageOut = $PageOut . $tempPage . "\"\n\n\n}\n//-->\n</script></head><body onLoad=\"setTimeout('delayer()', 5000)\">\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">";

		

	if(isset($GLOBALS['globalref'][0]->EscapedVars["UNAME"]) && isset($GLOBALS['globalref'][0]->EscapedVars["PWORD"]))
	{
		//log user in first

		//old code
		$query = "select UID, HashedMode, IsEncrypted, IsLocked, FailedLogins, LastLogin, Pistemp, pass from ODOUsers where user='" . $GLOBALS['globalref'][0]->EscapedVars["UNAME"] . "'";

		$result = $GLOBALS['globalref'][1]->Query($query);
		if(!mysqli_num_rows($result))
		{
				
			//if false then we need to tell the user access denied.
			$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">ACCESS DENIED</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";
				
			//log event
			$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Invalid username " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], WRONGUSERNAMESEVLEVEL);
				
			echo $PageOut;
			return false;
		}
			

		$row = mysqli_fetch_assoc($result);
		$intTries = $row["FailedLogins"] + 1;
		$IsLocked = $row["IsLocked"];
		$oldmode = $row["HashedMode"];
		$UID = $row["UID"];
		$curmode = $_SESSION["ODOUserO"]->GetHashMode() ;
		$pwd = $_SESSION["ODOUserO"]->ODOHash($GLOBALS['globalref'][0]->EscapedVars["PWORD"], $oldmode);

		$Pistemp = $row["Pistemp"];

		//check if locked out already
		if($IsLocked == 1) {
			//if false then we need to tell the user access denied.
			$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">ACCESS DENIED ACCOUNT IS LOCKED OUT</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";
				
			//log event
			$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Account was locked out already for " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], LOGINAFTERACCTLOCKEDSEV);
				
			echo $PageOut;
			return false;
		}

		//check password
		if($pwd != $row["pass"]) {
			//passwords do not match update failed number of logins
				
			//log event
			$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Invalid Password for " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], WRONGPASSWORDSEVLEVEL);

			$query = "update ODOUsers SET FailedLogins=" . $intTries;
			//update IsLocked field
			if($intTries > NUMBEROFVALIDPWORDTRIES)
			{
				//log event
				$GLOBALS['globalref'][4]->LogEvent("ACCOUNTLOCKED", "Account locked out " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], 2);

				$query = $query . ", IsLocked=1";
			}

			$query = $query . " where UID=" . $UID;
			$result = $GLOBALS['globalref'][1]->Query($query);

			//Log user in as guest
			$_SESSION["ODOUserO"]->LoginGuest();
				
			//if false then we need to tell the user access denied.
			$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">ACCESS DENIED</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";
			echo $PageOut;
			return false;
				
		} else {

			$query = "insert into LoggedIn (UID,SessionID,IPAdd) values(" . $UID . ",'" . session_id() . "','" . $_SERVER['REMOTE_ADDR'] . "')";
			$result = $GLOBALS['globalref'][1]->Query($query);

			if(!($GLOBALS['globalref'][1]->GetNumRowsAffected() > 0))
			{
				//throw error
				trigger_error("Could not log user in!");

				$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">There was an error logging you in. Please contact the website owner.</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";
				echo $PageOut;


				return false;

			} else {

				//$this->UID = $row["UID"];
				//$this->LoggedIn = true;
				//$this->IsGuest = false;
				//$this->username = $row["user"];
				//$this->email = $row["emailadd"];
				//$this->rname = $row["rname"];

				//make call to verify login info and update User object
				if($_SESSION["ODOUserO"]->VerifyandLogin($UID)) {

					$PageOut = $PageOut . "<div id=\"loginmessage\" class=\"loginmessage\">You have been logged in. Please Wait.</div>\n</div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

					//check if password is temp. If it is we need to load the change password script. Not refresh the page
					if($Pistemp == 1) {
						$PageOut = "<html><head><title>" . SERVERNAME . " Login</title><link rel='stylesheet' type='text/css' href='css/global.css'><script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"login.php?pg=";

						$PageOut = $PageOut . $GLOBALS['globalref'][0]->pg . "&fn=ChangePassword\"\n\n\n}\n//-->\n</script></head><body onLoad=\"setTimeout('delayer()', 5000)\">\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">\n";
							
						$PageOut = $PageOut . "<div id=\"loginmessage\" class=\"loginmessage\">Warning! Your password is temporary. We are going to load the password change form. Please wait.</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";


					} else {

						//else update hasmode if needed
						if($oldmode != $curmode) {
							$pwd = $GLOBALS['globalref'][1]->EscapeMe($_SESSION["ODOUserO"]->ODOHash($GLOBALS['globalref'][0]->EscapedVars["PWORD"], $curmode));

								
							$query = "UPDATE ODOUsers SET HashedMode=" . $curmode . ", pass='" . $GLOBALS['globalref'][1]->EscapeMe($_SESSION["ODOUserO"]->ODOHash($GLOBALS['globalref'][0]->EscapedVars["PWORD"], $curmode)) . "' WHERE UID=" . $UID;
							$result = $GLOBALS['globalref'][1]->Query($query);
						}

					}
					//we output login event and refresh page.
					echo $PageOut;

					return true;
				} else {
					$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">ERROR. There was an error trying to log you in. Please contact the system administrator.</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

					$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Login Failed for userid: " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"] . ". Failed at session verification on login page.", WRONGPASSWORDSEVLEVEL);
						
					echo $PageOut;
					return false;
				}
			}
		}


	} else {
		//output form


		echo("<!DOCTYPE HTML><html><head><title>" . SERVERNAME . " Login</title><link rel='stylesheet' type='text/css' href='css/global.css'></head><body>\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">\n");

		echo $_SESSION["ODOUserO"]->GetLoginForm(true);
			
		echo("</div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>");


	}

}

//use a defined var so dblogin script can tell what redirect to use
if(!defined("loginphp")) {
	define("loginphp",1);
}

if(isset($GLOBALS['globalref'][0]->EscapedVars["fn"])) {
	if($GLOBALS['globalref'][0]->EscapedVars["fn"] == "ChangePassword") {

		ChangePassword();

	} elseif($GLOBALS['globalref'][0]->EscapedVars["fn"] == "SelfPasswordResetPublic") {

		SelfPasswordResetPublic();

	} else {
		Login();
	}

} else {

	Login();
}



?>