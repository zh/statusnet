
<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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
 *
 * @category Installation
 * @package  Installation
 *
 * @author   Adrian Lang <mail@adrianlang.de>
 * @author   Brenda Wallace <shiny@cpan.org>
 * @author   Brett Taylor <brett@webfroot.co.nz>
 * @author   Brion Vibber <brion@pobox.com>
 * @author   CiaranG <ciaran@ciarang.com>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Eric Helgeson <helfire@Erics-MBP.local>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@status.net>
 * @author   Tom Adams <tom@holizz.com>
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 * @version  0.9.x
 * @link     http://status.net
 */

define('INSTALLDIR', dirname(__FILE__));

$external_libraries=array(
    array(
        'name'=>'gettext',
        'url'=>'http://us.php.net/manual/en/book.gettext.php',
        'check_function'=>'gettext'
    ),
    array(
        'name'=>'PEAR',
        'url'=>'http://pear.php.net/',
        'deb'=>'php-pear',
        'include'=>'PEAR.php',
        'check_class'=>'PEAR'
    ),
    array(
        'name'=>'DB',
        'pear'=>'DB',
        'url'=>'http://pear.php.net/package/DB',
        'deb'=>'php-db',
        'include'=>'DB/common.php',
        'check_class'=>'DB_common'
    ),
    array(
        'name'=>'DB_DataObject',
        'pear'=>'DB_DataObject',
        'url'=>'http://pear.php.net/package/DB_DataObject',
        'include'=>'DB/DataObject.php',
        'check_class'=>'DB_DataObject'
    ),
    array(
        'name'=>'Console_Getopt',
        'pear'=>'Console_Getopt',
        'url'=>'http://pear.php.net/package/Console_Getopt',
        'include'=>'Console/Getopt.php',
        'check_class'=>'Console_Getopt'
    ),
    array(
        'name'=>'Facebook API',
        'url'=>'http://developers.facebook.com/',
        'include'=>'facebook/facebook.php',
        'check_class'=>'Facebook'
    ),
    array(
        'name'=>'htmLawed',
        'url'=>'http://www.bioinformatics.org/phplabware/internal_utilities/htmLawed',
        'include'=>'htmLawed/htmLawed.php',
        'check_function'=>'htmLawed'
    ),
    array(
        'name'=>'HTTP_Request',
        'pear'=>'HTTP_Request',
        'url'=>'http://pear.php.net/package/HTTP_Request',
        'deb'=>'php-http-request',
        'include'=>'HTTP/Request.php',
        'check_class'=>'HTTP_Request'
    ),
    array(
        'name'=>'HTTP_Request2',
        'pear'=>'HTTP_Request2',
        'url'=>'http://pear.php.net/package/HTTP_Request2',
        'include'=>'HTTP/Request2.php',
        'check_class'=>'HTTP_Request2'
    ),
    array(
        'name'=>'Mail',
        'pear'=>'Mail',
        'url'=>'http://pear.php.net/package/Mail',
        'deb'=>'php-mail',
        'include'=>'Mail.php',
        'check_class'=>'Mail'
    ),
    array(
        'name'=>'Mail_mimeDecode',
        'pear'=>'Mail_mimeDecode',
        'url'=>'http://pear.php.net/package/Mail_mimeDecode',
        'deb'=>'php-mail-mimedecode',
        'include'=>'Mail/mimeDecode.php',
        'check_class'=>'Mail_mimeDecode'
    ),
    array(
        'name'=>'Mime_Type',
        'pear'=>'Mime_Type',
        'url'=>'http://pear.php.net/package/Mime_Type',
        'include'=>'MIME/Type.php',
        'check_class'=>'Mime_Type'
    ),
    array(
        'name'=>'Net_URL_Mapper',
        'pear'=>'Net_URL_Mapper',
        'url'=>'http://pear.php.net/package/Net_URL_Mapper',
        'include'=>'Net/URL/Mapper.php',
        'check_class'=>'Net_URL_Mapper'
    ),
    array(
        'name'=>'Net_LDAP2',
        'pear'=>'Net_LDAP2',
        'url'=>'http://pear.php.net/package/Net_LDAP2',
        'deb'=>'php-net-ldap2',
        'include'=>'Net/LDAP2.php',
        'check_class'=>'Net_LDAP2'
    ),
    array(
        'name'=>'Net_Socket',
        'pear'=>'Net_Socket',
        'url'=>'http://pear.php.net/package/Net_Socket',
        'deb'=>'php-net-socket',
        'include'=>'Net/Socket.php',
        'check_class'=>'Net_Socket'
    ),
    array(
        'name'=>'Net_SMTP',
        'pear'=>'Net_SMTP',
        'url'=>'http://pear.php.net/package/Net_SMTP',
        'deb'=>'php-net-smtp',
        'include'=>'Net/SMTP.php',
        'check_class'=>'Net_SMTP'
    ),
    array(
        'name'=>'Net_URL',
        'pear'=>'Net_URL',
        'url'=>'http://pear.php.net/package/Net_URL',
        'deb'=>'php-net-url',
        'include'=>'Net/URL.php',
        'check_class'=>'Net_URL'
    ),
    array(
        'name'=>'Net_URL2',
        'pear'=>'Net_URL2',
        'url'=>'http://pear.php.net/package/Net_URL2',
        'include'=>'Net/URL2.php',
        'check_class'=>'Net_URL2'
    ),
    array(
        'name'=>'Services_oEmbed',
        'pear'=>'Services_oEmbed',
        'url'=>'http://pear.php.net/package/Services_oEmbed',
        'include'=>'Services/oEmbed.php',
        'check_class'=>'Services_oEmbed'
    ),
    array(
        'name'=>'Stomp',
        'url'=>'http://stomp.codehaus.org/PHP',
        'include'=>'Stomp.php',
        'check_class'=>'Stomp'
    ),
    array(
        'name'=>'System_Command',
        'pear'=>'System_Command',
        'url'=>'http://pear.php.net/package/System_Command',
        'include'=>'System/Command.php',
        'check_class'=>'System_Command'
    ),
    array(
        'name'=>'XMPPHP',
        'url'=>'http://code.google.com/p/xmpphp',
        'include'=>'XMPPHP/XMPP.php',
        'check_class'=>'XMPPHP_XMPP'
    ),
    array(
        'name'=>'PHP Markdown',
        'url'=>'http://www.michelf.com/projects/php-markdown/',
        'include'=>'markdown.php',
        'check_class'=>'Markdown_Parser'
    ),
    array(
        'name'=>'OAuth',
        'url'=>'http://code.google.com/p/oauth-php',
        'include'=>'OAuth.php',
        'check_class'=>'OAuthRequest'
    ),
    array(
        'name'=>'Validate',
        'pear'=>'Validate',
        'url'=>'http://pear.php.net/package/Validate',
        'include'=>'Validate.php',
        'check_class'=>'Validate'
    )
);
$dbModules = array(
    'mysql' => array(
        'name' => 'MySQL',
        'check_module' => 'mysql', // mysqli?
        'installer' => 'mysql_db_installer',
    ),
    'pgsql' => array(
        'name' => 'PostgreSQL',
        'check_module' => 'pgsql',
        'installer' => 'pgsql_db_installer',
    ),
);

/**
 * the actual installation.
 * If call libraries are present, then install
 *
 * @return void
 */
function main()
{
    if (!checkPrereqs()) {
        return;
    }

    if (!empty($_GET['checklibs'])) {
        showLibs();
    } else {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            handlePost();
        } else {
            showForm();
        }
    }
}

/**
 * checks if an external libary is present
 *
 * @param string $external_library Name of library
 *
 * @return boolean indicates if library present
 */
function haveExternalLibrary($external_library)
{
    if (isset($external_library['include']) && !haveIncludeFile($external_library['include'])) {
        return false;
    }
    if (isset($external_library['check_function']) && ! function_exists($external_library['check_function'])) {
        return false;
    }
    if (isset($external_library['check_class']) && ! class_exists($external_library['check_class'])) {
        return false;
    }
    return true;
}

// Attempt to include a PHP file and report if it worked, while
// suppressing the annoying warning messages on failure.
function haveIncludeFile($filename) {
    $old = error_reporting(error_reporting() & ~E_WARNING);
    $ok = include_once($filename);
    error_reporting($old);
    return $ok;
}

/**
 * Check if all is ready for installation
 *
 * @return void
 */
function checkPrereqs()
{
    $pass = true;

    if (file_exists(INSTALLDIR.'/config.php')) {
         printf('<p class="error">Config file &quot;config.php&quot; already exists.</p>');
        $pass = false;
    }

    if (version_compare(PHP_VERSION, '5.2.3', '<')) {
        printf('<p class="error">Require PHP version 5.2.3 or greater.</p>');
        $pass = false;
    }

    $reqs = array('gd', 'curl',
                  'xmlwriter', 'mbstring','tidy');

    foreach ($reqs as $req) {
        if (!checkExtension($req)) {
            printf('<p class="error">Cannot load required extension: <code>%s</code></p>', $req);
            $pass = false;
        }
    }
    // Make sure we have at least one database module available
    global $dbModules;
    $missingExtensions = array();
    foreach ($dbModules as $type => $info) {
        if (!checkExtension($info['check_module'])) {
            $missingExtensions[] = $info['check_module'];
        }
    }

    if (count($missingExtensions) == count($dbModules)) {
        $req = implode(', ', $missingExtensions);
        printf('<p class="error">Cannot find mysql or pgsql extension. You need one or the other.');
        $pass = false;
    }

    if (!is_writable(INSTALLDIR)) {
        printf('<p class="error">Cannot write config file to: <code>%s</code></p>', INSTALLDIR);
        printf('<p>On your server, try this command: <code>chmod a+w %s</code>', INSTALLDIR);
        $pass = false;
    }

    // Check the subdirs used for file uploads
    $fileSubdirs = array('avatar', 'background', 'file');
    foreach ($fileSubdirs as $fileSubdir) {
        $fileFullPath = INSTALLDIR."/$fileSubdir/";
        if (!is_writable($fileFullPath)) {
            printf('<p class="error">Cannot write to %s directory: <code>%s</code></p>', $fileSubdir, $fileFullPath);
            printf('<p>On your server, try this command: <code>chmod a+w %s</code></p>', $fileFullPath);
            $pass = false;
        }
    }

    return $pass;
}

/**
 * Checks if a php extension is both installed and loaded
 *
 * @param string $name of extension to check
 *
 * @return boolean whether extension is installed and loaded
 */
function checkExtension($name)
{
    if (extension_loaded($name)) {
        return true;
    } elseif (function_exists('dl') && ini_get('enable_dl') && !ini_get('safe_mode')) {
        // dl will throw a fatal error if it's disabled or we're in safe mode.
        // More fun, it may not even exist under some SAPIs in 5.3.0 or later...
        $soname = $name . '.' . PHP_SHLIB_SUFFIX;
        if (PHP_SHLIB_SUFFIX == 'dll') {
            $soname = "php_" . $soname;
        }
        return @dl($soname);
    } else {
        return false;
    }
}

/**
 * Show list of libraries
 *
 * @return void
 */
function showLibs()
{
    global $external_libraries;
    $present_libraries=array();
    $absent_libraries=array();
    foreach ($external_libraries as $external_library) {
        if (haveExternalLibrary($external_library)) {
            $present_libraries[]=$external_library;
        } else {
            $absent_libraries[]=$external_library;
        }
    }
    echo<<<E_O_T
    <div class="instructions">
        <p>StatusNet comes bundled with a number of libraries required for the application to work. However, it is best that you use PEAR or you distribution to manage
        libraries instead, as they tend to provide security updates faster, and may offer improved performance.</p>
        <p>On Debian based distributions, such as Ubuntu, use a package manager (such as &quot;aptitude&quot;, &quot;apt-get&quot;, and &quot;synaptic&quot;) to install the package listed.</p>
        <p>On RPM based distributions, such as Red Hat, Fedora, CentOS, Scientific Linux, Yellow Dog Linux and Oracle Enterprise Linux, use a package manager (such as &quot;yum&quot;, &quot;apt-rpm&quot;, and &quot;up2date&quot;) to install the package listed.</p>
        <p>On servers without a package manager (such as Windows), or if the library is not packaged for your distribution, you can use PHP PEAR to install the library. Simply run &quot;pear install &lt;name&gt;&quot;.</p>
    </div>
    <h2>Absent Libraries</h2>
    <ul id="absent_libraries">
E_O_T;
    foreach ($absent_libraries as $library) {
        echo '<li>';
        if (isset($library['url'])) {
            echo '<a href="'.$library['url'].'">'.htmlentities($library['name']).'</a>';
        } else {
            echo htmlentities($library['name']);
        }
        echo '<ul>';
        if (isset($library['deb'])) {
            echo '<li class="deb package">deb: <a href="apt:' . urlencode($library['deb']) . '">' . htmlentities($library['deb']) . '</a></li>';
        }
        if (isset($library['rpm'])) {
            echo '<li class="rpm package">rpm: ' . htmlentities($library['rpm']) . '</li>';
        }
        if (isset($library['pear'])) {
            echo '<li class="pear package">pear: ' . htmlentities($library['pear']) . '</li>';
        }
        echo '</ul>';
    }
    echo<<<E_O_T
    </ul>
    <h2>Installed Libraries</h2>
    <ul id="present_libraries">
E_O_T;
    foreach ($present_libraries as $library) {
        echo '<li>';
        if (isset($library['url'])) {
            echo '<a href="'.$library['url'].'">'.htmlentities($library['name']).'</a>';
        } else {
            echo htmlentities($library['name']);
        }
        echo '</li>';
    }
    echo<<<E_O_T
    </ul>
E_O_T;
}

function showForm()
{
    global $dbModules;
    $dbRadios = '';
    $checked = 'checked="checked" '; // Check the first one which exists
    foreach ($dbModules as $type => $info) {
        if (checkExtension($info['check_module'])) {
            $dbRadios .= "<input type=\"radio\" name=\"dbtype\" id=\"dbtype-$type\" value=\"$type\" $checked/> $info[name]<br />\n";
            $checked = '';
        }
    }
    echo<<<E_O_T
        </ul>
    </dd>
</dl>
<dl id="page_notice" class="system_notice">
    <dt>Page notice</dt>
    <dd>
        <div class="instructions">
            <p>Enter your database connection information below to initialize the database.</p>
            <p>StatusNet bundles a number of libraries for ease of installation. <a href="?checklibs=true">You can see what bundled libraries you are using, versus what libraries are installed on your server.</a>
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

                <label for="dbtype">Type</label>
                $dbRadios
                <p class="form_guide">Database type</p>
            </li>

            <li>
                <label for="database">Name</label>
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
                <p class="form_guide">Database password (optional)</p>
            </li>
        </ul>
        <input type="submit" name="submit" class="submit" value="Submit" />
    </fieldset>
</form>

E_O_T;
}

function updateStatus($status, $error=false)
{
    echo '<li' . ($error ? ' class="error"': '' ) . ">$status</li>";
}

function handlePost()
{
    $host     = $_POST['host'];
    $dbtype   = $_POST['dbtype'];
    $database = $_POST['database'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $sitename = $_POST['sitename'];
    $fancy    = !empty($_POST['fancy']);
    $server = $_SERVER['HTTP_HOST'];
    $path = substr(dirname($_SERVER['PHP_SELF']), 1);

    echo <<<STR
    <dl class="system_notice">
        <dt>Page notice</dt>
        <dd>
            <ul>
STR;
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

    if (empty($sitename)) {
        updateStatus("No sitename specified.", true);
        $fail = true;
    }

    if ($fail) {
        showForm();
        return;
    }

    global $dbModules;
    $db = call_user_func($dbModules[$dbtype]['installer'], $host, $database, $username, $password);

    if (!$db) {
        // database connection failed, do not move on to create config file.
        return false;
    }

    updateStatus("Writing config file...");
    $res = writeConf($sitename, $server, $path, $fancy, $db);

    if (!$res) {
        updateStatus("Cannot write config file.", true);
        showForm();
        return;
    }

    /*
        TODO https needs to be considered
    */
    $link = "http://".$server.'/'.$path;

    updateStatus("StatusNet has been installed at $link");
    updateStatus("You can visit your <a href='$link'>new StatusNet site</a>.");
}

function Pgsql_Db_installer($host, $database, $username, $password)
{
    $connstring = "dbname=$database host=$host user=$username";

    //No password would mean trust authentication used.
    if (!empty($password)) {
        $connstring .= " password=$password";
    }
    updateStatus("Starting installation...");
    updateStatus("Checking database...");
    $conn = pg_connect($connstring);

    if ($conn ===false) {
        updateStatus("Failed to connect to database: $connstring");
        showForm();
        return false;
    }

    //ensure database encoding is UTF8
    $record = pg_fetch_object(pg_query($conn, 'SHOW server_encoding'));
    if ($record->server_encoding != 'UTF8') {
        updateStatus("StatusNet requires UTF8 character encoding. Your database is ". htmlentities($record->server_encoding));
        showForm();
        return false;
    }

    updateStatus("Running database script...");
    //wrap in transaction;
    pg_query($conn, 'BEGIN');
    $res = runDbScript(INSTALLDIR.'/db/statusnet_pg.sql', $conn, 'pgsql');

    if ($res === false) {
        updateStatus("Cannot run database script.", true);
        showForm();
        return false;
    }
    foreach (array('sms_carrier' => 'SMS carrier',
                'notice_source' => 'notice source',
                'foreign_services' => 'foreign service')
          as $scr => $name) {
        updateStatus(sprintf("Adding %s data to database...", $name));
        $res = runDbScript(INSTALLDIR.'/db/'.$scr.'.sql', $conn, 'pgsql');
        if ($res === false) {
            updateStatus(sprintf("Cannot run %d script.", $name), true);
            showForm();
            return false;
        }
    }
    pg_query($conn, 'COMMIT');

    if (empty($password)) {
        $sqlUrl = "pgsql://$username@$host/$database";
    } else {
        $sqlUrl = "pgsql://$username:$password@$host/$database";
    }

    $db = array('type' => 'pgsql', 'database' => $sqlUrl);

    return $db;
}

function Mysql_Db_installer($host, $database, $username, $password)
{
    updateStatus("Starting installation...");
    updateStatus("Checking database...");

    $conn = mysql_connect($host, $username, $password);
    if (!$conn) {
        updateStatus("Cannot connect to server '$host' as '$username'.", true);
        showForm();
        return false;
    }
    updateStatus("Changing to database...");
    $res = mysql_select_db($database, $conn);
    if (!$res) {
        updateStatus("Cannot change to database.", true);
        showForm();
        return false;
    }
    updateStatus("Running database script...");
    $res = runDbScript(INSTALLDIR.'/db/statusnet.sql', $conn);
    if ($res === false) {
        updateStatus("Cannot run database script.", true);
        showForm();
        return false;
    }
    foreach (array('sms_carrier' => 'SMS carrier',
                'notice_source' => 'notice source',
                'foreign_services' => 'foreign service')
          as $scr => $name) {
        updateStatus(sprintf("Adding %s data to database...", $name));
        $res = runDbScript(INSTALLDIR.'/db/'.$scr.'.sql', $conn);
        if ($res === false) {
            updateStatus(sprintf("Cannot run %d script.", $name), true);
            showForm();
            return false;
        }
    }

    $sqlUrl = "mysqli://$username:$password@$host/$database";
    $db = array('type' => 'mysql', 'database' => $sqlUrl);
    return $db;
}

function writeConf($sitename, $server, $path, $fancy, $db)
{
    // assemble configuration file in a string
    $cfg =  "<?php\n".
            "if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }\n\n".

            // site name
            "\$config['site']['name'] = '$sitename';\n\n".

            // site location
            "\$config['site']['server'] = '$server';\n".
            "\$config['site']['path'] = '$path'; \n\n".

            // checks if fancy URLs are enabled
            ($fancy ? "\$config['site']['fancy'] = true;\n\n":'').

            // database
            "\$config['db']['database'] = '{$db['database']}';\n\n".
            ($db['type'] == 'pgsql' ? "\$config['db']['quote_identifiers'] = true;\n\n":'').
            "\$config['db']['type'] = '{$db['type']}';\n\n";
    // write configuration file out to install directory
    $res = file_put_contents(INSTALLDIR.'/config.php', $cfg);

    return $res;
}

/**
 * Install schema into the database
 *
 * @param string $filename location of database schema file
 * @param dbconn $conn     connection to database
 * @param string $type     type of database, currently mysql or pgsql
 *
 * @return boolean - indicating success or failure
 */
function runDbScript($filename, $conn, $type = 'mysqli')
{
    $sql = trim(file_get_contents($filename));
    $stmts = explode(';', $sql);
    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if (!mb_strlen($stmt)) {
            continue;
        }
        // FIXME: use PEAR::DB or PDO instead of our own switch
        switch ($type) {
        case 'mysqli':
            $res = mysql_query($stmt, $conn);
            if ($res === false) {
                $error = mysql_error();
            }
            break;
        case 'pgsql':
            $res = pg_query($conn, $stmt);
            if ($res === false) {
                $error = pg_last_error();
            }
            break;
        default:
            updateStatus("runDbScript() error: unknown database type ". $type ." provided.");
        }
        if ($res === false) {
            updateStatus("ERROR ($error) for SQL '$stmt'");
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
        <title>Install StatusNet</title>
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
                        <img class="logo photo" src="theme/default/logo.png" alt="StatusNet"/>
                        <span class="fn org">StatusNet</span>
                    </a>
                </address>
            </div>
            <div id="core">
                <div id="content">
                    <h1>Install StatusNet</h1>
<?php main(); ?>
                </div>
            </div>
        </div>
    </body>
</html>
