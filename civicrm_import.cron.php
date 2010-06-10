<?php
require_once('civicrm_importer.class.php');

// The following line should be changed to the 
// path of your drupal installation
chdir('path/to/drupal/install');

require_once './includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);

ini_set('php_value memory_limit', '512M');
set_time_limit(0);

main();

// Implmentation
function main() {
	
	$options = return_import_options();	
	
	$import_job = new civi_import_job($options);

	$import_job->init();
}

function return_import_options() {

	return array(
		'cms_prefix' => var_get('civicrm_import_import_misc_cmsprefix', 'cms_'),
		'line_split' => var_get('civicrm_import_import_misc_filesplit', 5000),
		'db' => array(
			'host' => var_get('civicrm_import_db_host', 'localhost'),
			'name' => var_get('civicrm_import_db_name', 'civicrm_import_data'),
			'user' => var_get('civicrm_import_db_username', 'admin'),
			'pass' => var_get('civicrm_import_db_password', 'admin'),
		),
		'email' => array(
			'to' => var_get('civicrm_import_email_to', 'email@yourdomainname.com'),
			'to_greeting' => var_get('civicrm_import_email_to_greeting', 'John Doe'),
			'from' => var_get('civicrm_import_email_from', 'info@yourdomainname.com'),
		),
		'log' => array(
			'logging' => (var_get('civicrm_import_logging_toggle', 1) == 1) ? TRUE : FALSE,
			'path' => var_get('civicrm_import_logging_path', 'path/to/log/files'),
		),
	);
	
}

function var_get($variable, $value) {
	
	$result = db_result(db_query("SELECT value FROM {variable} WHERE name = '%s' LIMIT 0, 1", $variable));
	
	if($result == '') {
		return $value;
	} else {
		return unserialize($result);
	}
	
}
?>