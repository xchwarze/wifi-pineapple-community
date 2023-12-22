<?php namespace evilportal;

/* Code modified by Frieren Auto Refactor */

header('Content-Type: application/json');

require_once("API.php");
$api = new API();
echo $api->go();
