<?php
require_once('civicrm_importer.class.php');

// Attempting to locate drupal base path
// by compare it to the current script path
$script_path = $_SERVER['DOCUMENT_ROOT'] . $_SERVER['PHP_SELF'];
$base_path = substr($script_path, 0, strpos($script_path, '/sites/all/modules/'));
chdir($base_path);

require_once './includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);

// ini_set('php_value memory_limit', '512M');
set_time_limit(0);

main();

// Implmentation
function main() {
		
	$options = return_import_options();	
	$import_job = new civi_import_job($options);
	$import_job->init();
}


/*
 * Import Job Options
 */
function return_import_options() {
	
	// we are automatically grabbing database connection information
	global $db_url;
	
	$db = parse_url($db_url);
	$db['path'] = trim($db['path'], '\/\\');

	// make the default mail from at info@yourdomainname.com
	$default_mail = 'info@' . $_SERVER['HOST'];
	
	return array(
		'cms_prefix' => var_get('civicrm_import_import_misc_cmsprefix', 'cms_'),
		'line_split' => var_get('civicrm_import_import_misc_filesplit', 5000),
		'db' => array(
			'host' => $db['host'],
			'name' => $db['path'],
			'user' => $db['user'],
			'pass' => $db['pass'],
		),
		'email' => array(
			'toggle' => variable_get('civicrm_import_import_email_logging', 1),
			'from' => var_get('site_mail', $default_mail),
			'to' => var_get('civicrm_import_email_to', 'email@yourdomainname.com'),
			'cc' => var_get('civicrm_import_email_cc', 'email@yourdomainname.com'),
			'host' => var_get('civicrm_import_email_host', 'smtp.example.com'),
			'ssl' => var_get('civicrm_import_email_ssl', 1),
			'port' => var_get('civicrm_import_email_port', 465),
			'user' => var_get('civicrm_import_email_user', 'user@example.com'),
			'pass' => var_get('civicrm_import_email_pass', '123456'),
			'to_greeting' => 'Administrator',
		),
		'log' => array(
			'logging' => (var_get('civicrm_import_logging_toggle', 1) == 1) ? TRUE : FALSE,
			'path' => var_get('civicrm_import_logging_path', 'path/to/log/files'),
		),
	);
}

/*
 * Mimicking the variable_get drupal function
 */
function var_get($variable, $value) {
	
	$result = db_result(db_query("SELECT value FROM {variable} WHERE name = '%s' LIMIT 0, 1", $variable));
	
	if($result == '') {
		return $value;
	} else {
		return unserialize($result);
	}
	
}
?>