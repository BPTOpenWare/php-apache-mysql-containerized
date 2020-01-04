<?php

class ourConstants {
	
	/**
	 * AES Key for encryption
	 * @var String
	 */
	private $aesKey = "";
	
	/**
	 * Hash key
	 * @var String $SHA256h
	 */
	private $SHA256h = "";
	
	/**
	 * Hash key
	 * @var String
	 */
	private $SHA512h = "";
	
	/**
	 * MySql config IP/host
	 * @var String
	 */
	private $dbipadd = "";
	
	/**
	 * MySql config username
	 * @var String
	 */
	private $dbuname = "";
	
	/**
	 * MySql config password
	 * @var String
	 */
	private $dbpword = "";
	
	/**
	 * MySql config DB Name
	 * @var String
	 */
	private $dbname = "";
	
	/**
	 * Flag to use DB management of session
	 * @var unknown $useDBSession
	 */
	private $useDBSession = TRUE;
	
	/**
	 * MySql config IP/host
	 * @var String
	 */
	private $dbsessipadd = "";
	
	/**
	 * MySql config username
	 * @var String
	 */
	private $dbsessuname = "";
	
	/**
	 * MySql config password
	 * @var String
	 */
	private $dbsesspword = "";
	
	/**
	 * MySql config DB Name
	 * @var String
	 */
	private $dbsessname = "";
	
	function __construct() {
		
		$this->aesKey = "REPLACEMEWITHARANDOMKEYOFEQUALLN";
		
		//hash settings
		$this->SHA256h = '$5$rounds=5000$REPLACEMEWITHAHASHSALTKEY$';
		$this->SHA512h = '$6$rounds=5000$REPLACEMEWITHAHASHSALTKEY$';
		$this->dbipadd = "mysqlmaster";
		$this->dbuname = "TESTACCT";
		$this->dbpword = "testacct";
		$this->dbname = "BPTPOINT";
		$this->useDBSession = TRUE;
		$this->dbsessipadd = "mysqlmaster";
		$this->dbsessuname = "TESTACCTSESS";
		$this->dbsesspword = "testacctsess";
		$this->dbsessname = "BPTPOINTSESS";
	}
	
	public function getAesKey() {
		return $this->aesKey;
	}
	
	public function getSHA256h() {
		return $this->SHA256h;	
	}
	
	public function getSHA512h() {
		return $this->SHA512h;
	}
	
	public function getDbipadd() {
		return $this->dbipadd;
	}
	
	public function getDbuname() {
		return $this->dbuname;
	}
	
	public function getDbpword() {
		return $this->dbpword;
	}
	
	public function getDbname() {
		return $this->dbname;
	}
	
	public function isUseDBSession() {
		return $this->useDBSession;	
	}
	
	public function getDbsessipadd() {
		return $this->dbsessipadd;
	}
	
	public function getDbsessuname() {
		return $this->dbsessuname;
	}
	
	public function getDbsesspword() {
		return $this->dbsesspword;
	}
	
	public function getDbsessname() {
		return $this->dbsessname;
	}
	
	function __wakeup() {
		$this->aesKey = "REPLACEMEWITHARANDOMKEYOFEQUALLN";
		$this->SHA256h = '$5$rounds=5000$REPLACEMEWITHAHASHSALTKEY$';
		$this->SHA512h = '$6$rounds=5000$REPLACEMEWITHAHASHSALTKEY$';
		$this->dbipadd = "127.0.0.1";
		$this->dbuname = "TESTACCT";
		$this->dbpword = "testacct";
		$this->dbname = "BPTPOINT";
		$this->dbsessipadd = "127.0.0.1";
		$this->dbsessuname = "TESTACCTSESS";
		$this->dbsesspword = "testacctsess";
		$this->dbsessname = "BPTPOINTSESS";
		
	}
	
	function __sleep() {
	
		$this->aesKey = "";
		$this->dbipadd = "";
		$this->dbuname = "";
		$this->dbpword = "";
		$this->dbname = "";
		$this->dbsessipadd = "";
		$this->dbsessuname = "";
		$this->dbsesspword = "";
		$this->dbsessname = "";
		return( array_keys( get_object_vars( $this ) ) );
	
	}
}

?>