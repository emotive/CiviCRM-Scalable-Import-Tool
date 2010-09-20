/*****************************************
 * CiviCRM Scalable Import Module
 * by Chang Xiao (chang@emotivellc.com)
 * EMotive LLC, 2010
 * GNU GENERAL LICENSE - see LICENSE.txt
 * Last Update: 9/20/2010
 ****************************************/
 
1. Requirements
2. Installation
3. Configuration 
4. Usage
5. Known Issues
6. Upcoming features
7. Future considerations
8. Change log

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
	
	3. Configure the module from admin/civi_import/config

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
	
	(Optional, you can run cron.php in your cron job instead of civicrm_import.cron.php,
	it simply uses a issues shell_exec cURL to prevent timeout on some servers)
	
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
 * 6. Upcoming Features
 ************************************************************/
 
	1.	Ability to append contact data to update existing contacts
	2.	Track/view Import process
	3.  Add new group during the import process
	4.  Integrate mapping information with CiviCRM to save import mapping

	If you have other ideas and suggestions, please contact at chang@emotivellc.com
	and feel free to contribute to the code


/*************************************************************
 * 7. Future Consideration
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
 * 8. Change log
 ************************************************************/
 
 Alpha 3 release: 9/20/2010
 ------
 Note:
 ------
 Added auto detect database settings, logging path from configuration page
 Added auto path detection to drupal installation so no longer need to change path in
 civicrm_import.cron.php, cron.php and civicrm_importer.class.php
 
 Alpha 2 release: 6/10/2010
 ------
 Note:
 ------
 Fixed CiviCRM Contact API Memory Leak
 
 Alpha 1 release: 6/5/2010
 ------
 Note:
 ------
 First Working Version