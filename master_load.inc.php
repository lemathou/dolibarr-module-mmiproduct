<?php

require_once('env.inc.php');

if (!defined('NOSESSION')) {
	define('NOSESSION', '1');
}

$sapi_type = php_sapi_name();
// Test if batch mode
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: You are using PHP for CGI. To execute ".$script_file." from command line, you must use PHP for CLI mode.\n";
	exit(-1);
}

// Load Dolibarr environment
$res = 0;
// Try master.inc.php using relative path
if (!$res && file_exists("../master.inc.php")) {
	$res = @include "../master.inc.php";
}
if (!$res && file_exists("../../master.inc.php")) {
	$res = @include "../../master.inc.php";
}
if (!$res && file_exists("../../../master.inc.php")) {
	$res = @include "../../../master.inc.php";
}
if (!$res) {
	die("Include of master fails");
}

// Useful things
$moduleclassname = 'mod'.$modulename;
$moduledir = strtolower($modulename);
$modulecontext = $moduledir."@".$moduledir;
$moduleprefix = strtoupper($modulename);

// Loading
$langs->load("main");
$langs->load($modulecontext);

// Global variables
$error = 0;

/*
 * Main
 */

@set_time_limit(0);
print "***** ".$script_file." (".DOL_VERSION.") pid=".dol_getmypid()." *****\n";
dol_syslog($script_file." launched with arg ".join(',', $argv));
