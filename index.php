<?php
/**
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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
    if (common_config('site', 'fancy')) {
        return $req['p'];
    } else if ($_SERVER['PATH_INFO']) {
        return $_SERVER['PATH_INFO'];
    } else {
        return $req['p'];
    }
}

function handleError($error)
{
    common_log(LOG_ERR, "PEAR error: " . $error->getMessage());
    $msg = sprintf(_('The database for %s isn\'t responding correctly, '.
                     'so the site won\'t work properly. '.
                     'The site admins probably know about the problem, '.
                     'but you can contact them at %s to make sure. '.
                     'Otherwise, wait a few minutes and try again.'),
                   common_config('site', 'name'),
                   common_config('site', 'email'));

    $dac = new DBErrorAction($msg, 500);
    $dac->showPage();
    exit(-1);
}

function main()
{
    global $user, $action;

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

        if ($config['db']['mirror'] && $action_obj->isReadOnly()) {
            if (is_array($config['db']['mirror'])) {
                // "load balancing", ha ha
                $k = array_rand($config['db']['mirror']);

                $mirror = $config['db']['mirror'][$k];
            } else {
                $mirror = $config['db']['mirror'];
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
