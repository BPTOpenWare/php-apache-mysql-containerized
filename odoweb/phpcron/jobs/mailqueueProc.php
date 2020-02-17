<?php

/**
 * This isn't "thread safe" you shouldonly one run mailqueueProc job
 * for all of your cron containers. 
 */
require_once "/phpcron/enc.php";
require '/phpcron/vendor/autoload.php';

$enc = new ourConstants();

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

}


/*******************************************
 *Basic class to contain an e-mail
 *******************************************/
class ODOMailMessage {
	var $id;
	var $to;
	var $subject;
	var $message;
	var $headers;
	var $encrypted;
	var $processed = FALSE;
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
	if ((function_exists ( 'sodium_crypto_secretbox_open' )) && (function_exists ( 'sodium_unpad' )) && (defined ( 'SODIUM_LIBRARY_VERSION' ))) {
		
		// order a-z so iv works correctly
		// if we are in the wrong order the decryption will fail
		ksort ( $encData->encArray );
		
		/* Decrypt encrypted string */
		foreach ( $encData->encArray as $Name => $Value ) {
			
			$paddedMsg = sodium_crypto_secretbox_open ( $Value, "", $enc->getAesKey () );
			$encData->decArray [$Name] = sodium_unpad ( $paddedMsg, 16 );
		}
		
		return $encData;
	} else {
		foreach ( $encData->encArray as $Name => $Value ) {
			$encData->decArray [$Name] = $Value;
		}
		return $encData;
	}
}
	
	// DB connection info^M
$link = null;

try {
	
	$localMailHostOption = "/usr/sbin/sendmail -t -i -S {$enc->getLocalMailHostPort()}";
	
	define ( "DBIPADD", $enc->getDbipadd () );
	define ( "DBUNAME", $enc->getDbuname () );
	define ( "DBPWORD", $enc->getDbpword () );
	define ( "DBNAME", $enc->getDbname () );
	define ( "LOCALMAILHOST", $localMailHostOption);
	
	$link = new mysqli ( DBIPADD, DBUNAME, DBPWORD, DBNAME );
	
	if ($link->connect_errno) {
		throw new Exception ( 'Could not connect: ' . $link->connect_errno . ":" . $link->connect_error );
	}
	

	if(!$link->set_charset("utf8")) {
		die('Could not set charset!' . $link->error);
	}
	
	
	$query = "SELECT idMailQueue, rcvMail, subject, headers, message, encrypted FROM MailQueue";
	
	$result = $link->query ( $query ) or trigger_error ( 'Query failed at 103a: Query:' . $query . "Error:" . $link->error, E_USER_ERROR );
	
	if (! mysqli_num_rows ( $result )) {
		error_log("no messages found.");
		$link->close();
		exit(0);
	}
	
	$messages = array();
	
	//build a list of all messages
	while($row = mysqli_fetch_assoc ( $result )) {
		
		$newMessage = new ODOMailMessage();
		
		if($row["encrypted"] == 1) {
			$encMessage = new EncryptedData();
			$encMessage->encArray["rcvMail"] = $row["rcvMail"];
			$encMessage->encArray["subject"] = $row["subject"];
			$encMessage->encArray["headers"] = $row["headers"];
			$encMessage->encArray["message"] = $row["message"];
				
			//decrypt messages if needed
			ODODecrypt($encMessage);
			
			$newMessage->headers = $encMessage->decArray["headers"];
			$newMessage->message = $encMessage->decArray["message"];
			$newMessage->subject = $encMessage->decArray["subject"];
			$newMessage->to = $encMessage->decArray["rcvMail"];
			$newMessage->id = $row["idMailQueue"];
			
		} else {
			
			$newMessage->headers = $row["headers"];
			$newMessage->message = $row["message"];
			$newMessage->subject = $row["subject"];
			$newMessage->to = $row["rcvMail"];
			$newMessage->id = $row["idMailQueue"];
			
		}
		
		array_push($messages, $newMessage);
	}
	
	//mail messages using service or local sendmail relay
	//else submit mail using local
	foreach($messages as $newMessage) {
		
		if($enc->isUseLocalMail()) {
			
			if(mail($newMessage->to, $newMessage->subject, $newMessage->message, $newMessage->headers, LOCALMAILHOST)) {
				$newMessage->processed = TRUE;
			} else {
				error_log("Error mailing email with id:{$newMessage->id}");
			}
			
		} else {
			
			$email = new \SendGrid\Mail\Mail();
			$email->setFrom($enc->getSendgridFromEmail(), $enc->getSendgridFromName());
			$email->setSubject($newMessage->subject);
			$email->addTo("test@example.com", "Example User");
			$email->addContent("text/html", $newMessage->message);
			$sendgrid = new \SendGrid($enc->getSendGridAPI());
			try {
				$response = $sendgrid->send($email);
				$newMessage->processed = TRUE;
	
			} catch (Exception $e) {
				error_log("Caught exception: {$e->getMessage()}");
			}
			
		}
		
		
	}
	
	$result->free();
	
	$link->autocommit(false);
	
	//delete in batch
	foreach($messages as $newMessage) {
	
		if($newMessage->processed) {
			
			$query = "DELETE FROM MailQueue WHERE idMailQueue={$newMessage->id}";
			
			$link->query($query);
			
			if($link->affected_rows < 1) {
				
				error_log("Unknown error deleting MailQueue id:{$newMessage->id}");

			}
			
		}
	}
	

	//commit if every row was inserted
	$success = $link->commit();
	
	if(!$success) {
		error_log("Error committing rolling back");
		$link->rollback();
	}
	
	$link->autocommit(true);
	
	
} catch ( Exception $ex ) {
	error_log ( "file:{$ex->getFile()} line:{$ex->getLine()} message:{$ex->getMessage()}" );
	
	if($link != null) {
		$link->close();
	}
	
	exit(1);
}



?>