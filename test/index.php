<?php


/*
 *	CouchDBGateway
 *	==============
 *	A handy gateway for CouchDB written in php
 *	==============
 *	Usage:
 */
$path = dirname(__FILE__).'/lib';
require($path.'/CouchDBConnection.php');
require($path.'/CouchDBRequest.php');
require($path.'/CouchDBResponse.php');

//first we need to set the connection configuration array
$config = array(
	'host'=>'localhost',
	'port'=>5984,
	'db'=>'demodb',			//we should specify the database from the beginning.
	'username'=>'user',		//optional parameter, for authentication
	'password'=>'pass',		//optional parameter, for authentication
	'ssl'=>false,			//set this as true for https connection.
);

//create the CouchDBConnection with the config array
$db = new CouchDBConnection($config);

//retrieve all the docs as an associative array
$allDocs = $db->getAllDocuments(null,true);

//retrieve a view as associative array
$viewDocs = $db->getAllDocuments('/_design/Someview/_view/someview', true);

//create and insert a Document as an associative array
$book = array(
	'title'=>'The pragmatic programmer',
	'author'=>'Andrew Hunt and David Thomas',
	'ISBN'=>'020161622X ISBN-13: 978-0201616224',
);
$response = $db->saveDocument($book);
