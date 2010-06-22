<?php


/*
 * The configuration form builder that saves the configuration settings 
 * as drupal variables
 */
function civicrm_import_config_settings() {

 $form['civicrm_import_db'] = array(
	'#type' => 'fieldset',
	'#title' => t('Database Configuration Settings'),
	'#description' => t('Configuration Settings for the drupal/civicrm database'),
	'#collapsible' => TRUE,
	'collapsed' => FALSE,
  );
  
  $form['civicrm_import_db']['civicrm_import_db_host'] = array(
	'#type' => 'textfield',
	'#title' => t('CMS/CRM Database Host'),
	'#description' => t('Must be resolvable address, can be ip or domain names, or simply localhost '),
	'#default_value' => variable_get('civicrm_import_db_host', 'localhost'),
	'#size' => 20,
	'#required' => TRUE,
  );
  
  $form['civicrm_import_db']['civicrm_import_db_name'] = array(
	'#type' => 'textfield',
	'#title' => t('CMS/CRM Database Name'),
	'#description' => t('Database name or schema name'),
	'#default_value' => variable_get('civicrm_import_db_name', 'civicrm_import_data'),
	'#size' => 20,
	'#required' => TRUE,
  );
  
  $form['civicrm_import_db']['civicrm_import_db_username'] = array(
	'#type' => 'textfield',
	'#title' => t('CMS/CRM Database Username'),
	'#description' => t('username that has access to the walklist database'),
	'#default_value' => variable_get('civicrm_import_db_username', 'admin'),
	'#size' => 20,
	'#required' => TRUE,
  );
  
  $form['civicrm_import_db']['civicrm_import_db_password'] = array(
	'#type' => 'password',
	'#title' => t('CMS/CRM Database Password'),
	'#description' => t('password for the given database user'),
	'#default_value' => variable_get('civicrm_import_db_password', 'root'),
	'#size' => 20,
  );
  
 $form['civicrm_import_import_logging'] = array(
	'#type' => 'fieldset',
	'#title' => t('Import logging options'),
	'#description' => t('Logging options for the import process'),
	'#collapsible' => TRUE,
	'collapsed' => FALSE,
  );
 
  $form['civicrm_import_import_logging']['civicrm_import_logging_toggle'] = array(
	'#type' => 'select',
	'#title' => t('Logging toggle'),
	'#description' => t('Select the logging toggle for import'),
	'#options' => array(
		0 => t('Logging off'),
		1 => t('Logging on'),
	),
	'#default_value' => variable_get('civicrm_import_logging_toggle', 1),
	'#required' => TRUE,
  );

  $form['civicrm_import_import_logging']['civicrm_import_logging_path'] = array(
	'#type' => 'textfield',
	'#title' => t('Log PATH'),
	'#description' => t('Full system path without the trailing slash, (the directory must have write permission)'),
	'#default_value' => variable_get('civicrm_import_logging_path', 'path/to/log/files'),
	'#size' => 20,
	'#required' => TRUE,
  );
  
 $form['civicrm_import_import_email'] = array(
	'#type' => 'fieldset',
	'#title' => t('Email options'),
	'#description' => t('Email options for the importing process'),
	'#collapsible' => TRUE,
	'collapsed' => FALSE,
  );
  
  $form['civicrm_import_import_email']['civicrm_import_email_from'] = array(
	'#type' => 'textfield',
	'#title' => t('Email from'),
	'#description' => t('The From address in which import job status will be sent from, usually something like info@yourdomainname.com'),
	'#default_value' => variable_get('civicrm_import_email_from', 'email@yourdomainname.com'),
	'#size' => 20,
	'#required' => TRUE,
  );
  
  $form['civicrm_import_import_email']['civicrm_import_email_to'] = array(
	'#type' => 'textfield',
	'#title' => t('Email to'),
	'#description' => t('Email address where the status of the import will be sent to'),
	'#default_value' => variable_get('civicrm_import_email_to', 'email@yourdomainname.com'),
	'#size' => 20,
	'#required' => TRUE,
  );
  
  $form['civicrm_import_import_email']['civicrm_import_email_to_greeting'] = array(
	'#type' => 'textfield',
	'#title' => t('Email greeting:'),
	'#description' => t('The email greeting: i.e. John Doe'),
	'#default_value' => variable_get('civicrm_import_email_to_greeting', 'John Doe'),
	'#size' => 20,
  );

 $form['civicrm_import_import_misc'] = array(
	'#type' => 'fieldset',
	'#title' => t('Miscellaneous'),
	'#description' => t('Email options for the importing process'),
	'#collapsible' => TRUE,
	'collapsed' => FALSE,
  );
  
  $form['civicrm_import_import_misc']['civicrm_import_import_misc_cmsprefix'] = array(
	'#type' => 'textfield',
	'#title' => t('CMS db prefix'),
	'#description' => t('Your Drupal DB database table prefix i.e. (cms_)'),
	'#default_value' => variable_get('civicrm_import_import_misc_cmsprefix', 'cms_'),
	'#size' => 20,
  );
  
  $form['civicrm_import_import_misc']['civicrm_import_import_misc_filesplit'] = array(
	'#type' => 'textfield',
	'#title' => t('Import file split lines count'),
	'#description' => t('Splitting up the import file by how many lines per file i.e. (5000). Helps large imports'),
	'#default_value' => variable_get('civicrm_import_import_misc_filesplit', 5000),
	'#size' => 20,
	'#required' => TRUE,
  );
  
  
  return system_settings_form($form);
}

?>