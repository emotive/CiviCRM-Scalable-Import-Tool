<?php


class civicrm_import_utils {
	
	private $log_path;
	private $logging = FALSE;
	private $log_file;

	/*
	 * Constructor
	 */
	public function __construct($log_path, $logging) {
		$this->log_path = $log_path;
		$this->logging = $logging;
		$this->log_file = 'import_log_' . time() . '.log';
	}
	
	/*
	 * Get and Set Methods
	 */
	public function __get($variable) {
		return $this->$variable;
	}
	
	public function __set($variable, $value) {
		$this->$variable = $value;
	}
			
	/*
	 ***********************************************************************************
	 * File logging to provide error feedbacks
	 *
	 * @params 
	 * $message		|		string 		|	value: logging message
	 * $type		|		string		|	value: [report | error | error_csv]
	 *
	 * @returns
	 * TRUE if the logging message is successfully written
	 * FALSE if the logging message failed
	 */	
	public function _log($message, $type = 'report', $newline = TRUE) {
		
		if(!$this->logging) {
			return;
		} else {
			$new_line = '
';
			switch($type) {
				case 'error':
					$path = $this->log_path . '/error';
				break;
				
				case 'error_csv':
					$path = $this->log_path . '/error_csv';
				break;
				
				case 'report':
				default:
					$path = $this->log_path . '/log';
				break;
			}
			
			if(!is_dir($path)) {
				mkdir($path, 777);
			}
			$file_name = $path . '/' . $this->log_file;
			
			if($handle = fopen($file_name, 'a')) {
				$re = fwrite($handle, $message);
				$re1 = ($newline == TRUE) ? fwrite($handle, $new_line) : '';
				$re2 = fclose($handle);
				if ( $re != false && $re2 != false ) return true;
			}
			return false;		
		}
	}
	
	public function file_split($source_file, $destination_file, $split_count) {
		
		if($file_string = file_get_contents($source_file)) {
			
			// convert line breaks into <br />
			$_file_string = nl2br($file_string);
			$total_line_count = substr_count($_file_string, '<br />');
			
			// if not enough lines to split, we just return the csv string back
			// also if the count is 0 means we don't split the file
			if(($total_line_count <= $split_count) || $split_count == 0) {
				return $file_string;
			} else {
				$return_files = array();
				
				// turn the huge file into a 1D array
				$data = explode('<br />', $_file_string);
				// file name append increment counter 
				$x = 0;
				// split increment counter
				$y = 0;
				
				// walk through the new data array
				for($i = 0; $i< count($data); $i++) {
					
					$buffer .= $data[$i];
					
					$y++;
					if($y >= $split_count) {
						$x++;
						$buffer = trim($buffer);
						$this->_write($buffer, $destination_file . $x . '.csv');
						$return_files[] = $this->log_path . '/tmp/' . $destination_file . $x . '.csv';
						
						// start over
						unset($y, $buffer);
					}
					
					// when we reach the end
					if(count($data) - $i == 1) {
						$x++;
						$buffer = trim($buffer);
						$this->_write($buffer, $destination_file . $x . '.csv');	
						$return_files[] = $this->log_path . '/tmp/' . $destination_file . $x . '.csv';						
					}
					
				}
				
				return $return_files;
				
			}
		}
	}

	private function _write($message, $file_name) {
		
		$path = $this->log_path . '/tmp';
		
		if(!is_dir($path)) {
			mkdir($path, 777);
		}
		
		$_file_name = $path . '/' . $file_name;
	
		if($handle = fopen($_file_name, 'w')) {
			$re = fwrite($handle, $message);
			$re2 = fclose($handle);
			if ( $re != false && $re2 != false ) return true;
		}
		return false;		
	}
	
	
	public static function format_date($input, $format = 'yyyy-mm-dd') {
		
		// we don't want yyyy-mm-dd HH:MM:SS
		if(strlen($input) > 10 && strstr($input, ' ')) {
			$input = substr($input, 0, 10);
		}
		
		switch($format) {
			
			case 'mm-dd-yyyy':
				$_date = explode('-', $input);
				return $_date[2] . '-' . $_date[0] . '-' . $_date[1];
			break;
			
			case 'mm/dd/yyyy':
				$_date = explode('/', $input);
				return $_date[2] . '-' . $_date[0] . '-' . $_date[1];
			break;
			
			case 'yyyy-mm-dd':
			default:
				return $input;
			break;
		}
		
		
	}
	
	
	public static function flattenArray(array $array){
		$ret_array = array();
		foreach(new RecursiveIteratorIterator(new RecursiveArrayIterator($array)) as $value)
		{
			$ret_array[] = $value;
		}
		return $ret_array;
	}
	
	
} // end of class


?>