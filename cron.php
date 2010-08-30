<?php
/*****************************************
 * CiviCRM mass import tool
 * @Chang Xiao (chang@emotivellc.com)
 * a shell curl that can be executed without
 * script limitations
 *****************************************/

main();


function main() {

// path to the cron script
$script_uri = cururl();
$curl_uri = substr($script_uri, 0, strrpos($script_uri, '/')) . '/civicrm_import.cron.php';

shell_exec("curl $curl_uri");

}

/*
 * This function simply returns the current url of the web page
*/
function cururl()
{
	$pageURL = 'http';
	if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80") {
	$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
	$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}

	return $pageURL;
}

?>