#!/usr/bin/php
<?php

	/*
	AnalyticsWidget: A Google Analytics Widget for Mac OSX
	By Vlad 'Mancini' Alexa, http://vladalexa.com based on code by Robert Scriva

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
	*/

	include('./lib/xml.php'); 
	require('./lib/OutputFormat.php');
	
	date_default_timezone_set('UTC');
	
	function reportJSONArray($xml)	{
		$outputXML["report"]["attr"] = $xml["AnalyticsReport"]["Report attr"];
		$outputXML["report"]["title"] = $xml["AnalyticsReport"]["Report"]["Title"]["PrimaryDateRange"];

		$outputXML["graph"]["title"] = $xml["AnalyticsReport"]["Report"]["Title"];
		$outputXML["graph"]["daterange"] = $xml["AnalyticsReport"]["Report"]["Title"]["PrimaryDateRange"];
		$outputXML["graph"]["xaxislabel"] = $xml["AnalyticsReport"]["Report"]["Graph"]["XAxisLabel"];
		
		$outputXML["graph"]["narrative"] = $xml["AnalyticsReport"]["Report"]["Narrative"]["Line"]["Content"];
		if (strlen($outputXML["graph"]["narrative"]) == 0) $outputXML["graph"]["narrative"] = $xml["AnalyticsReport"]["Report"]["Narrative"][0]["Line"]["Content"];
		if (strlen($outputXML["graph"]["narrative"]) == 0) $outputXML["graph"]["narrative"] = $xml["AnalyticsReport"]["Report"]["Narrative"]["Line"][0]["Content"];
		if (strlen($outputXML["graph"]["narrative"]) == 0) syslog(LOG_WARNING,"AnalyticsWidget PHP error: narrative empty");
				
		$outputXML["graph"]["data"] = $xml["AnalyticsReport"]["Report"]["Graph"]["Serie"]["Point"];
		if (count($outputXML["graph"]["data"]) == 0) $outputXML["graph"]["data"] = $xml["AnalyticsReport"]["Report"]["Graph"]["Serie"][0]["Point"];
		if (count($outputXML["graph"]["data"]) == 0) syslog(LOG_WARNING,"AnalyticsWidget PHP error: data empty");
				
		$outputXML["graph"]["yaxislabel"] = $xml["AnalyticsReport"]["Report"]["Graph"]["Serie"]["Label"];
		if (strlen($outputXML["graph"]["yaxislabel"]) == 0) $outputXML["graph"]["yaxislabel"] = $xml["AnalyticsReport"]["Report"]["Graph"]["Serie"][0]["Label"];		
		if (strlen($outputXML["graph"]["yaxislabel"]) == 0) syslog(LOG_WARNING,"AnalyticsWidget PHP error: yaxislabel empty");					

		$totalRecords = count($xml["AnalyticsReport"]["Report"]["ItemSummary"]) / 2;
		for ($i = 0; $i < $totalRecords; $i++) { 
			$outputXML["sparkline"][$i]["data"] = $xml["AnalyticsReport"]["Report"]["Sparkline"][$i]["PrimaryValue"];
			$outputXML["sparkline"][$i]["summary"] = $xml["AnalyticsReport"]["Report"]["ItemSummary"][$i];
		}
		if (count($outputXML["sparkline"]) == 0) syslog(LOG_WARNING,"AnalyticsWidget PHP error: ItemSummary empty for ".$xml["AnalyticsReport"]["Report attr"]["name"]);		

		$totalRecords = count($xml["AnalyticsReport"]["Report"]["MiniTable"]) / 2;		
		if ($xml["AnalyticsReport"]["Report"]["MiniTable"][0] == 0) {
			$outputXML["tables"][0]["data"] = $xml["AnalyticsReport"]["Report"]["MiniTable"]["Row"];
			$outputXML["tables"][0]["keycolname"] = $xml["AnalyticsReport"]["Report"]["MiniTable"]["KeyColumnName"];
			$outputXML["tables"][0]["colname"] = $xml["AnalyticsReport"]["Report"]["MiniTable"]["ColumnName"];
		} else {
			for ($i = 0; $i < $totalRecords; $i++) { 
				$outputXML["tables"][$i]["data"] = $xml["AnalyticsReport"]["Report"]["MiniTable"][$i]["Row"];
				$outputXML["tables"][$i]["keycolname"] = $xml["AnalyticsReport"]["Report"]["MiniTable"][$i]["KeyColumnName"];
				$outputXML["tables"][$i]["colname"] = $xml["AnalyticsReport"]["Report"]["MiniTable"][$i]["ColumnName"];
			}
		}		
		if (count($outputXML["tables"]) == 0) syslog(LOG_WARNING,"AnalyticsWidget PHP error: MiniTable empty for ".$xml["AnalyticsReport"]["Report attr"]["name"]);			

		//DEBUG
		//file_put_contents ("/Users/valexa/Desktop/".$xml["AnalyticsReport"]["Report attr"]["name"].".txt" , print_r($outputXML,TRUE)."\n",FILE_APPEND);
		return($outputXML);
	}

	function getStats($siteProfile, $ga, $widgetid, $daterange) {
		// last 24 hours
		//''1w', '1m', '3m', '6m', '1y'
		$gdfmt = "";
		switch ($daterange) {
			case '1w':
				$date1 = mktime(0, 0, 0, date("m"), date("d")-6,  date("Y"));
				break;
			case '1m':
				$date1 = mktime(0, 0, 0, date("m")-1, date("d"),  date("Y"));
				break;
			case '3m':
				$date1 = mktime(0, 0, 0, date("m")-3, date("d"),  date("Y"));
				break;
			case '6m':
				$date1 = mktime(0, 0, 0, date("m")-6, date("d"),  date("Y"));
				break;
			case '1y':
				$date1 = mktime(0, 0, 0, date("m"), date("d")+1,  date("Y")-1);
				break;
			default:
				$date1 = mktime(0, 0, 0, date("m")-3, date("d"),  date("Y"));
				break;
		}

		$date2 = mktime(0, 0, 0, date("m"), date("d"),  date("Y"));
		$start = date('Ymd', $date1);
		$stop = date('Ymd', $date2);

		$reports = array("dailyvisitors","trafsources","content");

		foreach($reports as $report) {
			$gaReportXML = $ga->getReportData($siteProfile, $start, $stop, $report);		
			$xmlreports[] = XML_unserialize($gaReportXML); 
		}

		$i = 0;
		foreach($xmlreports as $xmlreport) {		
			$outputXML[$i] = reportJSONArray($xmlreport);
			$i++;
		}

		$of = new OutputFormat();
		$json_array = $of->arrayToJSON($outputXML);
		
		//DEBUG
		//file_put_contents ("/Users/valexa/Desktop/debug.txt" , print_r($json_array,TRUE)."\n",FILE_APPEND);		

		$myFile = "data/analyticswidget." . $widgetid . ".json";
		if (file_exists($myFile)) unlink($myFile);
		$fh = fopen($myFile, 'w') or die("can't open file");
		$write = fwrite($fh, $json_array);
		// some output required for the widget to register the output handler event
		if (fclose($fh)) { echo "true";} else { echo "false";} 
		
		if ($write == FALSE){
			$error = "Failed to write ".escapeshellcmd($myFile);
			$fileErr["err"] = true;
			$fileErr["msg"] = $error;

			$of = new OutputFormat();
			print_r($of->arrayToJSON($fileErr));			
		}
	}

	function getSites ( $ga ) {
		$accounts = $ga->getAccounts();
		
		if ( count($accounts) == 0 ) {
			$error = "Error getting analytics accounts for $username";

			$theErr["err"] = true;
			$theErr["msg"] = $error;

			$of = new OutputFormat();
			print_r($of->arrayToJSON($theErr));

			return;
		}
		
		// get all the profiles for all the accounts
		$i = 0;
		foreach ( $accounts as $account ) {
			$prof = $ga->getSiteProfiles($account["id"]);
			$profilelist[] = $prof;
			$accounts_enum = $accounts_enum . " ". $account["name"];
			$i += count($prof);
		}
		
		if ( $i == 0 ) {
			$error = "There were no profiles found for account(s): $accounts_enum";

			$theErr["err"] = true;
			$theErr["msg"] = $error;

			$of = new OutputFormat();
			print_r($of->arrayToJSON($theErr));

			return;
		}	
			
		$i = 0;
		foreach ( $profilelist as $profiles ) {
			foreach($profiles as $profile){ 
				$siteList[$i] = Array("name"=>$profile["name"], "id"=>$profile["id"]);
				$i++;
			}
		}

		$of = new OutputFormat();
		print_r($of->arrayToJSON($siteList));
	}

	$username = urldecode($argv[1]);
	$password = urldecode($argv[2]);
	$option   = $argv[3];
	$siteid   = $argv[4];
	$widgetid   = $argv[5];
	$daterange   = $argv[6];

	require_once(dirname(__FILE__).'/lib/tantan/lib.googleanalytics.php');

	$ga = new tantan_GoogleAnalytics();

	if (!$ga->login($username,$password)) {
		$error = "There was a problem logging into your Google Analytics account $username<br>".escapeshellcmd($ga->getError());
		
		$loginErr["err"] = true;
		$loginErr["msg"] = $error;
//		echo "loginError = true; errorText = \"$error\";";

		$of = new OutputFormat();
		print_r($of->arrayToJSON($loginErr));

		return;
	} else { // login ok
           $session = $ga->getSession();
  	}

	switch ( $option ) {
		case 'sites':
			getSites($ga);
		break;
		case 'stats':
			getStats($siteid, $ga, $widgetid, $daterange);
		break;
	}

?>