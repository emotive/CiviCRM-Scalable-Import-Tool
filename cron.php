<?php

// change the URL to the url of your drupal installation
$url = 'http://yoursite.com/sites/all/modules/civicrm_import/civicrm_import.cron.php';

shell_exec("curl $url");

?>