<?php
//  Author: Rich McKnight rich.mcknight@alfresco.com http://oldschooltechie.com
class CMISRepositoryWrapper {
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
	var $workspace;
	static $namespaces = array(
    	"cmis" => "http://docs.oasis-open.org/ns/cmis/core/200908/",
    	"cmisra" => "http://docs.oasis-open.org/ns/cmis/restatom/200908/",
    	"atom" => "http://www.w3.org/2005/Atom",
    	"app" => "http://www.w3.org/2007/app",
    );
	
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
			$this->workspace = CMISRepositoryWrapper::extractWorkspace($retval->body);
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
		$retval = new stdClass();
		$retval->body=curl_exec($session);
		$retval->code = curl_getinfo($session,CURLINFO_HTTP_CODE);
		curl_close($session);
		return $retval;
	}

	// Static Utility Functions
	static function processTemplate($template,$values=array()) {
		// Fill in the blanks -- 
		$retval=$template;
		if (is_array($values)) {
			foreach ($values as $name => $value) {
				$retval = str_replace("{" . $name . "}",$value,$template);
			}
		}
		// Fill in any unpoupated variables with ""
		return preg_replace("/{[a-zA-Z0-9_]+}/","",$retval);
		
	}
	
	static function doXQuery($xmldata,$xquery) {
		$doc=new DOMDocument();
		$doc->loadXML($xmldata);
		return CMISRepositoryWrapper::doXQueryFromNode($doc,$xquery);		
	}
	
	static function doXQueryFromNode($xmlnode,$xquery) {
		// Perform an XQUERY on a NODE
		// Register the 4 CMIS namespaces
		$xpath=new DomXPath($xmlnode);
        foreach (CMISRepositoryWrapper::$namespaces as $nspre => $nsuri) {
        	$xpath->registerNamespace($nspre,$nsuri);
        }
        return $xpath->query($xquery);
		
	}
	static function getLinksArray($xmlnode) {
		// Gets the links of an object or a workspace
		// Distinguishes between the two "down" links
		//  -- the children link is put into the associative array with the "down" index
		//  -- the descendants link is put into the associative array with the "down-tree" index
		//  These links are distinquished by the mime type attribute, but these are probably the only two links that share the same rel ..
		//    so this was done as a one off
		$links = array();
		$link_nodes = $xmlnode->getElementsByTagName("link");
		foreach ($link_nodes as $ln) {
			if ($ln->attributes->getNamedItem("rel")->nodeValue == "down" && $ln->attributes->getNamedItem("type")->nodeValue == "application/cmistree+xml") {
				//Descendents and Childredn share same "rel" but different document type
				$links["down-tree"] = $ln->attributes->getNamedItem("href")->nodeValue;
			} else {
				$links[$ln->attributes->getNamedItem("rel")->nodeValue] = $ln->attributes->getNamedItem("href")->nodeValue;
			}
		}	
		return $links;
	}
	static function extractObject($xmldata) {
		$doc=new DOMDocument();
		$doc->loadXML($xmldata);
		return CMISRepositoryWrapper::extractObjectFromNode($doc);		
		
	}
	static function extractObjectFromNode($xmlnode) {
		// Extracts the contents of an Object and organizes them into:
		//  -- Links
		//  -- Properties
		//  -- the Object ID
		$retval = new stdClass();
		$retval->links=CMISRepositoryWrapper::getLinksArray($xmlnode);
        $retval->properties=array();
		$prop_nodes = $xmlnode->getElementsByTagName("object")->item(0)->getElementsByTagName("properties")->item(0)->childNodes;
		foreach ($prop_nodes as $pn) {
			if ($pn->attributes) {
				$retval->properties[$pn->attributes->getNamedItem("propertyDefinitionId")->nodeValue] = $pn->getElementsByTagName("value")->item(0)->nodeValue;
			}
		}
        $retval->id=$xmlnode->getElementsByTagName("id")->item(0)->nodeValue;
        return $retval;
 	}

	static function extractObjectFeed($xmldata) {
		//Assumes only one workspace for now
		$doc=new DOMDocument();
		$doc->loadXML($xmldata);
		return CMISRepositoryWrapper::extractObjectFeedFromNode($doc);
	}
	static function extractObjectFeedFromNode($xmlnode) {
		// Process a feed and extract the objects
		//   Does not handle hierarchy
		//   Provides two arrays 
		//   -- one sequential array (a list)
		//   -- one hash table indexed by objectID
		$retval = new stdClass();
		$retval->objectList=array();
		$retval->objectsById=array();
		$result = CMISRepositoryWrapper::doXQueryFromNode($xmlnode,"//atom:entry");
		foreach ($result as $node) {
		    $obj = CMISRepositoryWrapper::extractObjectFromNode($node);
		    $retval->objectsById[$obj->id]=$obj;
		    $retval->objectList[]=&$retval->objectsById[$obj->id];
		}
		return $retval;
	}
	
	static function extractWorkspace($xmldata) {
		//Assumes only one workspace for now
		$doc=new DOMDocument();
		$doc->loadXML($xmldata);
		return CMISRepositoryWrapper::extractWorkspaceFromNode($doc);
	}
	static function extractWorkspaceFromNode($xmlnode) {
		// Assumes only one workspace for now
		// Load up the workspace object with arrays of
		//  links
		//  URI Templates
		//  Collections
		//  Capabilities
		//  General Repository Information
		$retval = new stdClass();
		$retval->links=CMISRepositoryWrapper::getLinksArray($xmlnode);
		$retval->uritemplates=array();
		$retval->collections=array();
		$retval->capabilities=array();
		$retval->repositoryInfo=array();
		$result= CMISRepositoryWrapper::doXQueryFromNode($xmlnode,"//cmisra:uritemplate");		
		foreach ($result as $node) {
			$retval->uritemplates[$node->getElementsByTagName("type")->item(0)->nodeValue] =
				$node->getElementsByTagName("template")->item(0)->nodeValue;
		}
		$result= CMISRepositoryWrapper::doXQueryFromNode($xmlnode,"//app:collection");		
		foreach ($result as $node) {
			$retval->collections[$node->getElementsByTagName("collectionType")->item(0)->nodeValue] =
				$node->attributes->getNamedItem("href")->nodeValue;
		}
		$result = CMISRepositoryWrapper::doXQueryFromNode($xmlnode,"//cmis:capabilities/*");
		foreach ($result as $node) {
		    $retval->capabilities[$node->nodeName]= $node->nodeValue;
		}
		$result = CMISRepositoryWrapper::doXQueryFromNode($xmlnode,"//cmisra:repositoryInfo/*");
		foreach ($result as $node) {
			if ($node->nodeName != "cmis:capabilities") {
		    	$retval->repositoryInfo[$node->nodeName]= $node->nodeValue;
			}
		}
		
		return $retval;
	}
}
