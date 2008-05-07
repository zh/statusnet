<?php

function handle_login() {
	if ($_REQUEST['METHOD'] == 'POST') {
		if (login_check_user($_REQUEST['user'], $_REQUEST['password'])) {
			
		} else {
		}
	} else {
		if (user_logged_in()) {
		} else {
			login_show_form();
		}
	}
}
	
function login_show_form() {
	html_start();
	html_head("Login");
	html_body();
}
	
function login_check_user($username, $password) {
	
}