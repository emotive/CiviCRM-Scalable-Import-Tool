/*****************************************
 * CiviCRM Scalable Import Module
 * by Chang Xiao (chang@emotivellc.com)
 * emotive LLC, 2010 - 2011
 * GNU GENERAL LICENSE - see LICENSE.txt
 * Last Update: 3/30/2011
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
	4. at least 512 MB PHP memory allocation to avoid memory exhaustion error
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
	
	1. Logging Toggle: turning "Logging on" is strongly recommended as 
	it will give you feedback on your import after its completion.
	
	2. Log PATH: if Logging is selected, the full path to where the logging file will be stored
	(This should be the full path to /sites/default/files/civicrm_import), the module 
	automatically finds the default path, make sure it exist and it's writable
	
	3. Email logging: If turning "Logging on" will send email notification to you when your 
	import job start as well and when it finishes, it will also give you summarized statistics
	on your import job
 
	4. Email to: Email status and update that should be sent to
	
	5. Email CC: An administrator or other party you wish to CC the email notifications to
	
	6. SMTP Host: Your email's SMTP address, for example: smtp.gmail.com
	
	7. Enable SSL?: Some SMTP requires SSL enabled
	
	8. SMTP Port: Default to 465, check your email provider
	
	9. SMTP User: Your username of the SMTP account: i.e. user@gmail.com
	
	10. SMTP Password: Your password associated with the SMTP account.
	
	11. CMS db prefix: Your drupal CMS table prefix.
	
	12. (Experimental, USE AT YOUR OWN CAUTION!!!!) Import file split lines count: 
	If you have a large file (say 250,000) records and
	set the line count to 50,000, the script will split the file into 5 parts and attempt
	to import through each file. This will prevent timeout and other PHP issues and
	improve import availability and job scheduling. Set this to 0 if you wish to disable this feature.

/*************************************************************
 * 4. Usage
 ************************************************************/	

	* civicrm_import.cron.php * should be ran by the cron on 
	a 5 minutes basis or longer interval. 
	
	For example, you can run it like
	curl http://www.example.com/sites/all/modules/civicrm_import/civicrm_import.cron.php -s
	 
	Import Steps
	 
	1. Browse a CSV file to import, (Recommend with column headers)
	2. Select Mapping options (First name and Email address are required import fields)
	3. Select De-Dupe, Date Format Options
	4. Select grouping/tagging options
	5. Check quick validation report, Import
		
/*************************************************************
 * 5. Known Issues
 ************************************************************/
	
	1. The script will look at blank email, states/province and postal codes as 
	   invalid fields.

	2. The script will only validate email, states/province and postal codes .
	 
	3. The quick validation processing only look at the first 100 columns of the
	   import file.
	
	4. The script will not format custom fields that are date type, so to import 
	   those fields, they must be in yyyy-mm-dd in order to be properly imported.
	   
	5. Email notifications on import completion does not send on large imports.
	   
/*************************************************************
 * 6. Upcoming Features
 ************************************************************/
 
	1.	Intelligent de-dupe by email address: Using email address as de-duping criteria,
	it will improve performance significantly on large databases.  
		
	2.  Custom field data type validation: for validate numbers, integers, etc.
	
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
 1.1 release 5/24/2011
 ------
 Note:
 ------
 Added email only deduping option for faster deduping
 Automatically added contact source to imported contact if the field is not mapped, this helps 
 identify the batch the contact is importd from (In the format of date + import_job_id)
 Updated documentation
 
 1.0 official release :) :) 3/30/2011
 ------
 Note:
 ------
 Added field mapping saving feature
 Import job table sorting now in descending order
 Updated documentation
 
 Beta 2 release: 2/23/2011
 ------
 Note:
 ------
 Added name to each import for helpful descriptions
 Added date format option in appending mode
 Fixed bug with single row import
 Fixed bug with database connection error in validation step
 Fixed bug with ignoring billing addresses when importing 
 only billing addresses.
 
 
 Beta 1 release: 12/23/2010
 ------
 Note:
 ------
 Added import append feature so contact data can be appended
 through the import process to existing contacts
 Added an import job view page to track the progress of import job
 Simplified configuration page
 Email notification is now SMTP based (independent from drupal mail system)
 Ability to add imported contacts to new groups
 Fixed inconsistency in logging, added error csv log to view a csv of validation
 errors, duplicate contacts, etc.
 Fixed bug where last contact in import job gets chopped
 
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