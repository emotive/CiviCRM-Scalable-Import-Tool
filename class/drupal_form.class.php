<?php
/*
 *****************************************
 * Wrapper class that allows developers
 * to create drupal forms separately 
 * in the forms folder
 *****************************************
 */
class drupal_module_form {

	private $module_name;

	public function __construct($module_name) {
		$this->module_name = $module_name;
	}
	
	public function __get($variable) {
		return $this->$variable;
	}
	
	public function __set($variable, $value) {
		$this->$variable = $value;
	}

	/*
	 *********************************************************************
	 * Callback function for each of the form process that allows
	 * form building to be separate in the [module]/form/$form_name.inc
	 * the .inc file must include the 
	 * [$this->module_name]_[$form_name]_form, 
	 * [$this->module_name]_[$form_name]_form_validate,
	 * [$this->module_name]_[$form_name]_form_submit
	 *
	 * @param
	 * string 	$form_name			form_name, (machine readable name)
	 * [additional_params]
	 *
	 * @return
	 * bool 	FALSE				if fails to load one of the form process
	 * array	$form				if process is build
	 * null							if process is validate or submit
	 *********************************************************************
	 */
	public function drupal_form($form_name = null, $args = array()) {
		
		// @drupal API (v6)
		$file = drupal_get_path('module', $this->module_name) . '/form/' . $form_name . '.inc';
		if(file_exists($file)) {
			require_once($file);
			// @drupal API (v6)
			return drupal_get_form($this->module_name . '_' . $form_name . '_form', $args);
		}
		
		return FALSE;
	}
	
	/*
	 ***************************************************************************
	 * Returns a generic drupal query resource for forms
	 * For single table queries
	 *
	 * @params (Must be in order!)
	 * array $fieldlist				key: field title	value: field_name (db)
	 * string $table				Table name (without drupal table prefix)
	 * array $where	(optional)		key: field_name (db) value: where value
	 * string $action				Action column if it is needed
	 *
	 * @return
	 * A drupal db resource (db_query)
	 ***************************************************************************
	 */	
	public static function _sort() {
		$args = func_get_args();
		
		// making things more readable
		$fields = $args[0];
		$table = $args[1];
		$where = $args[2];
		$action = $args[3];
		
		$header = array();
		$where_clause = '';

		# build the header
		foreach($fields as $title => $field) {
			$header[] = array('data' => t($title), 'field' => $field);
		}

		# action column :)
		if(isset($action)) {
			$header[] = t($action);
		}

		// @drupal API (v6)
		$sort = tablesort_sql($header);

		$field_list = implode(',', $fields);

		# build the where clause
		if(!isset($where)) {
			$where_clause = '';
		} else {
			if(count($where) == 1) {
				$key = key($where);
				$where_clause = sprintf("WHERE %s = '%s'", $key, $where[$key]);
			} else {
				$i = 0;
				$where_clause = 'WHERE ';
				foreach($where as $field => $value) {
					if($i == 0) {
						$where_clause .= sprintf("%s = '%s'", $field, $value);
					} else {
						$where_clause .= sprintf(" AND %s = '%s'", $field, $value); 
					}
					$i++;
				}
			}
		}

		# we can really just return $query
		$query = sprintf("SELECT %s FROM {%s} %s %s", $field_list, $table, $where_clause, $sort);

		return array(
			'header' => $header,
			'query' => $query,
		);
		
		// @drupal API (v6)
		// return pager_query($query, 50, 0, null);
	}
	
}