<?php

if (!defined('MICROBLOG')) { exit(1) }

class Action { // lawsuit

	var $args;
	
	function Action() {
	}
	
	function arg($key) {
		if (array_has_key($this->args, $key)) {
			return $this->args[$key];
		} else {
			return NULL;
		}
	}
	
	function handle($args) {
		$this->args = copy($argarray);
	}
}
