<?php


/*
 * The configuration form builder that saves the configuration settings 
 * as drupal variables
 */
function civicrm_import_config_settings() {
  
  $logging_path = rtrim($_SERVER['DOCUMENT_ROOT'], '\/\\').base_path().'sites/default/files/civicrm_import';
  $path_check = is_writable($logging_path);
  
  if(!$path_check) {
	$form['warning'] = array(
		'#type' => 'markup',
		'#prefix' => '<div class="messages warning">',
		'#suffix' => '</div>',
		'#value' => 'Warning: sites/default/files/civicrm_import either does not exist or do not have write permission, as the result logging will be disabled',
	);
	variable_set('civicrm_import_logging_toggle', 0);
  }
  
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
	'#default_value' => (!$path_check) ? 0 : variable_get('civicrm_import_logging_toggle', 1),
	'#required' => TRUE,
	'#disabled' => (!$path_check) ? TRUE : FALSE,
  );
  
  $form['civicrm_import_import_logging']['civicrm_import_logging_path'] = array(
	'#type' => 'textfield',
	'#title' => t('Log PATH'),
	'#description' => t('Full system path without the trailing slash, (the directory must have write permission)'),
	'#default_value' => variable_get('civicrm_import_logging_path', rtrim($_SERVER['DOCUMENT_ROOT'], '\/\\').base_path().'sites/default/files/civicrm_import'),
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
  
  $form['civicrm_import_import_email']['civicrm_import_import_email_logging'] = array(
	'#type' => 'select',
	'#title' => t('Email logging'),
	'#options' => array(
		0 => t('Logging off'),
		1 => t('Logging on'),
	),
	'#description' => t('If email logging is on, your successful import job result will be emailed to you'),
	'#default_value' => variable_get('civicrm_import_import_email_logging', 1),
  );
  
  $form['civicrm_import_import_email']['civicrm_import_email_to'] = array(
	'#type' => 'textfield',
	'#title' => t('Email to'),
	'#description' => t('Email address where the status of the import will be sent to'),
	'#default_value' => variable_get('civicrm_import_email_to', 'email@yourdomainname.com'),
	'#size' => 20,
	'#required' => TRUE,
  );
  
  $form['civicrm_import_import_email']['civicrm_import_email_cc'] = array(
	'#type' => 'textfield',
	'#title' => t('Email CC'),
	'#description' => t('Email address where the status of the import will be cc\'ed to'),
	'#default_value' => variable_get('civicrm_import_email_cc', 'email@yourdomainname.com'),
	'#size' => 20,
	'#required' => TRUE,
  );
  
  $form['civicrm_import_import_email']['civicrm_import_email_host'] = array(
	'#type' => 'textfield',
	'#title' => t('SMTP Host'),
	'#description' => t('SMTP host of your email provider'),
	'#default_value' => variable_get('civicrm_import_email_host', 'smtp.example.com'),
	'#size' => 20,
  );
  
  $form['civicrm_import_import_email']['civicrm_import_email_ssl'] = array(
	'#type' => 'checkbox',
	'#title' => t('Enable SSL?'),
	'#description' => t('Is SSL required?'),
	'#default_value' => variable_get('civicrm_import_email_ssl', 1),
	'#size' => 20,
  );
  
  $form['civicrm_import_import_email']['civicrm_import_email_port'] = array(
	'#type' => 'textfield',
	'#title' => t('SMTP Port'),
	'#description' => t('SMTP port for your email pvorider, usually 25 for Non-ssl, 465 for ssl enabled'),
	'#default_value' => variable_get('civicrm_import_email_port', 465),
	'#size' => 20,
  );
  
  $form['civicrm_import_import_email']['civicrm_import_email_user'] = array(
	'#type' => 'textfield',
	'#title' => t('SMTP User'),
	'#description' => t('SMTP username for your email pvorider, usually the whole email address'),
	'#default_value' => variable_get('civicrm_import_email_user', 'user@example.com'),
	'#size' => 20,
  );
  
  $form['civicrm_import_import_email']['civicrm_import_email_pass'] = array(
	'#type' => 'password',
	'#title' => t('SMTP User password'),
	'#description' => t('SMTP password for the user'),
	'#default_value' => variable_get('civicrm_import_email_pass', '123456'),
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