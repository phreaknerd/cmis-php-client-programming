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

	// Static Utility Functions
	static function processTemplate($template,$values=array()) {
		// Fill in the blanks -- 
		$retval=$template;
		if (is_array($values)) {
			foreach ($values as $name => $value) {
				$retval = str_replace("{" . $name . "}",$value,$retval);
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
		// RRM -- NEED TO ADD ALLOWABLEACTIONS
		$retval = new stdClass();
		$retval->links=CMISRepositoryWrapper::getLinksArray($xmlnode);
        $retval->properties=array();
		$prop_nodes = $xmlnode->getElementsByTagName("object")->item(0)->getElementsByTagName("properties")->item(0)->childNodes;
		foreach ($prop_nodes as $pn) {
			if ($pn->attributes) {
				$retval->properties[$pn->attributes->getNamedItem("propertyDefinitionId")->nodeValue] = $pn->getElementsByTagName("value")->item(0)->nodeValue;
			}
		}
        $retval->uuid=$xmlnode->getElementsByTagName("id")->item(0)->nodeValue;
        $retval->id=$retval->properties["cmis:objectId"];
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

// Option Contants for Array Indexing
// -- Generally optional flags that control how much information is returned
// -- Change log token is an anomoly -- but included in URL as parameter
define("OPT_MAX_ITEMS","maxItems");
define("OPT_SKIP_COUNT","skipCount");
define("OPT_FILTER","filter");
define("OPT_INCLUDE_PROPERTY_DEFINITIONS","includePropertyDefinitions");
define("OPT_INCLUDE_RELATIONSHIPS","includeRelationships");
define("OPT_INCLUDE_POLICY_IDS","includePolicyIds");
define("OPT_RENDITION_FILTER","renditionFilter");
define("OPT_INCLUDE_ACL","includeACL");
define("OPT_INCLUDE_ALLOWABLE_ACTIONS","includeAllowableActions");
define("OPT_DEPTH","depth");
define("OPT_CHANGE_LOG_TOKEN","changeLogToken");

define("LINK_ALLOWABLE_ACTIONS","http://docs.oasis-open.org/ns/cmis/link/200908/allowableactions");

define("MIME_ATOM_XML",'application/atom+xml');
define("MIME_ATOM_XML_ENTRY",'application/atom+xml;type=entry');
define("MIME_ATOM_XML_FEED",'application/atom+xml;type=feed');
define("MIME_CMIS_TREE",'application/cmistree+xml');
define("MIME_CMIS_QUERY",'application/cmisquery+xml');


// Many Links have a pattern to them based upon objectId -- but can that be depended upon?

class CMISService extends CMISRepositoryWrapper {
	var $_link_cache;
	function __construct($url,$username,$password) {
		parent::__construct($url,$username,$password);
		$this->_link_cache=array();
		$this->_title_cache=array();
	}
	
	// Utility Methods -- Added Titles
	// Should refactor to allow for single object
	function cacheObjectLinks($objs) {
		foreach ($objs->objectList as $obj) {
			$this->_link_cache[$obj->id]=$obj->links;
			$this->_title_cache[$obj-id]=$obj->properties["cmis:name"]; // Broad Assumption Here?
		}
	}
	
	function cacheEntryInfo($obj) {
			$this->_link_cache[$obj->id]=$obj->links;
			$this->_title_cache[$obj-id]=$obj->properties["cmis:name"]; // Broad Assumption Here?
	}
	
	function cacheFeedInfo ($objs) {
		foreach ($objs->objectList as $obj) {
			$this->cacheEntryInfo($obj);
		}
	}

	function getTitle($objectId) {
		if ($this->_title_cache[$objectId]) {
			return $this->_title_cache[$objectId];
		}
		$obj=$this->getObject($objectId);
		return $obj->properties["cmis:name"];
	}	
	function getLink($objectId,$linkName) {
		if ($this->_link_cache[$objectId][$linkName]) {
			return $this->_link_cache[$objectId][$linkName];
		}
		$obj=$this->getObject($objectId);
		return $obj->link[$linkName];
	}
	
	// Repository Services
	function getRepositories() {
		throw Exception("Not Implemented");
	}
	
	function getRepositoryInfo() {
		return $this->workspace;
	}
	
	function getTypeChildren() {
		throw Exception("Not Implemented");
	}

	function getTypeDescendants() {
		throw Exception("Not Implemented");
	}

	function getTypeDefinition() { // Nice to have
		//$myURL = $this->getLink($objectId,"describedby");
		throw Exception("Not Implemented");
	}

	function getObjectTypeDefinition($objectId) { // Nice to have
		$myURL = $this->getLink($objectId,"describedby");
		$ret=$this->doGet($myURL);
		return $ret;
	}
	//Navigation Services
	function getFolderTree() { // Would Be Useful
		throw Exception("Not Implemented");
	}

	function getDescendants() { // Nice to have
		throw Exception("Not Implemented");
	}

	function getChildren($objectId,$options=array()) {
		$myURL = $this->getLink($objectId,"down");
		//TODO: Need GenURLQueryString Utility
		$ret=$this->doGet($myURL);
		$objs=$this->extractObjectFeed($ret->body);
		$this->cacheFeedInfo($objs);
		return $objs;
	}

	function getFolderParent() { //yes
		throw Exception("Not Implemented");
	}

	function getObjectParents() { // yes
		throw Exception("Not Implemented");
	}

	function getCheckedOutDocs() {
		throw Exception("Not Implemented");
	}

	//Discovery Services
	
	static function getQueryTemplate() {
		ob_start();
		echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
?>
<cmis:query xmlns:cmis="http://docs.oasis-open.org/ns/cmis/core/200908/"
xmlns:cmism="http://docs.oasis-open.org/ns/cmis/messaging/200908/"
xmlns:atom="http://www.w3.org/2005/Atom"
xmlns:app="http://www.w3.org/2007/app"
xmlns:cmisra="http://docs.oasisopen.org/ns/cmis/restatom/200908/">
<cmis:statement>{q}</cmis:statement>
<cmis:searchAllVersions>{searchAllVersions}</cmis:searchAllVersions>
<cmis:includeAllowableActions>{includeAllowableActions}</cmis:includeAllowableActions>
<cmis:includeRelationships>{includeRelationships}</cmis:includeRelationships>
<cmis:renditionFilter>{renditionFilter}</cmis:renditionFilter>
<cmis:maxItems>{maxItems}</cmis:maxItems>
<cmis:skipCount>{skipCount}</cmis:skipCount>
</cmis:query>
<?
		return ob_get_clean();		
	}
	function query($q,$options=array()) {
		static $query_template;
		if (!isset($query_template)) {
			$query_template = CMISService::getQueryTemplate();
		}
		$hash_values=$options;
		$hash_values['q'] = $q;
		$post_value = CMISRepositoryWrapper::processTemplate($query_template,$hash_values);
	    echo "URL: " . $this->workspace->collections['query'];
		echo "POST_VALUE: $post_value";
		$objs = $this->doPost($this->workspace->collections['query'],$post_value,MIME_CMIS_QUERY);
		$this->cacheFeedInfo($objs);
 		return $objs;
	}

	function getContentChanges() {
		throw Exception("Not Implemented");
	}

	//Object Services
	static function getEntryTemplate() {
		ob_start();
		echo '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
?>
<atom:entry xmlns:cmis="http://docs.oasis-open.org/ns/cmis/core/200908/"
xmlns:cmism="http://docs.oasis-open.org/ns/cmis/messaging/200908/"
xmlns:atom="http://www.w3.org/2005/Atom"
xmlns:app="http://www.w3.org/2007/app"
xmlns:cmisra="http://docs.oasis-open.org/ns/cmis/restatom/200908/">
<atom:title>{title}</atom:title>
{SUMMARY}
{CONTENT}
<cmisra:object><cmis:properties>{PROPERTIES}</cmis:properties></cmisra:object>
</atom:entry>
<?
		return ob_get_clean();		
	}
	
	static function getPropertyTemplate() {
		ob_start();
?>
		<cmis:property{propertyType} propertyDefinitionId="{propertyId}">
			<cmis:value>{properties}</cmis:value>
		</cmis:property{propertyType}>
<?
		return ob_get_clean();		
	}
	
	static function processPropertyTemplates($propMap) {
		static $propTemplate;
		if (!isset($propTemplate)) {
			$propTemplate = CMISService::getPropertyTemplate();
		}
		$propertyContent="";
		$hash_values=array();
		foreach ($propMap as $propId => $propDef) {
			$hash_values['propertyType']=$propDef['propertyType'];
			$hash_values['propertyId']=$propId;
			if (is_array($propDef['value'])) {
				$first_one=true;
				$hash_values['properties']="";
				foreach ($propDef['value'] as $val) {
					//This is a bit of a hack
					if ($first_one) {
						$first_one=false;
					} else {
						$hash_values['properties'] .= "</cmis:values>\n<cmis:values>";
					}
					$hash_values['properties'] .= $val;
				}
			} else {
				$hash_values['properties']=$propDef['value'];
			}
			echo "HASH:\n";
			print_r(array("template" =>$propTemplate, "Hash" => $hash_values));
			$propertyContent  .= CMISRepositoryWrapper::processTemplate($propTemplate,$hash_values);
		}
		return $propertyContent;
	}
	
	static function getContentEntry($content,$content_type="application/octet-stream") {
		static $contentTemplate;
		if (!isset($contentTemplate)) {
			$contentTemplate = CMISService::getContentTemplate();
		}
		if ($content) {
			return CMISRepositoryWrapper::processTemplate($contentTemplate,array("content" => base64_encode($content),"content_type" => $content_type));
		} else {
			return "";
		}
	}

	static function getSummaryTemplate() {
		ob_start();
?>
		<atom:summary>{summary}</atom:summary>
<?
		return ob_get_clean();		
	}

	static function getContentTemplate() {
		ob_start();
?>
		<cmisra:content>
			<cmisra:mediatype>
				{content_type}
			</cmisra:mediatype>
			<cmisra:base64>
				{content}
			</cmisra:base64>
		</cmisra:content>
<?
		return ob_get_clean();		
	}
	static function createAtomEntry($name,$properties) {
		
	}
	function getObject($objectId,$options=array()) {
		$varmap=$options;
		$varmap["id"]=$objectId;
 		$obj_url = $this->processTemplate($this->workspace->uritemplates['objectbyid'],$varmap);
		$ret = $this->doGet($obj_url);
		$obj=$this->extractObject($ret->body);
		$this->cacheEntryInfo($obj);
 		return $obj;
	}

	function getObjectByPath($path,$options=array()) {
		$varmap=$options;
		$varmap["path"]=$path;
 		$obj_url = $this->processTemplate($this->workspace->uritemplates['objectbypath'],$varmap);
		$ret = $this->doGet($obj_url);
		$obj=$this->extractObject($ret->body);
		$this->cacheEntryInfo($obj);
 		return $obj;
	}

	function getProperties($objectId,$options=array()) {
		// May need to set the options array default -- 
		return getObject($objectId,$options);
	}

	function getAllowableActions($objectId,$options=array()) {
		// get stripped down version of object (for the links) and then get the allowable actions?
		// Low priority -- can get all information when getting object
		throw Exception("Not Implemented");
	}

	function getRenditions($objectId,$options=array(OPT_RENDITION_FILTER => "*")) {
		return getObject($objectId,$options);
	}

	function getContentStream() { // Yes
		throw Exception("Not Implemented");
	}

	function postObject($folderId,$objectName,$objectType,$properties=array(),$content=null,$content_type="application/octet-stream",$options=array()) { // Yes
		$myURL = $this->getLink($folderId,"down");
		static $entry_template;
		if (!isset($entry_template)) {
			$entry_template = CMISService::getEntryTemplate();
		}
		if (is_array($properties)) {
			$hash_values=$properties;
		} else {
			$hash_values=array();
		}
		if (!isset($hash_values["cmis:objectTypeId"])) {
			$hash_values["cmis:objectTypeId"]=array("propertyType" => "Id","value" => $objectType);
		}
		$properties_xml = CMISService::processPropertyTemplates($hash_values);
		if (is_array($options)) {
			$hash_values=$options;
		} else {
			$hash_values=array();
		}
		$hash_values["PROPERTIES"]=$properties_xml;
		$hash_values["SUMMARY"]=CMISService::getSummaryTemplate();
		if ($content) {
			$hash_values["CONTENT"]=CMISService::getContentEntry($content,$content_type);
		}
		if (!isset($hash_values['title'])) {
			$hash_values['title'] = $objectName;
		}
		if (!isset($hash_values['summary'])) {
			$hash_values['summary'] = $objectName;
		}
		$post_value = CMISRepositoryWrapper::processTemplate($entry_template,$hash_values);
		echo "POST_VALUE: $post_value";
		$ret = $this->doPost($myURL,$post_value,MIME_ATOM_XML_ENTRY);
		$obj=$this->extractObject($ret->body);
		$this->cacheEntryInfo($obj);
  		return $obj;
	}

	function createDocument($folderId,$fileName,$properties=array(),$content=null,$content_type="application/octet-stream",$options=array()) { // Yes
		return $this->postObject($folderId,$fileName,"cmis:document",$properties,$content,$content_type,$options);
	}

	function createDocumentFromSource() { //Yes?
		throw Exception("Not Implemented in This Binding");
	}

	function createFolder($folderId,$folderName,$properties=array(),$options=array()) { // Yes
		return $this->postObject($folderId,$folderName,"cmis:folder",$properties,null,null,$options);
	}
	
	function createRelationship() { // Not in first Release
		throw Exception("Not Implemented");
	}

	function createPolicy() { // Not in first Release
		throw Exception("Not Implemented");
	}

	function updateProperties($objectId,$properties=array(),$options=array()) { // Yes
		$varmap=$options;
		$varmap["id"]=$objectId;
		$objectName=$this->getTitle($objectId);
 		$obj_url = $this->getLink($objectId,"edit");		
		static $entry_template;
		if (!isset($entry_template)) {
			$entry_template = CMISService::getEntryTemplate();
		}
		if (is_array($properties)) {
			$hash_values=$properties;
		} else {
			$hash_values=array();
		}
		$properties_xml = CMISService::processPropertyTemplates($hash_values);
		if (is_array($options)) {
			$hash_values=$options;
		} else {
			$hash_values=array();
		}
		$hash_values["PROPERTIES"]=$properties_xml;
		$hash_values["SUMMARY"]=CMISService::getSummaryTemplate();
		if (!isset($hash_values['title'])) {
			$hash_values['title'] = $objectName;
		}
		if (!isset($hash_values['summary'])) {
			$hash_values['summary'] = $objectName;
		}
		$put_value = CMISRepositoryWrapper::processTemplate($entry_template,$hash_values);
		echo "PUT_VALUE: $put_value";
		$ret= $this->doPut($obj_url,$put_value,MIME_ATOM_XML_ENTRY);
		$obj=$this->extractObject($ret->body);
		$this->cacheEntryInfo($obj);
  		return $obj;
	}

	function moveObject() { //yes
		throw Exception("Not Implemented");
	}

	function deleteObject($objectId,$options=array()) { //Yes
		$varmap=$options;
		$varmap["id"]=$objectId;
 		$obj_url = $this->getLink($objectId,"edit");		
		$ret = $this->doDelete($obj_url);
		print "DELETING " . $obj_url .  " RET VALUE\n";
		print_r($ret);
		return;
	}

	function deleteTree() { // Nice to have
		throw Exception("Not Implemented");
	}

	function setContentStream() { //Yes
		throw Exception("Not Implemented");
	}

	function deleteContentStream() { //yes
		throw Exception("Not Implemented");
	}

	//Versioning Services // Maybe
	function getPropertiesOfLatestVersion() {
		throw Exception("Not Implemented");
	}

	function getObjectOfLatestVersion() {
		throw Exception("Not Implemented");
	}

	function getAllVersions() {
		throw Exception("Not Implemented");
	}

	function checkOut() {
		throw Exception("Not Implemented");
	}

	function checkIn() {
		throw Exception("Not Implemented");
	}

	function cancelCheckOut() {
		throw Exception("Not Implemented");
	}

	function deleteAllVersions() {
		throw Exception("Not Implemented");
	}

	//Relationship Services
	function getObjectRelationships() {
		// get stripped down version of object (for the links) and then get the relationships?
		// Low priority -- can get all information when getting object
		throw Exception("Not Implemented");
	}

	//Multi-Filing Services
	function addObjectToFolder() { // Probably
		throw Exception("Not Implemented");
	}

	function removeObjectFromFolder() { //Probably
		throw Exception("Not Implemented");
	}

	//Policy Services
	function getAppliedPolicies() {
		throw Exception("Not Implemented");
	}

	function applyPolicy() {
		throw Exception("Not Implemented");
	}

	function removePolicy() {
		throw Exception("Not Implemented");
	}

	//ACL Services
	function getACL() {
		throw Exception("Not Implemented");
	}

	function applyACL() {
		throw Exception("Not Implemented");
	}
}
