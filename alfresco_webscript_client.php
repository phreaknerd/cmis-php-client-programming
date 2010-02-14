<?php
//  Author: Rich McKnight rich.mcknight@alfresco.com http://oldschooltechie.com
class AlfrescoWebscriptClient {
	// Handles --
	//   Workspace -- but only endpoints with a single repo
	//   Entry -- but only for objects
	//   Feeds -- but only for non-hierarchical feeds
	// Does not handle --
	//   -- Hierarchical Feeds
	//   -- Types
	//   -- Others?
	// Only Handles Basic Auth
	// Very Little Error Checking
	// Does not work against pre CMIS 1.0 Repos
	var $url;
	var $username;
	var $password;
	var $authenticated;

	function __construct($url,$username,$password) {
		$this->connect($url,$username,$password);
	}

	function connect($url,$username,$password) {
		$this->url = $url;
		$this->username = $username;
		$this->password = $password;
		$this->authenticated = false;
		$retval=$this->doGet($this->url);
		if ($retval->code == 200 || $retval->code == 201) {
			$this->authenticated=true;
		}
	}

	function doGet($url) {
		return $this->doRequest($url);
	}

	function doDelete($url) {
		return $this->doRequest($url,"DELETE");
	}

	function doPost($url,$content,$contentType,$charset=null) {
		return $this->doRequest($url,"POST",$content,$contentType);
	}

	function doPut($url,$content,$contentType,$charset=null) {
		return $this->doRequest($url,"PUT",$content,$contentType);
	}

	function doRequest($url,$method="GET",$content=null,$contentType=null,$charset=null) {
		// Process the HTTP request
		// 'til now only the GET request has been tested
		// Does not URL encode any inputs yet
		$session = curl_init($url);
		curl_setopt($session,CURLOPT_HEADER,false);
		curl_setopt($session,CURLOPT_RETURNTRANSFER,true);
		curl_setopt($session,CURLOPT_USERPWD,$this->username . ":" . $this->password);
		curl_setopt($session,CURLOPT_CUSTOMREQUEST,$method);
		if ($contentType) {
			$headers=array();
			$headers["Content-Type"]=$contentType;
			curl_setopt($session,CURLOPT_HTTPHEADER, $headers);
		}
		if ($content) {
			curl_setopt($session,CURLOPT_POSTFIELDS, $content);
		}
		if ($method == "POST") {
			  curl_setopt($session, CURLOPT_HTTPHEADER, array("Content-Type: " . $contentType));			
			  curl_setopt($session,CURLOPT_POST,true);
		}
		$retval = new stdClass();
		$retval->body=curl_exec($session);
		$retval->code = curl_getinfo($session,CURLINFO_HTTP_CODE);
		curl_close($session);
		return $retval;
	}
}
