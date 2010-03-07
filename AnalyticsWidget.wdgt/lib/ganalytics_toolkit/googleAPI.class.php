<?php

//*****************************************************************************************************************************************
//* class: googleAPI
//* Purpose: This is version 1.0 of a toolkit that can be used to easily retreve data from the google analytics API
//* 
//* http://www.rawseo.com					- Justin Silverton - Jaslabs, inc. (April 30th, 2009)
//* Note: you can use this file for anything.  There are no restrictions.
//* 	  If you are using it in a cool project, send me an email: justin@jaslabs.com      
//* Requirements: PHP 5 and higher
//*****************************************************************************************************************************************
 
define("GOOGLEAPI_URL","https://www.google.com/accounts/ClientLogin");
define("GOOGLEAPI_FEEDS_URL","https://www.google.com/analytics/feeds/accounts/default");
define("GOOGLEAPI_DATA","https://www.google.com/analytics/feeds/data");

define("CLIENT_AGENT","rawseo-analyticstoolkit-1");

//requires PHP 5

class googleAPI {
	
	private $username;
	private $password;
	private $profile;
	private $profileData;		//stores all the profiles retrieved from the api (trackingID: sed in google tracking javascript (UA-...)
								//profileID: required to get any reporting information from your profile
	
	private $reportData;		//report data that is returned
								
	//oAuth tokens
	private $SID;
	private $LSID;
	private $Auth;
	
	//profile is the name of your profile when you login to your analytics account
	
	function __construct($username,$password,$profile) 
	{
		$this->username = $username;
		$this->password = $password;
		$this->profile = $profile;
		$this->profileData = array();
		$this->reportData = array();
		
		if ($this->login())
		{
			$this->getProfileInformation();	
		}
	}	
	
	
	
	//retrieve analytics reports
	//information about view report parameters
	//metrics:	(maximum 10 metrics per request, separated by commons): ga:pageviews,ga:uniquePageviews
	//dimensions: (maximum 7 metrics per request, separated by commas): ga:country,ga:browser)
	//dateStart: 	(example: 2008-07-10) (required)
	//dateEnd: 	  	(example: 2008-08-10) (required)
	//startIndex:	where we are starting in the results
	//maxResults:	maximum results we are displaying (google will only allow you to retrieve 1000 results max per query)
	
	public function viewReport($dateStart,$dateEnd,$metrics="",$dimensions="",$startIndex='1',$maxResults='1000')
	{
		$numMetrics = count(explode(',',$metrics));
		$numDemensions = count(explode(',',$dimensions));
		
		$response = $this->connect(GOOGLEAPI_DATA."?start-index=".$startIndex."&max-results=".$maxResults."&ids=".$this->profileData[$this->profile]['profileID']."&dimensions=".$dimensions."&metrics=".$metrics."&start-date=".$dateStart."&end-date=".$dateEnd,"","get");
	
		if ($response !== false)
		{
			$xml = new XMLReader();
    		$xml->xml($response);
    		$responseDecoded = $this->xml2assoc($xml);
    		$xml->close();
			    		
    		for ($x=0;$x<count($responseDecoded);$x++)
    		{
	    		for ($y=0;$y<count($responseDecoded[$x]['value']);$y++)
	    		{	
		    		switch ($responseDecoded[$x]['value'][$y]['tag'])
		    		{
			    		case 'dxp:aggregates':
			    			for ($z=0;$z<count($responseDecoded[$x]['value'][$y]['value']);$z++)
			    			{
				    			$this->reportData[$responseDecoded[$x]['value'][$y]['value'][$z]['attributes']['name']] = $responseDecoded[$x]['value'][$y]['value'][$z]['attributes']['value'];
				    		}
			    		break;
			    		case 'entry':
			    			$entry = array();
			    			for ($z=0;$z<count($responseDecoded[$x]['value'][$y]['value']);$z++)
			    			{
				    			switch($responseDecoded[$x]['value'][$y]['value'][$z]['tag'])
				    			{
					    			case 'dxp:dimension':
					    			case 'dxp:metric':
					    				$entry[$responseDecoded[$x]['value'][$y]['value'][$z]['attributes']['name']] = $responseDecoded[$x]['value'][$y]['value'][$z]['attributes']['value'];
					    			break;
				    			}
				    		}
				    		
				    		if (count($entry) == ($numMetrics+$numDemensions))
				    			$this->reportData[] = $entry;
			    		break;
		    		}
	    		}
    		}
    		
    		return $this->reportData;	
		} else {
			echo $this->lastResponse;
			return false;	
		}
	}
	
	
	//this gets the profile info associated with the analytics account
	private function getProfileInformation()
	{
		$response = $this->connect(GOOGLEAPI_FEEDS_URL,"","get");
		$currentProfile = "";
		
		$xml = new XMLReader();
    	$xml->xml($response);
    	$responseDecoded = $this->xml2assoc($xml);
    	$xml->close();
    	
    	for ($x=0;$x<count($responseDecoded);$x++)
    	{
    		for ($y=0;$y<count($responseDecoded[$x]['value']);$y++)
    		{
	    		if ($responseDecoded[$x]['value'][$y]['tag'] == 'entry')
	    		{
		    		for ($z=0;$z<count($responseDecoded[$x]['value'][$y]['value']);$z++)
		    		{
			    		switch($responseDecoded[$x]['value'][$y]['value'][$z]['tag'])
			    		{
				    		case 'title':
				    			if ($responseDecoded[$x]['value'][$y]['value'][$z]['value'])
				    				$currentProfile = $responseDecoded[$x]['value'][$y]['value'][$z]['value'];
				    		break;
				    		case 'dxp:tableId':
				    			$this->profileData[$currentProfile]['profileID'] = $responseDecoded[$x]['value'][$y]['value'][$z]['value']; 			
				    		break;
				    		case 'dxp:property':
				    		if ($responseDecoded[$x]['value'][$y]['value'][$z]['attributes']['name'] == 'ga:webPropertyId')
				    			$this->profileData[$currentProfile]['trackingID'] = $responseDecoded[$x]['value'][$y]['value'][$z]['attributes']['value'];
				    		break;
			    		}		    		
		    		}
	    		}
    		}
	    }
	}
	
	//convert XML stream to associative array
	private function xml2assoc($xml) {
    	$tree = null;
    	while($xml->read())
        switch ($xml->nodeType) {
            case XMLReader::END_ELEMENT: return $tree;
            case XMLReader::ELEMENT:
                $node = array('tag' => $xml->name, 'value' => $xml->isEmptyElement ? '' : $this->xml2assoc($xml));
                if($xml->hasAttributes)
                    while($xml->moveToNextAttribute())
                        $node['attributes'][$xml->name] = $xml->value;
                $tree[] = $node;
            break;
            case XMLReader::TEXT:
            case XMLReader::CDATA:
                $tree .= $xml->value;
        }
    return $tree;
	}
	 
	//login into the google analytics server
	private function login()
	{
		$response = $this->connect(GOOGLEAPI_URL,"accountType=HOSTED_OR_GOOGLE&Email=".$this->username."&Passwd=".$this->password."&source=".CLIENT_AGENT."&service=analytics");
		
		if ($response !== false)
		{	
			$response_arr = explode("\n",$response);
		
			for ($x=0;$x<count($response_arr);$x++)
			{
				$response_line = explode('=',$response_arr[$x]);
			
				switch($response_line[0])
				{
					case 'SID':
						$this->SID = $response_line[1];
					break;
					case 'LSID':
						$this->LSID = $response_line[1];
					break;
					case 'Auth':
						$this->Auth = $response_line[1];
					break;
				}
			}
			return true;	
		}
		
		return false;
	}
	
	//connect to the server	
	private function connect($url,$postvars="",$type="post")
	{
		$connectHeaders = array();
		
		$handle = curl_init();
		
		//if we are already authenticated, send the auth token (required with each request)
		if (isset($this->Auth))
			$connectHeaders[] = 'Authorization: GoogleLogin auth='.$this->Auth;
		
		//this is to fix a bug with the twitter servers
		//curl sends the expect header, and the twitter servers reject it
		
		curl_setopt($handle,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($handle,CURLOPT_URL,$url);
		
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($handle, CURLOPT_SSL_VERIFYHOST,  2);
		
		if ($type == 'post')
		{
			$connectHeaders[] = 'Content-type: application/x-www-form-urlencoded';
			curl_setopt($handle,CURLOPT_POST,true);
			curl_setopt($handle,CURLOPT_POSTFIELDS,$postvars);
		}
		
		curl_setopt($handle,CURLOPT_HTTPHEADER,$connectHeaders);
		
		$response = curl_exec($handle);
		$response_code_arr = curl_getinfo($handle); 
		$response_code = $response_code_arr['http_code'];
		
		curl_close($handle);	
				
		switch($response_code)
		{
			case 200:
				return $response;
			break;
			default:
				//this means we got an error
				$this->lastResponse = $response;
				return false;		
			break;
		}
	}
}

?>