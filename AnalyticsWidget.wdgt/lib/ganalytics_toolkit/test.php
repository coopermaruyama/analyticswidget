<?php
//*****************************************************************************************************************************************
//* class: googleAPI (this is a test file to show you an example of how
//* Purpose: This is version 1.0 of a toolkit that can be used to easily retreve data from the google analytics API
//* 
//* http://www.rawseo.com					- Justin Silverton - Jaslabs, inc. (April 30th, 2009)
//* Note: you can use this file for anything.  There are no restrictions.
//* 	  If you are using it in a cool project, I would like to see it.  send me an email: justin@jaslabs.com      
//* Requirements: PHP 5 and higher
//*****************************************************************************************************************************************

require_once 'googleAPI.class.php';

$gapi = new googleAPI('your username','your password',"a profile");
$reportData = $gapi->viewReport("2009-04-01","2009-04-25","ga:pageviews,ga:visits,ga:newVisits","ga:country,ga:city,ga:date,ga:browserVersion");

print_r($reportData);
?>