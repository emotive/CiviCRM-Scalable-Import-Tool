<?php

// Attempting to locate drupal base path
// by compare it to the current script path
$script_path = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['PHP_SELF'];
$base_path = substr($script_path, 0, strpos($script_path, '/sites/all/modules/'));
chdir($base_path);

require_once('./sites/default/civicrm.settings.php');

require_once('CRM/Core/Config.php');
require_once('CRM/Core/DAO.php');
$config =& CRM_Core_Config::singleton();

// CiviCRM APIs
/*require_once('api/v2/Contact.php');
require_once('api/v2/Location.php');
require_once('api/v2/GroupContact.php');
require_once('api/v2/Group.php');
require_once('api/v2/EntityTag.php');*/

require_once('api/api.php');

// Utilities
// if(!class_exists('parseCSV')) {
	require_once('lib/civicrm_import_csv.class.php');
// }

require_once('lib/ez_sql_core.php');
require_once('lib/ez_sql_mysql.php');
require_once('lib/class.phpmailer.php');

// internal classes
require_once('civicrm_import_util.class.php');
require_once('civicrm_import_db.class.php');
// validation class - currently using it on the client side (drupal)
require_once('civicrm_import_validate.class.php');

// the class
class civi_import_job extends civicrm_import_db {
	
	/*****************************
	 * Data members
	 *****************************/
	 
	 
	/* *********************************************
	 * Import options
	 *
	 * $options = array(
	 *			'cms_prefix' => '',
	 *			'line_split' => 5000,
	 *			'email' => array(
	 *				'to' => 'email@yourdomain.com',
	 *				'cc' => 'email1@yourdomain.com',
	 *				'from' => 'info@yourdomain.com',
	 *				'to_greeting' => 'John Doe',
	 *				'host' => 'smtp.gmail.com',
	 *				'ssl' => 1,
	 *				'port' => 465,
	 *				'user' => 'johndoe@example.com',
	 *				'pass' => '12345',
	 8				'toggle' => 1,
	 *			),
	 *			'db' => array(
	 *				'host' => 'db host',
	 *				'name' => 'db name',
	 *				'user' => 'db user',
	 *				'pass' => 'db pass',
	 *			),
	 *			'log' => array(
	 *				'logging' => TRUE,
	 *				'path' => full/path/to/log/files
	 *			),
	 *	);
	 */
	private $options = array();

	// CSV parser
	private $csv;
	
	// Logger and file splitter
	private $log;
	
	// PHP mailer
	private $mailer;
	
	// Import queue data fetched
	private $data;
	
	// CiviCRM contact API array of csv column => field
	private $contact_data = array();
	
	// CiviCRM location API array
	private $location_data = array();
	
	// Created Contacts using the contact API
	private $contacts = array();
	
	private $contact_imported = 0;
	
	private $location_imported = 0;
	
	// Error counter
	private $errors;
	
	/*
	 * Constructor
	 */
	 
	// Chang is here, some error checking here would be great
	public function __construct($options = array()) {
		
		// set options
		$this->options = $options;
		
		// start the db connection
		parent::__construct($this->options['db']['host'], $this->options['db']['name'], $this->options['db']['user'], $this->options['db']['pass']);
		
		// start the parser
		$this->csv = new civicrm_import_csv();
		$this->csv->encoding('UTF-8');
		
		// start the logger
		$this->log = new civicrm_import_utils($this->options['log']['path'], $this->options['log']['logging']);
		
		// start the mailer
		// #BUG: if mailing notification is disabled, don't bother to initiate the mailer
		$this->mailer = new PHPMailer();
		$this->mailer->SetFrom($this->options['email']['from'], 'Import Scheduler');
		$this->mailer->AddAddress($this->options['email']['to'], $this->options['email']['to_greeting']);
		$this->mailer->AddAddress($this->options['email']['cc']);
		$this->mailer->AltBody    = "To view the message, please use an HTML compatible email viewer!";
		
		if(isset($this->options['email']['host'])) {
			$this->mailer->IsSMTP(); 
			$this->mailer->Host = $this->options['email']['host'];
		}
		
		if(isset($this->options['email']['user'])) {
			$this->mailer->SMTPAuth = TRUE;
			$this->mailer->Username = $this->options['email']['user'];
			$this->mailer->Password = $this->options['email']['pass'];
		}
		if(isset($this->options['email']['ssl'])) {
			$this->mailer->SMTPSecure = 'ssl';
		}
		if(isset($this->options['email']['port'])) {
			$this->mailer->Port = $this->options['email']['port'];
		}		
	}
	
	/*
	 * Get and Set Method
	 */
	public function __get($variable) {
		return $this->$variable;
	}
	
	public function __set($variable, $value) {
		$this->$variable = $value;
	}
	
	/*
	 *********************************************************************
	 * Handle the overall importing logic
	 * 1. fetching the import job
	 * 2. Sort out all the mapping information
	 * 3. Update import job status
	 * 4. Process the import 
	 */
	public function init() {
	
		$this->fetch_import_job();
		$this->mapping_sort();
						
		$this->import_status_update('start');
		$this->mail('started');
		
		
		// splitting up the files
		$file_path = $this->_fetch_filepath();
		
		$split_files = $this->log->file_split($file_path, 'tmp_import_job' . $this->data->jobid . '_' , $this->options['line_split']);
		$offset = ($this->data->headings == 1) ? 1 : 0;
		
		if(is_array($split_files)) {
			// go through each split file and process the import
			for($i=0;$i<count($split_files);$i++) {
				if($i == 0) {
					$this->process_import($split_files[$i], $offset);
				} else {
					$this->process_import($split_files[$i]);
				}
			}
			for($i=0;$i<count($split_files);$i++) {
				unlink($split_files[$i]);
			}			
		} else {
			// in this case we are just parsing the string returned by the file splitter
			// #FIX: CSV parser chops last record if string input given
			$split_files .= '
			';
			$this->process_import($split_files, $offset);
		}

		// #FIX: 
		// stop gap measure for MySQL server time out after import is finished
		// Need to switch to Drupal db boot_strap for persistent connection
		$this->reset_db();
		
		// assuming if fatal errors occured before this step, this will never be called
		$this->file_delete();
		$this->import_status_update('finish');
		$this->mail('finish');
		
		exit();
	}
	
	/*
	 ***********************************************************************************
	 * Parse given CSV file or string and go through the civicrm API import process
	 *
	 * @params
	 * (string) input		|		the input csv filepath or the csv string 
	 * offset				|		if the first row should be ignored
	 *
	 * @returns
	 *
	 */		
	private function process_import($input, $offset = 0) {
		// parse the CSV file
		$this->_parse($input, $offset);
		
		// create contacts, custom fields
		$this->contact_create($this->data->type);
	
		// location data (address, phone numbers)
//		if(!empty($this->location_data)) {
//			$this->location_create($this->data->type);
//		}

		// import phone
		if(isset($this->location_data['address_home']) ||
		isset($this->location_data['address_billing'])) {
			$this->address_create($this->data->type);
		}
		
		// import address
		if(isset($this->location_data['phone_home']) ||
		isset($this->location_data['phone_work']) ||
		isset($this->location_data['phone_other'])
		) {
			$this->phone_create($this->data->type);	
		}

			
		// if we are doing an import job
		if($this->data->type == 'import') {
			
			// import groups
			if(!empty($this->data->import_groups)) {
				$this->groups_add();
			}
			// import tags
			if(!empty($this->data->import_tags)) {
				$this->tags_add();
			}
		}
	}
	
	/*
	 ***********************************************************************************
	 * Fetch the oldest submitted import job from the queue table and return the 
	 * import parameters
	 * 
	 * @returns (internal)
	 * (array) $this->data				|	key: jobid			value: jobid
	 *									|	key: fid			value: drupal fid from the {file} table
	 *									|	key: headings		value: 0 or 1 
	 *									|	key: dupe_check		value: 0 or 1
	 *									|	key: date_format	value: int
	 *									|	key: import_groups	value: serialized array
	 *									|	key: import_tags	value: serialized array
	 *									|	key: mapping		value: serialized array of matched column => field name
	 */	
	private function fetch_import_job() {
		
			$query = sprintf("SELECT * FROM %s 
					WHERE status = %d AND cron = %d 
					ORDER BY date_submitted asc LIMIT 0, 1", $this->options['cms_prefix'] . 'civicrm_import_job', 0, 0);
		
			$results = $this->db->get_results($query, OBJECT);
			
			// no job with cron = 0, status = 0 fetched, exit
			if(!$results) {
				exit('no active import job');
			} else {
				// return the import job object
				$this->data = $results[0];
			}
	}
	
	/*
	 ***********************************************************************************
	 * Update the current job status in the queue table 
	 * 
	 * Import status is controlled by two fields: status / cron
	 * 
	 * status determines what step the import is in
	 * 0 = not started
	 * 1 = importing contact (contact data)
	 * 2 = importing address data (location data [a, b]
	 * 3 = adding contacts to group(s)
	 * 4 = adding contacts to tag(s)
	 * 5 = complete
	 *
	 * cron determines the completion status of an import
	 * 0 = not started
	 * 1 = finished
	 * 2 = processing/error
	 *
	 * @params
	 *
	 * @returns
	 */
	private function import_status_update($status = 'start') {	

		$_query = "UPDATE %s %s WHERE jobid = %d";
		
		switch($status) {
			case 'finish':
				// get log file path and record them
				$logs = $this->log_files();
				$sub_query = sprintf("SET cron = 1, status = 5, date_complete = '%s', log = '%s'", date('Y-m-d H:i:s'), serialize($logs));
			break;
			case 'contact':
				$sub_query = "SET status = 1";
			break;
			case 'location':
				$sub_query = "SET status = 2";
			break;
			case 'group':
				$sub_query = "SET status = 3";
			break;
			case 'tag':
				$sub_query = "SET status = 4";
			break;
			case 'error':
				$sub_query = "SET cron = 99";
			break;
			case 'start':
			default:
				$sub_query = sprintf("SET cron = 2, date_started = '%s'", date('Y-m-d H:i:s'));
			break;	
		}
		
		$query = sprintf($_query, $this->options['cms_prefix'] . 'civicrm_import_job', $sub_query, $this->data->jobid); 
		
		if(!$this->db->query($query) ) {
			// if this happens, we will have to email, because can't execute the query
		}
	}
	
	/*
	 ***********************************************************************************
	 * Separate field matching parameters for contact API calls and location API calls
	 *
	 * @params (internal) 
	 * $this->data->mapping [serialized array]	|	{key: matched csv column	value: API field (subfield)}
	 * 
	 * @returns (internal)
	 * (array) $this->contact_data				|	key: matched csv column		value: contact API field
	 * (array) $this->location_data	see: _location_matching()
	 */
	private function mapping_sort() {
		$mapping = unserialize($this->data->mapping);
		
		foreach($mapping as $key => $value) {
		
			if($value == 'do_not_import') {
				unset($mapping[$key]);
			}
			
			switch($value) {
				case 'phone|home':
				case 'phone|work':
				case 'phone|other':
				case 'street_address|home':
				case 'supplemental_address_1|home':
				case 'supplemental_address_2|home':
				case 'city|home':
				case 'state_province|home':
				case 'postal_code|home':
				case 'postal_code_suffix|home':
				case 'street_address|billing':
				case 'supplemental_address_1|billing':
				case 'supplemental_address_2|billing':
				case 'city|billing':
				case 'state_province|billing':
				case 'postal_code|billing':
				case 'postal_code_suffix|billing':
					$this->location_data[$key] = $mapping[$key];
					unset($mapping[$key]);
				break;
			}
		}
		
		$this->contact_data = $mapping;
		$this->_location_matching();
	}
	
	/*
	 ***********************************************************************************
	 * Format the location_API colum match
	 *
	 * @params (internal) 
	 * (array) $this->location_data		|	key: matched csv column		value: API field (subfield)
	 * 
	 * @returns (internal)
	 * (array) $this->location_data		|	key: [phone_home] 			value: array( column => field_name)	
	 *									|	key: [phone_work]			value: array( column => field_name)	
	 *									|	key: [phone_other]			value: array( column => field_name)	
	 *									|	key: [phone_home]			value: array( column => field_name)	
	 *									|	key: [phone_billing]		value: array( column => field_name)											
	 */
	private function _location_matching() {

		foreach($this->location_data as $column => $value) {
			$_value = explode('|', $value);
			
			if($_value[0] == 'phone') {
				switch($_value[1]) {
					case 'home':
						$data['phone_home'][$column] = $_value[0];
					break;
					
					case 'work':
						$data['phone_work'][$column] = $_value[0];
					break;
					
					case 'other':
						$data['phone_other'][$column] = $_value[0];
					break;
				}
			} else {
				switch($_value[1]) {
					case 'home':
						$data['address_home'][$column] = $_value[0];
					break;
					
					case 'billing':
						$data['address_billing'][$column] = $_value[0];
					break;
				}
			}
			
			$this->location_data = $data;
		}
	}
	
	/*******************************************************************
	 * Parsing given (CSV) file or string input into 2D array
	 *
	 * @params
	 * (parseCSV) $this->csv		|		parseCSV object	initialized 
	 *										in the constructor
	 * 
	 * (string) $input				|		CSV file or string input
	 * (int)	$offset				|		Parse Offset
	 *
	 */
	private function _parse($input, $offset = 0) {
			// We never use heading TRUE because it will turn our
			// array into associative array
			$this->csv->heading = FALSE;
			$this->csv->offset = $offset;
			$this->csv->parse($input);
			
			// update the number of contacts
			$query = sprintf("SELECT contact_count FROM %s WHERE jobid = %d",
				$this->options['cms_prefix'] . 'civicrm_import_job',
				$this->data->jobid);
			
			$count = $this->db->get_var($query);
			$count+=count($this->csv->data);
			
			$this->db->query(
				sprintf("UPDATE %s SET contact_count = %d WHERE jobid = %d",
					$this->options['cms_prefix'] . 'civicrm_import_job',
					$count,
					$this->data->jobid)
			);	
	}	
		
	private function fetch_contact_id($external_identifier) {
		return $this->db->get_var(
			sprintf("SELECT id FROM civicrm_contact WHERE external_identifier = '%s'", $external_identifier)
		);
	}
	
	
	/*******************************************************************
	 * Parsing given (CSV) file or string input into 2D array
	 *
	 * @params
	 * #internal
	 * (array) $this->csv->data		|		key: row count				value: row array
	 * (array) $this->contact_data	|		key: column/field match		value: API call field
	 * 
	 * @returns
	 * #internal
	 * (array) $this->contacts		|		key: created_contact id		value: column/field array
	 */	
	private function contact_create($mode = 'import') {
		
		$this->import_status_update('contact');
		
		// bug fix for $this->contacts keep being appended
		if(!empty($this->contacts)) {
			unset($this->contacts);
			$this->contacts = array();
		}
		
		for($i = 0; $i< count($this->csv->data); $i++) {
				
			$param = array(
				'version' => '3',
				'contact_type' => 'Individual',
			);
			if($mode == 'import') {
				$param['dupe_check'] = ($this->data->dupe_check == 1) ? TRUE : FALSE;
				
				// #FIX issue 87: auto-append contact source if it is not mapped
				// appending the date and the job id to the import
				if(!in_array('contact_source', $this->contacts)) {
					$param['contact_source'] = date('Ymd') . '_' . $this->data->jobid;
				}
				
				// #FIX issue 72: Add email only de-dupe feature
				if($this->data->dupe_check == 2) {
					$mapping = array_flip($this->contact_data);
					
					$email = $this->csv->data[$i][$mapping['email']];
					$contact_id = $this->check_email($email);
					if(is_numeric($contact_id)) {
						// for email only dedupe, we delete the param and skip this iteration
						$this->log->_log('Error on CSV line ' . $i . ':, (Matching contact found:, '. $contact_id .'),' . implode(',', $this->csv->data[$i]), 'error_csv');
						unset($param);
						continue;					
					}
				}
			}
			
			// in case we are doing an append with *ONLY* Location data we still need to fill $this->contact
			if(count($this->contact_data) == 1) {
				$id = array_values($this->contact_data);
				$column = array_keys($this->contact_data);
				if($id[0] == 'external_identifier') {
					$contact_id = $this->fetch_contact_id($this->csv->data[$i][$column[0]]);
				} else {
					$contact_id = $this->csv->data[$i][$column[0]];
				}
				if(isset($contact_id) && $contact_id != '') {
					$this->contacts[$contact_id] = $this->csv->data[$i];
				} else {
					// unmatched append row
					$this->log->_log('Record not found in database:,' . implode(',', $this->csv->data[$i]), 'error_csv');
				}			
			} else {
				foreach($this->contact_data as $column => $field) {
					// dealing with some special fields because of CiviCRM's internal workings
					switch($field) {
						case 'birth_date':
							$param[$field] = civicrm_import_utils::format_date($this->csv->data[$i][$column], $this->data->date_format);
						break;
						case 'gender':
							$param[$field] = civicrm_import_utils::format_gender($this->csv->data[$i][$column]);
						break;
						// in appending job you have to get contact_id if they choose to match to external identifier
						case 'external_identifier':
							// get the contact id
							if($mode == 'import') {
								$param[$field] = $this->csv->data[$i][$column];
							} else {
								$param['contact_id'] = $this->fetch_contact_id($this->csv->data[$i][$column]);
							}
						break;					
						default:
							$param[$field] = $this->csv->data[$i][$column];
						break;
					}
				}
				// print_r($param);
				// data filtering validation: 
				// if the $param does not fit our validation requirement
				// i.e. First name, Last name, Email, we do not import them.
				// #FEATURE: $this->check_filter should return an array of bad fields so we can pin them down in 
				// the log
				if($this->check_filter($param, $mode)) {
				
					if($mode == 'import') {
						// @CiviCRM API (v3)
						$contact = civicrm_api('contact', 'create', $param);
					} else {
						if(isset($param['contact_id']) && $param['contact_id'] != '') {
							// @CiviCRM API (v3)
							$param['id'] = $param['contact_id'];
							unset($param['contact_id']);
							$contact = civicrm_api('contact', 'create', $param);
						} else {
							// log all the ones that did not find a matching record into the error_csv
							$this->log->_log('Error on CSV line ' . $i . ':, (No matching contact found with the id provided),' . implode(',', $this->csv->data[$i]), 'error_csv');
						}
					}
					// #FIXED: memory leak from API call
					CRM_Core_DAO::freeResult();
					
					if($contact['is_error'] == 1) {
						$this->log->_log('Error on CSV line ' . $i . ':, (' . $contact['error_message'] . '),' . implode(',', $this->csv->data[$i]), 'error_csv');
					} else {
						$this->contacts[$contact['id']] = $this->csv->data[$i];
						$this->contact_imported++;
						
						// record the contact import count for tracking
						if($this->contact_imported % 100 == 0) {
							$this->update_count('contact');
						}
					}
				} else {
					// log this row as bad row
					$this->log->_log('Error on CSV line ' . $i . ':, (Bad last name or email on CSV line),' . implode(',', $this->csv->data[$i]) , 'error_csv');
				}
				$this->update_count('contact');
			}
		}
		
		$this->log->_log($this->contact_imported . ' number of contact records created from ' . count($this->csv->data) . ' number of rows read from CSV file');
	
		// free the original parsed csv from memory
		unset($this->csv->data);
	}
	
	

	/***********************************************************************************************************
	 * Use CiviCRM API to create phone number of various types
	 *
	 * @params
	 * #internal
	 * (array) $this->location_data		|		key: phone_home				value: matched_column => field_name
	 *												 phone_work
	 *												 phone_other
	 *				
	 * (array) $this->contacts			|		key: contact_id				value: column/field array
	 *
	 * #external
	 * (string) $mode							The mode of the import: (import/append)
	 * 
	 * @return
	 * #internal
	 * null
	 */	
	private function phone_create($mode = 'import') {
		foreach($this->contacts as $contact_id => $column_field_array) {
			// let's find out which phone columns have been matched
			$phone_import = array(
				'home' => (isset($this->location_data['phone_home'])) ? TRUE : FALSE,
				'work' => (isset($this->location_data['phone_work'])) ? TRUE : FALSE,
				'other' => (isset($this->location_data['phone_other'])) ? TRUE : FALSE,
			);
			
			foreach($phone_import as $type => $import_or_not) {
				if($import_or_not) {
					// building the meta data
					$phone_type = 'phone_' . $type;	
					$matched_column = array_keys($this->location_data[$phone_type]);
					$is_primary = ($type == 'home') ? 1 : 0;
					
					$phone_insert_value = $column_field_array[$matched_column[0]];
									
					// making the api call
					// right now we are not making any distinctions on import/append
					// phone number will just be added to the contact regardless
					$this->_phone_create($phone_insert_value, $contact_id, $type, $is_primary, $mode);		
				}
				
			}
		}	
	}
	
	/***********************************************************************************************************
	 * Use CiviCRM API to create addresses for given contact
	 *
	 * @params
	 * #internal
	 * (array) $this->location_data		|		key: address_home			value: matched_column => field_name
	 *												 address_billing
	 *				
	 * (array) $this->contacts			|		key: contact_id				value: column/field array
	 *
	 * #external
	 * (string) $mode							The mode of the import: (import/append)
	 * 
	 * @return
	 * #internal
	 * null
	 */	
	private function address_create($mode = 'import') {
		foreach($this->contacts as $contact_id => $column_field_array) {
			$address_import = array(
				'home' => (isset($this->location_data['address_home'])) ? TRUE : FALSE,
				'billing' => (isset($this->location_data['address_billing'])) ? TRUE : FALSE,
			);
			
			foreach($address_import as $type => $import_or_not) {
				
				if($import_or_not) {
					$address_type = 'address_' . $type;
					$is_primary = ($type == 'home') ? 1 : 0;
					
					// $matched_column = array_keys($this->location_data[$phone_type]);
					
					// $address_insert_value = $column_field_array[$matched_column[0]];
					
					$this->_address_create($this->location_data[$address_type], $column_field_array, $contact_id, $type, $is_primary, $mode);
				}
			}
		}				
	}
	
	// we might need email update as well

	/*
	 * Handles the location API calls
	 * In our case, we only care about
	 * Addresses (Home, Billing) | Phone (Home, Work, Other)
	 */
	private function location_create($mode = 'import') {
		
		$this->import_status_update('location');
		
		$x = 0;
		foreach($this->contacts as $contact_id => $column_field_array) {
			
			$location_param = array(
				'version'    => '3.0',
				'contact_id' => $contact_id,			
			);
				
			if(isset($this->location_data['phone_home'])) {
				foreach($this->location_data['phone_home'] as $column_matched => $field) {
					$this->phone_create($field, $contact_id, 'Home', 1);
				}
			}
			if(isset($this->location_data['phone_work'])) {
				foreach($this->location_data['phone_work'] as $column_matched => $field) {
					$this->phone_create($field, $contact_id, 'Work');
				}
			}
			if(isset($this->location_data['phone_other'])) {
				foreach($this->location_data['phone_other'] as $column_matched => $field) {
					$this->phone_create($field, $contact_id, 'Other');
				}
			}
			if(isset($this->location_data['address_home'])) {
				$address_home['location_type'] = 'Home';
				foreach($this->location_data['address_home'] as $column_matched => $field) {
					$address_home[$field] = $column_field_array[$column_matched];
				}
				$address_home['version'] = '3';
				$address_home['location_type_id'] = 1;
				$address_home['contact_id'] = $contact_id;
				$address_home['is_primary'] = 1;
				
			}
			if(isset($this->location_data['address_billing'])) {
				$address_billing['location_type'] = 'Billing';
				foreach($this->location_data['address_billing'] as $column_matched => $field) {
					$address_billing[$field] = $column_field_array[$column_matched];
				}
			}			
			
			// Location API requires 1 to 1 address calls so we have to determine if
			// The import has a Home or Billing address or both and generate either 
			// one of 2 Location API calls.
			$home_address_only = (isset($this->location_data['address_home']) && !isset($this->location_data['address_billing'])) ? TRUE : FALSE;
			$billing_address_only = (isset($this->location_data['address_billing']) && !isset($this->location_data['address_home'])) ? TRUE : FALSE;
			$both_address = (isset($this->location_data['address_home']) && isset($this->location_data['address_billing'])) ? TRUE : FALSE;
			
			if($home_address_only) {
				$address_billing['location_type_id'] = 1;
				$address_billing['is_primary'] = 1;
				$location_param['address'] = array(1 => $address_home);
			}
			if($billing_address_only) {
				$address_billing['location_type_id'] = 5;
				$address_billing['is_primary'] = 1;
				$location_param['address'] = array(1 => $address_billing);
			}
			
			if($both_address) {
				
				$location_param1 = array(
					'version'    => '3.0',
					'contact_id' => $contact_id,
					'address' => array(1 => $address_home),
				);
				
				// set address of the outside call to the billing address
				$location_param['address'] = array(1 => $address_billing);
				
				if($mode == 'import') {
					$location_result1 = civicrm_location_add($location_param1);
				} else {
					$location_result1 = civicrm_location_update($location_param1);
				}
				if($location_result1['is_error'] == 1) {
					$this->log->_log('Error (Location API Call) on ContactID: ' . $contact_id . ': ' . $location_result1['error_message'], 'error');
				} else {
					$x++;
				}
			}
						
			$location_param['phone'] = $phone_param;
			// unset the params so they don't get built up as we go through the array
			unset($phone_param, $address_home, $address_billing);
			
			if($mode == 'import') {
				$location_result2 = civicrm_location_add($location_param);
			} else {
				$location_result2 = civicrm_location_update($location_param);
			}
			
			if($location_result2['is_error'] == 1) {
				$this->log->_log('Error (Location API Call) on ContactID: ' . $contact_id . ': ' . $location_result2['error_message'], 'error');
			} else {
				$this->location_imported++;
					// record the number of location data imported
					if($this->location_imported % 100 == 0) {
						$this->update_count('location');
					}
			}
		}
		
		$this->update_count('location');
		$this->log->_log($x . ' number of location data (A) imported.'); // from ' . count($this->contacts) . ' number of created contacts');
		$this->log->_log($this->location_imported . ' number of location data (B) imported.'); // from ' . count($this->contacts) . ' number of created contacts');
		
	}


	/*
	 ***********************************************************************************
	 * Updatethe import count
	 *
	 * @params 
	 * (string) $count				Type of counter: contact or location
	 * 
	 * @returns void		
	 */
	private function update_count($count = 'contact') {
		
		$query = '';
		
		switch($count) {
			case 'location':
				$query = sprintf("UPDATE %s SET location_count = %d WHERE jobid = %d",
							$this->options['cms_prefix'] . 'civicrm_import_job',
							$this->location_imported,
							$this->data->jobid
						);
			break;
			
			case 'contact':
			default:
				$query = sprintf("UPDATE %s SET import_count = %d WHERE jobid = %d",
					$this->options['cms_prefix'] . 'civicrm_import_job',
					$this->contact_imported,
					$this->data->jobid
				);
			break;
		}
		
		$this->db->query($query);
	}

	/*
	 ***********************************************************************************
	 * Add contacts to groups if it is choosen from the import screen
	 *
	 * @params (internal) 
	 * (array) $this->contacts					|	key: contact_id					value: array:(column => field_name)
	 * (mixed) $this->data->import_groups
	 *		   (array) | key: [numerical index] | value: group_id (existing)
	 *		   (string)  name of the new group to create
	 * 
	 * @returns void		
	 */
	private function groups_add() {
	
		$this->import_status_update('group');
	
		$contact_ids = array_keys($this->contacts);
	
		// all the group the contacts will be added to
		$groups = unserialize($this->data->import_groups);
		
		# Determine if new group needs to be created
		# case 1: existing groups
		if(is_array($groups)) {
			foreach($groups as $group_id) {
				$params = array(
					'version' => '3',
					'group_id' => $group_id,
				);
				
				$i = 0;
				// add the contact_ids to the param
				foreach($contact_ids as $contact_id) {
					if($i == 0) {
						$contact_key = 'contact_id'; 	
					} else {
						$contact_key = 'contact_id.' . $i;
					}
					$params[$contact_key] = $contact_id;
					$i++;
				}
				
				// @CiviCRM API (v3)
				$contact_group_add_result = civicrm_api('group_contact', 'create', $params);
				
				// what happens if it throws an error? is it better to add each person individually?
				if($contact_group_add_result['is_error'] == 1) {
					$this->log->_log('Error adding to group: ' . $contact_group_add_result['error_message'], 'error');
				} else {
					$group_title = $this->db->get_var(sprintf("SELECT title FROM civicrm_group WHERE id = %d", $group_id));
					$this->log->_log($contact_group_add_result['values']['added'] . ' number of contact records added to group ' . $group_title);
					$this->log->_log($contact_group_add_result['values']['not_added'] . ' number of contact records not added to group ' . $group_title);
				}
			}
		# case 2: we get a string, create a new group first
		} else {
			$group_params = array(
				'version'	  => '3',
				'name'        => str_replace(' ', '_', strtolower($groups)), // machine readable name
				'title'       => $groups,
				'description' => '',
				'is_active'   => 1,
				'visibility'  => 'User and User Admin Only',
				'group_type'  => array( '1' => 1, '2' => 1 ),
			);
			
			// @CiviCRM API (v3)
			$result = civicrm_api('Group', 'create', $group_params );
			if ( $result['is_error'] == 1) {
				# God forbid adding group causes error
				$this->log->_log('Error adding to group: ' . $contact_group_add_result['error_message'], 'error');
				# Send an email
			} else {
				$group_id = $result['id'];
				$params = array(
					'version' => '3',
					'group_id' => $group_id,
				);
				$i = 1;
				foreach($contact_ids as $contact_id) {
					$contact_key = 'contact_id.' . $i;
					$params[$contact_key] = $contact_id;
					$i++;
				}
				
				// @CiviCRM API (v3)
				$contact_group_add_result = civicrm_api('group_contact', 'create', $params);
				if($contact_group_add_result['is_error'] == 1) {
					$this->log->_log('Error adding to group: ' . $contact_group_add_result['error_message'], 'error');
					# Send an email
				} else {
					$group_title = $this->db->get_var(sprintf("SELECT title FROM civicrm_group WHERE id = %d", $group_id));
					$this->log->_log($contact_group_add_result['values']['added'] . ' number of contact records added to group: ' . $group_title);
					$this->log->_log($contact_group_add_result['values']['not_added'] . ' number of contact records not added to group: ' . $group_title);
				}
			}			
		}
	}

	/*
	 ***********************************************************************************
	 * Add contacts to tags if it is choosen from the import screen
	 *
	 * @params (internal) 
	 * (array) $this->contacts			|	key: contact_id					value: array:(column => field_name)
	 * 
	 * @returns void		
	 */
	private function tags_add() {
		
		$this->import_status_update('tag');
		
		$contact_ids = array_keys($this->contacts);
		
		// all the tags that should be slapped on
		$tags = unserialize($this->data->import_tags);
		
		foreach($tags as $tag_id) {
			$params = array(
				'version' => 3,
				'tag_id' => $tag_id,
			);

			$i = 0;
			// add the contact_ids to the param
			foreach($contact_ids as $contact_id) {
				if($i == 0) {
					$contact_key = 'contact_id';	
				} else {
					$contact_key = 'contact_id.' . $i;
				}
				$params[$contact_key] = $contact_id;
				$i++;
			}
			
			// @CiviCRM API (v3)
			$contact_tag_add_result = civicrm_api('entity_tag', 'create', $params);
			
			if($contact_tag_add_result['is_error'] == 1) {
				$this->log->_log('Error adding to tag: ' . $contact_tag_add_result['error_message'], 'error');
			} else {
				$this->log->_log($contact_tag_add_result['values']['added'] . ' number of contact records added to tag_id ' . $tag_id);
				$this->log->_log($contact_tag_add_result['values']['not_added'] . ' number of contact records not added to tag_id ' . $tag_id);
			}
		}
	}

	/*
	 ***********************************************************************************
	 * Return the import job log file locations
	 *  
	 * @param:
	 * null
	 *
	 * @return
	 * (array) $data						key:	log, error, csv
	 *										value:	file path of the log.
	 */	
	private function log_files() {
		
		$data = array();
		
		$log['log'] = $this->log->__get('log_path') . '/log/' . $this->log->__get('log_file');
		$log['error'] = $this->log->__get('log_path') . '/error/' . $this->log->__get('log_file');
		$log['csv'] = $this->log->__get('log_path') . '/error_csv/' . $this->log->__get('log_file');
		
		foreach($log as $type => $path) {
			if(is_readable($path)) {
				$data[$type] = $path;
			}
		}
		
		return $data;
	}
	
	/*
	 ***********************************************************************************
	 * Send Email notifications, this is the only feedback method for the import
	 *  
	 * FIXME:
	 * Email message needs to be customizable through configuration
	 *
	 * @params
	 * (string) $type							| type of email sending out [started | finish | error]
	 * 
	 * @returns void
	 */
	private function mail($type = 'started') {
		
		$cur_date = date('Y-m-d H:i:s');
		
		$logs = $this->log_files();
		
		// get log file
		if(file_exists($logs['log'])) {
			$log = file_get_contents($logs['log']);
			$log = nl2br($log);
		}		
		
		switch($type) {
			
			case 'finish':
				$subject = 'Your import job number: ' . $this->data->jobid . ' has been completed';
				$body = "<p>Dear  " . $this->options['email']['to_greeting'] . "</p>
				
				<p>Your import job has been completed on $cur_date. Below are the details:</p>
				
				$log
				<p>&nbsp;</p>
				<p>Please let us know if you have any questions,</p>
				<br />
				<br />
				Sincerely, <br />
				";
				
				if(isset($logs['error'])) {
					$this->mailer->AddAttachment($logs['error']);
				}					
			break;
			
			case 'error':
				$subject = 'An error has occured';
				$body = 'There has been an error';
				
			break;
			
			case 'started':
			default:
				$subject = 'Your import job number: ' . $this->data->jobid . ' has started';
				$body = "<p>Dear  " . $this->options['email']['to_greeting'] . "</p>
				<p>Your import job has started on $cur_date. The import process will usually take 2 to 12 hours
				depending on the file size, please allow 24 hours to check the progress. An email will be sent
				to you once the import process has completed</p>
				<br />
				<br />
				<p>Please let us know if you have any questions,</p>
				<br />
				<br />
				<p>Sincerely,<br />
				";
				
			break;
			
		}
		
		$this->mailer->Subject = $subject;
		$this->mailer->MsgHTML($body);

		if($this->options['email']['toggle'] == 1) {
			if(!$this->mailer->Send()) {
			  $this->log->_log("Mailer Error for $type email: " . $mail->ErrorInfo, 'error');
			}
		}
	}
	
	/*
	 ***********************************************************************************
	 * Fetch filepath
	 *
	 * @params (internal) 
	 * (int) $this->data->fid					| fid from drupal's {files} table
	 * (string) $this->options['cms_prefix']	| CMS/CRM database prefix
	 * 
	 * @returns
	 * $filepath								| Relative filepath stored in drupal's {files} table
	 */
	private function _fetch_filepath() {
		
		$query = sprintf("SELECT filepath FROM %s WHERE fid = %d", $this->options['cms_prefix'] . 'files', $this->data->fid);
		
		$file_path = $this->db->get_var($query);
		
		return $file_path;
	}

	/*
	 ***********************************************************************************
	 * Delete file from the file system
	 *
	 * @params (internal) 
	 * (int) $this->data->fid						| fid from drupal's {files} table
	 * 
	 * @returns void
	 */
	private function file_delete() {
	
		$path = $this->_fetch_filepath();
	
		if (is_file($path)) {
			if(unlink($path)) {
				$query = sprintf("DELETE FROM %s WHERE fid = %d", $this->options['cms_prefix'] . 'files', $this->data->fid);
				$this->db->query($query);
			}
		}
	}
	
	
	/*
	 ***********************************************************************************
	 * Reconnect to the db (stop gap measure against MySQL server time out 
	 *
	 * @params (internal) 
	 * (array) $this->options['db']				| key: host, name, user, pass	|	value: db connection info
	 * 
	 * @returns void
	 */
	private function reset_db() {
	
		unset($this->db);
		
		// re-connect
		parent::__construct($this->options['db']['host'], $this->options['db']['name'], $this->options['db']['user'], $this->options['db']['pass']);
		
	}
	
	
	/*
	 ***********************************************************************************
	 * Filter out bad email addresses or names html tags
	 * checks to see either email address or last name is in the list of imported fields
	 *
	 * @params 
	 * (array) $contact_param				| key: API_field	|	value: API_field_value
	 * 
	 * @returns
	 * (bool) TRUE
	 * (bool) FALSE
	 */
	private function check_filter($contact_param = array(), $mode = 'import') {
		
		// FIXED: if mode is not import (append), we don't need to check for basic import fields because
		// we really just need the ID match
		if($mode != 'import') {
			return TRUE;
		}
		
		$check = array();
		$weight = 0;
		
		// using a weight system to decide what's valid and what's not
		if(isset($contact_param['email'])) {
			$weight += 10;
		}
		if(isset($contact_param['last_name'])) {
			$weight += 6;
		}
		if(isset($contact_param['first_name'])) {
			$weight += 5;
		}

		// just email
		if($weight == 10) {
			$check[] = civicrm_import_validate::valid_email($contact_param['email']);
		// email plus last name
		} elseif($weight == 16) {
			$check[] = civicrm_import_validate::valid_email($contact_param['email']);
			$check[] = civicrm_import_validate::html_match($contact_param['last_name']);
		// email plus first name
		} elseif($weight == 15) {
			$check[] = civicrm_import_validate::valid_email($contact_param['email']);
			$check[] = civicrm_import_validate::html_match($contact_param['first_name']);
		// first plus last name
		} elseif($weight == 11) {
			$check[] = civicrm_import_validate::html_match($contact_param['first_name']);
			$check[] = civicrm_import_validate::html_match($contact_param['last_name']);			
		// email, first, last name
		} elseif($weight == 21) {
			$check[] = civicrm_import_validate::valid_email($contact_param['email']);
			$check[] = civicrm_import_validate::html_match($contact_param['first_name']);
			$check[] = civicrm_import_validate::html_match($contact_param['last_name']);
		} else {
			$check[] = false;
		}
	
	   if(in_array(FALSE, $check)) {
	   		return FALSE;
	   } else {
	   		return TRUE;
	   }
	   
	}

	/*
	 ***********************************************************************************
	 * Check given email address to see if a contact exists for the email
	 * 
	 *
	 * @params 
	 * (string) $email
	 * 
	 * @returns
	 * int $contact_id
	 * (bool) FALSE
	 */	
	private function check_email($email) {
		$email = trim($email);
		$contact_id = CRM_Core_DAO::singleValueQuery(sprintf("SELECT contact_id FROM civicrm_email WHERE email = '%s'", $email));
		
		if(!is_numeric($contact_id)) {
			return FALSE;
		}
		return $contact_id;
		
	}

	/*
	 ***********************************************************************************
	 * Create a phone record using civicrm API (v3)
	 * 
	 * @params 
	 * (string) $phone					Phone number
	 * (int) $contact_id				internal contact id
	 * (string) $location_type			Home | Work | Other
	 * (int) $is_primary				0 or 1 
	 * 
	 * @returns
	 * null
	 */		
	private function _phone_create($phone, $contact_id, $location_type = 'Home', $is_primary = 0, $mode = 'import') {
		switch($location_type) {
			case 'Work':
			case 'work':
				$location_type_id = 2;
			break;
			
			case 'Other':
			case 'other':
				$location_type_id = 6;
			break;
			
			case 'Home':
			case 'home':
			default:
				$location_type_id = 1;
			break;
			
			break;
		}
	
		$phone_param = array(
			'version' => '3',
			'location_type_id' => $location_type_id, 
			'phone' => $phone,
			'contact_id' => $contact_id, 
			'is_primary' => $is_primary,
		);
		
		// when we are doing append, we will just append and not replace because replace has 3 calls
		
		
		// @CiviCRM API (v3)
		$result = civicrm_api('Phone', 'create', $phone_param);
		if($result['is_error'] == 1) {
			$this->log->_log('Error (Phone API Call) on ContactID: ' . $contact_id . ': ' . $result['error_message'], 'error');
		}
	
	}
	
	
	/*
	 ***********************************************************************************
	 * Create an address record using civicrm API (v3)
	 * 
	 * @params 
	 * (array) $address_fields			key: column matched		value: address field name
	 * (array) $address_data			key: column				value: address data
	 * (int) $contact_id				internal contact id
	 * (string) $location_type			Home | Billing
	 * (int) $is_primary				0 | 1
	 * (string) $mode					import | append
	 * 
	 * @returns
	 * null
	 */			
	private function _address_create($address_fields, $address_data, $contact_id, $location_type = 'Home', $is_primary = 0, $mode = 'import') {
		switch($location_type) {			
			case 'Billing':
			case 'billing':
				$location_type_id = 5;
				$billing = 1;
			break;
			
			case 'Home':
			case 'home':
			default:
				$location_type_id = 1;
				$billing = 0;
			break;
			
		}
		
		$params = array(
			'version' => '3',
			'contact_id' => $contact_id,
			'is_primary' => $is_primary,
			'is_billing' => $billing,
			'location_type_id' => $location_type_id,
		);
		
		foreach($address_fields as $column_matched => $field) {
				$params[$field] = $address_data[$column_matched];
		}	
		
		// @CiviCRM API (v3)
		$result = civicrm_api('Address', 'create', $params);
		if($result['is_error'] == 1) {
			$this->log->_log('Error (Address API Call) on ContactID: ' . $contact_id . ': ' . $result['error_message'], 'error');

		}
	}
	
} // end of class

