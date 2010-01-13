<?php

// FATMAN v1.0 - settings

// Please see help.html for instructions.

// Freeagent credentials (emailadress:pass)
// By default, HTTP Basic Auth is used, so you will be prompted to enter your username and password in the browser.
// If you want to secure access manually (using .htaccess for example), you can specify your credentials and company name (e.g. company.freeagentcentral.com) below:

// $company = "";
// $fac_username = "";
// $fac_password = "";

// Set this to the displayed symbol for your currency (there's no  way to pull this from API currently)
$currency = 'EUR';

// Set this to the amount of recent tasks to show (keep to the bare minimum as it will slow page loads down)
$recenttasklimit = 10;

// Set to TRUE if you want to log the start time and end time in the timeslip comment field, e.g. 14:12-19:54.
// This doesn't seem to show up on the default invoices.
$timeslipcomment = TRUE;

// Default values for new projects
$defaultbillingbasis = 1;
$defaultbudget = 0;
$defaultbudgetunits = "Hours";

// * Error reporting
error_reporting(E_ALL & E_NOTICE);

// * our time tracking logfile
$logfile = "freeagentlog.xml";

// * our time tracking state file; your company and userid will be appended to this, e.g. $statefile.$companyid.$userid
$statefile = "freeagentlog.state";


// END OF SETTINGS
?>
