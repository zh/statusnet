<?php

define('INSTALLDIR', dirname(__FILE__));
define('MICROBLOG', true);

require_once(INSTALLDIR . "/common.php");

$action = $_REQUEST['action'];
$actionfile = INSTALLDIR."/actions/$action.php";

if (file_exists($actionfile)) {
	require_once($actionfile);
	$action_class = ucfirst($action) . "Action";
	if (function_exists($action_function)) {
	call_user_func($action_function);
} else {
	// redirect to main
}

?>