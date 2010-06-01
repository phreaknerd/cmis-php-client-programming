<?php
//  Author: Rich McKnight rich.mcknight@alfresco.com http://oldschooltechie.com
require_once('cmis_repository_wrapper.php');
$repo_url = $_SERVER["argv"][1];
$repo_username = $_SERVER["argv"][2];
$repo_password = $_SERVER["argv"][3];
$repo_search_text = $_SERVER["argv"][4];
$repo_debug = $_SERVER["argv"][5];
   
$client=new CMISService($repo_url,$repo_username,$repo_password);

if ($repo_debug) {
	print "Repository Information:\n===========================================\n";
	print_r($client->workspace);
	print "\n===========================================\n\n";
}

$query=sprintf("SELECT cmis:name,score() as rel from cmis:document WHERE CONTAINS('%s')",$repo_search_text);
$objs=$client->query($query);
if ($repo_debug) {
	print "Returned Objects\n:\n===========================================\n";
	print_r($objs);
	print "\n===========================================\n\n";
}

foreach ($objs->objectList as $obj) {
	print "Document: " . $obj->properties['cmis:name'] . "\n";
}

if ($repo_debug > 2) {
	print "Final State of CLient:\n===========================================\n";
	print_r($client);
}
