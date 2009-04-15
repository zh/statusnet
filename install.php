<?php
/**
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2009, Controlez-Vous, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

define('INSTALLDIR', dirname(__FILE__));

function main()
{
    if (!checkPrereqs())
    {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        handlePost();
    } else {
        showForm();
    }
}

function checkPrereqs()
{
    if (file_exists(INSTALLDIR.'/config.php')) {
         ?><p class="error">Config file &quot;config.php&quot; already exists.</p>
         <?php
        return false;
    }

    if (version_compare(PHP_VERSION, '5.0.0', '<')) {
            ?><p class="error">Require PHP version 5 or greater.</p><?php
		    return false;
    }

    $reqs = array('gd', 'mysql', 'curl',
                  'xmlwriter', 'mbstring',
                  'gettext');

    foreach ($reqs as $req) {
        if (!checkExtension($req)) {
            ?><p class="error">Cannot load required extension &quot;<?php echo $req; ?>&quot;.</p><?php
		    return false;
        }
    }

	if (!is_writable(INSTALLDIR)) {
         ?><p class="error">Cannot write config file to &quot;<?php echo INSTALLDIR; ?>&quot;.</p>
	       <p>On your server, try this command:</p>
	       <blockquote>chmod a+w <?php echo INSTALLDIR; ?></blockquote>
         <?php
	     return false;
	}

	if (!is_writable(INSTALLDIR.'/avatar/')) {
         ?><p class="error">Cannot write avatar directory &quot;<?php echo INSTALLDIR; ?>/avatar/&quot;.</p>
	       <p>On your server, try this command:</p>
	       <blockquote>chmod a+w <?php echo INSTALLDIR; ?>/avatar/</blockquote>
         <?
	     return false;
	}

	return true;
}

function checkExtension($name)
{
    if (!extension_loaded($name)) {
        if (!dl($name.'.so')) {
            return false;
        }
    }
    return true;
}

function showForm()
{
?>
<p>Enter your database connection information below to initialize the database.</p>
<form method='post' action='install.php'>
	<fieldset>
	<ul class='form_data'>
	<li>
	<label for='sitename'>Site name</label>
	<input type='text' id='sitename' name='sitename' />
	<p>The name of your site</p>
	</li>
	<li>
	<li>
	<label for='host'>Hostname</label>
	<input type='text' id='host' name='host' />
	<p>Database hostname</p>
	</li>
	<li>
	<label for='host'>Database</label>
	<input type='text' id='database' name='database' />
	<p>Database name</p>
	</li>
	<li>
	<label for='username'>Username</label>
	<input type='text' id='username' name='username' />
	<p>Database username</p>
	</li>
	<li>
	<label for='password'>Password</label>
	<input type='password' id='password' name='password' />
	<p>Database password</p>
	</li>
	</ul>
	<input type='submit' name='submit' value='Submit'>
	</fieldset>
</form>
<?
}

function updateStatus($status, $error=false)
{
?>
	<li>
<?
    print $status;
?>
	</li>
<?
}

function handlePost()
{
?>
	<ul>
<?
    $host = $_POST['host'];
    $database = $_POST['database'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $sitename = $_POST['sitename'];

    if (empty($host)) {
        updateStatus("No hostname specified.", true);
        showForm();
        return;
    }

    if (empty($database)) {
        updateStatus("No database specified.", true);
        showForm();
        return;
    }

    if (empty($username)) {
        updateStatus("No username specified.", true);
        showForm();
        return;
    }

    if (empty($password)) {
        updateStatus("No password specified.", true);
        showForm();
        return;
    }

    if (empty($sitename)) {
        updateStatus("No sitename specified.", true);
        showForm();
        return;
    }

    updateStatus("Starting installation...");
    updateStatus("Checking database...");
    $conn = mysql_connect($host, $username, $password);
    if (!$conn) {
        updateStatus("Can't connect to server '$host' as '$username'.", true);
        showForm();
        return;
    }
    updateStatus("Changing to database...");
    $res = mysql_select_db($database, $conn);
    if (!$res) {
        updateStatus("Can't change to database.", true);
        showForm();
        return;
    }
    updateStatus("Running database script...");
    $res = runDbScript(INSTALLDIR.'/db/laconica.sql', $conn);
    if ($res === false) {
        updateStatus("Can't run database script.", true);
        showForm();
        return;
    }
    foreach (array('sms_carrier' => 'SMS carrier',
                   'notice_source' => 'notice source',
                   'foreign_services' => 'foreign service')
             as $scr => $name) {
        updateStatus(sprintf("Adding %s data to database...", $name));
        $res = runDbScript(INSTALLDIR.'/db/'.$scr.'.sql', $conn);
        if ($res === false) {
            updateStatus(sprintf("Can't run %d script.", $name), true);
            showForm();
            return;
        }
    }
    updateStatus("Writing config file...");
    $sqlUrl = "mysqli://$username:$password@$host/$database";
    $res = writeConf($sitename, $sqlUrl);
    if (!$res) {
        updateStatus("Can't write config file.", true);
        showForm();
        return;
    }
    updateStatus("Done!");
?>
	</ul>
<?
}

function writeConf($sitename, $sqlUrl)
{
    $res = file_put_contents(INSTALLDIR.'/config.php',
                             "<?php\n".
                             "\$config['site']['name'] = \"$sitename\";\n\n".
                             "\$config['db']['database'] = \"$sqlUrl\";\n\n");
    return $res;
}

function runDbScript($filename, $conn)
{
    $sql = trim(file_get_contents($filename));
    $stmts = explode(';', $sql);
    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if (!mb_strlen($stmt)) {
            continue;
        }
        $res = mysql_query($stmt, $conn);
        if ($res === false) {
            return $res;
        }
    }
    return true;
}

?>
<html>
<head>
	<title>Install Laconica</title>
	<link rel="stylesheet" type="text/css" href="theme/base/css/display.css?version=0.7.1" media="screen, projection, tv"/>
	<link rel="stylesheet" type="text/css" href="theme/base/css/modal.css?version=0.7.1" media="screen, projection, tv"/>
	<link rel="stylesheet" type="text/css" href="theme/default/css/display.css?version=0.7.1" media="screen, projection, tv"/>
</head>
<body>
	<div id="wrap">
	<div id="core">
	<div id="content">
	<h1>Install Laconica</h1>
<?php main(); ?>
	</div>
	</div>
	</div>
</body>
</html>
