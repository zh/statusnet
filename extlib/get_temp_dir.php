<?php
if ( !function_exists('sys_get_temp_dir')) {
	function sys_get_temp_dir() {
		if (!empty($_ENV['TMP'])) { return realpath($_ENV['TMP']); }
		if (!empty($_ENV['TMPDIR'])) { return realpath( $_ENV['TMPDIR']); }
		if (!empty($_ENV['TEMP'])) { return realpath( $_ENV['TEMP']); }
		$tempfile=tempnam(uniqid(rand(),TRUE),'');
		if (file_exists($tempfile)) {
			unlink($tempfile);
		}
		return realpath(dirname($tempfile));
	}
}
?>
