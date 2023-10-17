<?php
#exit($_SERVER['REQUEST_URI']);
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
#дебаг

$matches = [];
$match   = preg_match('/\w+/', $_GET['r'], $matches);
$target  = ucfirst($matches[0]);


	require_once "Api.php";

	if (file_exists("Controllers/{$target}Controller.php")) {
		require_once("Controllers/{$target}Controller.php");
		$api = new $target();
		echo $api->run();
	} else {
		exit('err');
	}
