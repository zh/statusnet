<?php

define('INSTALLDIR', dirname(__FILE__));

require_once(INSTALLDIR . "/common.php");

$action = $_REQUEST['action'];
$actionfile = INSTALLDIR."/actions/$action.php";

if (file_exists($actionfile)) {
	require_once($actionfile);
	$action_function = 'handle_' . $action;
	if (function_exists($action_function)) {
	call_user_func($action_function);
} else {
	// redirect to main
}

?>