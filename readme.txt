/*****************************************
 * CiviCRM Scalable Import Module
 * by Chang Xiao (chang@emotivellc.com)
 * EMotive LLC, 2010
 * GNU GENERAL LICENSE - see LICENSE.txt
 ****************************************/
 
1. Requirements
2. Installation
3. Configuration 
4. Usage
5. Known Issues
6. Future considerations
7. Change log

/*************************************************************
 * 1. Requirements
 ************************************************************/
 
This module requires the following:
 
	1. Drupal Version 6.x
	2. CiviCRM 3.x (Versions with API V2)
	3. *Your Drupal and CiviCRM needs to be in the same database*
	3. Recommended of PHP memory limit control (either through .htaccess or ini_set())
	4. 512 MB PHP memory allocation to avoid memory exhaustion error
	5. Drupal upload element module
	6. (Optional) CURL installed [on linux machines]

/*************************************************************
 * 2. Installation 
 ************************************************************/	

 The following steps are needed to make the module work:
 
	1. Upload all the contents in the civicrm_import to your drupal 
	sites/all/modules/ folder.
	
	2. Enable the module "CiviCRM Mass Import" from admin/build/modules
	
	3. Configurate the module from admin/civi_import/config
	
	4. Change the following line in *civicrm_importer.class.php*
	* chdir('path/to/drupal_install');*
	This should be changed to the *full path* of your drupal installation such as 
	chdir('/var/www'), etc.
	
	5. Change the following line in *civicrm_import.cron.php*
	* chdir('path/to/drupal_install');*
	This should be changed to the *full path* of your drupal installation such as 
	chdir('/var/www'), etc.

/*************************************************************
 * 3. Configuration
 ************************************************************/		

	1. CMS/CRM Database Host: The host address of your Drupal/CiviCRM
	(It can be either the ip address or resolvable domain names)
	
	2. CMS/CRM Database Name: The name of the database that Drupal/CiviCRM
	lives
	
	3. CMS/CRM Database Username: Username for database access
	
	4. CMS/CRM Database Password: Password for database access
	
	5. Logging Toggle: Select Yes or No for error and import file logging
	
	6. Log PATH: if Logging is selected, the full path to where the logging file will be stored
	(This should be the full path to /sites/default/files/civicrm_import)
	
	7. Email from: Email from address of the sender
 
	8. Email to: Email status and update that should be sent to
	
	9. Email greeting: The name of the email: i.e. John Doe
	
	10. CMS db prefix: The Drupal database prefix, (should include the underscore _)
	
	10. Import file split lines count: If you have a large file (say 250,000) records and
	set the line count to 50,000, the script will split the file into 5 parts and attempt
	to import through each file. This will prevent timeout and other PHP issues and
	improve import availability and job scheduling.

/*************************************************************
 * 4. Usage
 ************************************************************/	

	* civicrm_import.cron.php * should be ran by the cron on 
	a 5 minutes basis or longer interval. If running the file
	results in php memory exhaustion error, please run
	cron.php which uses shell_exec and CURL command, you will 
	need to have curl installed on the server.
 
	1. Browse a CSV file to import, (Recommend with column headers)
	2. Select Mapping options (First name and Email address are required import fields)
	3. Select De-Dupe, Date Format Options
	4. Select grouping/tagging options
	5. Check quick validation report, Import
	
	(If you are going to use cron.php, you need to edit the first line of
	$url = 'http://yoursite.com/sites/all/modules/civicrm_import/civicrm_import.cron.php';
	to your drupal site url)
	
/*************************************************************
 * 5. Known Issues
 ************************************************************/
	
	1. The script will look at blank email, states/province and postal codes as 
	   invalid fields.

	2. The script will only validate email, states/province and postal codes 
	 
	3. The quick validation processing only look at the first 100 columns of the
	   import file
	
	4. The script will not format custom fields that are date type, so to import 
	   those fields, they must be in yyyy-mm-dd in order to be properly imported
	   
/*************************************************************
 * 6. Future Consideration
 ************************************************************/
 
	1.	Further Decoupling of processes (Email, Validation, Logging)
	2.	Use Drupal/CiviCRM native database resources (drupal db bootstrap, CiviCRM Dao)
		to perform SQL queries and making CMS/CRM database independent
	3.	Use CiviCRM import API to perform import rather than using the Contact/Location/Grouping/Tagging
	4.	More field level validation for different type of fields.
	5.	Validation options and rules for importing (so it will only import records that meets
		certain criteria
	
	If you have other ideas and suggestions, please contact at chang@emotivellc.com
	and feel free to contribute to the code
	
/*************************************************************
 * 7. Change log
 ************************************************************/
 
 Alpha 2 release: 6/10/2010
 Alpha 1 release: 6/5/2010