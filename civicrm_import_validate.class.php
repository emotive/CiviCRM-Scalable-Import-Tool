<?php

require_once('lib/parsecsv.class.php');
require_once('civicrm_import_util.class.php');
require_once('civicrm_import_db.class.php');

/*
 * Import Validation Class
 */
class civicrm_import_validate extends civicrm_import_db {
	
	private $csv;
	private $custom_field_errors;
	public $log;
	
	public function __construct($db = array(), $logging = array()) {
		$this->csv = new parseCSV();
		$this->csv->heading = FALSE;
		
		// the logger file name should be consistent with logger in
		// contact_importer.php otherwise it would be confusing
		$toggle = ($logging == 1) ? TRUE : FALSE;
		$this->log = new civicrm_import_utils($logging['path'], $toggle);
		
		// invoke the parent and establish db connection
		parent::__construct($db['host'], $db['name'], $db['user'], $db['pass']);
	}
	
	public function __get($variable) {
		return $this->$variable;
	}
	
	public function __set($variable, $value) {
		$this->$variable = $value;
	}
	
	/*******************************************************************************************************************
	 * Validate Standard Import Fields (Email, States, Zip Codes)
	 * @param
	 * (array) mapping			|	key:	csv column		value: field name
	 * (string) csv_filepath	|							value: file path of the csv file
	 * (int) depth				|							value: number of rows to scan in the csv file
	 * (bool) csv_log			|							value: TRUE | FALSE  (whether log the error into csv or not)
	 *
	 * @returns
	 * (array) data				|	key:	status			value: 0 | 1 (fail, pass)
	 * 							|	key:	[errors]		value: empty | [array]
	 *							|	key:	report			value: report file path
	 */	
	public function validate_fields($_mapping = array(), $csv_filepath, $depth = 100, $csv_log = FALSE) {
		
		// set loggin
		$this->log->__set('logging', $csv_log);
		
		$mapping = array();
		
		#1 Extract the standard fields that we are checking from the raw mapping array
		foreach($_mapping as $column => $field) {
			if($field == 'email') {
				$mapping[$column] = $field;
			}
			if(strstr($field, 'state_province')) {
				$mapping[$column] = $field;
			}
			if(strstr($field, 'postal_code')) {
				$mapping[$column] = $field;
			}
		}
		
		#2 Parse the first x number of records
		$this->csv->offset = 1;
		$this->csv->limit = $depth;
		$this->csv->auto($csv_filepath);
		
		$return_data = array();
		
		$invalid = array(
			'email' => 0,
			'state' => 0,
			'zip' => 0,
		);
		
		foreach($mapping as $column => $field) {
			for($i = 0; $i<count($this->csv->data); $i++) {
				if($field == 'email') {
					if(!$this->valid_email($this->csv->data[$i][$column])) {
						$invalid['email']++;
						// add the record to the csv
						$this->csv->data[$i][$column] .= ' [invalid email]';
						$this->log->_log($this->csv->unparse(array($this->csv->data[$i])), 'error_csv', FALSE);
					}
				}
				if(strstr($field, 'state_province')) {
					if(!$this->valid_state($this->csv->data[$i][$column])) {
						$invalid['state']++;
						// add the record to the csv	
						$this->csv->data[$i][$column] .= ' [invalid state/province]';
						$this->log->_log($this->csv->unparse(array($this->csv->data[$i])), 'error_csv', FALSE);						
					}
				}
				if(strstr($field, 'postal_code')) {
					if(!$this->valid_zip($this->csv->data[$i][$column])) {
						$invalid['zip']++;
						// add the record to the csv
						$this->csv->data[$i][$column] .= ' [invalid zip code]';
						$this->log->_log($this->csv->unparse(array($this->csv->data[$i])), 'error_csv', FALSE);				
					}
				}
			}
		}
		
		if(array_sum($invalid) == 0) {
			$return_data['status'] = 1;
		} else {
			$return_data['status'] = 0;
			
			if($invalid['email'] > 0) {
				$return_data['errors']['email'] = $invalid['email'];
			}
			if($invalid['state'] > 0) {
				$return_data['errors']['state'] = $invalid['state'];
			}
			if($invalid['zip'] > 0) {
				$return_data['errors']['zip'] = $invalid['zip'];
			}
		}
		
		// kill validation data container
		unset($this->csv->data);
		
		return $return_data;
	}
	
	/*******************************************************************************************************************
	 * Validate SET custom field values (CheckBox, Select, Radio) against their values from the DB
	 * @param
	 * (array) mapping			|	key:	csv column		value: field name
	 * (string) csv_filepath	|							value: file path of the csv file
	 * (int) depth				|							value: number of rows to scan in the csv file
	 * (bool) csv_log			|							value: TRUE | FALSE  (whether log the error into csv or not)
	 *
	 * @returns
	 * 
	 * TRUE						|	Passes SET value custom field validation or no SET VALUE custom field detected
	 * FALSE					|	Fails custom field validation
	 * $this->custom_field_errors (internal)
	 */	
	public function validate_custom_fields($mapping = array(), $csv_filepath, $depth = 100, $csv_log = FALSE) {
		
		$this->log->__set('logging', $csv_log);
		
		# 1st, get the import data to see if there's any custom fields 
		// $mappings = unserialize($this->data->mapping);
		$custom_fields_mapping = array();
		$custom_fields_mapping_set = array();
		
		foreach($mapping as $column => $field) {
			if(strstr($field, 'custom_')) {
				$custom_fields_mapping[$column] = substr($field, 7, 8); // just want the custom field id
			}
		}
		
		// if there's no custom fields to import, don't waste time
		if(!empty($custom_fields_mapping)) {
		
			# 2nd, are they radios, checkboxes or selections? (SET values)
			foreach($custom_fields_mapping as $column => $custom_field_id) {
				
				$custom_field_type = $this->db->get_var(sprintf("SELECT html_type FROM civicrm_custom_field WHERE id = %d", $custom_field_id));
					
					if($custom_field_type == 'Select' || $custom_field_type == 'CheckBox' || $custom_field_type == 'Radio') {
						$custom_fields_mapping_set[$column] = $custom_field_id;
					}
			}
			
			// only care if the custom field we import has set values
			if(!empty($custom_fields_mapping_set)) {
			
				# 3rd, fetch the expected set values of the custom fields
				$errors = 0;
				foreach($custom_fields_mapping_set as $column => $custom_field_id) {
				
					$query = sprintf("SELECT cov.value FROM civicrm_option_value cov 
					JOIN civicrm_option_group cog ON cov.option_group_id = cog.id
					JOIN civicrm_custom_field ccf ON ccf.option_group_id = cog.id
					WHERE ccf.option_group_id IS NOT NULL AND ccf.id = %d", $custom_field_id);
					
					if($results = $this->db->get_results($query, ARRAY_N)) {
					
						# 4 match the fields with csv data
						$_res = civicrm_import_utils::flattenArray($results);
						
						#4.1 parse limited data
						$this->csv->offset = 1;
						$this->csv->limit = $depth;
						$this->csv->parse($csv_filepath);
						
						
						for($i = 0; $i<count($this->csv->data); $i++) {
							if(!in_array($this->csv->data[$i][$column], $_res)) {
								$errors++;
								// mark the error and log the data validation error
								$this->csv->data[$i][$column] .= ' [acceptable values are: ' . implode(',', $_res) . ']';
								$this->log->_log($this->csv->unparse(array($this->csv->data[$i])), 'error_csv', FALSE);
							}
						}
					}
					
				} // end of foreach
			
				if($errors > 0) {
					// Trigger error email and point or attach the error csv report and include the message below
					// echo $errors . " Error(s) occured. Inconsistent custom data detected, this import job is cancelled";
					$this->custom_field_errors = $errors;
					return FALSE;	
				}
				
				// At this point we are successful?
				unset($this->csv->data);
				
				return TRUE;
			}
		
		} else {
			// if no custom field detected, just return TRUE
			return TRUE;
		}
	} // end of validate custom field
	
	/*******************************************************************************************************************
	 * Validate state value from abbreviated list and full list (US states)
	 * @param
	 * (string) state_input		|	value: raw state input
	 *
	 * @returns
	 * 
	 * TRUE						|	State value is valid
	 * FALSE					|	State value is invalid
	 */	
	public static function valid_state($state_input) {
	
		$states_short = array('AL','AK','AS','AZ','AR','CA','CO','CT','DE','DC','FM','FL','GA','GU','HI','ID','IL','IN','IA','KS','KY','LA','ME','MH','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','MP','OH','OK','OR','PW','PA','PR','RI','SC','SD','TN','TX','UT','VT','VI','VA','WA','WV','WI','WY');
		
		$states_full = array('ALABAMA','ALASKA','AMERICAN SAMOA','ARIZONA','ARKANSAS','CALIFORNIA','COLORADO','CONNECTICUT','DELAWARE','DISTRICT OF COLUMBIA','FEDERATED STATES OF MICRONESIA','FLORIDA','GEORGIA','GUAM','HAWAII','IDAHO','ILLINOIS','INDIANA','IOWA','KANSAS','KENTUCKY','LOUISIANA','MAINE','MARSHALL ISLANDS','MARYLAND','MASSACHUSETTS','MICHIGAN','MINNESOTA','MISSISSIPPI','MISSOURI','MONTANA','NEBRASKA','NEVADA','NEW HAMPSHIRE','NEW JERSEY','NEW MEXICO','NEW YORK','NORTH CAROLINA','NORTH DAKOTA','NORTHERN MARIANA ISLANDS','OHIO','OKLAHOMA','OREGON','PALAU','PENNSYLVANIA','PUERTO RICO','RHODE ISLAND','SOUTH CAROLINA','SOUTH DAKOTA','TENNESSEE','TEXAS','UTAH','VERMONT','VIRGIN ISLANDS','VIRGINIA','WASHINGTON','WEST VIRGINIA','WISCONSIN','WYOMING');
		
		// string less than 2 chars are bad!
		if(strlen($state_input) < 2) {
			return FALSE;
		
		// check abbr version of the states
		} elseif(strlen($state_input) == 2) {
			
			if(in_array(strtoupper($state_input), $states_short)) {
				return TRUE;
			} else {
				return FALSE;
			}
		}
		// check full version of the states
		else {
		
			if(in_array(strtoupper($state_input), $states_full)) {
				return TRUE;
			} else {
				return FALSE;
			}
		}
	}
	
	/*******************************************************************************************************************
	 * Validate zip code values
	 * @param
	 * (string) zip_code		|	value: raw zip code input
	 *
	 * @returns
	 * 
	 * TRUE						|	zip code value is valid
	 * FALSE					|	zip code value is invalid
	 */	
	public static function valid_zip($zip_code) {
		if(!is_numeric($zip_code)) {
			return FALSE;
		} else {
			return TRUE;
		}
	}
	
	/*******************************************************************************************************************
	 * Validate email address
	 * @param
	 * (string) email			|	value: email input
	 *
	 * @returns
	 * 
	 * TRUE						|	email address value is valid
	 * FALSE					|	email address value is invalid
	 */	
	public static function valid_email($email) {
	   $isValid = true;
	   $atIndex = strrpos($email, "@");
	   if (is_bool($atIndex) && !$atIndex)
	   {
		  $isValid = false;
	   }
	   else {
		  $domain = substr($email, $atIndex+1);
		  $local = substr($email, 0, $atIndex);
		  $localLen = strlen($local);
		  $domainLen = strlen($domain);
		  if ($localLen < 1 || $localLen > 64)
		  {
			 // local part length exceeded
			 $isValid = false;
		  }
		  else if ($domainLen < 1 || $domainLen > 255) {
			 // domain part length exceeded
			 $isValid = false;
		  }
		  else if ($local[0] == '.' || $local[$localLen-1] == '.') {
			 // local part starts or ends with '.'
			 $isValid = false;
		  }
		  else if (preg_match('/\\.\\./', $local)) {
			 // local part has two consecutive dots
			 $isValid = false;
		  }
		  else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
			 // character not valid in domain part
			 $isValid = false;
		  }
		  else if (preg_match('/\\.\\./', $domain)) {
			 // domain part has two consecutive dots
			 $isValid = false;
		  }
		  else if(!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
			 // character not valid in local part unless 
			 // local part is quoted
			 if (!preg_match('/^"(\\\\"|[^"])+"$/',
				 str_replace("\\\\","",$local)))
			 {
				$isValid = false;
			 }
		  }
	   }
	   return $isValid;	
	}

   /*******************************************************************************************************************
	* Check user input for HTML special characters and make sure it is not injected (Code from Drupal)
	* @param
	* (string) $text			|	value: text input
	*
	* @returns
	* 
	* encoded text
	*/
	public static function check_plain($text) {
	
	  static $php525;
	
	  if (!isset($php525)) {
		$php525 = version_compare(PHP_VERSION, '5.2.5', '>=');
	  }
	
	  if ($php525) {
		return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	  }
	  return (preg_match('/^./us', $text) == 1) ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : '';
	  
	}


   /*******************************************************************************************************************
	* Check user input for HTML tags using regex
	* @param
	* (string) $input		|	value: text input
	*
	* @returns
	* TRUE					| contains HTML tags
	* FALSE					| does not contain HTML tags
	*/
	public static function html_match($input) {
		$match = preg_match("/<\/?\w+((\s+(\w|\w[\w-]*\w)(\s*=\s*(?:\".*?\"|'.*?'|[^'\">\s]+))?)+\s*|\s*)\/?>/i", $input);
		
		if($match > 0) {
			return FALSE;
		} else {
			return TRUE;
		}
		
	}
	

} // end of class


?>