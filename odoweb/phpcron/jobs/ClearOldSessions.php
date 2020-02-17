<?php


/**
 * This isn't "thread safe" you shouldonly one run ClearOldSessions job
 * for all of your cron containers.
 */
require_once "/phpcron/enc.php";


$enc = new ourConstants();

// DB connection info^M
$link = null;

try {
	

	define ( "DBIPADD", $enc->getDbsessipadd() );
	define ( "DBUNAME", $enc->getDbsessuname() );
	define ( "DBPWORD", $enc->getDbsesspword() );
	define ( "DBNAME", $enc->getDbsessname() );
	

	$link = new mysqli ( DBIPADD, DBUNAME, DBPWORD, DBNAME );
	
	if ($link->connect_errno) {
		throw new Exception ( 'Could not connect: ' . $link->connect_errno . ":" . $link->connect_error );
	}
	
	
	if(!$link->set_charset("utf8")) {
		die('Could not set charset!' . $link->error);
	}
	
	
	$query = "DELETE FROM ODOSessions WHERE RevisionTS <= DATE_SUB(CURDATE(),INTERVAL 1 HOUR)";
	
	$result = $link->query ( $query ) or trigger_error ( 'Query failed at 103a: Query:' . $query . "Error:" . $link->error, E_USER_ERROR );
	
	
	
} catch ( Exception $ex ) {
	error_log ( "file:{$ex->getFile()} line:{$ex->getLine()} message:{$ex->getMessage()}" );
	
	
} finally {
	
	if($link != null) {
		$link->close();
	}
	
}




?>
