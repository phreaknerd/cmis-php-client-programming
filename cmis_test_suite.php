<?php
//  Author: Rich McKnight rich.mcknight@alfresco.com http://oldschooltechie.com
require_once('cmis_repository_wrapper.php');
$repo_url = $_SERVER["argv"][1];
$repo_username = $_SERVER["argv"][2];
$repo_password = $_SERVER["argv"][3];
$repo_folder = $_SERVER["argv"][4];
$repo_new_folder=$_SERVER["argv"][5];
$repo_debug = $_SERVER["argv"][6];
   
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

$my_new_folder=$client->createFolder($myfolder->id,$repo_new_folder);
if ($repo_debug) {
	print "Return From Create Folder\n:\n===========================================\n";
	print_r($my_new_folder);
	print "\n===========================================\n\n";
}

$obj=$client->createDocument($my_new_folder->id,"TextFile.txt",array(),"THIS IS A NEW DOCUMENT","text/plain");
if ($repo_debug) {
        print "Return From Create Document\n:\n===========================================\n";
        print_r($obj);
        print "\n===========================================\n\n";
}

$obj_del=$client->createDocument($my_new_folder->id,"TextFileDel.txt",array(),"THIS IS A NEW DOCUMENT To Be Deleted","text/plain");
if ($repo_debug) {
        print "Return From Create Document\n:\n===========================================\n";
        print_r($obj_del);
        print "\n===========================================\n\n";
}



$objs=$client->getChildren($my_new_folder->id);
if ($repo_debug) {
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

echo "DELETEING " . $obj_del->properties['cmis:name'] ."\n";
$client->deleteObject($obj_del->id);

$objs=$client->getChildren($my_new_folder->id);
if ($repo_debug) {
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

if ($repo_debug > 2) {
	print "Final State of CLient:\n===========================================\n";
	print_r($client);
}
