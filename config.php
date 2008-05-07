<?php

$dsn = array(
			     'phptype'  => 'pgsql',
			     'username' => 'someuser',
			     'password' => 'apasswd',
			     'hostspec' => 'localhost',
			     'database' => 'thedb',
			 );

$options = array(
				     'debug'       => 2,
				     'portability' => DB_PORTABILITY_ALL,
				 );

$db =& DB::connect($dsn, $options);
if (PEAR::isError($db)) {
	    die($db->getMessage());
}

$config['db'] =
  array( 'username' => 'stoica',
		 'password' => 'replaceme',

