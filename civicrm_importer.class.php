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
require_once('api/v2/Contact.php');
require_once('api/v2/Location.php');
require_once('api/v2/GroupContact.php');
require_once('api/v2/Group.php');
require_once('api/v2/EntityTag.php');

// Utilities
if(!class_exists('parseCSV')) {
	require_once('lib/parsecsv.class.php');
}

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
	 *				'from' => 'info@yourdomain.com',
	 *				'to_greeting' => 'John Doe',
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
		$this->csv = new parseCSV();
		$this->csv->encoding('UTF-8');
		$this->csv->heading = FALSE;
		
		// start the logger
		$this->log = new civicrm_import_utils($this->options['log']['path'], $this->options['log']['logging']);
		
		// start the mailer
		$this->mailer = new PHPMailer();
		$this->mailer->SetFrom($this->options['email']['from'], 'CiviCRM Import Scheduler');
		$this->mailer->AddAddress($this->options['email']['to'], $this->options['email']['to_greeting']);
		$this->mailer->AltBody    = "To view the message, please use an HTML compatible email viewer!";
		
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
		// $this->mail('started');
		
		
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
			$this->process_import($split_files, $offset);
		}

		// big fix: 
		// stop gap measure for MySQL server time out after import is finished
		// Need to switch to Drupal db boot_strap for persistent connection
		$this->reset_db();
		
		// assuming if fatal errors occured before this step, this will never be called
		$this->file_delete();
		$this->import_status_update('finish');
		// $this->mail('finish');
		
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
		
		// if we are doing an import job
		if($this->data->type == 'import') {
			// create contacts, custom fields
			$this->contact_create();
			
			// location data (address, phone numbers)
			if(!empty($this->location_data)) {
				$this->location_create();
			}
			// import groups
			if(!empty($this->data->import_groups)) {
				$this->groups_add();
			}
			// import tags
			if(!empty($this->data->import_tags)) {
				$this->tags_add();
			}
		}
		
		// for an append job
		if($this->data->type == 'append') {
		
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
				echo 'no active import job';
				exit();
			} else {
				// return the import job object
				$this->data = $results[0];
			}
	}
	
	/*
	 ***********************************************************************************
	 * Update the current job status in the queue table 
	 * 
	 * @params
	 *
	 * @returns
	 */
	private function import_status_update($status = 'start') {	

		$_query = "UPDATE %s %s WHERE jobid = %d";
		
		switch($status) {
			case 'finish':
				$sub_query = sprintf("SET cron = 1, status = 1, date_complete = '%s'", date('Y-m-d H:i:s'));
			break;
			
			case 'error':
				$sub_query = "SET cron = 1, status = 2";
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
			$this->csv->offset = $offset;
			$this->csv->heading = FALSE;
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
	
	private function contact_update() {
		// bug fix for $this->contacts keep being appended
		if(!empty($this->contacts)) {
			unset($this->contacts);
			$this->contacts = array();
		}

		for($i = 0; $i< count($this->csv->data); $i++) {
			$param = array(
				'contact_type' => 'Individual',
			);
			
			// construct the contact id
			foreach($this->contact_data as $column => $field) {
				switch($field) {
					case 'birth_date':
						$param[$field] = civicrm_import_utils::format_date($this->csv->data[$i][$column], $this->data->civicrm_date_options);
					break;
					
					case 'external_identifier':
						// get the contact id
						$param['contact_id'] = $this->fetch_contact_id($this->csv->data[$i][$column]);
					break;
					
					default:
						$param[$field] = $this->csv->data[$i][$column];
					break;
				}				
			}
			
			if(isset($param['contact_id']) && $param['contact_id'] != '') {
				$contact = civicrm_contact_update($param);
				// bug fix: memory leak from API call
				CRM_Core_DAO::freeResult();
			}
		}
		
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
	private function contact_create($mode = 'create') {
	
		// bug fix for $this->contacts keep being appended
		if(!empty($this->contacts)) {
			unset($this->contacts);
			$this->contacts = array();
		}
		
		for($i = 0; $i< count($this->csv->data); $i++) {
			
			$param = array(
				'contact_type' => 'Individual',
				'dupe_check' => ($this->data->dupe_check == 1) ? TRUE : FALSE,
			);
			
			foreach($this->contact_data as $column => $field) {
				
				switch($field) {
					case 'birth_date':
						$param[$field] = civicrm_import_utils::format_date($this->csv->data[$i][$column], $this->data->civicrm_date_options);
					break;
					
					default:
						$param[$field] = $this->csv->data[$i][$column];
					break;
				}
			}
			
			// data filtering validation: 
			// if the $param does not fit our validation requirement
			// i.e. First name, Last name, Email, we do not import them.
			if($this->check_filter($param)) {
				$contact = civicrm_contact_create($param);
				// bug fix: memory leak from API call
				CRM_Core_DAO::freeResult();
				
				if($contact['is_error'] == 1) {
					$this->log->_log('Error on CSV line ' . $i . ': ' . $contact['error_message'], 'error');
				} else {
					$this->contacts[$contact['contact_id']] = $this->csv->data[$i];
					$this->contact_imported++;
					
					// record the contact import count for tracking
					if($this->contact_imported % 100 == 0) {
						$this->update_count('contact');
					}
				}
			} else {
				// log this row as bad row
				$this->log->_log("Bad name or email on CSV line $i," . implode(',', $this->csv->data[$i]), 'error');
			}
		}
		
		$this->update_count('contact');
		$this->log->_log($y . ' number of contact records created from ' . count($this->csv->data) . ' number of rows parsed from CSV file');
		
		// free the original parsed csv from memory
		unset($this->csv->data);
	}
	
	
	/*
	 * Handles the location API calls
	 * In our case, we only care about
	 * Addresses (Home, Billing) | Phone (Home, Work, Other)
	 */
	private function location_create() {
		
		$x = 0;
		foreach($this->contacts as $contact_id => $column_field_array) {
			
			$location_param = array(
				'version'    => '3.0',
				'contact_id' => $contact_id,			
			);
				
			if(isset($this->location_data['phone_home'])) {
				foreach($this->location_data['phone_home'] as $column_matched => $field) {
					$phone_param[] = array('location_type' => 'Home', 'phone' => $column_field_array[$column_matched]);
				}
			}
			if(isset($this->location_data['phone_work'])) {
				foreach($this->location_data['phone_work'] as $column_matched => $field) {
					$phone_param[] = array('location_type' => 'Work', 'phone' => $column_field_array[$column_matched]);
				}
			}
			if(isset($this->location_data['address_home'])) {
				$address_home['location_type'] = 'Home';
				foreach($this->location_data['address_home'] as $column_matched => $field) {
					$address_home[$field] = $column_field_array[$column_matched];
				}
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
			$billing_address_only = (isset($this->location_data['address_billing']) && !isset($this->location_data['address_billing'])) ? TRUE : FALSE;
			$both_address = (isset($this->location_data['address_home']) && isset($this->location_data['address_billing'])) ? TRUE : FALSE;
			
			if($home_address_only) {
				$location_param['address'] = array(1 => $address_home);
			}
			if($billing_address_only) {
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
				
				$location_result1 = civicrm_location_add($location_param1);
				if($location_result1['is_error'] == 1) {
					$this->log->_log('Error (Location API Call) on ContactID: ' . $contact_id . ': ' . $location_result1['error_message'], 'error');
				} else {
					$x++;
				}
			}
						
			$location_param['phone'] = $phone_param;
			
			// unset the params so they don't get built up as we go through the array
			unset($phone_param, $address_home, $address_billing);
			
			$location_result2 = civicrm_location_add($location_param);
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
		$this->log->_log($x . ' number of pre-location data imported.'); // from ' . count($this->contacts) . ' number of created contacts');
		$this->log->_log($this->location_imported . ' number of location data imported.'); // from ' . count($this->contacts) . ' number of created contacts');
		
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
	
		$contact_ids = array_keys($this->contacts);
	
		// all the group the contacts will be added to
		$groups = unserialize($this->data->import_groups);
		
		# Determine if new group needs to be created
		# case 1: existing groups
		if(is_array($groups)) {
			foreach($groups as $group_id) {
				$params = array(
					'group_id' => $group_id,
				);
				
				$i = 1;
				// add the contact_ids to the param
				foreach($contact_ids as $contact_id) {
					$contact_key = 'contact_id.' . $i;
					$params[$contact_key] = $contact_id;
					$i++;
				}
				
				$contact_group_add_result = civicrm_group_contact_add($params);
				
				// what happens if it throws an error? is it better to add each person individually?
				if($contact_group_add_result['is_error'] == 1) {
					$this->log->_log('Error adding to group: ' . $contact_group_add_result['error_message'], 'error');
				} else {
					$this->log->_log($contact_group_add_result['added'] . ' number of contact records added to group_id ' . $group_id);
					$this->log->_log($contact_group_add_result['not_added'] . ' number of contact records not added to group_id ' . $group_id);
				}
			}
		# case 2: we get a string, create a new group first
		} else {
			$group_params = array(
				'name'        => str_replace(' ', '_', strtolower($groups)), // machine readable name
				'title'       => $groups,
				'description' => '',
				'is_active'   => 1,
				'visibility'  => 'User and User Admin Only',
				'group_type'  => array( '1' => 1, '2' => 1 ),
			);

			$result = civicrm_group_add( $group_params );
			if ( civicrm_error ( $result )) {
				# God forbid adding group causes error
				$this->log->_log('Error adding to group: ' . $contact_group_add_result['error_message'], 'error');
				# Send an email
			} else {
				$params = array(
					'group_id' => $result['result'],
				);
				$i = 1;
				foreach($contact_ids as $contact_id) {
					$contact_key = 'contact_id.' . $i;
					$params[$contact_key] = $contact_id;
					$i++;
				}
				
				$contact_group_add_result = civicrm_group_contact_add($params);
				if($contact_group_add_result['is_error'] == 1) {
					$this->log->_log('Error adding to group: ' . $contact_group_add_result['error_message'], 'error');
					# Send an email
				} else {
					$this->log->_log($contact_group_add_result['added'] . ' number of contact records added to group_id ' . $group_id);
					$this->log->_log($contact_group_add_result['not_added'] . ' number of contact records not added to group_id ' . $group_id);
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
		
		$contact_ids = array_keys($this->contacts);
		
		// all the tags that should be slapped on
		$tags = unserialize($this->data->import_tags);
		
		foreach($tags as $tag_id) {
			$params = array(
				'tag_id' => $tag_id,
			);

			$i = 1;
			// add the contact_ids to the param
			foreach($contact_ids as $contact_id) {
				$contact_key = 'contact_id.' . $i;
				$params[$contact_key] = $contact_id;
				$i++;
			}

			$contact_tag_add_result =& civicrm_entity_tag_add($params);
			
			if($contact_tag_add_result['is_error'] == 1) {
				$this->log->_log('Error adding to tag: ' . $contact_tag_add_result['error_message'], 'error');
			} else {
				$this->log->_log($contact_tag_add_result['added'] . ' number of contact records added to tag_id ' . $tag_id);
				$this->log->_log($contact_tag_add_result['not_added'] . ' number of contact records not added to tag_id ' . $tag_id);
			}
		}
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
		$log_filepath = $this->log->__get('log_path') . '/log/' . $this->log->__get('log_file');
		$err_filepath = $this->log->__get('log_path') . '/error/' . $this->log->__get('log_file');
		
		// get log file
		if(file_exists($log_filepath)) {
			$log = file_get_contents($log_filepath);
			$log = nl2br($log);
		}		
		
		switch($type) {
			
			case 'finish':
				$subject = 'Your import job number: ' . $this->data->jobid . ' has been completed';
				$body = "<p>Dear  " . $this->options['email']['to_greeting'] . "
				
				Your import job has been completed on $cur_date. Below are the details:</p>
				
				$log
				
				<p>Please let us know if you have any questions,</p>
				<br />
				<br />
				Sincerely, <br />
				";
				
				$this->mailer->AddAttachment($err_filepath);
					
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

		if(!$this->mailer->Send()) {
		  $this->log->_log("Mailer Error for $type email: " . $mail->ErrorInfo, 'error');
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
	 *
	 * @params 
	 * (array) $contact_param				| key: API_field	|	value: API_field_value
	 * 
	 * @returns
	 * (bool) TRUE
	 * (bool) FALSE
	 */
	private function check_filter($contact_param = array()) {
	
		$check = array();
	   
	   if(isset($contact_param['first_name'])) {
	   		$check[] = civicrm_import_validate::html_match($contact_param['first_name']);
	   }
	   if(isset($contact_param['last_name'])) {
	   		$check[] = civicrm_import_validate::html_match($contact_param['last_name']);
	   }
	   if(isset($contact_param['email'])) {
	   		$check[] = civicrm_import_validate::valid_email($contact_param['email']);
	   }
	   
	   if(in_array(FALSE, $check)) {
	   		return FALSE;
	   } else {
	   		return TRUE;
	   }
	   
	}
} // end of class

?>