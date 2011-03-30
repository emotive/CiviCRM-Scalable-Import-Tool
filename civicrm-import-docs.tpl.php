<?php $path = base_path() . drupal_get_path('module', 'civicrm_import') . '/help'; ?>
<p>Table of Contents</p>
<ul style="border:1px solid grey; width:300px; padding:20px auto">
  <li><a href="#overview">Overview</a></li>
  <li><a href="#new_import">Start a new import</a></li>
  <li><a href="#import_config">Import Configurations</a></li>
  <li><a href="#import_jobs">View Import Jobs</a></li>
  <li><a href="#custom_field">Custom Field Value Table</a></li>
  <li><a href="#tips">Useful tips</a></li>
</ul>
<h2><a name="overview" id="overview"></a>Overview</h2>
<p>The CiviCRM Mass Import module aims to facilitate large scale contact import. <em>For smaller import jobs (less than 2 MB) import file, it is suggested to use the native CiviCRM import process</em>.</p>
<p>This module offers a few advantages:</p>
<ul>
  <li><strong>Speed:</strong> Through simplified import validation process, contacts can be imported at faster pace.</li>
  <li><strong>Background Process: </strong>The import job runs in the background so your do not have to keep the browser open at all times.</li>
  <li><strong>Staggered Import (beta): </strong>Splitting the import job into smaller pieces and process them sequentially to avoid dreadful time-out problems from PHP and MySQL.</li>
  <li><strong>Mail Notification: </strong>Site owners will be notified via email on their import status.</li>
</ul>
<p>Some things that this module lacks:</p>
<ul>
  <li><strong>Validation: </strong>This module only does a quick validation on <em>email address</em>, <em>states</em>, <em>zip code</em> for the first 100 records to be imported. It does not validate all the fields. A good and <strong>CLEAN IMPORT FILE</strong> is vitally important for the success of the import job.</li>
  <li><strong>Limited Fields: </strong>The module does not offer every field that the standard contact import process CiviCRM offers, it mainly offers fields in the following categories:
    <ul>
      <li>Contact Fields (First Name, Last Name, Email, DOB, ...)</li>
      <li>Address Fields (Home address, Business address)</li>
      <li>Communication Fields (Home phone, Work phone, ...)</li>
      <li>Custom Fields</li>
    </ul>
  </li>
  <li><strong>Contact types: </strong>The module only allows<em><strong> Individual Contact Type </strong></em>import, also no relationship can be imported.</li>
</ul>
<p>It is also strongly suggested that you have a dedicated server and at least 512 MB of memory allocated to PHP. The ability to set php configuration settings such as timeout limit, etc would also be very helpful.</p>
<h2><a name="new_import" id="new_import"></a>Start a new import</h2>
<p>To start a new import, go to <em>admin/civi_import/import</em> on your site path. You should see a screen similar to below:</p>
<p>&nbsp;</p>
<p><img src="<?php print $path; ?>/new_import_1.png" width="647" height="542" alt="New Import" /></p>
<ul>
  <li><strong>Import Name: </strong>Put a name for this import job, i.e. &quot;Membership import 12/22/2011&quot;<strong><br />
    <br />
  </strong></li>
  <li><strong>Import Type: </strong>choose <em>New Import</em> if you wish to import new contacts into CiviCRM. If you wish to add (append) additional information on existing contacts choose <em>Data Append </em>mode. Please note that if you choose to use data append mode, you <strong>MUST </strong>have a field in your import file that can be mapped to the <strong>internal contact id </strong>or <strong>external identifier </strong>(if it exists) for the existing contacts. Note that the id mapping is very important because if mapped to the wrong contacts the result could be bad contact data.<br />
    <br />
  Example: (Mapping to internal contact id)<br />
  <br />
  <em>Existing contacts:<br />
  <br />
  </em>
  <table width="95%" border="1">
    <tr>
      <td><strong>Internal Contact ID</strong></td>
      <td><strong>First Name</strong></td>
      <td><strong>Last Name</strong></td>
      <td><strong>Email</strong></td>
      </tr>
    <tr>
      <td>1</td>
      <td>John</td>
      <td>Doe</td>
      <td>johndoe@email.com</td>
      </tr>
    <tr>
      <td>2</td>
      <td>William</td>
      <td>Johnson</td>
      <td>wjohnson@email.com</td>
      </tr>
    <tr>
      <td>3</td>
      <td>Jane</td>
      <td>Doe</td>
      <td>jadoe@email.com</td>
      </tr>
    <tr>
      <td>4</td>
      <td>Robert</td>
      <td>Thompson</td>
      <td>rthompson@email.com</td>
      </tr>
  </table>
  <em>  </em><br />
  <em>Appending file:</em><br />
    <br />
    <table width="95%" border="1">
      <tr>
        <td><strong>Internal Contact ID (mapping to)</strong></td>
        <td><strong>Middle Name</strong></td>
        <td><strong>Date of Birth</strong></td>
      </tr>
      <tr>
        <td>1</td>
        <td>Brian</td>
        <td>12/22/1982</td>
      </tr>
      <tr>
        <td>2</td>
        <td>Smith</td>
        <td>3/4/1977</td>
      </tr>
      <tr>
        <td>3</td>
        <td>Louise</td>
        <td>7/8/1967</td>
      </tr>
      <tr>
        <td>4</td>
        <td>David</td>
        <td>11/12/1955</td>
      </tr>
    </table>
    <br />
    <em>Resulting contact data (after import)</em><br />
    <br />
    <table width="95%" border="1">
      <tr>
        <td><strong>Internal Contact ID</strong></td>
        <td><strong>First Name</strong></td>
        <td><strong>Middle Name</strong></td>
        <td><em><strong>Last Name</strong></em></td>
        <td><em><strong>Date of Birth</strong></em></td>
        <td><strong>Email</strong></td>
      </tr>
      <tr>
        <td>1</td>
        <td>John</td>
        <td>Brian</td>
        <td><em>Doe</em></td>
        <td><em>12/22/1982</em></td>
        <td>johndoe@email.com</td>
      </tr>
      <tr>
        <td>2</td>
        <td>William</td>
        <td>Smith</td>
        <td><em>Johnson</em></td>
        <td><em>3/4/1977</em></td>
        <td>wjohnson@email.com</td>
      </tr>
      <tr>
        <td>3</td>
        <td>Jane</td>
        <td>Louise</td>
        <td><em>Doe</em></td>
        <td><em>7/8/1967</em></td>
        <td>jadoe@email.com</td>
      </tr>
      <tr>
        <td>4</td>
        <td>Robert</td>
        <td>David</td>
        <td><em>Thompson</em></td>
        <td><em>11/12/1955</em></td>
        <td>rthompson@email.com</td>
      </tr>
    </table>
    <br />
<br />
  </li>
  <li><strong>Import File: </strong>Upload the import file, please first click &quot;Choose File&quot; then click &quot;Update&quot; to upload the file. Note that the import file <strong>MUST </strong>be an CSV format (comma separated value file) that can be saved from Microsoft Excel or other spreadsheet software. The upload limit can fluctuate due to maximum file size allowed via PHP configuration.<br />
    <br />
  </li>
  <li><strong>Saved Field Mapping:</strong> Use a previously saved field mapping in this import. This allows a faster import process if you have import files that have the same order of column headings and the saved field mapping serves as a &quot;mapping template&quot; that can be reloaded.</li>
</ul>
<p>Lastly, if the first row of your import file contains column headers (It is usually the case, check the box that says &quot;First row contains column headers&quot;)</p>
<p>After you clicked &quot;Next, you should see the following screen&quot;</p>
<p><img src="<?php print $path; ?>/new_import_2.png" width="647" height="542" alt="New Import, step 2" />]</p>
<p>There are three sections in this import step:</p>
<ul>
  <li><strong>Import field mapping: </strong>The screen will preview the first two rows of data from your import file and give you a mapping option for each field. Please carefully map each import file column to a CiviCRM contact field. (Note that if your first row contains column headers they will appear as well)<br />
    (It is strongly recommended to import
    <strong>&quot;Source of Contact Data&quot; </strong>and/or <strong>External Identifier</strong> field, it can be used to identify contacts imported during a specific import job in case any error occurs)<br />
  </li>
  <li><strong>Import Options: </strong>Various options for the import.
    <ul>
      <li>Check for duplicate contact: enable this option will check all imported contacts against existing contacts. If an existing contact is found, the duplicated record will not be imported. The module uses the CiviCRM default de-dupe rule. (Check duplicate contact based on email)</li>
      <li>Date Format: The date format in the import file, mainly used in <strong>&quot;Date of Birth&quot; </strong>and other custom date fields.</li>
    </ul>
  </li>
  <li><strong>Import contact group(s) and tag(s) options: </strong>Here you will have the ability to add the newly imported contacts to one or more groups and/or one or more tags. You can add the newly imported contacts to existing groups and tags or choose to create a new group for the imported contacts.</li>
  <li><strong>Field mapping name: </strong>If you wish to re-use the field mapping from this import job, you can enter a name, next time you are importing a file with the same column headings you can reuse the field mapping from this import job.</li>
</ul>
<p>Once you have clicked next, you may see the following screen:</p>
<p><img src="<?php print $path; ?>/new_import_3.png" alt="import validation" width="578" height="530" /></p>
<p>It is important that you take a look at the quick validation report to see contacts that failed to validate. It is suggested that the import file be cleaned and restart the import process until validation come back with no errors. It should look like below:</p>
<p><img src="<?php print $path; ?>/new_import_4.png" alt="clean validation" width="566" height="522" /></p>
<p>Once you have clicked &quot;Import&quot;, your import will start. (The import job should start within the time range set by the cron job, please see readme.txt for more information.</p>
<h2><a name="import_config" id="import_config"></a>Import Configuration</h2>
<p><img src="<?php print $path; ?>/import_config_1.png" alt="Import Configuration" width="577" height="1242" /></p>
<ol>
  <li> Logging Toggle: turning  &quot;Logging on&quot; is strongly recommended as <br />
    it will give you feedback on your  import after its completion.<br />
  </li>
  <li>Log PATH: if Logging is selected,  the full path to where the logging file will be stored<br />
    (This should be the full path to  /sites/default/files/civicrm_import), the module <br />
    automatically finds the default path,  make sure it exist and it can be written.<br />
  </li>
  <li>Email logging: If turning  &quot;Logging on&quot; will send email notification to you when your <br />
    import job start as well and when it  finishes, it will also give you summarized statistics<br />
    on your import job.<br />
  </li>
  <li>Email to: Email status and update  that should be sent to.<br />
  </li>
  <li>Email CC: An administrator or other  party you wish to CC the email notifications to.</li>
  <li>SMTP Host: Your email's SMTP  address, for example: smtp.gmail.com</li>
  <li>Enable SSL?: Some SMTP requires SSL  enabled</li>
  <li> SMTP Port: Default to 465, check  your email provider</li>
  <li>SMTP User: Your username of the SMTP  account: i.e. <a href="mailto:user@gmail.com">user@gmail.com</a></li>
  <li>SMTP Password: Your password  associated with the SMTP account.</li>
  <li> CMS db prefix: Your drupal CMS  table prefix.</li>
  <li>(Experimental, USE AT YOUR OWN  CAUTION!!!!) Import file split lines count: <br />
    If you have a large file (say 250,000)  records and<br />
    set the line count to 50,000, the  script will split the file into 5 parts and attempt<br />
    to import through each file. This will  prevent timeout and other PHP issues and<br />
    improve import availability and job  scheduling. Set this to 0 if you wish to disable this feature.</li>
</ol>
<h2><a name="import_jobs" id="import_jobs"></a>View Import Jobs</h2>
<p><img src="<?php print $path; ?>/import_job.png" alt="Import job" width="623" height="238" /></p>
<p>You can always take a look at previous import jobs as well as import job in progress, this page gives information regarding to each import jobs.</p>
<p>A few things to take note: </p>
<ul>
  <li><strong>Status: </strong>Job status may reflect the following (in order during the import process)
    <ol>
      <li> <em>Not started</em>: When the import job is submitted but yet to be processed</li>
      <li><em>Importing Contacts: </em>The import module is importing contact data</li>
      <li><em> Importing address data: </em>The import module is importing address data and phone numbers</li>
      <li><em> Adding contacts to group:</em> The import module is adding the imported contacts to selected group(s)</li>
      <li><em> Adding contacts to tag:</em> The import module is adding the imported contacts to selected tags</li>
      <li><em>Complete:</em> The import process is complete.</li>
    </ol>
  </li>
  <li><strong>Processed: </strong>This field designates if the import job has been processed, for more detailed progress check the status column</li>
  <li><strong>Contacts Imported: </strong>This shows how many contacts are actually imported over the number of contacts detected from the import file</li>
  <li><strong>Log Files: </strong>Shows links to the import job logs including the import log and the error logs</li>
  <li><strong>Action: </strong>You have the option to delete an import job after it is complete, doing so will not delete all the contacts imported, this should be used when you have an import job that's &quot;Stuck&quot; in order to clear the import queue.</li>
</ul>
<h2><a name="custom_field" id="custom_field"></a>Custom Field Value Table</h2>
<p>The custom field value table serves the function of providing reference of what the &quot;SET VALUE&quot; custom fields values are allowed.</p>
<p>For example, a custom field named housing type only allows three values:</p>
<table width="95%" border="1" cellpadding="3" cellspacing="3">
  <tr>
    <td><strong>Field Label</strong></td>
    <td><strong>Value</strong></td>
  </tr>
  <tr>
    <td>Condominium</td>
    <td>Condo</td>
  </tr>
  <tr>
    <td>Single Family</td>
    <td>SFamily</td>
  </tr>
  <tr>
    <td>Duplex</td>
    <td>Dup</td>
  </tr>
</table>
<p>So if you wish to import this custom field &quot;housing type&quot;, the field value in your import file must be one of the three in the &quot;value&quot; column in the above table. The import module itself does not provide custom field validation.</p>
<h2><a name="tips" id="tips"></a>Useful tips</h2>
<p>Here are some tips that can help facilitate the import process</p>
<ol>
  <li><strong>Prepare a clean import file:</strong> Make sure that the records in the spreadsheet have valid data, use spot check to verify that primary fields all exist and does not contain unrecognized characters and symbols.</li>
  <li><strong>Break-up your large import file into multiple imports:</strong> The possibility of a failed import increases with larger import file, so it would be helpful to break up the file before hand and import them separately.</li>
  <li><strong>Always import source of contact: </strong>Always import &quot;source of contact&quot; field, it should be an unique identifier that you can use to search for contacts created from an import. Moreover, if an import job fails you can always search and purge the contacts imported using the &quot;source of contact&quot; field.</li>
  <li><strong>Verify &quot;SET VALUE&quot; fields: </strong>For fields such as &quot;states&quot;, &quot;gender&quot;, or any &quot;SET VALUE&quot; custom fields, check what the allowed values are and make sure that your import file only contains those allowed values.</li>
  <li><strong>Use proper date format: </strong>The import module expects proper date format given in order to import &quot;date&quot; type fields. To verify that you have the proper date format, you should format the date in the spreadsheet, then open the CSV file in a notepad to make sure that the date format is correct.</li>
</ol>