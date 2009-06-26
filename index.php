<?php
/**
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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
define('LACONICA', true);

require_once INSTALLDIR . '/lib/common.php';

$user = null;
$action = null;

function getPath($req)
{
    if ((common_config('site', 'fancy') || !array_key_exists('PATH_INFO', $_SERVER))
        && array_key_exists('p', $req)) {
        return $req['p'];
    } else if (array_key_exists('PATH_INFO', $_SERVER)) {
        return $_SERVER['PATH_INFO'];
    } else {
        return null;
    }
}

function handleError($error)
{
    if ($error->getCode() == DB_DATAOBJECT_ERROR_NODATA) {
        return;
    }

    $logmsg = "PEAR error: " . $error->getMessage();
    if(common_config('site', 'logdebug')) {
        $logmsg .= " : ". $error->getDebugInfo();
    }
    common_log(LOG_ERR, $logmsg);
    if(common_config('site', 'logdebug')) {
        $bt = $error->getBacktrace();
        foreach ($bt as $line) {
            common_log(LOG_ERR, $line);
        }
    }
    if ($error instanceof DB_DataObject_Error ||
        $error instanceof DB_Error) {
        $msg = sprintf(_('The database for %s isn\'t responding correctly, '.
                         'so the site won\'t work properly. '.
                         'The site admins probably know about the problem, '.
                         'but you can contact them at %s to make sure. '.
                         'Otherwise, wait a few minutes and try again.'),
                       common_config('site', 'name'),
                       common_config('site', 'email'));
    } else {
        $msg = _('An important error occured, probably related to email setup. '.
                 'Check logfiles for more info..');
    }

    $dac = new DBErrorAction($msg, 500);
    $dac->showPage();
    exit(-1);
}

function main()
{
    // quick check for fancy URL auto-detection support in installer.
    if (isset($_SERVER['REDIRECT_URL']) && ((dirname($_SERVER['REQUEST_URI']) . '/check-fancy') === $_SERVER['REDIRECT_URL'])) {
        die("Fancy URL support detection succeeded. We suggest you enable this to get fancy (pretty) URLs.");
    }
    global $user, $action, $config;

    Snapshot::check();

    if (!_have_config()) {
        $msg = sprintf(_("No configuration file found. Try running ".
                         "the installation program first."));
        $sac = new ServerErrorAction($msg);
        $sac->showPage();
        return;
    }

    // For database errors

    PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'handleError');

    // XXX: we need a little more structure in this script

    // get and cache current user

    $user = common_current_user();

    // initialize language env

    common_init_language();

    $path = getPath($_REQUEST);

    $r = Router::get();

    $args = $r->map($path);

    if (!$args) {
        $cac = new ClientErrorAction(_('Unknown page'), 404);
        $cac->showPage();
        return;
    }

    $args = array_merge($args, $_REQUEST);

    Event::handle('ArgsInitialize', array(&$args));

    $action = $args['action'];

    if (!$action || !preg_match('/^[a-zA-Z0-9_-]*$/', $action)) {
        common_redirect(common_local_url('public'));
        return;
    }

    // If the site is private, and they're not on one of the "public"
    // parts of the site, redirect to login

    if (!$user && common_config('site', 'private') &&
        !in_array($action, array('login', 'openidlogin', 'finishopenidlogin',
                                 'recoverpassword', 'api', 'doc', 'register'))) {
        common_redirect(common_local_url('login'));
        return;
    }

    $action_class = ucfirst($action).'Action';

    if (!class_exists($action_class)) {
        $cac = new ClientErrorAction(_('Unknown action'), 404);
        $cac->showPage();
    } else {
        $action_obj = new $action_class();

        // XXX: find somewhere for this little block to live

        if (common_config('db', 'mirror') && $action_obj->isReadOnly($args)) {
            if (is_array(common_config('db', 'mirror'))) {
                // "load balancing", ha ha
                $arr = common_config('db', 'mirror');
                $k = array_rand($arr);
                $mirror = $arr[$k];
            } else {
                $mirror = common_config('db', 'mirror');
            }
            $config['db']['database'] = $mirror;
        }

        try {
            if ($action_obj->prepare($args)) {
                $action_obj->handle($args);
            }
        } catch (ClientException $cex) {
            $cac = new ClientErrorAction($cex->getMessage(), $cex->getCode());
            $cac->showPage();
        } catch (ServerException $sex) { // snort snort guffaw
            $sac = new ServerErrorAction($sex->getMessage(), $sex->getCode());
            $sac->showPage();
        } catch (Exception $ex) {
            $sac = new ServerErrorAction($ex->getMessage());
            $sac->showPage();
        }
    }
}

main();

// XXX: cleanup exit() calls or add an exit handler so
// this always gets called

Event::handle('CleanupPlugin');
