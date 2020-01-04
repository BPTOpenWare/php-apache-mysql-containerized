<?PHP
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
require_once "enc.php";
require_once "sessionDBManager.php";
require_once 'genericUserObjects.php';

//globalref is defined here and assigned when called by ODOSession initialization. 
//Wakeup will need to reregister each object to this array as they will have 
//new addresses and references when a session is reinitialized. 
global $globalref;
$globalref = array();

class CodeNode {
	private $aryFunctions;
	private $ObjectID;
	private $ObjectName;


	function __construct($OID, $OName) {
		$this->ObjectID = $OID;
		$this->ObjectName = $OName;
		$this->aryFunctions = array();
	}

	function AddFunction($CodeID, $FName) {
		$this->aryFunctions[$CodeID]=$FName;
	}

	function GetFunctionName($CodeID) {
		if(isset($this->aryFunctions[$CodeID])) {
			
			return $this->aryFunctions[$CodeID];
		} else {
			
			return false;
		}
	}

	function GetCodeID($FNName) {
		
		foreach($this->aryFunctions as $FNID=>$Name) 
		{
		
			if($FNName == $Name) {
				return $FNID;
			}
		}

		return 0;
	}

	function GetObjectID() {
		return $this->ObjectID;
	}

	function GetObjectName() {
		return $this->ObjectName;
	}

}

class ODORegisterObject {
	
	private $RObjects;
	
	function __construct() {
		$this->RObjects = array();
	}

	function GetObjName($ObjID) {
	
		$Rvalue = "0";
		foreach($this->RObjects as $OBN=>$Object) 
		{
			if($Object->GetObjectID() == $ObjID) {
				$Rvalue = $OBN;
			}
		} 
		
		return $Rvalue;
	}

	function GetObjID($ObjectName) {
		if(isset($this->RObjects[$ObjectName])) {
			$TempCodeNode =& $this->RObjects[$ObjectName];
			return $TempCodeNode->GetObjectID();
		} else {
			return false;
		}
	}

	
	function GetFunctionID($ObjectName, $FnName) {
		if(isset($this->RObjects[$ObjectName])) {
			$TempCodeNode =& $this->RObjects[$ObjectName];
			

			if($TempCodeNode->GetCodeID($FnName) == 0) {
				return -1;
			}
		
			return $TempCodeNode->GetCodeID($FnName);

		} else {
			return false;
		}
	}

	function GetFunctionName($ObjectName, $FNID) {
		if(isset($this->RObjects[$ObjectName])) {
			$TempCodeNode =& $this->RObjects[$ObjectName];
			if($TempCodeNode->GetFunctionName($FNID) == 0) {
				return "";
			}
		
			return $TempCodeNode->GetFunctionName($FNID);

		} else {
			return "";
		}

	}

	function RegisterFunction($ObjectName, $ClassName, $FunctionName) {
		//we first search the database for className then function name
		//once we have the ObjID and CodeSegID we no longer need the Classname
		//We only need ObjID, ObjectName, FunctionName, and CodeSegID. All of
		//these values must be stored for later function calling. 
		//first build a query to search for class

		$query = "select ObjectNames.ObjID, ObjectNames.Name, Objects.CodeSegID, Objects.fnName from ObjectNames, Objects where ObjectNames.Name='" . $GLOBALS['globalref'][1]->EscapeMe($ClassName) . "' AND Objects.ObjID = ObjectNames.ObjID AND Objects.fnName = '" . $GLOBALS['globalref'][1]->EscapeMe($FunctionName) . "'";
		
		$result = $GLOBALS['globalref'][1]->Query($query);

		if(!mysqli_num_rows($result)) {
			return false;
			//Object is not defined
		} else {
			$row = mysqli_fetch_assoc($result);
			
			//there should be only one returned value. If there is more than 1 then there is an issue
			//with the database
			if(isset($this->RObjects[$ObjectName])) {
				
				$TempPlaceHolder =& $this->RObjects[$ObjectName];
			} else {
				
				$TempPlaceHolder = new CodeNode($row["ObjID"], $row["Name"]);
				$this->RObjects[$ObjectName] =& $TempPlaceHolder;
			}
			if(!($TempPlaceHolder->GetFunctionName($row["CodeSegID"]))) {
				$TempPlaceHolder->AddFunction($row["CodeSegID"], $row["fnName"]);
			}
			return true;
		}
	}

	function AddSystemObjectFunction($ObjectName, $ObID, $FunctionName, $CodeID) {
		
		//there should be only one returned value. If there is more than 1 then there is an issue
		//with the database
		

		if(isset($this->RObjects[$ObjectName])) {
			$TempPlaceHolder =& $this->RObjects[$ObjectName];
		} else {
			//No Class name is used as above
			$TempPlaceHolder = new CodeNode($ObID, $ObjectName);
			$this->RObjects[$ObjectName] =& $TempPlaceHolder;
		}
		
	
		if(!($TempPlaceHolder->GetFunctionName($CodeID))) {
			
			$TempPlaceHolder->AddFunction($CodeID, $FunctionName);
		}
	}
}

/**
 * Represents entire object
 * to be loaded entire page
 * @author nict
 *
 */
class DynamicObjectCode {

	var $header;
	var $allFunctions;
	var $footer;
	
}


/**
 * ODOSession object manages the session
 * using common routines for ODOWeb
 */
class ODOSession {

	var $pg;
	var $ob;
	private $ObjectName; //*wakeup?
	var $fn;
	private $FunctionName;
	var $ObjectOnlyOutput;
	var $EscapedVars;
	private $CommonPageNames; //*wakeup?
	private $RegisteredFunctions; //*wakeup?
	var $UserObjectArray;
	private $SerializedObjects; //*wakeup?
	public $onMobileDevice;
	public $overrideMobile;
	private $csrfTokenArray; //array of tokens for csrf protection
	private $sessionObjStartTime;
	private $logSessionTimeMessage;
	private $pageLoadedFromCache;
	private $cachedObjectCodePath;
	private $cachedPageCodePath;
	
	//SerializedObjects is used to store user classes that need to exist between sessions.
	//objects asigned to the Session array will not work when they are woken back up as the
	//classes are not defined yet during session start. unserialize must be called later

	function __construct() {
		
		$this->sessionObjStartTime = microtime(true);
		
		global $globalref;
        	$globalref[0] = &$this;
		$this->pg = -1;
		$this->ob = -1;
		$this->ObjectName = "";
		$this->fn = -1;
		$this->FunctionName = "";
		$this->ObjectOnlyOutput = 0;
		$this->EscapedVars = array();
		$this->CommonPageNames = array();
		$this->SerializedObjects = array();
		$this->UserObjectArray = array();
		$this->RegisteredFunctions = new ODORegisterObject();
		//We load up arrays for Function Object and CommonPageNames
		//this drastically reduces the number of MySQL queries
		$this->LoadCommonNameArrays();
		$this->LoadValidSystemCalls();
		
		//check user agent to set initial mobile param
		$useragent=$_SERVER['HTTP_USER_AGENT'];
		if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4))) {
			$this->onMobileDevice = true;
		}
		
		$this->overrideMobile = false;
		$this->pageLoadedFromCache = false;
		$this->cachedObjectCodePath = "";
		$this->cachedPageCodePath = "";
		$this->csrfTokenArray = array();
		$this->logSessionTimeMessage = "";
		
	}

	/**
	 * gets the registered object's name reference
	 * Registered objects are used to avoid creating duplicate
	 * objects stored in the session on multple pages this wraps
	 * session use that could be replaced with a database at a later date
	 * @param string $ObName
	 */
	public function GetObjectRef($ObName) {
		if(isset($this->UserObjectArray[$ObName])) {
			return ($this->UserObjectArray[$ObName]);
		} elseif(isset($this->SerializedObjects[$ObName])) {
			if($this->WakeupRegisteredObject($ObName)) {
				return ($this->UserObjectArray[$ObName]);
			} else {
				return NULL;
			}

		} else {
			return NULL;
		}

	}

	/**
	 * Registers a new function that a user
	 * can call directly through the system using a 
	 * post or get request
	 * @param string $ObjectName
	 * @param string $ClassName
	 * @param string $FunctionName
	 * @return boolean
	 */
	public function RegisterPublicFunction($ObjectName, $ClassName, $FunctionName) {
		if($this->RegisteredFunctions->RegisterFunction($ObjectName, $ClassName, $FunctionName)) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * System functions are loaded to allow user to 
	 * login/logout from any page. Negative object id numbers are used
	 * to avoid conflicting with dynamically loaded objects from the
	 * database
	 */
	private function LoadValidSystemCalls() {

		//system objects will be added to the List with Negative ID numbers
		$this->RegisteredFunctions->AddSystemObjectFunction("ODOUserO", -3, "ChangePasswordPublic", -31);
		$this->RegisteredFunctions->AddSystemObjectFunction("ODOUserO", -3, "LoginGuest", -32);
		$this->RegisteredFunctions->AddSystemObjectFunction("ODOUserO", -3, "LogoutPublic", -33);
		$this->RegisteredFunctions->AddSystemObjectFunction("ODOUserO", -3, "Login", -34);
		$this->RegisteredFunctions->AddSystemObjectFunction("ODOUserO", -3, "SelfPasswordResetPublic", -35);
	}

	/**
	 * build the array we compare against when checking if a page exists
	 */
	private function LoadCommonNameArrays() {
		//Load Common Page names first
		$query = "select PageID, PageName from ODOPages";
		$result = $GLOBALS['globalref'][1]->Query($query);

		while ($row = mysqli_fetch_assoc($result))
		{
			$this->CommonPageNames[$row["PageName"]] = $row["PageID"];

		}

	}

	/**
	 * Checks if a variable object name is registered
	 * @param string $ObjectName
	 */
	public function IsRegistered($ObjectName) {
		if(isset($this->UserObjectArray[$ObjectName])) {
			return true;
		} else {
			return false;
		}

	}

	/**
	 * registers an object in dynamical loaded pages
	 * @param string $ObjectName Object variable name to register
	 * @param unknown $Obj Object reference to register
	 */
	public function RegisterObject($ObjectName, &$Obj) {
		//only used for

		if(!isset($this->UserObjectArray[$ObjectName])) {
			$this->UserObjectArray[$ObjectName] = &$Obj;
		}
	}


	/**
	 * escapes all get and post passed variables into a common array
	 * @param boolean $loadmobilesite if true will trigger a redirect to mobile index
	 */
	public function ConvertPostGetVars($loadmobilesite = FALSE) {
		//we convert get vars then post vars so post vars override
		//redirect if loadmobilesite is false and onmobiledevice is true

		if((isset($_GET["overrideMobile"]))||(isset($_POST["overrideMobile"]))) {
			$this->overrideMobile = true;
		}
		
		if($this->onMobileDevice && !$this->overrideMobile && !$loadmobilesite) {
			header('Location: mobile/index.php');
			exit();
		} 
		
		
		$loadPage = -1;
		
		if(isset($_POST["pg"])) {
			$loadPage = $_POST["pg"];
			
		} else if(isset($_GET["pg"])) {
			$loadPage = $_GET["pg"];
		}
		
		if(is_numeric($loadPage)) {
			$this->pg = -1;
			foreach( $this->CommonPageNames as $key=>$value ) {
				if($value == $loadPage) {
					//check for mobile
					if(($loadmobilesite)&&(isset($this->CommonPageNames["m" . $key]))) {
						$this->pg = $this->CommonPageNames["m" . $key];
					} else {
						$this->pg = $loadPage;
					}
				}
			}
		} else {
			if(isset($this->CommonPageNames[$loadPage])) {
				if(($loadmobilesite)&&(isset($this->CommonPageNames["m" . $loadPage]))) {
						$this->pg = $this->CommonPageNames["m" . $loadPage];
				} else {
					$this->pg = $this->CommonPageNames[$loadPage];
				}
			} else {
				$this->pg = -1;
			}
		}

		if(isset($_GET["ob"])) {
			if(is_numeric($_GET["ob"])) {
				$this->ObjectName = $this->RegisteredFunctions->GetObjName($_GET["ob"]);
				if(!$this->ObjectName == "0") {
					$this->ob = $_GET["ob"];
				} else {
					$this->ObjectName = "";
					$this->ob = -1;
				}

			} else {
				$this->ob = $this->RegisteredFunctions->GetObjID($_GET["ob"]);
				if($this->ob == false) {
					$this->ObjectName = "";
					$this->ob = -1;
				} else {
					//else we have a valid obID
					$this->ObjectName = $this->EscapeMe($_GET["ob"]);
				}

			}
		}

		if(isset($_POST["ob"])) {
			if(is_numeric($_POST["ob"])) {
				$this->ObjectName = $this->RegisteredFunctions->GetObjName($_POST["ob"]);
				if(!$this->ObjectName == "0") {
					$this->ob = $_POST["ob"];
				} else {
					$this->ObjectName = "";
					$this->ob = -1;
				}

			} else {
				$this->ob = $this->RegisteredFunctions->GetObjID($_POST["ob"]);
				if($this->ob == false) {
					$this->ObjectName = "";
					$this->ob = -1;
				} else {
					//else we have a valid obID
					$this->ObjectName = $this->EscapeMe($_POST["ob"]);
				}

			}
		}

		if((isset($_GET["fn"])) && ($this->ob != -1)) {
			if(is_numeric($_GET["fn"])) {
				$this->FunctionName = $this->RegisteredFunctions->GetFunctionName($this->ObjectName, $_GET["fn"]);
				if($this->FunctionName != "") {
					$this->fn = $this->EscapeMe($_GET["fn"]);
				} else {
					$this->fn = -1;
				}
			} else {
				$this->fn = $this->RegisteredFunctions->GetFunctionID($this->ObjectName, $_GET["fn"]);
				if($this->fn != -1) {

					$this->FunctionName = $this->EscapeMe($_GET["fn"]);
				} else {
					$this->FunctionName = "";
				}
			}

		}

		if((isset($_POST["fn"])) && ($this->ob != -1)) {
			if(is_numeric($_POST["fn"])) {
				$this->FunctionName = $this->RegisteredFunctions->GetFunctionName($this->ObjectName, $_POST["fn"]);
				if($this->FunctionName != "") {
					$this->fn = $this->EscapeMe($_POST["fn"]);
				} else {
					$this->fn = -1;
				}
			} else {
				$this->fn = $this->RegisteredFunctions->GetFunctionID($this->ObjectName, $_POST["fn"]);
				if($this->fn != -1) {
					$this->FunctionName = $this->EscapeMe($_POST["fn"]);
				} else {
					$this->FunctionName = "";
				}
			}

		}


		$this->ObjectOnlyOutput = false;
		if((isset($_GET["ObjectOnlyOutput"]))&&($_GET["ObjectOnlyOutput"] == "1")) {

			$this->ObjectOnlyOutput = true;
		}

		if((isset($_POST["ObjectOnlyOutput"]))&&($_POST["ObjectOnlyOutput"] == "1")) {
			$this->ObjectOnlyOutput = true;
		}

		//now escape the entire array
		//we overwrite Get vars with Post vars
		foreach($_GET as $Var=>$Value) {
			if(!is_array($Value)) {
				$this->EscapedVars[$this->EscapeMe($Var)] = $this->EscapeMe($Value);
			}
		}

		foreach($_POST as $Var=>$Value) {
			if(!is_array($Value)) {
				$this->EscapedVars[$this->EscapeMe($Var)] = $this->EscapeMe($Value);
			}
		}
		
		//verify csrf
		$this->verifyCSRFTokenInRequest();
	}

	/**
	 * simply calls the ododbo escapeme function
	 * @param unknown $Variable variable to be escaped
	 * @return unknown escaped variable
	 */
	private function EscapeMe($Variable) {
		$Rvalue = "";

		$Rvalue = $GLOBALS['globalref'][1]->EscapeMe($Variable);

		return $Rvalue;
	}

	/**
	 * directly calls the serialize/unserialize for registered objects
	 * @param String $Name object name
	 */
	private function WakeupRegisteredObject($Name) {
		if(!isset($_SESSION["ODOSessionO"]->UserObjectArray[$Name])) {
			$_SESSION["ODOSessionO"]->UserObjectArray[$Name] = unserialize($_SESSION["ODOSessionO"]->SerializedObjects[$Name]);

			if(GLOBALUSEROBJECTS) {
				if(!isset($GLOBALS[$Name])) {
					$GLOBALS[$Name] =& $_SESSION["ODOSessionO"]->UserObjectArray[$Name];
				}

			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * builds the serialize and unserialize script for the dynamic page
	 * @param string $Name
	 * @return string
	 */
	private function BuildWakeupObjectScript($Name) {

		$Script = "";
		if(isset($this->SerializedObjects[$Name])) {
			
			if($this->pageLoadedFromCache) {
				if(!isset($this->UserObjectArray["$Name"])) { 
					$this->UserObjectArray["$Name"] = unserialize($this->SerializedObjects["$Name"]);
				}
				
				if(GLOBALUSEROBJECTS) {
					
					if(!isset($GLOBALS["$Name"])) { 
						$GLOBALS["$Name"] =& $_SESSION["ODOSessionO"]->UserObjectArray["$Name"];
					}	
				}
				
			} else {
				
				$Script = "if(!isset(\$_SESSION[\"ODOSessionO\"]->UserObjectArray[\"$Name\"])) { \n \$_SESSION[\"ODOSessionO\"]->UserObjectArray[\"$Name\"] = unserialize(\$_SESSION[\"ODOSessionO\"]->SerializedObjects[\"$Name\"]);\n}\n";
				
				if(GLOBALUSEROBJECTS) {
					$Script = $Script . "if(!isset(\$GLOBALS[\"$Name\"])) { \n \$GLOBALS[\"$Name\"] =& \$_SESSION[\"ODOSessionO\"]->UserObjectArray[\"$Name\"];\n}\n";
				
				}
				
			}
		}

		return $Script;
	}


	/**
	 * this function will output a string for evaluation. It will reinitialize all objects
	 * this code is to only be placed after the class definitions and before page code.		
	 */
	private function BuildWakeupAllObjectsScript() {
		
		$Script = "";

		foreach($this->SerializedObjects as $Name=>$Obj)
		{
			$Script = $Script . $this->BuildWakeupObjectScript($Name);

		}


		return $Script;
	}

	/**
	 * Some reverse proxies allow for a cookie to 
	 * indicate the instance id the reverse proxy should
	 * always be redirected back to. This can preserve 
	 * session data and simplify the application.
	 */
	private function SetStickySessionCookie() {
		
		if((defined('USESTICKYSESSION'))&& (USESTICKYSESSION == 1)) {
			
			if((defined('STICKYSESSIONHEADER')) && (defined('STICKYSESSIONCOOKIE')) && 
					(strlen(STICKYSESSIONHEADER)>0) && (isset($_SERVER['HTTP_' . STICKYSESSIONHEADER]))) {
				$instanceID = $_SERVER['HTTP_' . STICKYSESSIONHEADER];
				
				//set the cookie for the instance id
				setcookie(STICKYSESSIONCOOKIE, $instanceID);
			}
			
		}
		
	}
	
	/**
	 * Builds the call user func call
	 * for when object and function is passed into request
	 */
	private function BuildCallUserFuncScript() {
		
		$ScriptToRun = "";
		
		if($this->pageLoadedFromCache) {
			
			if($this->ob < -1) {
				call_user_func(array($_SESSION[$this->ObjectName],$this->FunctionName));
			} elseif($GLOBALS['globalref'][6]->IsDynamic) {
				//we only want to call a user defind function
				//if the page is dynamic
				call_user_func(array($_SESSION["ODOSessionO"]->UserObjectArray[$this->ObjectName],$this->FunctionName));
			}
				
		} else {
			
			if($this->ob < -1) {
				
				//if we have a system call and
				//the page is not dynamic then we need to call it now
				if($GLOBALS['globalref'][6]->IsDynamic) {
					
					$ScriptToRun .= "\n call_user_func(array(\$_SESSION[\"" . $this->ObjectName . "\"],'" . $this->FunctionName . "'));\n\n";
						
				} else {
					call_user_func(array($_SESSION[$this->ObjectName],$this->FunctionName));
				}
				
			} elseif($GLOBALS['globalref'][6]->IsDynamic) {
				//we only want to call a user defind function
				//if the page is dynamic
				$ScriptToRun .= "\n call_user_func(array(\$_SESSION[\"ODOSessionO\"]->UserObjectArray[\"" . $this->ObjectName . "\"],'" . $this->FunctionName . "'));\n\n";
			}	
		
		}
		
		return $ScriptToRun;
	}
	
	/**
	 * Call to open th page and load all objects for it
	 * @param boolean $mobile 
	 */
	public function OpenPage($mobile = FALSE) {
		
		$this->SetStickySessionCookie();
		
		$this->ConvertPostGetVars($mobile);

		//if user does not have access output generic access denied message
		if(!$this->CheckForRightsLoadPage())
		{
			//else notify user.
			echo "<html><title>" . SERVERNAME . " Access denied</title><link rel='stylesheet' type='text/css' href='css/global.css'><body>\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">\n<div id=\"loginerror\" class=\"loginerror\">ACCESS DENIED</div></div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";
			
			return;
		} 

		if($GLOBALS['globalref'][6]->IsDynamic) {
			$callEval = true;
		} else {
			$callEval = false;
		}
		
		if($this->pageLoadedFromCache) {
			$callEval = false;
		}
		
		$GLOBALS['globalref'][6]->ScriptToRun = "";
		
		//load object code first
		if($callEval) {
			$GLOBALS['globalref'][6]->ScriptToRun = $GLOBALS['globalref'][6]->ObjectCode;
		} elseif(($GLOBALS['globalref'][6]->IsDynamic)&&(is_file($this->cachedObjectCodePath))) {
			//else include the cached version of object code
			include $this->cachedObjectCodePath;
		}
		
		//build wakeup deserialize script next
		if($GLOBALS['globalref'][6]->IsDynamic) {
			$GLOBALS['globalref'][6]->ScriptToRun .= $this->BuildWakeupAllObjectsScript();
		}
		
		//make call to user function if call requested
		if(($this->ObjectName != "") && ($this->FunctionName != ""))
		{
			$GLOBALS['globalref'][6]->ScriptToRun .= $this->BuildCallUserFuncScript();
		}
		
		//check if object only output and add content to output.
		if((!$this->ObjectOnlyOutput)&&($callEval)) {
			
			$GLOBALS['globalref'][6]->ScriptToRun .= $GLOBALS['globalref'][6]->Content;
			
		}
		
		if(($callEval)&&(defined('LOGSESSIONTIME'))&&(LOGSESSIONTIME == 1)) {
			$beforeEval = microtime(true);
			$totalTime = $beforeEval - $this->sessionObjStartTime;
				
			$this->logSessionTimeMessage = "Time before eval of page:{$totalTime} ";
		}
		
		//call eval if needed
		if($callEval) {
			
			try {
				$ScriptToRun = $GLOBALS['globalref'][6]->ScriptToRun;
				$evalReturn = eval($ScriptToRun);
			} catch(ParseError $perror) {
				$evalReturn = false;
				$GLOBALS['globalref'][4]->LogEvent("PARSEERROR", "Line:" . $perror->getLine() . ":" . $perror->getMessage() , 1);
				
			}
			
		} else if(($this->pageLoadedFromCache) && (!$this->ObjectOnlyOutput)) {
			
			if($GLOBALS['globalref'][6]->IsDynamic) {
				include $this->cachedPageCodePath;
			} else {
				//read in the page and echo it
				$cachedPage = file_get_contents($this->cachedPageCodePath);
				echo $cachedPage;
			}
		} else if((!$this->pageLoadedFromCache) && (!$this->ObjectOnlyOutput)) {
			
			echo $GLOBALS['globalref'][6]->Content;
		}
		
		
		if(($callEval)&&(defined('LOGSESSIONTIME'))&&(LOGSESSIONTIME == 1)) {
			$afterEval = microtime(true);
			$totalTime = $afterEval - $this->sessionObjStartTime;
			$evalTime = $afterEval - $beforeEval;
				
		
			$this->logSessionTimeMessage .= "Time after eval of page:{$totalTime} Total Eval time:{$evalTime}";
				
		}
		
		if(($callEval) && (!is_null($evalReturn)) && (!$evalReturn) && (DUMPEVALERROR)) {
		
			$filename = DUMPEVALDIR;
			$filename .= date('Ymd-His');
			$filename .= "_";
			$filename .= $GLOBALS['globalref'][3]->getusername();
			$filename .= ".txt";
			
			
			file_put_contents($filename, $GLOBALS['globalref'][6]->ScriptToRun);
		}
		
		//build the cache
		if((defined('CACHEPAGES')) && (CACHEPAGES == 1) && (!$this->pageLoadedFromCache)) {
			$this->BuildCache();
		}
		
		
	}

	/**
	 * Checks for rights by page and loads the
	 * page either from Cache or from DB
	 */
	private function CheckForRightsLoadPage() {

		$this->pageLoadedFromCache = false;
		$this->cachedObjectCodePath = "";
		$this->cachedPageCodePath = "";
		
		if(defined('CACHEPAGES') && CACHEPAGES == 1) {
				
			if($this->CheckForCachedPage()) {
				$this->pageLoadedFromCache = true;	
				
			}
		}
		
		if(!$this->pageLoadedFromCache) {
			
			return $this->LoadPageFromDB();
		}
		
		return true;
	}
	

	/**
	 * Pages are stored on the local filesystem
	 * with pageid+allgroupids.php
	 */
	private function CheckForCachedPage() {
		
		//build key
		$gidKey = $GLOBALS['globalref'][3]->GetUserGroupIDArray();
		$pathBeg = OURCACHEPATH . $this->pg . $gidKey;
		$staticPath = $pathBeg . ".html";
		$dynamicPath = $pathBeg . ".php";
		$dynamicObjPath = $pathBeg . "_OB.php";	
		//if we find the cached page
		//check if page is dynamic and if it is check if we found
		//the dynamic cached object code for the page
		if((is_file($staticPath))) {
			
			$this->cachedPageCodePath = $staticPath;
			$this->cachedObjectCodePath = "";
			
			return true;
			
		} elseif((is_file($dynamicPath))) {
			
			$GLOBALS['globalref'][6]->IsDynamic = true;
			$this->cachedPageCodePath = $dynamicPath;
			
			if(is_file($dynamicObjPath)) {
				$this->cachedObjectCodePath = $dynamicObjPath;
			}
			
			return true;
		}
		
		return false;
		
	}
	
	/**
	 * Creates a cached version of the page using all the group ids
	 * the user is a part of and the page id
	 * @param String $PageScript
	 */
	private function BuildCache() {
		
		//build key
		//output page first
		$gidKey = $GLOBALS['globalref'][3]->GetUserGroupIDArray();
		$ourKey = $this->pg . $gidKey;
		
		if($GLOBALS['globalref'][6]->IsDynamic) {
			$cachePath = OURCACHEPATH . $ourKey . ".php";
		} else {
			$cachePath = OURCACHEPATH . $ourKey . ".html";
		}
		
		if(!is_file($cachePath)) {
			$handle = fopen($cachePath, "x");
		
			if($GLOBALS['globalref'][6]->IsDynamic) {
				$PageScript = "<?PHP\n\n{$GLOBALS['globalref'][6]->Content}\n\n?>";
			} else {
				$PageScript = $GLOBALS['globalref'][6]->Content;
			}
		
			fwrite($handle, $PageScript);
		
			fclose($handle);
		
			//log creation of the cached file
			$Comment = "Created cached page for page id:{$this->pg} filename:{$cachePath}";
		
			$GLOBALS['globalref'][4]->LogEvent("CACHEDPAGE", $Comment, 5);
		
			//next output objects
			if($GLOBALS['globalref'][6]->IsDynamic) {
				$ourKey = $this->pg . $gidKey . "_OB.php";
		
				$cachePath = OURCACHEPATH . $ourKey;
	
				if(!is_file($cachePath)) {
					$handle = fopen($cachePath, "x");
		
					$PageScript = "<?PHP\n\n{$GLOBALS['globalref'][6]->ObjectCode}\n\n?>";
		
					fwrite($handle, $PageScript);
		
					fclose($handle);
			
					//	log creation of the cached file
					$Comment = "Created cached page for objects of page id:{$this->pg} filename:{$cachePath}";
			
					$GLOBALS['globalref'][4]->LogEvent("CACHEDOBJECTS", $Comment, 5);
				}
			}
			
		}
		
	}
	
	private function LoadPageFromDB() {

		//check group permission
		if((!isset($this->pg)) || ($this->pg == -1)) {
			header("HTTP/1.0 404 Not Found");
				
			$this->errorBadRequest("404", "Page Not Found!");
			//exit
		}
		
		$query = "select ODOPages.PageContent, ODOPages.IsDynamic, ODOPages.IsAdmin, ODOPages.ModID, Modules.ModName, ODOGACL.GID, ODOUserGID.UID from ODOPages, Modules, ODOGACL, ODOUserGID where ( ( ODOPages.PageID = ";
		
		$query = $query . $this->pg . " ) AND (ODOPages.ModID = Modules.ModID) AND ( ODOPages.PageID = ODOGACL.PageID ) AND ( ODOGACL.GID = ODOUserGID.GID ) AND ( ODOUserGID.UID = ";
		
		$query = $query . $GLOBALS['globalref'][3]->getUID() . ") )";
		
		$result = $GLOBALS['globalref'][1]->Query($query);
		
		if(!mysqli_num_rows($result))
		{
			$Comment = "User has tried to access a page they do not have access rights to.";
			$GLOBALS['globalref'][4]->LogEvent("ACCESSDENIED", $Comment, 5);
			return false;
		}
		
		//if we are here then we need to load PageContent and Page info
		$row = mysqli_fetch_assoc($result);
		$GLOBALS['globalref'][6]->Content = $row["PageContent"];
		$GLOBALS['globalref'][6]->PageID = $this->pg;
		$GLOBALS['globalref'][6]->IsDynamic = $row["IsDynamic"];
		$GLOBALS['globalref'][6]->IsAdmin = $row["IsAdmin"];
		$GLOBALS['globalref'][6]->ModuleID = $row["ModID"];
		$GLOBALS['globalref'][6]->ModuleName = $row["ModName"];
		
		//check object permission
		if($row["IsDynamic"]) {
		
			if(!$this->LoadObjectsforPage()) {
				return false;
			}
		}
		
		return true;
	}
	
	
	///////////////////////////////////////////////
	//Name: LoadObjectsforPage
	//Desc: This function will load all objects for a given page
	//While module objects can be asigned to a page in the CMS
	//we do not load objects based on modules. We only load objects
	//based on object rights.
	//There are only two types of objects in a page. Admin and User
	//If an object is an Admin object but the page is not flagged as
	//an admin page then we need to check if the flag is set to allow
	//Admin objects loaded onto non admin pages.
	//
	private function LoadObjectsforPage() {

		//ModArray is flaged with Module constants to load.  We will load the constants last
		$ModArray = array();

		//check if Objects are even in page and check for Admin Flag
		if($this->ob > -1)
		{
			$query = "select ODOPageObject.ObjID, ODOPageObject.PageID, ObjectNames.IsAdmin FROM ODOPageObject, ObjectNames WHERE ODOPageObject.ObjID = " . $this->ob . " AND ODOPageObject.PageID = " . $this->pg;

			if((!ADMINOBJECTSINNONADMINPAGES) && ($GLOBALS['globalref'][6]->IsAdmin)) {
				//check if this is an admin page

				$query = $query . " AND ObjectNames.IsAdmin = 0";

			}

			$result = $GLOBALS['globalref'][1]->Query($query);

			if(!mysqli_num_rows($result))
			{
				$Comment = "User has tried to access an object that is NOT assigned to page.";
				$GLOBALS['globalref'][4]->LogEvent("ACCESSDENIED", $Comment, 5);
				return false;
			}
		}

		//next Load Objects based on User Rights
		$query = "select * FROM ODOPageObject, ObjectNames WHERE ODOPageObject.PageID = " . $this->pg . " AND ODOPageObject.ObjID = ObjectNames.ObjID";

		$result = $GLOBALS['globalref'][1]->Query($query);

		//to be tacked onto top of page content


		if(!mysqli_num_rows($result))
		{
			//no logging done. No objects assigned to page
			return true;
		} else {


				//next Load Object code
				$query2 = "select Objects.ObjID, Objects.CodeSegID, Objects.fnName, Objects.Code, Objects.IsHeader, Objects.IsFooter, ANY_VALUE(ODOObjectACL.GID), ODOUserGID.UID from Objects, ODOObjectACL, ODOUserGID WHERE ((ODOUserGID.UID = " . $GLOBALS['globalref'][3]->getUID() . ") AND (ODOUserGID.GID = ODOObjectACL.GID) AND (ODOObjectACL.CodeSegID = Objects.CodeSegID) AND (";
				
				$count = 0;
				$codeArray = array();
				
				//else we need to load up objects user has access rights to
				while ($row = mysqli_fetch_assoc($result)) {
				
					//first check off ModID
					$ModArray[$row["ModID"]] = 1;
						
					if($count > 0) {
						$query2 .= " OR ";
					} 
					
					$query2 .= "Objects.ObjID={$row["ObjID"]}";
					
					$count++;
				}

				$query2 .= ")) Group By CodeSegID";
				
				$result2 = $GLOBALS['globalref'][1]->Query($query2);

				if(mysqli_num_rows($result2) > 0) {
					
					$NewCodeSegment = "";
					$CodeHeader = "";
					$CodeFooter = "";


					while ($row2 = mysqli_fetch_assoc($result2)) {
						
						if(!isset($codeArray[$row2["ObjID"]])) {
							
							$codeArray[$row2["ObjID"]] = new DynamicObjectCode();
							
						}
						
						if($row2["IsHeader"]) {
							
							$codeArray[$row2["ObjID"]]->header = $row2["Code"];
							
						} elseif($row2["IsFooter"]) {
								
							$codeArray[$row2["ObjID"]]->footer = $row2["Code"];
							
						} else {

							$codeArray[$row2["ObjID"]]->allFunctions = $codeArray[$row2["ObjID"]]->allFunctions . $row2["Code"];
						}

					}

					foreach($codeArray as $Key=>$Object) {
						
						$GLOBALS['globalref'][6]->ObjectCode = $Object->header . $Object->allFunctions . $Object->footer . $GLOBALS['globalref'][6]->ObjectCode;
							
					}

					
				} 
				

			//Load Constants
			foreach($ModArray as $ModID=>$value)
			{
				//we load modID=1 by default at startup.
				if($ModID != 1) {
					$GLOBALS['globalref'][2]->LoadModuleConstantsModID($ModID);
				}

			}

			return true;
		}
	}

	/**
	 * Simple function to generate a CSRF token
	 * The token is stored in the session and can
	 * be referenced and block a request before loading
	 * a dynamic page o
	 * @param boolean $BlockLoadingPage
	 * @return string token
	 */
	public function generateCSRFToken($blockLoadingPage, $pageName, $objectName, $fnName) {
		
		$ourKey = $this->buildCSRFTokenKey($pageName, $objectName, $fnName);
		
		$ourPageCSRFArray = array();
		
		if(isset($this->csrfTokenArray[$ourKey])) {
			$ourPageCSRFArray = $this->csrfTokenArray[$ourKey];
		} 
		
		$csrf = $GLOBALS['globalref'][7]->randomstring(75);
		
		$ourPageCSRFArray[$csrf] = $blockLoadingPage;
		
		$this->csrfTokenArray[$ourKey] = $ourPageCSRFArray;
		
		return $csrf;
	}
	
	/**
	 * Checks for any CSRFToken with the given key type
	 * Clears all tokens if it exists.
	 * @param unknown $clearIfFound
	 * @param unknown $pageName
	 * @param unknown $objectName
	 * @param unknown $fnName
	 */
	public function checkForAnyCSRFToken($clearIfFound, $pageName, $objectName, $fnName) {
		$this->checkCSRFToken(null, $clearIfFound, $pageName, $objectName, $fnName, true);
		
	}
	
	/**
	 * Checks if a CSRF token is found in the array
	 * and clears it if the flag is set
	 * @param boolean $clearIfFound
	 * @return boolean true if found otherwise false
	 */
	public function checkCSRFToken($checkingCsrf, $clearIfFound, $pageName, $objectName, $fnName, $checkAll=FALSE) {
		
		$ourKey = $this->buildCSRFTokenKey($pageName, $objectName, $fnName);
		
		if(isset($this->csrfTokenArray[$ourKey])) {
			
			$ourIndex = 0;
			$ourCSRFArray =& $this->csrfTokenArray[$ourKey];
			
			foreach($ourCSRFArray as $csrf=>$blocked) {
				
				if($csrf == $checkingCsrf || $checkAll) {
					
					if($clearIfFound) {
						array_splice($ourCSRFArray,$ourIndex);
					}
					
					return true;
				}
				
				$ourIndex++;
			}
		}
		
		return false;
	}
	
	/**
	 * Called during page load. If csrf tokens exist
	 * in the array it is assumed the next request to
	 * a page or an object function call must
	 * contain the token
	 */
	private function verifyCSRFTokenInRequest() {
		
		$checkingcsrf = null;
		
		if(isset($_POST["odowebcsrf"])) {
			$checkingcsrf = $_POST["odowebcsrf"];
		} else if(isset($_GET["odowebcsrf"])) {
			$checkingcsrf = $_GET["odowebcsrf"];
		}
		
		$ourKey = $this->buildCSRFTokenKey($this->pg, $this->ObjectName, $this->FunctionName);
		$ourAllPagesKey = $this->buildCSRFTokenKey(null, $this->ObjectName, $this->FunctionName);
		
 		if((isset($this->csrfTokenArray[$ourKey]))&&(count($this->csrfTokenArray[$ourKey])>0)) {
			
 			$ourCsrfArray =& $this->csrfTokenArray[$ourKey];
			
			if(($checkingcsrf != null) && (isset($ourCsrfArray[$checkingcsrf]))) {
				
				//delete it
				foreach($ourCsrfArray as $csrf=>$blocked) {
				
					if($csrf == $checkingcsrf) {
							
						array_splice($ourCsrfArray,current($ourCsrfArray));
						 
					}
				}
				
				
			} else {
				
				//it was not found so if any of the tokens exist 
				//for this then we need to check if we should block the
				//request
				foreach($ourCsrfArray as $curCsrf=>$block) {
					if($block) {
						header("HTTP/1.0 403 Forbidden");
						
						$this->errorBadRequest("403", "Forbidden Bad CSRF Token");
						
					}
				}
			}
		} 
		
		if(isset($this->csrfTokenArray[$ourAllPagesKey])) {
			
			$ourCsrfArray =& $this->csrfTokenArray[$ourAllPagesKey];
			
			if(isset($ourCsrfArray[$checkingcsrf])) {
			
				//delete it
				foreach($ourCsrfArray as $csrf=>$blocked) {
				
					if($csrf == $checkingcsrf) {
							
						array_splice($ourCsrfArray,current($ourCsrfArray));
						
					}
				}
			
			} else {
			
				//it was not found so if any of the tokens exist
				//for this then we need to check if we should block the
				//request
				foreach($ourCsrfArray as $curCsrf=>$block) {
					if($block) {
						header("HTTP/1.0 403 Forbidden");
			
						$this->errorBadRequest("403", "Forbidden Bad CSRF Token");
			
					}
				}
			}
		}
		
	}
	
	/**
	 * Retreives custom error handler page if it
	 * exists in the database and returns with the response
	 * @param string $ErrorResponseCode
	 * @param string $ErrorResponse
	 */
	private function errorBadRequest($ErrorResponseCode, $ErrorResponse) {
		
		$query = "select * from ODOPages WHERE PageName='{$ErrorResponseCode}'";
		$result = $GLOBALS['globalref'][1]->Query($query);
		
		if(!mysqli_num_rows($result)) {
			echo("{$ErrorResponseCode} {$ErrorResponse}");
		} else {
			$row = mysqli_fetch_assoc($result);
			echo($row["PageContent"]);
		}
		
		$Comment = "{$ErrorResponseCode} {$ErrorResponse} pg={$this->EscapedVars["pg"]}";
		
		if($this->ObjectName != "") {
			$Comment .= " ob={$this->ObjectName}";
		}
		
		if($this->FunctionName != "") {
			$Comment .= " fn={$this->FunctionName}";
		}
		
		$GLOBALS['globalref'][4]->LogEvent("{$ErrorResponseCode}ERRORRESPONSE", $Comment, 5);
		exit(1);
	}
	
	/**
	 * creates the key for the csrf token
	 * @param unknown $pageName
	 * @param unknown $objectName
	 * @param unknown $fnName
	 * @return string|unknown
	 */
	private function buildCSRFTokenKey($pageName, $objectName, $fnName) {
		//pageName can not be empty
		//if it is empty then all requests will be blocked
		
		$ourKey = "ALLPAGES";
		
		if((isset($pageName)) && ($pageName!=null)) {
			
			if(!is_numeric($pageName)) {
				//locate it in the common name pages
				if(isset($this->CommonPageNames[$pageName])) {
					
					$pageName = $this->CommonPageNames[$pageName];
					
				} else {
					trigger_error("Page ID passed was not found in current authorized pages. user will never be able to authenticate csrf!",E_ODOWEB_APPERROR);
				}
			} else if(is_int($pageName)) {
				//just in case integer was passed in case of string
				$pageName = strval($pageName);
			}
			
			$ourKey = $pageName;
		}
		
		if((isset($objectName)) && ($objectName!=null) && (strlen($objectName)>0)) {
			$ourKey .= $objectName;
		}
		
		if((isset($fnName)) && ($fnName!=null) && (strlen($fnName)>0)) {
			$ourKey .= $fnName;
		}
		
		
		
		return $ourKey;
	}
	
	
	function __sleep() {



		foreach($this->UserObjectArray as $Name=>$Obj)
		{
			$this->SerializedObjects[$Name] = serialize($Obj);


		}
		
		if((defined('LOGSESSIONTIME'))&&(LOGSESSIONTIME == 1)) {
			$curTime = microtime(true);
			$totalTime = $curTime - $this->sessionObjStartTime;
					
			$this->logSessionTimeMessage .= " Total time of Session object:{$totalTime}";
			
			error_log($this->logSessionTimeMessage);

			$this->logSessionTimeMessage = "";
		}
		
		$this->cachedObjectCodePath = "";
		$this->cachedPageCodePath = "";
		
		unset($this->UserObjectArray);
		return( array_keys( get_object_vars( $this ) ) );
	}


	function __wakeup() {
		
		$this->sessionObjStartTime = microtime(true);
		$this->logSessionTimeMessage = "";
		
		$GLOBALS['globalref'][0] =& $this;
		$this->pg = -1;
		$this->ob = -1;
		$this->ObjectName = "";
		$this->fn = -1;
		$this->FunctionName = "";
		$this->ObjectOnlyOutput = 0;
		$this->EscapedVars = array();
		$this->UserObjectArray = array();
		
	}


}




class ODODB {
	//Establish connection with DB
	//This class is not stored in the Session for several reasons. The primary being that wakeup 
	//on several objects require a database connection first. 
	
	//revisit system key checks and multiple DB userids***
	private $mysqli;
	
	//DB connection info
	private $dbipadd = "";
	private $dbuname = "";
	private $dbpword = "";
	private $dbname = "";

	
    private $inTransaction = false;
    
	
	function __construct() {
		$enc = new ourConstants();
		
		$this->dbipadd = $enc->getDbipadd();
		$this->dbuname = $enc->getDbuname();
		$this->dbpword = $enc->getDbpword();
		$this->dbname = $enc->getDbname();
		
		$this->mysqli = null;
		
        global $globalref;
        $globalref[1] = &$this;
    }

	function EscapeMe($Variable) {
		$Rvalue = "";

			$Rvalue = $Variable;
		
		$Rvalue = $this->mysqli->real_escape_string($Rvalue);
		return $Rvalue;
	}

	function GetNumRowsAffected() {

		return $this->mysqli->affected_rows;

	}

	function LastInsertID() {

		return $this->mysqli->insert_id;
	}

	function Connect() {

		//we are not using pconnect here for a lot of reasons!
		if($this->mysqli == null) {
			$this->mysqli = new mysqli($this->dbipadd, $this->dbuname, $this->dbpword, $this->dbname);
			
			if($this->mysqli->connect_errno) {
				die('Could not connect: ' . $this->mysqli->connect_errno . ':' . $this->mysqli->connect_error);
			}
			
			if(!$this->mysqli->set_charset("utf8")) {
				die('Could not set charset!' . $this->mysqli->error);
			}
				
		}
		
	}

	function Query($myquery) {

		if((defined('LOGDBQUERYTIME'))&&(LOGDBQUERYTIME == 1)) {
			$startTime = microtime(true);
		}
		
		$result = $this->mysqli->query($myquery);
		
		if(!$result) {
			
			trigger_error('Query failed at 103a: Query:' . $myquery . "Error:" . $this->mysqli->errno . ":" . $this->mysqli->error, E_USER_ERROR);
		} 
	

		if((defined('LOGDBQUERYTIME'))&&(LOGDBQUERYTIME == 1)) {
			$endTime = microtime(true);
			$diffTime = $endTime - $startTime;
			
			$message = "Total Query Time:{$diffTime} for query: {$myquery}";
			
			error_log($message);
			
		}
		
		return $result;

	}

	function MultiQuery($myquery) {
	
		if((defined('LOGDBQUERYTIME'))&&(LOGDBQUERYTIME == 1)) {
			$startTime = microtime(true);
		}
		
		$result = mysqli_multi_query($this->mysqli, $myquery);
	
		if(!$result) {
				
			trigger_error('Query failed at 103a: Query:' . $myquery . "Error:" . $this->mysqli->errno . ":" . $this->mysqli->error, E_USER_ERROR);
		}
	
		if((defined('LOGDBQUERYTIME'))&&(LOGDBQUERYTIME == 1)) {
			$endTime = microtime(true);
			$diffTime = $endTime - $startTime;
				
			$message = "Total Query Time:{$diffTime} for query: {$myquery}";
			
			error_log($message);
		}
		
		return $result;
	
	}
	
	function prepare($query) {
		
		$mStm = $this->mysqli->prepare($query);
		
		if((is_bool($mStm))&&(!$mStm)) {
			trigger_error('Building statement failed for query:' . $query . "Error:" . $this->mysqli->errno . ":" . $this->mysqli->error, E_USER_ERROR);
			
		}
		
		return $mStm;
		
	}
	
	function commit() {
		
        $this->inTransaction = false;
        
        $success = $this->mysqli->commit();
        
		$this->endTransaction();

        $GLOBALS['globalref'][4]->processAfterTransaction();
        return $success;
		
	}
	
	function rollBack() {
        
        $this->inTransaction = false;
		$this->mysqli->rollback();
		$this->mysqli->autocommit(true);
        $GLOBALS['globalref'][4]->processAfterTransaction(true);
        
    }

	function startTransaction() {
		$this->mysqli->autocommit(false);
        $this->inTransaction = true;
	}
	
    function checkInTransaction() {
        return $this->inTransaction;
    }

	function endTransaction() {
		$this->mysqli->autocommit(true);
	}

	function setThrowExceptionOnCommit($blThrow) {
		if(is_bool($blThrow)) {
			$this->throwExceptionOnCommit = $blThrow;
		}
	}
	
	function CloseDBCon() {
		return $this->mysqli->close();
	}

}

class ODOConstantCacheItem {
	var $ModID;
	var $ModName;
	var $Constants;
	
	function __construct() {
		$this->ModID = -1;
		$this->ModName = "";
		$this->Constants = array();
	}
	
}

class ODOConstants {
	//constants are defined in the DB table Constants...um der?
	//this class simply loads the system constants. 
	

	private $ConstantsCache;
	
	
	function __construct() {
		
		$GLOBALS['globalref'][2] =& $this;
		
		$this->ConstantsCache = array();
		
		
		$this->LoadConstants();
	}


	function LoadConstants() {
		
		//ModID is key
		if(!isset($this->ConstantsCache["1"])) {
			
			
		
			$strquery = "select * from Constants where ModID = 1";
			$result = $GLOBALS['globalref'][1]->Query($strquery);

			$this->ConstantsCache["1"] = new ODOConstantCacheItem();
			$this->ConstantsCache["1"]->ModID = 1;
			$this->ConstantsCache["1"]->ModName = "Standard";
			
			while ($row = mysqli_fetch_assoc($result)) 
			{
				
				$this->ConstantsCache["1"]->Constants[$row["ConstName"]] = $row["Value"];
			}
		} 
		
		foreach($this->ConstantsCache["1"] ->Constants as $Name=>$Value) {
			
			if(!defined($Name)) {
				if(!define($Name, $Value))
				{
					//Throw error
					trigger_error("Constant was not defined: " . $Name);
				}
			}
		}

	}

	function LoadModuleConstantsModID($ModID) {
	
		if(!isset($this->ConstantsCache[$ModID])) {
			$strquery = "SELECT ConstID, ConstName, Value, Constants.ModID, Modules.ModName FROM Constants LEFT JOIN Modules on Constants.ModID=Modules.ModID WHERE Constants.ModID=" . $ModID;

			$result = $GLOBALS['globalref'][1]->Query($strquery);

			$this->ConstantsCache[$ModID] = new ODOConstantCacheItem();
			$this->ConstantsCache[$ModID]->ModID = $ModID;
			
		
			while ($row = mysqli_fetch_assoc($result)) 
			{
				if(($this->ConstantsCache[$ModID]->ModName == "")&&(!is_null($row["ModName"]))) {
					$this->ConstantsCache[$ModID]->ModName = $row["ModName"];
				}
				
				$this->ConstantsCache[$ModID]->Constants[$row["ConstName"]] = $row["Value"];
				if(!define($row["ConstName"], $row["Value"]))
				{
					//Throw error
					trigger_error("Constant was not defined: " . $row["ConstName"]);
				} 
					
			}
			
		} else {
				foreach($this->ConstantsCache[$ModID] ->Constants as $Name=>$Value) {
				
					if(!defined($Name)) {
						if(!define($Name, $Value))
						{
							//Throw error
							trigger_error("Constant was not defined: " . $Name);
						}
					}
				}
		}

	}


	function LoadModuleConstants($ModuleName) {
		
		//check for Module Name
		$LocalModID = -1;
		
		foreach($this->ConstantsCache as $ModID=>$Constant) {
				if($Constant->ModName == $ModuleName) {
					$LocalModID = $ModID;
					break;
				}
		}
		
		if($LocalModID == -1) {
			$ModuleName = $GLOBALS['globalref'][1]->EscapeMe($ModuleName);
			$strquery = "select ModID from Modules where ModName = '" . $ModuleName . "'";

			$result = $GLOBALS['globalref'][1]->Query($strquery);

			$row = mysqli_fetch_assoc($result);
			
			if(is_null($row)) {
					trigger_error("Module Name not found:" . $ModuleName);
			} 					
			
			$LocalModID = $row["ModID"];
			$strquery = "select * from Constants where ModID = " . $LocalModID;

			$result = $GLOBALS['globalref'][1]->Query($strquery);

			$this->ConstantsCache[$LocalModID] = new ODOConstantCacheItem();
			$this->ConstantsCache[$LocalModID]->ModID = $LocalModID;
		
			while ($row = mysqli_fetch_assoc($result)) 
			{
				if($this->ConstantsCache[$LocalModID]->ModName == "") {
					$this->ConstantsCache[$LocalModID]->ModName = $ModuleName;
				}
				
				$this->ConstantsCache[$LocalModID]->Constants[$row["ConstName"]] = $row["Value"];
				if(!define($row["ConstName"], $row["Value"]))
				{
					//Throw error
					trigger_error("Constant was not defined: " . $Name);
				}
			}
			
		} else {
			
			foreach($this->ConstantsCache[$LocalModID] ->Constants as $Name=>$Value) {
			
				if(!define($Name, $Value))
				{
					//Throw error
					trigger_error("Constant was not defined: " . $Name);
				}
			}
		}
			
	}

	function __wakeup() {
		$GLOBALS['globalref'][2] =& $this;
		$this->LoadConstants();
		
	}
	
}

//class definitions
class ODOUser {
	//variables
	private $UID;
	private $GENUID; //used after registration before login
	private $LoggedIn;
	private $IsGuest;
	private $username;
	private $email;
	private $rnamelast;
	private $rnamefirst;
	private $LastLoginTime;
	private $IsTemp;
	private $SHA256h = "";
	private $SHA512h = "";
	private $iv = "";
	private $groups;
    
	function getUID() {
		return $this->UID;
	}

	function getGENUID() {
			return $this->GENUID;
	}
	
	function setGENUID($newUID) {
			$this->GENUID = $newUID;
	}
	
	function getLoggedIn() {
		return $this->LoggedIn;
	}
	
	function getIsGuest() {
		return $this->IsGuest;
	}

	function getusername() {
		return $this->username;
	}

	function getemail() {
		return $this->email;
	}

	function getrname() {
		$rvalue = $this->rnamefirst . " " . $this->rnamelast;
		return $rvalue;
	}

	function getlastname() {
		return $this->rnamelast;
	}
	
	function getfirstname() {
		return $this->rnamefirst;
	}
	
	function getlastlogintime() {
		return $this->LastLoginTime;
	}

	function getIsTemp() {
		return $this->IsTemp;
	}

	function getUserIV() {
		return $this->iv;
	}
	
	function IsEncrypted() {
		if(strlen($this->iv)>0) {
			return true;
		} else {
			return false;
		}
	}
	
	function setEmail($newEmail) {
		
		if($this->isNonGuestLogin()) {
			$email = $GLOBALS['globalref'][1]->EscapeMe($newEmail);
		
			$this->email = $email;
		
			$this->updateEncryptedValues();
		}
		
	}
	
	function setLastName($newLName) {
		
		if($this->isNonGuestLogin()) {
			$lname = $GLOBALS['globalref'][1]->EscapeMe($newLName);
		
			$this->rnamelast = $lname;
		
			$this->updateEncryptedValues();
		}
		
	}
	
	function setFirstName($newFName) {
		
		if($this->isNonGuestLogin()) {
			$fname = $GLOBALS['globalref'][1]->EscapeMe($newFName);
		
			$this->rnamefirst = $fname;
		
			$this->updateEncryptedValues();
		}
		
	}
	
	function updateUserInfo($email, $LName, $FName) {
		
		if($this->isNonGuestLogin()) {
			$fname = $GLOBALS['globalref'][1]->EscapeMe($FName);
			$lname = $GLOBALS['globalref'][1]->EscapeMe($LName);
			$email = $GLOBALS['globalref'][1]->EscapeMe($email);
			
			$this->email = $email;
			$this->rnamefirst = $fname;
			$this->rnamelast = $lname;
			
			$this->updateEncryptedValues();
		}
		
	}
	
	private function updateEncryptedValues() {
	
		$email = "";
		$lname = "";
		$fname = "";
		
		if($this->IsEncrypted()) {
			$encData = new EncryptedData();
			$encData->iv = $this->iv;
			$encData->decArray["emailadd"] = $this->email;
			$encData->decArray["rnameLast"] = $this->rnamelast;
			$encData->decArray["rnameFirst"] = $this->rnamefirst;
							
			$encData = $GLOBALS['globalref'][7]->ODOEncrypt($encData);
			
			$email = $GLOBALS['globalref'][1]->EscapeMe($encData->encArray["emailadd"]);
			$lname = $GLOBALS['globalref'][1]->EscapeMe($encData->encArray["rnameLast"]);
			$fname = $GLOBALS['globalref'][1]->EscapeMe($encData->encArray["rnameFirst"]);
			
		} else {
			$email = $this->email;
			$lname = $this->rnamelast;
			$fname = $this->rnamefirst;
		}
		
		$query = "UPDATE ODOUsers SET emailadd='" . $email . "', rnameLast='" . $lname . "', rnameFirst='" . $fname . "' WHERE UID=" . $this->UID;
		
		$result = $GLOBALS['globalref'][1]->Query($query);

		if($GLOBALS['globalref'][1]->GetNumRowsAffected() <= 0) {
			trigger_error("We could not update ODOUser table. updateEncryptedValues error", E_USER_ERROR);
		} else {
			$GLOBALS['globalref'][4]->LogEvent("ODOUSERUPDATE", "User updated their information.", ODOUSERUPDATE);
		}
			
	}
	
	function __construct() {
		
		$enc = new ourConstants();
		
		global $globalref;
        	$globalref[3] = &$this;
		$this->UID = 0;
		$this->GENUID = 0;
		$this->LoggedIn = false;
		$this->IsGuest = false;
		$this->username = "no name";
		$this->email = "nowhere@nowhere.com";
		$this->rnamelast = "Not Logged In";
		$this->rnamefirst = "";
		$this->IsTemp = 0;
		$this->LastLoginTime = "0000-00-00 00:00:00";
		$this->SHA256h = $enc->getSHA256h();
		$this->SHA512h = $enc->getSHA512h();	
		$this->iv = "";
        $this->groups = array();
		
	}
	
	function isNonGuestLogin() {
		if(($this->LoggedIn)&&(!($this->IsGuest))) {
			return true;
		}

		return false;
	}

	function VerifyandLogin($UID) {
		//this function will use current session data to login a previous
		//user that was already logged in. this is primaryly used for the sepearte
		//login script page.

		//if we are logged in though we do nothing. Guest logins should be logged
		// out before calling this function
		if(!$this->LoggedIn) { 

			$remoteIP = $GLOBALS['globalref'][7]->getRemoteIP();
			
			$query = "select * from LoggedIn where UID=" . $UID . " AND SessionID='" . session_id() . "' AND IPAdd='" . $remoteIP . "'";
			$result = $GLOBALS['globalref'][1]->Query($query);
			if(!mysqli_num_rows($result)) {

				return false;

			} else {

				$query = "select * from ODOUsers where UID=" . $UID;
				$result = $GLOBALS['globalref'][1]->Query($query);
				
				if(!mysqli_num_rows($result)) {
					trigger_error("We could not get user info from the database during verification. The Userid maybe invalid. userid:" . $UID, E_USER_ERROR);
					
					return false;
				} else {
					
					$row = mysqli_fetch_assoc($result);
					$this->UID = $row["UID"];
					$this->LoggedIn = true;
					$this->IsGuest = false;
					$this->LastLoginTime = $row["LastLogin"];
					$this->IsTemp = $row["Pistemp"];
					$this->iv = $row["EncIV"];
					if($row["IsEncrypted"] == 1) {
						
							if($GLOBALS['globalref'][7]->isMcryptAvailable()) {
								
								$encData = new EncryptedData();
								$encData->iv = $row["EncIV"];
								$encData->encArray["emailadd"] = $row["emailadd"];
								$encData->encArray["rnameLast"] = $row["rnameLast"];
								$encData->encArray["rnameFirst"] = $row["rnameFirst"];
								
								$encData = $GLOBALS['globalref'][7]->ODODecrypt($encData);
								
								$this->email = $encData->decArray["emailadd"];
								$this->rnamelast = $encData->decArray["rnameLast"];
								$this->rnamefirst = $encData->decArray["rnameFirst"];
								
							} else {
								$this->email = "";
								$this->rnamelast = "";
								$this->rnamefirst = "";
								$GLOBALS['globalref'][4]->LogEvent("DECRYPTERROR", "User info for " . $UID . " is encrypted but mcrypt is not available on server.", 3);
							}
					} else {
							$this->email = $row["emailadd"];
							$this->rnamelast = $row["rnameLast"];
							$this->rnamefirst = $row["rnameFirst"];
					
					}
						
					$this->username = $row["user"];
					
					
					$GLOBALS['globalref'][4]->LogEvent("LOGIN", "User Logged In", LOGINSEVLEVEL);
					
					$query = "UPDATE ODOUsers SET LastLogin=CURRENT_TIMESTAMP WHERE UID=" . $UID;
					$result = $GLOBALS['globalref'][1]->Query($query);
				
                    $this->LoadUserGroups();
                    
					return true;
				}
				
			}

		} else {

			return false;
		}

	}


	//Random String generation for temp password 
	private function randomstring($len)
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
		$this->SelfPasswordReset(1);
	}

	//fn: SelfPasswordReset
	//$PromptUser: 0-no echo, 1-echo all, 2-return divs
	//Desc: This will display the current 
	function SelfPasswordReset($PromptUser) {
		//this function allows a user to reset a password if they have completed the
		//question answer section. 

		if(ALLOWUSERANYPAGELOGIN)
		{

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
							$ResetQ = $row["ResetQ"];
							$ResetA = $row["ResetA"];
					
					}
				} 				
					
				//include check to see if e-mail, ResetQ, and ResetA are not null
				
				if((strlen($email) < 5)||($ResetQ==0)||(strlen($ResetA)<1)) {
					
					if($PromptUser == 1) {
						$PageOut = "<!DOCTYPE HTML><html><head>\n<link rel='stylesheet' type='text/css' href='css/global.css'><title>" . SERVERNAME . " Error</title><script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"index.php?pg=";
						
						$PageOut = $PageOut . $GLOBALS['globalref'][0]->pg . "\"\n\n\n}\n//-->\n</script></head>\n<body onLoad=\"setTimeout('delayer()', 5000)\">\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">\n";
						
						$PageOut= $PageOut . "<div id=\"loginerror\" class=\"loginerror\">ERROR:You have not setup your password reset question, reset answer, or e-mail address! Please Contact System Admin to reset your password.</div>\n</div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body>\n</html>\n";
						
						echo $PageOut;
						return false;
						
					} elseif($PromptUser == 2) {
						$Rvalue = "<div id=\"loginerror\" class=\"loginerror\">Error:You Have not setup your password reset question, reset answer, or e-mail address! Please Contact System Admin to reset your password.</div>";
						return $Rvalue;
					} else {
						return false;
					}
				}


				if($this->ODOHash($GLOBALS['globalref'][0]->EscapedVars["ResetA"], $mode) == $ResetA)
				{

					//we have a valid responce so reset and send password

					$temppass = $this->randomstring(10);
					
					$query = "UPDATE ODOUsers SET pass='" . $GLOBALS['globalref'][1]->EscapeMe($this->ODOHash($temppass, $this->GetHashMode())) . "', Pistemp=1, IsLocked=0, HashedMode=" .  $this->GetHashMode() . " where user='" . $GLOBALS['globalref'][0]->EscapedVars["UNAME"] . "'";

					$result = $GLOBALS['globalref'][1]->Query($query);
				
					$remoteIP = $GLOBALS['globalref'][7]->getRemoteIP();
						
					$message = "There was a password reset requested for " . SERVERNAME . ". at time=" . date(DATE_RFC822) . " from address=" . $remoteIP . ". Please use the password found below to log back into your account. \n \n Password: " . $temppass . "\n\n";
				
					$headers  = 'MIME-Version: 1.0' . "\n";
					$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\n";
					$headers .= 'From: ' . REGREPLYEMAIL . "\n";
					$headers .= 'Reply-To:  ' . REGREPLYEMAIL . "\n";
					$headers .= 'X-Mailer: ODOWeb' . "\n";

					mail($email, "Password reset on " . SERVERNAME, $message, $headers) or $GLOBALS['globalref'][4]->LogEvent("EMAILERROR", "Mail sending failed for password reset!", 4);

					$GLOBALS['globalref'][4]->LogEvent("FORGOTPASSWORDRESET", "Reseting of password using Q&A for username " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], 3);
	
					if($PromptUser == 1) {

						$PageOut = "<html>\n<head>\n<title>" . SERVERNAME . " Password Reset</title>\n<link rel='stylesheet' type='text/css' href='css/global.css'><script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"index.php?pg=";
						
						$PageOut = $PageOut . $GLOBALS['globalref'][0]->pg . "&ob=ODOUserO&fn=Login&ObjectOnlyOutput=1\"\n\n\n}\n//-->\n</script></head>\n<body onLoad=\"setTimeout('delayer()', 5000)\">\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">";
						
						$PageOut= $PageOut . "<div id=\"loginmessage\" class=\"loginmessage\">Success! You have successfully reset your password. An e-mail has been sent to the e-mail address you have recorded with your new temporary password. You will be prompted to change your password on your first login. Please wait to be redirected to the login page.</div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body></html>";

						echo $PageOut;
						return true;
						
					} elseif($PromptUser == 2) {
						$Rvalue = "<div id=\"loginmessage\" class=\"loginmessage\">Success!:You have successfully reset your password. An e-mail has been sent to the e-mail address you have recorded with your new temporary password. You will be prompted to change your password on your first login.</div>";
						return $Rvalue;

					} else {
						return true;
					}

				} else { 
					if($PromptUser == 1) {
						
						$PageOut = "<html><head><title>" . SERVERNAME . " Password Reset</title>\n<link rel='stylesheet' type='text/css' href='css/global.css'>\n<script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"index.php?pg=";
						$PageOut = $PageOut . $GLOBALS['globalref'][0]->pg . "&ob=ODOUserO&fn=SelfPasswordResetPublic&ObjectOnlyOutput=1\"\n\n\n}\n//-->\n</script></head><body onLoad=\"setTimeout('delayer()', 5000)\"><div class=\"loginHeader\"></div><div class=\"loginLeft\"></div><div class=\"loginCenter\">";
						$PageOut= $PageOut . "<div id=\"loginerror\" class=\"loginerror\">Access Denied! You have answered the question wrong. Please wait to be redirected to the login page.</div></div><div class=\"loginRight\"></div><div class=\"loginFooter\"></div></body></html>";
						
					} elseif($PromptUser == 2) {
						$Rvalue = "<div id=\"loginerror\" class=\"loginerror\">ACCESS DENIED!</div>";
					}
					//log event
					$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Invalid Answer/Question pair for userid: " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], WRONGPASSWORDSEVLEVEL);
					
					//Incr locked out count
					if(LOCKOUTONSELFPASSRESET) {
						
						$intTries = $intTries + 1;
						$IsLocked = $row["IsLocked"];
						
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
					
					if($PromptUser == 1) {
						echo $PageOut;
					} elseif($PromptUser == 2) {
						return $Rvalue;
					} else {
						return false;
					}
				}


			} else {

				$PageOut = "<html><Head><title>Self Password Reset</title>\n<link rel='stylesheet' type='text/css' href='css/global.css'>\n</head><body><div class=\"loginHeader\"></div><div class=\"loginLeft\"></div><div class=\"loginCenter\">";

				//if no UNAME then prompt.
				if(isset($GLOBALS['globalref'][0]->EscapedVars["UNAME"])) {
					$query = "select ResetQuestions.ResetQ from ODOUsers, ResetQuestions where user='" . $GLOBALS['globalref'][0]->EscapedVars["UNAME"] . "' AND ODOUsers.ResetQID=ResetQuestions.ResetQID"; 
					$result = $GLOBALS['globalref'][1]->Query($query);

					$ResetQ = "";
					
					if(mysqli_num_rows($result) > 0) {
						
						$row = mysqli_fetch_assoc($result);
						$ResetQ = $row["ResetQ"];
						
						$PageOut .= $this->GetResetQuestionForm($GLOBALS['globalref'][0]->EscapedVars["UNAME"], $ResetQ);
					
						$PageOut .= "</div><div class=\"loginRight\"></div><div class=\"loginFooter\"></div></body></html>";
						
					} else {
						$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Question for user not setup but user requested password reset:" . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], WRONGPASSWORDSEVLEVEL);
						
						$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">You have not setup your password reset question or answer! Please return to the home page <a href=\"index.php?pg=Home\">here</a>.</div></div><div class=\"loginRight\"></div><div class=\"loginFooter\"></div></body></html>";
						
					}
					

					

				} else {
				
					$PageOut .= $this->GetResetQuestionForm("", "");
					
					$PageOut .= "</div><div class=\"loginRight\"></div><div class=\"loginFooter\"></div></body></html>";
					
				}
			
			echo $PageOut;

			}

		} else {
			
			trigger_error("You are not allowed to reset password at this page.", E_USER_ERROR);
			//else we throw an error into the system log and log them in as guest
	
			return false;
								
		}
	}

	function ChangePasswordPublic() {
		$this->ChangePassword(1);
	}	

	function ChangePassword($PromptUser) {
		//PromptUser 0-No Output 1-echo output 2-return output
		if(!$this->LoggedIn)
		{
			return false;

		} else {
			
			if($this->VerifyUser()) {

				if(isset($GLOBALS['globalref'][0]->EscapedVars["OldPword"]) && isset($GLOBALS['globalref'][0]->EscapedVars["NewPword"]) && isset($GLOBALS['globalref'][0]->EscapedVars["ConfirmPword"])) {

					//Confirm Old password
					if(($this->LoggedIn) && (!$this->IsGuest)) {
						
						//load the refresh this page script
						$PageOut = "<!DOCTYPE HTML>\n<html>\n<head>\n<title>" . SERVERNAME . " Login</title>\n<link rel='stylesheet' type='text/css' href='css/global.css'>\n<script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"index.php?pg=";
								
						$PageOut = $PageOut . $GLOBALS['globalref'][0]->EscapedVars["pg"] . "\"\n\n\n}\n//-->\n</script></head><body onLoad=\"setTimeout('delayer()', 5000)\">\n<div class=\"loginHeader\"></div>\n<div class=\"loginLeft\"></div>\n<div class=\"loginCenter\">";

						$PageOutEnd = "</div>\n<div class=\"loginRight\"></div>\n<div class=\"loginFooter\"></div>\n</body>\n</html>";
						
						$query = "SELECT pass, HashedMode, IsLocked, FailedLogins, Pistemp FROM ODOUsers WHERE UID=" . $this->UID;

						$result = $GLOBALS['globalref'][1]->Query($query);
						if(!mysqli_num_rows($result)) {
							//do nothing just exit
							$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">ACCESS DENIED</div>" . $PageOutEnd;
							//log event
							$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Invalid username: " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], WRONGUSERNAMESEVLEVEL);
							if($PromptUser == 1)
							{
								echo $PageOut;
								return false;
							} elseif($PromptUser == 2) {
								return "<div id=\"loginerror\" class=\"loginerror\">ACESS DENIED</div>";
							} else {
								return false;
							}
						}

						$row = mysqli_fetch_assoc($result);
				
						$oldmode = $row["HashedMode"];
			
						$curmode = $this->GetHashMode() ;
						$pwd = $this->ODOHash($GLOBALS['globalref'][0]->EscapedVars["OldPword"], $oldmode);
						$Locked = $row["IsLocked"];
						$FailedLogins = $row["FailedLogins"];
				
				
						if($pwd!=$row["pass"]) { 
							
							//Confirm Old password
							if($PromptUser == 1) {
								
								$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">Invalid Old Password</div>" . $PageOutEnd;
								echo $PageOut;
								return false;
							} elseif($PromptUser == 2) {
								return "<div id=\"loginerror\" class=\"loginerror\">Invalid Old Password</div>";
							} else {
								return false;
							}

						} else {

							//Confirm Matched Passwords
							if($GLOBALS['globalref'][0]->EscapedVars["NewPword"] != $GLOBALS['globalref'][0]->EscapedVars["ConfirmPword"]) {
								$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">Your new passwords do not match!</div>" . $PageOutEnd;

								if($PromptUser == 1) {
									echo $PageOut;
									return false;
								} elseif($PromptUser == 2) {
									return "<div id=\"loginerror\" class=\"loginerror\">Your new passwords do not match!</div>";
								} else {
									return false;
								}
							}

							if((strlen($GLOBALS['globalref'][0]->EscapedVars["NewPword"]) < 3) || (strlen($GLOBALS['globalref'][0]->EscapedVars["NewPword"]) > 10)) {
								$PageOut = $PageOut . "<div id=\"loginmessage\" class=\"loginmessage\">Your new password is either to short or to long. Please choose a password at least 4 characters long and no more than 10 characters.</div>" . $PageOutEnd;
	
								if($PromptUser == 1) {
									echo $PageOut;
									return false;
								} elseif($PromptUser == 2) {
									return "<div id=\"loginmessage\" class=\"loginmessage\">Your new password is either to short or to long. Please choose a password at least 4 characters long and no more than 10 characters.</div>";
								} else {

									return false;
								}
							}

							//update User Info and clear Temp flag
							$query = "update ODOUsers set Pistemp=0, FailedLogins=0, pass='" .  $GLOBALS['globalref'][1]->EscapeMe($this->ODOHash($GLOBALS['globalref'][0]->EscapedVars["NewPword"], $curmode)) . "'";
							
							if($curmode != $oldmode) {
								$query = $query . ", HashedMode=" . $curmode;
							}
							
							$query = $query . " where UID=" . $this->UID;

							$result = $GLOBALS['globalref'][1]->Query($query);
							if($GLOBALS['globalref'][1]->GetNumRowsAffected() < 1) { 
								trigger_error("Could not Reset Password!");
							} else {
								$PageOut = $PageOut . "<div id=\"loginmessage\" class=\"loginmessage\">Your password has been changed.</div>" . $PageOutEnd;

								if($PromptUser == 1) {
									echo $PageOut;
									return true;
								} elseif($PromptUser == 2) {
									return "<div id=\"loginmessage\" class=\"loginmessage\">Your password has been changed</div>";
								} else {
	
									return true;
								}
								
							}
						}
					} else {
						
						//we shouldn't be here. Just return if we are
						return false;
					}
				} else {
					
					//else we return false or output form
					//how do we handle html segment output?
					
					if($PromptUser == 1) {
						$ReturnValue = "<!DOCTYPE HTML><html><head><title>" . SERVERNAME . " Reset Password</title>\n<link rel='stylesheet' type='text/css' href='css/global.css'>\n</head><body><div class=\"loginHeader\"></div><div class=\"loginLeft\"></div><div class=\"loginCenter\">";
			
						$ReturnValue .= $this->GetChangePassForm() . "</div><div class=\"loginRight\"></div><div class=\"loginFooter\"></div></body></html>";

						echo $ReturnValue;
	
					} elseif($PromptUser == 2) {
						//css classes used boxed:title,content 
	
						return $this->GetChangePassForm();

					} else {
						
						//no passed variables so we have to fail the request
						return false;

					}
				}

			} else {

				return false;
			}

		}

	}
	
	function LoginGuest() {
		//log user out if logged in
		if($this->LoggedIn)
		{
			$this->Logout(false);
		}
		
		//verify there is a guest account to login with
		$query = "select * from ODOUsers where user='guest'"; 
		$result = $GLOBALS['globalref'][1]->Query($query);
		
		if(mysqli_num_rows($result) > 0)
		{
			$row = mysqli_fetch_assoc($result);
			$this->UID = $row["UID"];
			$this->LoggedIn = true;
			$this->IsGuest = true;
			$this->username = "guest";
			$this->email = "nowhere";
			$this->rnamelast = "Guest";
			$this->rnamefirst = "Anonymous";
			return true;
		} else {

			return false;
		}
	
	}

	////////////////////////////////////////////
	//Name: ODOUser:LogoutPublic()
	//Description: This is only used for Form submits
	////////////////////////////////////////////
	function LogoutPublic() {
		if(isset($GLOBALS['globalref'][0]->EscapedVars["PromptUser"])) {

			$testVal = boolval($GLOBALS['globalref'][0]->EscapedVars["PromptUser"]);
			
			
			$this->Logout(boolval($GLOBALS['globalref'][0]->EscapedVars["PromptUser"]));
			
		} else { 

			$this->Logout(true);
		}

	}

	////////////////////////////////////////////
	//Name: ODOUser:Logout()
	//In: $PromptUser=will output basic prompt with timer that
	//will refresh to the current page if set to true. If set to false
	//it expects the calling function/object to output needed html
	//Return Value: Boolean True if user was logged in and logout was ok.
	//false if logout failed and user was not logged in
	//Desc: This is the basic logout function for a user. It can be called
	//by the Login script or the User PHP code.
	///////////////////////////////////////////
	function Logout($PromptUser) {

		//load the refresh this page script
		$PageOut = "<!DOCTYPE HTML><html><head><title>" . SERVERNAME . " Login</title>\n<link rel='stylesheet' type='text/css' href='css/global.css'>\n<script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"index.php?pg=";
		$PageOut = $PageOut . $GLOBALS['globalref'][0]->pg . "\"\n\n\n}\n//-->\n</script></head><body onLoad=\"setTimeout('delayer()', 5000)\"><div class=\"loginHeader\"></div><div class=\"loginLeft\"></div><div class=\"loginCenter\">";

		$PageOutEnd = "</div><div class=\"loginRight\"></div><div class=\"loginFooter\"></div>";
		
		//log user out
		$query = "select * from LoggedIn where UID='" . $this->UID . "'";
		

		$result = $GLOBALS['globalref'][1]->Query($query);
		
		if(!mysqli_num_rows($result))
		{
			if($PromptUser) 
			{
				$PageOut = $PageOut . "<div id=\"loginmessage\" class=\"loginmessage\">You are not logged in.</div>" . $PageOutEnd;
				echo $PageOut;
			}
			
			//reset values			
			$this->UID = 0;
			$this->LoggedIn = false;
			$this->IsGuest = false;
			$this->username = "none";
			$this->email = "nowhere";
			$this->rnamelast = "Not Logged In";
			$this->rnamefirst = "";
			$this->LastLoginTime = "0000-00-00 00:00:00";
			$this->iv = "";
			return false;
			
		} else {
			
			$query = "delete from LoggedIn where UID='" . $this->UID . "'";
			$result = $GLOBALS['globalref'][1]->Query($query);
		
			if($GLOBALS['globalref'][1]->GetNumRowsAffected() > 0) 
			{
				
				$GLOBALS['globalref'][4]->LogEvent("LOGOUT", "Loging out user", LOGOUTSEVLEVEL);
	
				//reset values			
				$this->UID = 0;
				$this->LoggedIn = false;
				$this->IsGuest = false;
				$this->username = "none";
				$this->email = "nowhere";
				$this->rnamelast = "Not Logged In";
				$this->rnamefirst = "";
				$this->iv = "";
				$this->LastLoginTime = "0000-00-00 00:00:00";
		
			
				$pg = $_SESSION["ODOSessionO"]->pg;
				
				$_SESSION = array();
				
				//load system objects
				$_SESSION["ODOSessionO"] = new ODOSession();
				$_SESSION["ODOConstantsO"] = new ODOConstants();
				$_SESSION["ODOUserO"] = &$this;
				$_SESSION["ODOLoggingO"] = new ODOLogging();
				$_SESSION["ODOErrorO"] = new ODOError();
				$_SESSION["ODOPageO"] = new ODOPage();
				$_SESSION["ODOUtil"] = new ODOUtil();
	
				$_SESSION["ODOSessionO"]->pg = $pg;
				
				if($PromptUser)
				{
					$PageOut = $PageOut . "<div id=\"loginmessage\" class=\"loginmessage\">You are logged off.</div>" . $PageOutEnd;
					echo $PageOut;
                }
            
                $this->ClearUserGroups();
				
				return true;
				
			} else {
				return false;
			}
		}

	}
	
	///////////////////////////////////////////////////////////////////////
	//Name: User:Login()
	//Input: Two post variables (UNAME, PWORD) and one Get varialbe 
	//ObjectOnlyOutput
	//ConstantsIN: AllowUserAnyPageLogin - if not set then this function 
	//will not run
	//User Output: if the flag ObjectOnlyOutput is set then the login form 
	//will be sent and a confirmation page of if the login worked or not will
	//be sent. 
	//Function Output: returns boolean value on if the system could login
	//Description: This function will log a user in. There are several modes.
	//
	//MODE#0: The first mode would be with no user output. A user created form will 
	//pass this object the username and password via POST variables. $UNAME $PWORD, 
	//The function will login the user and exit returning true. ALLOWUSERANYPAGELOGIN
	//must be set to true. ObjectOnlyOutput GET VARIABLE must be set to 0 or not
	//initialized for this mode to work.
	//
	//MODE#1: The second mode will use a user created form to enter the Uname 
	//and password. The form will use this URL to submit the request:
	//index.php?ObjectOnlyOutput=1&pg=YOURPAGENUMBER&ob=ODOUser&fn=Login 
	//This function will output to the user if they have been logged in or not
	//and it will refresh to the same exact page they logged in from using the pg
	//variable. 
	//
	//MODE#2: The third mode is to allow the login function to do all the output
	//to the user. A link can be setup with this URL
	//index.php?ObjectOnlyOutput=1&pg=YOURPAGENUMBER&ob=ODOUser&fn=Login
	//with no POST variables sent to it for a username(UNAME) or password(PWORD)
	//A form will be created with the proper link to refresh to the login function
	//and after the login is completed the login form will notify the user
	//of the result and will return to the calling page. 
	///////////////////////////////////////////////////////////////////////
	function Login() {
		//must check AllowUserAnyPageLogin before running
		if(ALLOWUSERANYPAGELOGIN)
		{
			//all we do is create form and wait for responce.
			//check if they are logged in first.
			
			if($this->LoggedIn)
			{
				//Log user off first
				
				$this->Logout(0);
				
			} 
			
				//load the refresh this page script
			
			$tempPage = $_SESSION['ODOSessionO']->pg;
			
			if(defined("DEFAULTAFTERLOGINPAGE")) {
				$tempPage = DEFAULTAFTERLOGINPAGE;
			}

			$PageOut = "<!DOCTYPE HTML><html>\n<head>\n<title>" . SERVERNAME . " Login</title>\n<link rel='stylesheet' type='text/css' href='css/global.css'>\n<script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"index.php?pg=";
			$PageOut = $PageOut . $tempPage . "\"\n\n\n}\n//-->\n</script>\n</head>\n<body onLoad=\"setTimeout('delayer()', 5000)\">\n";

			$PageOutStart = "<div class=\"loginHeader\">\n</div>\n<div class=\"loginLeft\">\n</div>\n<div class=\"loginCenter\">";
			
			$PageOutEnd = "</div>\n<div class=\"loginRight\">\n</div>\n<div class=\"loginFooter\">\n</div>\n</body>\n</html>\n";
			
			$PageOut .= $PageOutStart;
			
			if(isset($GLOBALS['globalref'][0]->EscapedVars["UNAME"]) && isset($GLOBALS['globalref'][0]->EscapedVars["PWORD"]))
			{
				//log user in first
				
				//get hash mode
				$query = "SELECT UID, HashedMode, IsEncrypted, IsLocked, FailedLogins, LastLogin, Pistemp FROM ODOUsers WHERE user='" . $GLOBALS['globalref'][0]->EscapedVars["UNAME"] . "'";
				
				$result = $GLOBALS['globalref'][1]->Query($query);
				if(!mysqli_num_rows($result)) {
					//do nothing just exit

					//log event
					$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Invalid username: " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], WRONGUSERNAMESEVLEVEL);
					
					if($GLOBALS['globalref'][0]->ObjectOnlyOutput == 1)
					{
						
							$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">ACCESS DENIED</div>" . $PageOutEnd;
						
							echo $PageOut;
						
					}
					
					return false;
				}

				$row = mysqli_fetch_assoc($result);
				
				$oldmode = $row["HashedMode"];
				$UID = $row["UID"];
				$curmode = $this->GetHashMode() ;
				$pwd = $this->ODOHash($GLOBALS['globalref'][0]->EscapedVars["PWORD"], $oldmode);
				$Locked = $row["IsLocked"];
				$FailedLogins = $row["FailedLogins"];
				$this->LastLoginTime = $row["LastLogin"];
				$IsEncrypted = $row["IsEncrypted"];
				$this->IsTemp = $row["Pistemp"];
				
				$query = "select * from ODOUsers where user='" . $GLOBALS['globalref'][0]->EscapedVars["UNAME"] . "' and pass='" . $GLOBALS['globalref'][1]->EscapeMe($pwd) . "' and IsLocked=0";
	
				$result = $GLOBALS['globalref'][1]->Query($query);
				if(!mysqli_num_rows($result))
				{	

						$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">ACCESS DENIED</div>";

					//if false then we need to tell the user access denied.
					if($Locked == 1) {
						$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">Your account has been locked out.</div>" . $PageOutEnd;
						//log event
						$GLOBALS['globalref'][4]->LogEvent("ACCOUNTLOCKED", "Attempted to login after account locked. " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], LOGINAFTERACCTLOCKEDSEV);
						
						//define locked out so dblogindenied can check
						if(!defined('lockedout')) {
							define('lockedout', 1);
						}
						
						if($GLOBALS['globalref'][0]->ObjectOnlyOutput == 1)
						{
							
								echo $PageOut;
						
						}
					
						return false;
					}
					
					//log event
					$GLOBALS['globalref'][4]->LogEvent("LOGINFAILED", "Invalid username/password pair for userid: " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], WRONGPASSWORDSEVLEVEL);
					
					//update locked out flag
					
					$intTries = $FailedLogins + 1;
	
					$query = "update ODOUsers SET FailedLogins=" . $intTries;
					//update IsLocked field
					if($intTries > NUMBEROFVALIDPWORDTRIES)
					{
							
						$query = $query . ", IsLocked=1";
						
						$GLOBALS['globalref'][4]->LogEvent("ACCOUNTLOCKED", "User has gone past number of valid Pword tries " . $GLOBALS['globalref'][0]->EscapedVars["UNAME"], ACCOUNTLOCKEDSEVLEVEL);
					}
						
					$query = $query . " where UID=" . $row["UID"];
					$result = $GLOBALS['globalref'][1]->Query($query);

					//Log user in as guest
					$this->LoginGuest();
		
					//if object function was called by GET and output only flag was set
					if($GLOBALS['globalref'][0]->ObjectOnlyOutput == 1)
					{
						$PageOut .= $PageOutEnd;
						
						echo $PageOut;
						
					}
					
					return false;

				} else {
					$row = mysqli_fetch_assoc($result);
					
					$query = "SELECT * FROM LoggedIn WHERE UID=" . $row["UID"];
					$result2 = $GLOBALS['globalref'][1]->Query($query);
				
					if(mysqli_num_rows($result2) > 0)
					{
						$row2 = mysqli_fetch_assoc($result2);
			
						//we would have logged off if this session was still logged in
						
						if(!ALLOWMULTIPLESESSIONS) {
							$query = "DELETE FROM LoggedIn WHERE UID=" . $row["UID"];
							$result2 = $GLOBALS['globalref'][1]->Query($query);
						}

					}

					$remoteIP = $GLOBALS['globalref'][7]->getRemoteIP();
					
					$query = "insert into LoggedIn (UID,SessionID,IPAdd) values(" . $row["UID"] . ",'" . session_id() . "','" . $remoteIP . "')";
					$result = $GLOBALS['globalref'][1]->Query($query);
				
					
					if(!($GLOBALS['globalref'][1]->GetNumRowsAffected() > 0)) 
					{
							//throw error
						trigger_error("Could not log user in!");
						
							//return false
						if($GLOBALS['globalref'][0]->ObjectOnlyOutput == 1)
						{
							
							$PageOut = $PageOut . "<div id=\"loginerror\" class=\"loginerror\">There was an error logging you in. Please contact the website owner.</div>" . $PageOutEnd;
							echo $PageOut;

						} 
						return false;

					} else {
						
						$this->UID = $row["UID"];
						$this->LoggedIn = true;
						$this->IsGuest = false;
						$this->username = $row["user"];
						$this->iv = $row["EncIV"];
						
						if($IsEncrypted == 1) {
							if($GLOBALS['globalref'][7]->isMcryptAvailable()) {
								$encData = new EncryptedData();
								$encData->iv = $this->iv;
								$encData->encArray["emailadd"] = $row["emailadd"];
								$encData->encArray["rnameLast"] = $row["rnameLast"];
								$encData->encArray["rnameFirst"] = $row["rnameFirst"];
								
								$encData = $GLOBALS['globalref'][7]->ODODecrypt($encData);
								$this->email = $encData->decArray["emailadd"];
								$this->rnamelast = $encData->decArray["rnameLast"];
								$this->rnamefirst = $encData->decArray["rnameFirst"];
							} else {
								$this->email = "";
								$this->rnamelast = "";
								$this->rnamefirst = "";
								$GLOBALS['globalref'][4]->LogEvent("DECRYPTERROR", "User info for " . $UID . " is encrypted but mcrypt is not available on server.", 3);
							}
						} else {
							$this->email = $row["emailadd"];
							$this->rnamelast = $row["rnameLast"];
							$this->rnamefirst = $row["rnameFirst"];
					
						}
						
						$GLOBALS['globalref'][4]->LogEvent("LOGIN", "User Logged In", LOGINSEVLEVEL);
					
						$query = "UPDATE ODOUsers SET LastLogin=CURRENT_TIMESTAMP, FailedLogins=0";
						
						if($oldmode != $curmode) {
							$query = $query . ", HashedMode=" . $curmode . ", pass='" . $GLOBALS['globalref'][1]->EscapeMe($this->ODOHash($GLOBALS['globalref'][0]->EscapedVars["PWORD"], $curmode)) . "'";
						}
						
						$query = $query . " WHERE UID=" . $this->UID;
						$result = $GLOBALS['globalref'][1]->Query($query);
						
                        //populate the user group array
                        $this->LoadUserGroups();
                        
						if($GLOBALS['globalref'][0]->ObjectOnlyOutput == 1)
						{
						
							//get please wait page.
							$PageOut = $PageOut . "<div id=\"loginmessage\" class=\"loginmessage\">You have been logged in. Please Wait.</div>" . $PageOutEnd;
				
							//check if password is temp. If it is we need to load the change password script. Not refresh the page
							if($row["Pistemp"] == 1) {
								
								
								$PageOut = "<!DOCTYPE HTML><html>\n<head>\n<title>" . SERVERNAME . " Login</title>\n<link rel='stylesheet' type='text/css' href='css/global.css'>\n<script type=\"text/javascript\">\n<!--\nfunction delayer()\n{\nwindow.location = \"index.php?pg=";

								$PageOut = $PageOut . $GLOBALS['globalref'][0]->pg . "&ob=ODOUserO&fn=ChangePasswordPublic&ObjectOnlyOutput=1\"\n\n\n}\n//-->\n</script>\n</head>\n<body onLoad=\"setTimeout('delayer()', 5000)\">\n";
					
								$PageOut = $PageOut . $PageOutStart . "<div id=\"loginmessage\" class=\"loginmessage\">Warning! Your password is temporary. We are going to load the password change form. Please wait...</div>" . $PageOutEnd;


							}
							//we output login event and refresh page.
							echo $PageOut;
						} 
						return true;	
					}
						
				}

			} else {
				//output form
				if($GLOBALS['globalref'][0]->ObjectOnlyOutput == 1)
				{
					//check ODOPage for dblogin page
					
						//something has happened to the user editable dblogin page?
						//echo default
						$MyOutput = "<!DOCTYPE HTML><html><head><title>";

						//set in constants
						$MyOutput = $MyOutput . SERVERNAME;

						$MyOutput = $MyOutput . " Login</title><link rel='stylesheet' type='text/css' href='css/global.css'></head><body>" . $PageOutStart;

						//start editing here if needed
						//templates will all have LeftNav and Right even if the divs are not positioned as such
						$MyOutput = $MyOutput . $this->GetLoginForm(true) . $PageOutEnd;
					
						echo $MyOutput;

				} else {
					//else we return login form

					return $this->GetLoginForm(true);
				} 
			}

		} else {
			
			trigger_error("You are not allowed to login at this page.", E_USER_ERROR);
			//else we throw an error into the system log and log them in as guest
	
			return false;
								
		}
	}

	function VerifyUser() {
		//compares IP/UID/SessionID in database to make sure they are logged in
		//will set logged in. Logged in field should not be trusted though until
		//VerifyUser is run again after each new page load.
		if($this->IsGuest)
		{
			return true;
		}

		if(!$this->LoggedIn)
		{
			//we are not logged in so return true
			return true;
		}

		$remoteIP = $GLOBALS['globalref'][7]->getRemoteIP();
		
		$query = "select * from LoggedIn where UID=" . $this->UID . " and SessionID='" . session_id() . "' and IPAdd='" . $remoteIP . "'";
		$result = $GLOBALS['globalref'][1]->Query($query);

		if(mysqli_num_rows($result))
		{
			return true;

		} else {
			
			$GLOBALS['globalref'][4]->LogEvent("ODOUSERVERIFYFAILED", "User verify failed. Session ID, IP Address, or UID has changed since login", 2);
			return false;
		}

	}


    /*********************************************
    *Loads the groups for the current user
    *********************************************/
	private function LoadUserGroups() {
    
        if($this->UID > 0) {
        
            $query = "SELECT ODOUserGID.GID as GID, ODOGroups.GroupName as Name FROM ODOUserGID, ODOGroups WHERE ODOUserGID.UID={$this->UID} AND ODOUserGID.GID=ODOGroups.GID";
            $result = $GLOBALS['globalref'][1]->Query($query);

            while($row = mysqli_fetch_assoc($result)) {
                
                $Name = strtoupper($row["Name"]);
                
                $this->groups[$Name] = $row["GID"];
                
            }
            
            //sort by name
            if(sizeof($this->groups)>0) {
            	ksort($this->groups);
            }
            
        }
    
    }
    
    /**
     * Builds an array of user group ids sorted by Name
     * This is used as a partial to the key used in page caching
     */
    public function GetUserGroupIDArray() {
    	
    	$groupKey = "";
    	
    	foreach($this->groups as $Name=>$GID) {
    		$groupKey .= $GID;	
    	}
    	
    	return $groupKey;
    	
    }

    /*********************************************
    *Clears the groups array and creates a new one
    **********************************************/
    private function ClearUserGroups() {
       
        foreach($this->groups as $Name=>$GID) {
            unset($this->groups[$Name]);
        }
       
       $this->groups = array();
       
    }


    /***********************************************
    *Checks the current user's groups for the passed
    *Name value
    *@param Name String of group to search
    *@return boolean if in group true otherwise false
    ************************************************/
    public function InGroup($Name) {
    
        $Name = strtoupper($Name);
        
        if(isset($this->groups[$Name])) {
            
            return true;
            
        } else {
            
            return false;
            
        }
        
    }

	//gets the highest hash level possible
	function GetHashMode() {
		if((defined('CRYPT_SHA512'))&&(CRYPT_SHA512 == 1)&&(defined('ODOSHA'))&&(ODOSHA == 1)) {
			return 1;
		} elseif((defined('CRYPT_SHA256'))&&(CRYPT_SHA256 == 1)&&(defined('ODOSHA'))&&(ODOSHA <= 2)) {
			return 2;
		} else {
			return 3;
		}
	}
	
	//if a mode that is requested isn't available we just return the empty string
	//and log the issue.
	function ODOHash($message, $mode) {
		if(($mode == 1)&&(defined('CRYPT_SHA512'))&&(CRYPT_SHA512 == 1)&&(defined('ODOSHA'))) {
			return crypt($message,$this->SHA512h);
		} elseif(($mode <= 2)&&(defined('CRYPT_SHA256'))&&(CRYPT_SHA256 == 1)&&(defined('ODOSHA'))) {
			return crypt($message,$this->SHA256h);
		} else {
			
			if($mode != 3) {
				$GLOBALS['globalref'][4]->LogEvent("HASHNOTICE", "Requested mode was " . $GLOBALS['globalref'][1]->EscapeMe($mode) . " but mode is not available for hashing. Using md5 sum instead.", 5);
			}
			
			return md5($message);
		}
	}
	
	/*******************************************************
	*Name: GetLoginForm
	*Desc: This will build a simple login form using divs. By
	*default we use a standard submit button. If JSVALIDATELOGIN
	*is defined and set to 1 we will use a button with an onclick event
	*to call loginvalidate. If loginphp is defined we redirect the login
	*form to the login page. 
	*******************************************************/
	function GetLoginForm($ShowForgotPassword) {
		
		$RValue = "\n<div id=\"loginbox\" class=\"loginbox\">\n<div id=\"logintitle\" class=\"logintitle\">Login</div>\n<div id=\"logincontent\" class=\"logincontent\">\n<FORM action=\"";
		
		
		if(defined("loginphp")) {

			$RValue .= "login.php?";

		} else {
	
			$RValue .= "index.php?ObjectOnlyOutput=1&ob=ODOUserO&fn=Login&";
		}

		$RValue .= "pg=" . $_SESSION['ODOSessionO']->pg . "\" name=\"loginform\" method=\"POST\" data-ajax=\"false\" enctype=\"application/x-www-form-urlencoded\">\n<div id=\"loginuserdiv\"><span id=\"loginuserlabel\" class=\"loginlabel\">Username:</span><span id=\"loginuser\" class=\"logintext\"><INPUT type=\"text\" id=\"loginusertext\" name=\"UNAME\" size=\"12\" maxlength=\"20\"></span></div>\n<div id=\"loginpassdiv\"><span id=\"loginpasslabel\">Password:</span><span id=\"loginpass\" class=\"logintext\"><INPUT type=\"password\" id=\"loginpasstext\" name=\"PWORD\" size=\"12\" maxlength=\"10\"></span></div>\n";
		
		if($ShowForgotPassword) {
			$RValue .= "<div id=\"loginresetlink\" class=\"loginresetlink\"><a href=\"";
			
			if(defined("loginphp")) {
				$RValue .= "login.php?";
			} else {
				$RValue .= "index.php?ob=ODOUserO&ObjectOnlyOutput=1&";
			}
			
			$RValue .= "pg=" . $GLOBALS['globalref'][0]->pg . "&fn=SelfPasswordResetPublic\">I Forgot My Password!</a></div>\n";
		
		}
		
		$RValue .= "<div id=\"loginsubmit\">";
		
		if((defined("JSVALIDATELOGIN"))&&(JSVALIDATELOGIN == 1)) {
			$RValue .= "<INPUT type=\"button\" id=\"loginsubmit\" name=\"login\" value=\"login\" onclick=\"loginvalidate();\">";
		} else {
			$RValue .= "<INPUT type=\"submit\" id=\"loginsubmit\" name=\"login\" value=\"login\">";
		}
		
		$RValue .= "\n</div>\n</FORM>\n</div>\n</div>\n";
		
		return $RValue;
		
	}
	
	/************************************************
	*Name: GetChangePassForm
	*Desc: builds the change password form html
	***********************************************/
	function GetChangePassForm() {
		
		$RValue = "\n<div id=\"loginbox\" class=\"loginbox\">\n<div id=\"logintitle\" class=\"logintitle\">Change Password</div>\n<div id=\"logincontent\" class=\"logincontent\">\n<FORM action=\"";
		
		
		if(defined("loginphp")) {

			$RValue .= "login.php?fn=ChangePassword&";

		} else {
	
			$RValue .= "index.php?ObjectOnlyOutput=1&ob=ODOUserO&fn=ChangePasswordPublic&";
		}

		$RValue .= "pg=" . $_SESSION['ODOSessionO']->pg . "\" name=\"loginform\" method=\"POST\" data-ajax=\"false\" enctype=\"application/x-www-form-urlencoded\">\n<div id=\"loginuserdiv\"><span id=\"loginuserlabel\" class=\"loginlabel\">Username:</span><span id=\"loginuser\" class=\"logintext\">" . $this->username . "</span></div>\n";
		
		//old login pass
		$RValue .= "<div id=\"loginoldpassdiv\"><span id=\"loginoldpasslabel\" class=\"loginlabel\">Old Password:</span><span id=\"loginoldpass\" class=\"logintext\"><INPUT type=\"password\" id=\"loginoldpasstext\" name=\"OldPword\" size=\"12\" maxlength=\"10\"></span></div>\n";
		
		//new login pass
		$RValue .= "<div id=\"loginnewpassdiv\"><span id=\"loginnewpasslabel\" class=\"loginlabel\">New Password:</span><span id=\"loginnewpass\" class=\"logintext\"><INPUT type=\"password\" id=\"loginnewpasstext\" name=\"NewPword\" size=\"12\" maxlength=\"10\"></span></div>\n";
		
		//new login pass confirm
		$RValue .= "<div id=\"loginnewpassconfirmdiv\"><span id=\"loginnewpassconfirmlabel\" class=\"loginlabel\">Confirm New:</span><span id=\"loginnewpassconfirm\" class=\"logintext\"><INPUT type=\"password\" id=\"loginnewpassconfirmtext\" name=\"ConfirmPword\" size=\"12\" maxlength=\"10\"></span></div>\n<div id=\"loginsubmit\">";
		
		if((defined("JSVALIDATELOGIN"))&&(JSVALIDATELOGIN == 1)) {
			$RValue .= "<INPUT type=\"button\" id=\"loginsubmit\" name=\"login\" value=\"login\" onclick=\"loginvalidate();\">";
		} else {
			$RValue .= "<INPUT type=\"submit\" id=\"loginsubmit\" name=\"login\" value=\"login\">";
		}
		
		$RValue .= "</div>\n</FORM>\n</div>\n</div>\n";
		
		return $RValue;
	}
	
	/***************************************************
	*Name: GetResetQuestionForm
	*Desc: Builds the reset question form
	***************************************************/
	function GetResetQuestionForm($username, $rquestion) {
		
		$RValue = "\n<div id=\"loginbox\" class=\"loginbox\">\n<div id=\"logintitle\" class=\"logintitle\">Login</div>\n<div id=\"logincontent\" class=\"logincontent\">\n<FORM action=\"";
		
		
		if(defined("loginphp")) {

			$RValue .= "login.php?fn=SelfPasswordResetPublic&";

		} else {
	
			$RValue .= "index.php?ObjectOnlyOutput=1&ob=ODOUserO&fn=SelfPasswordResetPublic&";
		}

		$RValue .= "pg=" . $_SESSION['ODOSessionO']->pg . "\" name=\"loginform\" method=\"POST\" data-ajax=\"false\" enctype=\"application/x-www-form-urlencoded\">\n<div id=\"loginuserdiv\"><span id=\"loginuserlabel\" class=\"loginlabel\">Username:</span><span id=\"loginuser\" class=\"logintext\">";

		if((isset($username))&&(!is_null($username))&&(strlen($username)>0)&&(isset($rquestion))&&(!is_null($rquestion))&&(strlen($rquestion)>0)) {
			
			$RValue .= "<input type=\"hidden\" id=\"loginusertext\" name=\"UNAME\" value=\"" . htmlentities($username) . "\">" . htmlentities($username) . "</span></div>\n<div id=\"loginresetqdiv\"><span id=\"loginresetqlabel\" class=\"loginlabel\">Reset Question: </span><span id=\"loginresetq\" class=\"logintext\">" . $rquestion . "</span></div>\n<div id=\"loginresetadiv\"><span id=\"loginresetalabel\" class=\"loginlabel\">Reset Answer: </span><span id=\"loginreseta\" class=\"logintext\"><INPUT type=\"text\" id=\"loginresetatext\" name=\"ResetA\" size=\"25\" maxlength=\"100\"></span></div>\n<div id=\"loginsubmit\">";
			
			if((defined("JSVALIDATELOGIN"))&&(JSVALIDATELOGIN == 1)) {
				$RValue .= "<INPUT type=\"button\" id=\"loginsubmit\" name=\"change\" value=\"change\" onclick=\"loginvalidate();\">";
			} else {
				$RValue .= "<INPUT type=\"submit\" id=\"loginsubmit\" name=\"change\" value=\"change\">";
			}
			
		} else {
			
			$RValue .= "<input type=\"text\" id=\"loginusertext\" name=\"UNAME\" size=\"12\" maxlength=\"20\"></span></div>\n<div id=\"loginsubmit\">";
			
			if((defined("JSVALIDATELOGIN"))&&(JSVALIDATELOGIN == 1)) {
				$RValue .= "<INPUT type=\"button\" id=\"loginsubmit\" name=\"GetResetQuestion\" value=\"Get Reset Question\" onclick=\"loginvalidate();\">";
			} else {
				$RValue .= "<INPUT type=\"submit\" id=\"loginsubmit\" name=\"GetResetQuestion\" value=\"Get Reset Question\">";
			}
		}
		
		$RValue .= "\n</div>\n</FORM>\n</div>\n</div>\n";
		
		return $RValue;
	}
	
	/**
	 * Reloads the user's groups
	 */
	public function reloadGroups() {
		
		//reset
		$this->ClearUserGroups();
		
		//load
		$this->LoadUserGroups();
		
	}
	
	function __sleep() {
		$this->SHA256h = "";
		$this->SHA512h = "";
		return( array_keys( get_object_vars( $this ) ) );
	}
	
	function __wakeup() {
		$enc = new ourConstants();
		
		$GLOBALS['globalref'][3] =& $this;
		$this->SHA256h = $enc->getSHA256h();
		$this->SHA512h = $enc->getSHA512h();
	}
	
}





class ODOLogging {
    
    private $logQueue;
    private $emailQueue;
    
	//this will use the DB object to log system events
	//it will mail logs if need be
	//used heavily with the error object
	function __construct() {
		global $globalref;
        	$globalref[4] = &$this;
        $this->logQueue = array();
        $this->emailQueue = array();
        
	}

	function DebugLog($ActionCode, $Comment) {
		global $globalref;
		$UID = $globalref[3]->getUID();
		//check for negative UID. Value in table is unsigned
		if($UID < 0) {
			$UID = 0;
		}
		
		if(strlen($Comment)>255) {
			$Comment = substr($Comment, 0, 255);
		}
		
		if(strlen($ActionCode)>20) {
			$ActionCode = substr($ActionCode, 0, 20);
		}
		
		$debugArray = debug_backtrace();
		
		$stackTraceStr = print_r($debugArray, true);
		
		$message = $ActionCode . $Comment . ":" . $stackTraceStr;
		
		error_log($message);
		
	}
	
	
	function LogEvent($ActionCode, $Comment, $SevLevel, $TransactionAware=true) {
		global $globalref;
		$UID = $globalref[3]->getUID();
		//check for negative UID. Value in table is unsigned
		if($UID < 0) {
			$UID = 0;
		}
	
		if(strlen($Comment)>255) {
			$Comment = substr($Comment, 0, 255);
		}
		
		if(strlen($ActionCode)>20) {
			$ActionCode = substr($ActionCode, 0, 20);
		}
		
        
            
        $Event = new ODOLogEvent();
            
        $Event->UID = $UID;
        $Event->Comment = $GLOBALS['globalref'][1]->EscapeMe($Comment);
        $Event->ActionCode = $GLOBALS['globalref'][1]->EscapeMe($ActionCode);
        $Event->SevLevel = $GLOBALS['globalref'][1]->EscapeMe($SevLevel);
		
        //if the event log is not transaction aware then we need to queue it
        //so we can log it after the rollback is complete and the transaction is over
        if((!$TransactionAware)&&($GLOBALS['globalref'][1]->checkInTransaction())) {
            array_push($this->logQueue, $Event);
        } else {
            //if the event is transaction aware then we allow mysql to manage it
            $this->LogEventObject($Event);
        }
            
        if( ($SevLevel <= EMAILTHRESHOLD) && EMAILEVENTS) {
        
            $headers  = 'MIME-Version: 1.0' . "\n";
            $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\n";
            $headers .= 'From: ' . SYSADMINEMAILADDY . "\n";
            $headers .= 'Reply-To: ' . SYSADMINEMAILADDY . "\n";
            $headers .= 'X-Mailer: ODOWeb' . "\r\n";

            $message = "This is an e-mail from the ODOWeb service at " . $_SERVER['SERVER_NAME'] . ".\n\n";
            $message = $message . "An event has been recorded with a severity level of " . $SevLevel . ". You have requested that all events with this high of a severity be e-mailed to you. To turn off this feature please set the constant EMAILEVENTS to false or change your e-mail threshold.\n\n";
            $message = $message . "EVENT ACTION CODE: " . $ActionCode . "\nCaused by User ID: " . $UID . "\nComments: " . $Comment . "\n\n";
               
            $EmailMsg = new ODOMailMessage();
            
            $EmailMsg->to = SYSADMINEMAILADDY;
            $EmailMsg->subject = "Logged Event from " . SERVERNAME;
            $EmailMsg->message = $message;
            $EmailMsg->headers = $headers;
            
            //if the event is transaction aware then we do not want to send
            //a message until the commit is completed
            if(($TransactionAware)&&($GLOBALS['globalref'][1]->checkInTransaction())) {
                array_push($this->emailQueue, $EmailMsg);
            } else {
                //if it is not transaction aware we just go ahead and
                //email it now
                $this->EmailMessageObject($EmailMsg);
            }
 
 
        }
        
	}

    /*******************************************
    *Logs the event object passed
    *@param LogEvent LogEventObject
    *@param OverrideSystemLog boolean
    *******************************************/
    public function LogEventObject($LogEvent, $OverrideSystemLog=false) {
        
        $forwardedFor = "";
        $instanceId = "";
        
        $remoteIP = $GLOBALS['globalref'][7]->getRemoteIP();
        
        if((defined('INSTANCEIDHEADER')) && (strlen(INSTANCEIDHEADER)>0) && (isset($_SERVER['HTTP_' . INSTANCEIDHEADER]))) {
        	$instanceId = $_SERVER['HTTP_' . INSTANCEIDHEADER];
        }
        
    	//if user set the constant to use the system log instead of odo's system logger then
    	//log to that instead. We still provide an override in case they only want to log certain 
    	//severities or directly
    	if((defined("USESYSTEMLOG")) && (USESYSTEMLOG == 1) && (!$OverrideSystemLog)) {
    		
    		$message = "UID: {$LogEvent->UID}, Action: {$LogEvent->ActionCode}, SevLevel: {$LogEvent->SevLevel}, RIP: {$remoteIP}, Comment: {$LogEvent->Comment}, ForwardedFor: {$forwardedFor}";
    		
    		error_log($message);
    		
    	} else {
    		
    		$Query = "insert into ODOLogs ( UID, ActionDone, Comment, Severity, IP, ForwardedFor, InstanceId, ModID ) values ( {$LogEvent->UID}, '{$LogEvent->ActionCode}', '{$LogEvent->Comment}', {$LogEvent->SevLevel}, '{$remoteIP}'";
    		
    		if(strlen($forwardedFor) > 0 ) {
    			$Query .= ", '{$forwardedFor}'";
    		} else {
    			$Query .= ", NULL";
    		}
    		
    		if(strlen($instanceId) > 0 ) {
    			$Query .= ", '{$instanceId}'";
    		} else {
    			$Query .= ", NULL";
    		}
    		
    		$Query .= ", {$LogEvent->ModID})";
    		
    		$result = $GLOBALS['globalref'][1]->Query($Query);
    		
    	}
         
    }

    /*********************************************
    *Simply calls the PHP mail function
    *@param EmailObj email object
    *@return boolean true or false
    *********************************************/
    private function EmailMessageObject($EmailObj) {
  
    	//check for remote server settings
    	if(defined("USEREMOTEEMAILSERVER")&&(USEREMOTEEMAILSERVER == 1)) {
    		

    		$query = "INSERT INTO MailQueue(rcvMail, subject, headers, message, encrypted) values(?,?,?,?,";
    		
    		if(defined("REMOTEEMAILENCRYPTED")&&(REMOTEEMAILENCRYPTED == 1)) {
    			
    			$query .= "1)";
    			
    		} else {
    			
    			$query .= "0)";
    			
    		}
    		 
    		
    		$mStm = $GLOBALS['globalref'][1]->prepare($query);
    		
    		$mStm->bind_param("ssss", $EmailObj->to, $EmailObj->subject, $EmailObj->headers, $EmailObj->message);
    		 
    		if(!$mStm->execute ()) {
    		
    			$Comment = "Error dropping an e-mail to MailQueue";
    		
    			$this->LogEvent("EMAILERROR", $Comment, 100);
    		
    			return false;
    		}

    		if($mStm->affected_rows == 0) {
    				
    			$Comment = "Error dropping an e-mail to MailQueue no rows inserted";
    		
    			$this->LogEvent("EMAILERROR", $Comment, 100);
    		
    			return false;
    		}
    		
    	} else {
    		
    		//else submit mail using local
    		if(!mail($EmailObj->to, $EmailObj->subject, $EmailObj->message, $EmailObj->headers)) {
    			return false;
    		} 
    		
    	}
    	
		return true;
         
    }

    /*******************************************
    *emails immediately or delayed based on 
    *transaction aware status
    *@param EmailObj email object
    *@return boolean true or false
    *******************************************/
    function TransactionAwareEmail($EmailObj) {
        
        if($GLOBALS['globalref'][1]->checkInTransaction()) {
                array_push($this->emailQueue, $EmailObj);
        } else {
                //if it is not transaction aware we just go ahead and
                //email it now
                if(!$this->EmailMessageObject($EmailObj)) {
                    return false;
                }
        }
    
        return true;
        
    }

    /*****************************************
    *Method is called by ODODB to process
    *delayed logs and delayed emails
    ******************************************/
    public function processAfterTransaction($rollBack=false) {
    
        //log events that are transaction aware were already
        //rolledback. the logqueue are for events that we always submit and
        //are NOT transaction aware
        foreach($this->logQueue as $LogEvent) {
            $this->LogEventObject($LogEvent);
        }
    
        //the e-mail queue is the opposite. If we have a transaction
        //aware e-mail then we want to delay the mail until we know for
        //certain if we are to submit it. If rollback is set then we do
        //not set ti
        if(!$rollBack) {
            foreach($this->emailQueue as $emailMsg) {
                $this->EmailMessageObject($emailMsg);
            }
        }
    
    }

	function __wakeup() {
		$GLOBALS['globalref'][4] =& $this;
        $this->logQueue = array();
        $this->emailQueue = array();
    }

    function __sleep() {
        
        unset($this->logQueue);
        unset($this->emailQueue);
        
		return( array_keys( get_object_vars( $this ) ) );
        
    }

}

/**
 * E_USER_ERROR: always fatal error, Always display error page, will show error description if show errors flag is set
 * E_USER_WARNING: non fatal error, Displays error page only when exit on all errors is set. Will show description of error if show errors is set. Non fatal errors implies that script triggering the error will continue on and display an application specific message.
 * E_USER_NOTICE: non fatal error, Display error page only when exit on all errors is set. Will show description of error if show errors is set. Non fatal errors implies that script triggering the error will continue on and display an application specific message. 
 * default: always fatal error, Always display error page, will show error description if show errors flag is set
 */
class ODOError {
	//log an error
	//report error to user
	var $oldErrorHandler;
    var $ajaxResponse;

	
	function __construct() {
		$GLOBALS['globalref'][5] =& $this;
		
		if(!defined('E_ODOWEB_APPERROR')) {
                   define('E_ODOWEB_APPERROR', -1);
                }
		
		$this->oldErrorHandler = set_error_handler(array($this, 'ODOErrorHandler'));
        $this->ajaxResponse = false;
	}
	
	function ODOErrorHandler($errno, $errstr, $errfile, $errline)
	{
		
		//return false so application can attempt to
		//catch the error. Otherwise continue
		if($errno == E_ODOWEB_APPERROR) {
			return false;	
		}
		
		//dump script if turned on
		if(DUMPEVALERROR) {
					
			$filename = DUMPEVALDIR;
			$filename .= date('Ymd-His');
			$filename .= "_";
			$filename .= $GLOBALS['globalref'][3]->getusername();
			$filename .= ".txt";
			file_put_contents($filename, $GLOBALS['globalref'][6]->ScriptToRun);
		}
		
		//rollback if in transaction
        if($GLOBALS['globalref'][1]->checkInTransaction()) {
            $GLOBALS['globalref'][1]->rollBack();
        }
    
        $Comment = "ErrorNumber: " . $errno . "; Error: " . $errstr . "; ErrorLine#: " . $errline . "; ErrorFile: " . $errfile;
        $errContent = "<div id=\"odoweberr\">$errstr</div>";
        $errContentDetail = "<div id=\"odoweberr\"><center><H3>ERROR:</H3> [$errno] $errstr <br> on line $errline in file $errfile</center></div>";
        $content = "";
        
        $jsonError = new JSONResponse();
        $jsonError->errorFlag = true;
        $jsonError->errorMsg = $errstr;
        
        if(SHOWERRORS == 1) {
        	$errContent = "<div id=\"odoweberr\">$errstr</div>";
        } elseif(SHOWERRORS == 2) {
        	$errContent = "<div id=\"odoweberr\"><center><H3>ERROR:</H3> [$errno] $errstr <br> on line $errline in file $errfile</center></div>";
        }
      
        $content = $this->buildErrorContent($errContent);
        
		switch ($errno) {

			case E_USER_ERROR:
				
				$GLOBALS['globalref'][4]->LogEvent("USERERROR", $Comment, 2);
				 
				if($this->ajaxResponse) {
					echo json_encode($jsonError);
				} else {
					if((!headers_sent())&&(SHOWERRORS > 0)) {
						header("HTTP/1.1 500 Internal Server Error");
					}
					echo $content;

				}
				
				exit(1);
				
    		case E_USER_WARNING:
    			
    			$GLOBALS['globalref'][4]->LogEvent("USERWARNING", $Comment, 4);
    			 
				if (SHOWERRORS > 0) {
					if ($this->ajaxResponse) {
						echo json_encode ( $jsonError );
						exit ( 1 );
					} else {
						
						if ((! headers_sent ()) && (SHOWERRORS > 0)) {
							header ( "HTTP/1.1 500 Internal Server Error" );
						}
						
						echo $content;
					}
				}
				
				if (EXITONALLERRORS) {
					exit ( 1 );
				}

    			
        		
				break;

    		case E_USER_NOTICE:
				
				$GLOBALS['globalref'][4]->LogEvent("USERNOTICE", $Comment, 4);
				
				if(SHOWERRORS > 0) {
					if($this->ajaxResponse) {
						echo json_encode($jsonError);
						exit(1);
					} else {
				
						if((!headers_sent())&&(SHOWERRORS > 0)) {
							header("HTTP/1.1 500 Internal Server Error");
						}
				
				
						echo $content;
				
					}
				}
				
				if(EXITONALLERRORS) {
					exit(1);
				}
					
				
        		break;

    		default:
					
				$GLOBALS['globalref'][4]->LogEvent("UNKOWNERROR", $Comment, 2);
					
		
				if($this->ajaxResponse) {
					echo json_encode($jsonError);
				} else {
					if((!headers_sent())&&(SHOWERRORS > 0)) {
						header("HTTP/1.1 500 Internal Server Error");
					}
					echo $content;

				}
				
				exit(1);
				
				break;
    		}

    		return true;
		
	}

	private function buildErrorContent($errContent) {
	
		$content = "";
	
		//set heder if we are outputting the
		//setup error display
		if(strlen($GLOBALS['globalref'][6]->CustomErrorPage) > 0) {
	
			$content = $GLOBALS['globalref'][6]->CustomErrorPage;
	
			//replace content with error message. if showErrors is true
			$content = str_replace("<div id=\"odoweberr\"></div>", $errContent, $content);
	
	
		} else {
	
			$content = "<html><head></head><body><center>\n";
	
			$content .= $errContent;
	
			$content .= "<br>Fatal Error Aborting...<br/></center>\n</body></html>";
	
		}
	
		return $content;
	
	}
	
	function __wakeup() {
		$GLOBALS['globalref'][5] =& $this;
		
                if(!defined('E_ODOWEB_APPERROR')) {
                   define('E_ODOWEB_APPERROR', -1);
                }

		$oldErrorHandler = set_error_handler(array($this, 'ODOErrorHandler'));
		$this->ajaxResponse = false;
		
	}
}


class ODOPage {
	//stores existing pageid
	var $PageID;
	var $ObjectCode;
	var $Content;
	var $IsDynamic;
	var $IsAdmin;
	var $ScriptToRun;
	var $CustomErrorPage;
	var $ModuleID;
	var $ModuleName;
	
	function __construct() {
		$GLOBALS['globalref'][6] =& $this;
		$this->PageID = -1;
		$this->ObjectCode = "";
		$this->Content = "";
		$this->IsDynamic = 0;
		$this->IsAdmin = 0;
		$this->CustomErrorPage = null;
		$this->ScriptToRun = "";
		$this->ModuleID = 0;
		$this->ModuleName = "";
        $this->LoadCustomErrorPage();
	}

	private function LoadCustomErrorPage() {
        
		$query = "SELECT PageContent FROM ODOPages WHERE PageName='CustomErrorPage'";
        
        $result = $GLOBALS['globalref'][1]->Query($query);
        
        if(mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $this->CustomErrorPage = $row["PageContent"];
        }
    
	}
	
	function __sleep() {
		$this->PageID = -1;
		$this->ObjectCode = "";
		$this->Content = "";
		$this->ScriptToRun = "";
		$this->IsDynamic = 0;
		$this->IsAdmin = 0;
		$this->ModuleID = 0;
		$this->ModuleName = "";
		
		return( array_keys( get_object_vars( $this ) ) );
	}
	
	function __wakeup() {
		$GLOBALS['globalref'][6] =& $this;
		$this->PageID = -1;
		$this->ObjectCode = "";
		$this->Content = "";
		$this->ScriptToRun = "";
		$this->IsDynamic = 0;
		$this->IsAdmin = 0;
		$this->ModuleID = 0;
		$this->ModuleName = "";
		
	}
}

class ODOUtil {
	//contains standard utils with a few minor changes

	private $aesKey;

	function __construct() {
		$enc = new ourConstants();
		
		$GLOBALS['globalref'][7] =& $this;
		$this->aesKey = $enc->getAesKey();
		
	}
	
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
	
	function ODOUrlEncode($varname) {
		//replace arg divider
		//sorry if this breaks your code but you should know better than to use & or &amp as a var!
		//This function should only be called to build a URL and not for form data. This is used in ODO
		//code where we have to 
		$Rvalue = "";
		$varvalue = NULL;
		if(isset($_GET[$varname])) {
			$varvalue = $_GET[$varname];
		}
		
		//again we overwrite get with post
		if(isset($_POST[$varname])) {
			$varvalue = $_POST[$varname];
		}
		
		if(isset($varvalue)) {
			$replace = array("&amp;","&amp","&");
			$Rvalue = str_ireplace($replace,"_",$varvalue);
			$Rvalue = urlencode($Rvalue);
		}
		
		return $Rvalue;
		
	}
	
	function ODOPrepareObjectToBlob($obj, $complevel="medium") {
		$complevel = strtolower($complevel);
		$compint = 4;
		if($complevel == "low") {
			$compint = 1;
		} elseif($complevel == "medium") {
			$compint = 4;
		} elseif($complevel == "high") {
			$compint = 9;
		} else {
			$compint = 4;
		}

		return (bzcompress(serialize($obj),$compint));

	}

	function ODOGetObjectFromBlob($blob) {
		return (unserialize(bzdecompress($blob)));
	}

	function isMcryptAvailable() {
		if((function_exists('sodium_pad'))&&(function_exists('sodium_crypto_secretbox'))&&(defined('SODIUM_LIBRARY_VERSION'))&&
				(defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES'))&&(defined('ENCRYPTODOUSER'))&&(ENCRYPTODOUSER == 1)) {
		return 1;
		} else {
			return 0;
		}
	}
	
	/********************************************
	*Name: ODOEncrypt
	*Desc: Encrypts the data in encData and
	*populates the iv in the object. If IV is not set in the object 
	*then it will attempt to use the current ODOUser's IV
	*Param: encData EncryptedData object 
	*containing the data to encrypt
	*Returns: encData populated with encrypted
	*data and iv
	********************************************/
	function ODOEncrypt($encData) {

		
		if((function_exists('sodium_pad'))&&(function_exists('sodium_crypto_secretbox'))&&(defined('SODIUM_LIBRARY_VERSION'))&&
				(defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES'))&&(defined('ENCRYPTODOUSER'))&&(ENCRYPTODOUSER == 1)) {


			/* Intialize encryption */
			if($encData->useODOUserIV) {
				if(strlen($_SESSION["ODOUserO"]->getUserIV())>0) {
					$encData->iv = $_SESSION["ODOUserO"]->getUserIV();
				} else {
					$GLOBALS['globalref'][4]->LogEvent("SODIUMNOTICE", "Encryption was requested with an empty IV string.", 5);
				}
			} else {
				if(!strlen($encData->iv)>0) {
					$encData->iv = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
				} 
			}
		
			
			//order a-z so iv works correctly
			//if we are in the wrong order the decryption will fail
			ksort($encData->decArray);
			
			/* Encrypt data */
			foreach($encData->decArray as $Name=>$Value) {
				
				$padded_message = sodium_pad($Value, 16);
				$encData->encArray[$Name] = sodium_crypto_secretbox($padded_message, $encData->iv, $this->aesKey);

			}

			
			return $encData;

		} else {
			//no encryption is available. Populate the enc string 
			//with standard messages. IV will be empty string
			foreach($encData->decArray as $Name=>$Value) {
				
				$encData->encArray[$Name] = $Value;

			}
			
			return $encData;
		}

	}
	
	/***************************************************
	*Name: ODODecrypt
	*Desc: Decrypts the data contained in encData. If the IV
	*is not set then it will use the current ODOUser's IV.
	*Params: encData EncryptedData object that contains
	*the data to be decrypted
	*Returns: encData EncryptedData object with the 
	*decrypted array populated.
	***************************************************/
	function ODODecrypt($encData) {
		
		
		if((function_exists('sodium_crypto_secretbox_open'))&&(function_exists('sodium_unpad'))&&(defined('SODIUM_LIBRARY_VERSION'))&&
				(defined('ENCRYPTODOUSER'))&&(ENCRYPTODOUSER == 1)) {

			
			if($encData->useODOUserIV) {
				if(strlen($_SESSION["ODOUserO"]->getUserIV())>0) {
					$iv = $_SESSION["ODOUserO"]->getUserIV();
					$encData->iv = $iv;
				} else {
					$GLOBALS['globalref'][4]->LogEvent("SODIUMNOTICE", "Decryption was requested with an empty IV string.", 5);
				}
			} else {
				if(strlen($encData->iv)>0) {
					$iv = $encData->iv;
				} else {
					$GLOBALS['globalref'][4]->LogEvent("SODIUMNOTICE", "Decryption was requested with an empty IV string.", 5);
				}
			}
			
			//order a-z so iv works correctly
			//if we are in the wrong order the decryption will fail
			ksort($encData->encArray);
			
			/* Decrypt encrypted string */
			foreach($encData->encArray as $Name=>$Value) {
		
				$paddedMsg = sodium_crypto_secretbox_open($Value, $encData->iv, $this->aesKey);
				$encData->decArray[$Name] = sodium_unpad($paddedMsg, 16);
			}

		
			return $encData;
			
		} else {
			foreach($encData->encArray as $Name=>$Value) {
				$encData->decArray[$Name] = $Value;
			}
			return $encData;
		}
		
	}
	
	function getRemoteIP() {
		
		$remoteIP = $_SERVER['REMOTE_ADDR'];
		$forwardedFor = "";
		$instanceId = "";
		
		if((defined('FORWARDFORHEADER')) && (strlen(FORWARDFORHEADER)>0) && (isset($_SERVER['HTTP_' . FORWARDFORHEADER]))) {
			$forwardedFor = $_SERVER['HTTP_' . FORWARDFORHEADER];
			 
			$pos = stripos($forwardedFor, ",");
			 
			if(!$pos) {
		
				$remoteIP = trim($forwardedFor);
		
			} else {
		
				$remoteIP = trim(substr($forwardedFor, 0, $pos));
		
			}
			 
		}
		
		return $remoteIP;
		
	}
	
	function __sleep() {
		$this->aesKey = "";
		return( array_keys( get_object_vars( $this ) ) );
	}
	
	function __wakeup() {
		$enc = new ourConstants();
		
		$GLOBALS['globalref'][7] =& $this;
		$this->aesKey = $enc->getAesKey();
	}
	
}

/**********************************************
*Class: EncryptedData
*Desc: Provides a basic container for encrypted
*data. 
**********************************************/ 
class EncryptedData {
	var $encArray;
	var $decArray;
	var $sleepDec;
	var $publicData;
	var $iv;
	var $useODOUserIV;
	
	//Constructor
	function __construct() {
		$this->iv = "";
		$this->publicData = "";
		$this->encArray = array();
		$this->decArray = array();
		//turn on to allow decripted data to persist between requests
		$this->sleepDec = false;
		$this->useODOUserIV=false;
	}
	
	function __sleep() {
		if(!$this->sleepDec) {
			unset($decArray);
			$this->decArray = array();
		}
		return( array_keys( get_object_vars( $this ) ) );
	}
}

/********************************************
*Basic class to contain a log event
********************************************/
class ODOLogEvent {
    
    var $UID;
    var $Comment;
    var $ActionCode;
    var $SevLevel;
    var $ModID=0;

}

/*******************************************
*Basic class to contain an e-mail
*******************************************/
class ODOMailMessage {
     var $to;
     var $subject;
     var $message;
     var $headers;
    
}

class JSONResponse {
    var $errorFlag = false;
    var $errorMsg = "";
    var $errorCode = 0;
    var $success = true;
    var $successMsgCode = 0;
    var $successMsg = "";
    var $dbid = 0;
}

/***********************************************
 * Basic exception class for ODOWeb apps
 **********************************************/
class ODOWebAppException extends Exception { 
	
	public function __construct($message) {
		parent::__construct($message, -1);
	}
	
	// custom string representation of object
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
	
}

 
?>
