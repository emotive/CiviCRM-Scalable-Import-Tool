<?php

require_once('lib/ez_sql_core.php');
require_once('lib/ez_sql_mysql.php');

class civicrm_import_db {
	
	protected $db;
	
	/*
	 * Constructor
	 */
	public function __construct($host, $db, $user, $pass) {
		$this->db = new ezSQL_mysql($user,$pass,$db,$host);
	}

}




?>