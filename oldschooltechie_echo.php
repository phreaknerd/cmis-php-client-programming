<?php
//  Author: Rich McKnight rich.mcknight@alfresco.com http://oldschooltechie.com
require_once('alfresco_webscript_client.php');
$repo_url = "http://localhost:8080/alfresco/service/index";
$repo_username = "admin";
$repo_password = "admin";
$echo_url="http://localhost:8080/alfresco/service/oldschooltechie/echo/a/b/c/d?x=x&y=y";
$client=new AlfrescoWebscriptClient($repo_url,$repo_username,$repo_password);
$myContent="Hello World\nHowdy Mundo\n";
$contentType="text/plain";
$retval=$client->doGet($echo_url);
echo "******* GET *******\n";
print_r($retval);
$retval=$client->doPut($echo_url,$myContent,$contentType);
echo "******* PUT *******\n";
print_r($retval);
$retval=$client->doPost($echo_url,$myContent,$contentType);
echo "******* POST *******\n";
print_r($retval);
$retval=$client->doDelete($echo_url);
echo "******* DELETE *******\n";
print_r($retval);

