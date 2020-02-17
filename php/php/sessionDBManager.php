<?php
require_once "enc.php";
		
/**
 * Copyright (C) 2017  Bluff Point Technologies LLC
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
class SessionDBManager implements SessionHandlerInterface
{
	
	private $cached;

	private $mysqli;
	
	public function __construct() {
		
		$this->cached = false;
		
	}
	
    public function open($savePath, $sessionName)
    {
    	$enc = new ourConstants();
    	
    	$this->mysqli = new mysqli($enc->getDbsessipadd(), $enc->getDbsessuname(), $enc->getDbsesspword(), $enc->getDbsessname());
    		
    	if($this->mysqli->connect_errno) {
    		error_log('SesionDBManager could not connect: ' . $this->mysqli->connect_errno . ':' . $this->mysqli->connect_error);
    		return false;
    	}
    		
    	if(!$this->mysqli->set_charset("utf8")) {
    		error_log('SesionDBManager could not set charset!' . $this->mysqli->error);
    		return false;
    	}
    	
        return true;
    }

    public function close()
    {
    	if(!$this->mysqli->close()) {
    		error_log("Error closing SessionDBManager mysqli connection.");
    		return false;
    	}
    	
        return true;
    }

    public function read($id)
    {

        $blob = "";
    	
    	try {
    	
    		$query = "SELECT data FROM ODOSessions WHERE SessionID=?";
    	
    		$mStm = $this->mysqli->prepare($query);
    		
    		if((is_bool($mStm))&&(!$mStm)) {
    			throw new Exception('SessionDBManager Building statement failed for query:' . $query . "Error:" . $this->mysqli->errno . ":" . $this->mysqli->error);
    			
    		}
    		
    		$mStm->bind_param("s", $id);
    	
    		if(!$mStm->execute ()) {
    				
    			throw new Exception("SessionDBManager Error executing query!");
    		}
    	
    		/* store result */
    		$mStm->store_result();
    	
	    	//if previous value exists then try to resolve
    		if($mStm->num_rows > 0) {
    		
    			if(!$mStm->bind_result($data))
    			{
   					$mStm->close();
   					throw new Exception("SessionDBManager Error binding result!");
   				}
    		
    		
   				if($mStm->fetch()) {
    				
   					$blob = bzdecompress($data);
   					
   					$this->cached = true;
   					
    			} else {
    				throw new Exception("SessionDBManager Error fetching session data");
    			}
    		
    		}
    		
    		$mStm->close();
    	
    	} catch(Exception $ex) {
    		error_log("file:{$ex->getFile()} line:{$ex->getLine()} message:{$ex->getMessage()}");
    		echo "Internal session management error. Please try again later";
    		return null;
    	}
    	
    	
        return $blob;
    }

    public function write($id, $data)
    {
		try {
			
			
			$blob = bzcompress($data,4);
			
			if ($this->cached) {
				
				$query = "UPDATE ODOSessions SET data=?, RevisionTS=CURRENT_TIMESTAMP WHERE SessionID=?";
				
			} else {
				
				$query = "INSERT INTO ODOSessions(data, SessionID) values(?, ?)";
			}
			
			$mStm = $this->mysqli->prepare($query);
    		
    		if((is_bool($mStm))&&(!$mStm)) {
    			throw new Exception('SessionDBManager Building statement failed for query:' . $query . "Error:" . $this->mysqli->errno . ":" . $this->mysqli->error);
    			
    		}
				
			$mStm->bind_param("ss", $blob, $id);
				
			if (!$mStm->execute ()) {
					
				throw new Exception ( "Error executing query for id:{$id} Error: {$this->mysqli->errno}:{$this->mysqli->error}" );
			}
				
			// if previous value exists then try to resolve
			if($mStm->affected_rows == 0) {
					
				throw new Exception ( "Error storring session data." );
			}
				
		} catch ( Exception $ex ) {
			error_log("file:{$ex->getFile()} line:{$ex->getLine()} message:{$ex->getMessage()}");
			echo "Internal session management error. Please try again later";
			return false;
		}
    	
        return true;
    }
    
	public function destroy($id) {
		
		try {
			
			$query = "DELETE FROM ODOSessions WHERE SessionID=?";
			
			$mStm = $this->mysqli->prepare($query);
			
			if((is_bool($mStm))&&(!$mStm)) {
				throw new Exception('SessionDBManager Building statement failed for query:' . $query . "Error:" . $this->mysqli->errno . ":" . $this->mysqli->error);
				 
			}
			
			$mStm->bind_param ( "s", $id );
			
			if (! $mStm->execute ()) {
				
				throw new Exception ( "SessionDBManager Error executing query!" );
			}
			
			// if previous value exists then try to resolve
			if ($mStm->affected_rows == 0) {
				
				throw new Exception ( "SessionDBManager Error deleting session data." );
			}
				
			
		} catch ( Exception $ex ) {
			error_log("file:{$ex->getFile()} line:{$ex->getLine()} message:{$ex->getMessage()}");
			echo "Internal session management error. Please try again later";
			return false;
		}
		
		return true;
	}

    public function gc($maxlifetime)
    {
        try {
			
			
			$query = "DELETE FROM BPTPOINT.ODOSessions WHERE RevisionTS < subdate(current_timestamp(), interval ? second)";
			
			$mStm = $this->mysqli->prepare($query);
				
			if((is_bool($mStm))&&(!$mStm)) {
				throw new Exception('SessionDBManager Building statement failed for query:' . $query . "Error:" . $this->mysqli->errno . ":" . $this->mysqli->error);
					
			}
			
			$mStm->bind_param("i", $maxlifetime);
				
			if (!$mStm->execute()) {
					
				throw new Exception ( "Error executing query!" );
			}
				
			
		} catch(Exception $ex) {
			error_log("file:{$ex->getFile()} line:{$ex->getLine()} message:{$ex->getMessage()}");
			echo "Internal session management error. Please try again later";
			return false;
		}
		
		return true;

    }
    
}

$enc = new ourConstants();

if($enc->isUseDBSession()) {
	$handler = new SessionDBManager();
	session_set_save_handler($handler, true);
}

?>
