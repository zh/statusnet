<?php
/**
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2009, Control Yourself, Inc.
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
	$pass = true;

    if (file_exists(INSTALLDIR.'/config.php')) {
         ?><p class="error">Config file &quot;config.php&quot; already exists.</p>
         <?php
        $pass = false;
    }

    if (version_compare(PHP_VERSION, '5.0.0', '<')) {
            ?><p class="error">Require PHP version 5 or greater.</p><?php
		    $pass = false;
    }

    $reqs = array('gd', 'mysql', 'curl',
                  'xmlwriter', 'mbstring',
                  'gettext');

    foreach ($reqs as $req) {
        if (!checkExtension($req)) {
            ?><p class="error">Cannot load required extension: <code><?php echo $req; ?></code></p><?php
		    $pass = false;
        }
    }

	if (!is_writable(INSTALLDIR)) {
         ?><p class="error">Cannot write config file to: <code><?php echo INSTALLDIR; ?></code></p>
	       <p>On your server, try this command: <code>chmod a+w <?php echo INSTALLDIR; ?></code>
         <?php
	     $pass = false;
	}

	if (!is_writable(INSTALLDIR.'/avatar/')) {
         ?><p class="error">Cannot write avatar directory: <code><?php echo INSTALLDIR; ?>/avatar/</code></p>
	       <p>On your server, try this command: <code>chmod a+w <?php echo INSTALLDIR; ?>/avatar/</code></p>
         <?
	     $pass = false;
	}

	return $pass;
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
    echo<<<E_O_T
        </ul>
    </dd>
</dl>
<dl id="page_notice" class="system_notice">
    <dt>Page notice</dt>
    <dd>
        <div class="instructions">
            <p>Enter your database connection information below to initialize the database.</p>
        </div>
    </dd>
</dl>
<form method="post" action="install.php" class="form_settings" id="form_install">
    <fieldset>
        <legend>Connection settings</legend>
        <ul class="form_data">
            <li>
                <label for="sitename">Site name</label>
                <input type="text" id="sitename" name="sitename" />
                <p class="form_guide">The name of your site</p>
            </li>
            <li>
                <label for="fancy-enable">Fancy URLs</label>
                <input type="radio" name="fancy" id="fancy-enable" value="enable" checked='checked' /> enable<br />
                <input type="radio" name="fancy" id="fancy-disable" value="" /> disable<br />
                <p class="form_guide" id='fancy-form_guide'>Enable fancy (pretty) URLs. Auto-detection failed, it depends on Javascript.</p>
            </li>
            <li>
                <label for="host">Hostname</label>
                <input type="text" id="host" name="host" />
                <p class="form_guide">Database hostname</p>
            </li>
            <li>
                <label for="host">Database</label>
                <input type="text" id="database" name="database" />
                <p class="form_guide">Database name</p>
            </li>
            <li>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" />
                <p class="form_guide">Database username</p>
            </li>
            <li>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" />
                <p class="form_guide">Database password</p>
            </li>
        </ul>
        <input type="submit" name="submit" class="submit" value="Submit" />
    </fieldset>
</form>

E_O_T;
}

function updateStatus($status, $error=false)
{
?>
                <li <?php echo ($error) ? 'class="error"': ''; ?>><?print $status;?></li>

<?php
}

function handlePost()
{
?>

<?php
    $host     = $_POST['host'];
    $database = $_POST['database'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $sitename = $_POST['sitename'];
    $fancy    = !empty($_POST['fancy']);
?>
    <dl class="system_notice">
        <dt>Page notice</dt>
        <dd>
            <ul>
<?php
	$fail = false;

    if (empty($host)) {
        updateStatus("No hostname specified.", true);
		$fail = true;
    }

    if (empty($database)) {
        updateStatus("No database specified.", true);
		$fail = true;
    }

    if (empty($username)) {
        updateStatus("No username specified.", true);
		$fail = true;
    }

    if (empty($password)) {
        updateStatus("No password specified.", true);
		$fail = true;
    }

    if (empty($sitename)) {
        updateStatus("No sitename specified.", true);
		$fail = true;
    }

	if($fail){
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
    $res = writeConf($sitename, $sqlUrl, $fancy);
    if (!$res) {
        updateStatus("Can't write config file.", true);
        showForm();
        return;
    }
    updateStatus("Done!");
    if ($path) $path .= '/';
    updateStatus("You can visit your <a href='/$path'>new Laconica site</a>.");
?>

<?php
}

function writeConf($sitename, $sqlUrl, $fancy)
{
    $res = file_put_contents(INSTALLDIR.'/config.php',
                             "<?php\n".
                             "if (!defined('LACONICA')) { exit(1); }\n\n".
                             "\$config['site']['name'] = \"$sitename\";\n\n".
                             ($fancy ? "\$config['site']['fancy'] = true;\n\n":'').
                             "\$config['db']['database'] = \"$sqlUrl\";\n\n".
                             "?>");
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
<?php echo"<?"; ?> xml version="1.0" encoding="UTF-8" <?php echo "?>"; ?>
<!DOCTYPE html
PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
       "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en_US" lang="en_US">
    <head>
        <title>Install Laconica</title>
	<link rel="shortcut icon" href="favicon.ico"/>
        <link rel="stylesheet" type="text/css" href="theme/default/css/display.css?version=0.8" media="screen, projection, tv"/>
        <!--[if IE]><link rel="stylesheet" type="text/css" href="theme/base/css/ie.css?version=0.8" /><![endif]-->
        <!--[if lte IE 6]><link rel="stylesheet" type="text/css" theme/base/css/ie6.css?version=0.8" /><![endif]-->
        <!--[if IE]><link rel="stylesheet" type="text/css" href="theme/default/css/ie.css?version=0.8" /><![endif]-->
        <script src="js/jquery.min.js"></script>
        <script src="js/install.js"></script>
    </head>
    <body id="install">
        <div id="wrap">
            <div id="header">
                <address id="site_contact" class="vcard">
                    <a class="url home bookmark" href=".">
                        <img class="logo photo" src="theme/default/logo.png" alt="Laconica"/>
                        <span class="fn org">Laconica</span>
                    </a>
                </address>
            </div>
            <div id="core">
                <div id="content">
                    <h1>Install Laconica</h1>
<?php main(); ?>
                </div>
            </div>
        </div>
    </body>
</html>
