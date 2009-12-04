<?php
//  Author: Rich McKnight rich.mcknight@alfresco.com http://oldschooltechie.com
require_once('cmis_repository_wrapper.php');
$repo_url = $_SERVER["argv"][1];
$repo_username = $_SERVER["argv"][2];
$repo_password = $_SERVER["argv"][3];
$repo_folder = $_SERVER["argv"][4];
$repo_debug = $_SERVER["argv"][5];
   
$client=new CMISService($repo_url,$repo_username,$repo_password);

if ($repo_debug) {
	print "Repository Information:\n===========================================\n";
	print_r($client->workspace);
	print "\n===========================================\n\n";
}

$myfolder=$client->getObjectByPath($repo_folder);
if ($repo_debug) {
	print "Folder Object:\n===========================================\n";
	print_r($myfolder);
	print "\n===========================================\n\n";
}
// We only had one object in the feed so get its "down" link -- the one to get all of its children
$children_url = $myfolder->links['down'];
if ($repo_debug) {
	print "Folder Get Children URL=" . $children_url . "\n";
}

// Get all of the child objects
$ret=$client->doGet($children_url);
$objs=$client->extractObjectFeed($ret->body);
if ($repo_debug) {
	if ($repo_debug > 1) {
		print "Folder Children XML and HTTP STATUS:\n===========================================\n";
		print_r($ret);
	}
	print "Folder Children Objects\n:\n===========================================\n";
	print_r($objs);
	print "\n===========================================\n\n";
}

foreach ($objs->objectList as $obj) {
	if ($obj->properties['cmis:baseTypeId'] == "cmis:document") {
		print "Document: " . $obj->properties['cmis:name'] . "\n";
	} elseif ($obj->properties['cmis:baseTypeId'] == "cmis:folder") {
		print "Folder: " . $obj->properties['cmis:name'] . "\n";
	} else {
		print "Unknown Object Type: " . $obj->properties['cmis:name'] . "\n";
	}
}

