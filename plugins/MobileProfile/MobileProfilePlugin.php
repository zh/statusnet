<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * XHTML Mobile Profile plugin that uses WAP 2.0 Plugin
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

define('PAGE_TYPE_PREFS',
       'application/vnd.wap.xhtml+xml, application/xhtml+xml, text/html;q=0.9');

require_once INSTALLDIR.'/plugins/Mobile/WAP20Plugin.php';


/**
 * Superclass for plugin to output XHTML Mobile Profile
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class MobileProfilePlugin extends WAP20Plugin
{
    public $DTD             = null;
    public $serveMobile     = false;

    function __construct($DTD='http://www.wapforum.org/DTD/xhtml-mobile10.dtd')
    {
        $this->DTD       = $DTD;

        parent::__construct();
    }


    function onStartShowHTML($action)
    {
        if (!$type) {
            $httpaccept = isset($_SERVER['HTTP_ACCEPT']) ?
              $_SERVER['HTTP_ACCEPT'] : null;

            $cp = common_accept_to_prefs($httpaccept);
            $sp = common_accept_to_prefs(PAGE_TYPE_PREFS);

            $type = common_negotiate_type($cp, $sp);

            if (!$type) {
                throw new ClientException(_('This page is not available in a '.
                                            'media type you accept'), 406);
            }
        }

        // XXX: This should probably graduate to WAP20Plugin

        // If they are on the mobile site, serve them MP
        if ((common_config('site', 'mobileserver').'/'.
             common_config('site', 'path').'/' == 
            $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'])) {

            $this->serveMobile = true;
        }
        else {
            // If they like the WAP 2.0 mimetype, serve them MP
            if (strstr('application/vnd.wap.xhtml+xml', $type) !== false) {
                $this->serveMobile = true;
            }
            else {
                // If they are a mobile device that supports WAP 2.0, 
                // serve them MP

                // XXX: Browser sniffing sucks

                // I really don't like going through this every page, 
                // find a better way

                // May be better to categorize the devices in terms of 
                // low,mid,high-end

                // Or, detect the mobile devices based on their support for 
                // MP 1.0, 1.1, or 1.2 may be ideal. Possible?

                $this->mobiledevices = 
                    array('alcatel', 'android', 'audiovox', 'au-mic,', 
                          'avantgo', 'blackberry', 'blazer', 'cldc-', 'danger', 
                          'epoc', 'ericsson', 'ericy', 'ipone', 'ipaq', 'j2me', 
                          'lg', 'midp-', 'mobile', 'mot', 'netfront', 'nitro', 
                          'nokia', 'opera mini', 'palm', 'palmsource', 
                          'panasonic', 'philips', 'pocketpc', 'portalmmm', 
                          'rover', 'samsung', 'sanyo', 'series60', 'sharp', 
                          'sie-', 'smartphone', 'sony', 'symbian', 
                          'up.browser', 'up.link', 'up.link', 'vodafone', 
                          'wap1', 'wap2', 'windows ce');

                $httpuseragent = strtolower($_SERVER['HTTP_USER_AGENT']);

                foreach($this->mobiledevices as $md) {
                    if (strstr($httpuseragent, $md) !== false) {
                        $this->serveMobile = true;
                        break;
                    }
                }
            }

            // If they are okay with MP, and the site has a mobile server, 
            // redirect there
            if ($this->serveMobile && 
                common_config('site', 'mobileserver') !== false &&
                (common_config('site', 'mobileserver') != 
                    common_config('site', 'server'))) {

                // FIXME: Redirect to equivalent page on mobile site instead
                header("Location: ".$this->_common_path(''));
                exit();
            }
        }

        if (!$this->serveMobile) {
            return true;
        }

        header('Content-Type: '.$type);

        $action->extraHeaders();

        $action->startXML('html',
                        '-//WAPFORUM//DTD XHTML Mobile 1.0//EN',
                        $this->DTD);

        $language = $action->getLanguage();

        $action->elementStart('html', array('xmlns' => 'http://www.w3.org/1999/xhtml',
                                            'xml:lang' => $language));

        return false;
    }


    function onStartShowHeadElements($action)
    {
        if (!$action->serveMobile) {
            return true;
        }

        $action->showTitle();
        $action->showShortcutIcon();
        $action->showStylesheets();
        $action->showFeeds();
        $action->showDescription();
        $action->extraHead();
    }


    function onStartShowStatusNetStyles($action)
    {
        if (!$this->serveMobile) {
            return true;
        }

        if (file_exists(theme_file('css/mp-screen.css'))) {
            $action->cssLink('css/mp-screen.css', null, 'screen');
        }
        else {
            $action->element('link', array('rel' => 'stylesheet',
                                         'type' => 'text/css',
                                         'href' => common_path('plugins/MobileProfile/mp-screen.css') . '?version=' . STATUSNET_VERSION,
                                         'media' => 'screen'));
        }

        if (file_exists(theme_file('css/mp-handheld.css'))) {
            $action->cssLink('css/mp-handheld.css', null, 'handheld');
        }
        else {
            $action->element('link', array('rel' => 'stylesheet',
                                         'type' => 'text/css',
                                         'href' => common_path('plugins/MobileProfile/mp-handheld.css') . '?version=' . STATUSNET_VERSION,
                                         'media' => 'handheld'));
        }

        return false;
    }


    function onStartShowHeader($action)
    {
        if (!$this->serveMobile) {
            return true;
        }

        $action->elementStart('div', array('id' => 'header'));
        $this->_showLogo($action);
        $this->_showPrimaryNav($action);
        if (common_logged_in()) {
            $action->showNoticeForm();
        }
        $action->elementEnd('div');

        return false;
    }


    function _showLogo($action)
    {
        $action->elementStart('address', 'vcard');
        $action->elementStart('a', array('class' => 'url home bookmark',
                                       'href' => common_local_url('public')));
        if (common_config('site', 'mobilelogo') || 
            file_exists(theme_file('logo.png')) || 
            file_exists(theme_file('mobilelogo.gif'))) {

            $action->element('img', array('class' => 'photo',
                'src' => (common_config('site', 'mobilelogo')) ? common_config('site', 'mobilelogo') : 
                            ((file_exists(theme_file('mobilelogo.gif'))) ? (theme_path('mobilelogo.gif')): theme_path('logo.png')),
                'alt' => common_config('site', 'name')));
        }
        $action->element('span', array('class' => 'fn org'), common_config('site', 'name'));
        $action->elementEnd('a');
        $action->elementEnd('address');
    }


    function _showPrimaryNav($action) {
        $user = common_current_user();
        $connect = '';
        if (common_config('xmpp', 'enabled')) {
            $connect = 'imsettings';
        } else if (common_config('sms', 'enabled')) {
            $connect = 'smssettings';
        } else if (common_config('twitter', 'enabled')) {
            $connect = 'twittersettings';
        }

        $action->elementStart('ul', array('id' => 'site_nav_global_primary'));
        if ($user) {
            $action->menuItem(common_local_url('all', array('nickname' => $user->nickname)),
                            _('Home'));
            $action->menuItem(common_local_url('profilesettings'),
                            _('Account'));
            if ($connect) {
                $action->menuItem(common_local_url($connect),
                                _('Connect'));
            }
            if (common_config('invite', 'enabled')) {
                $action->menuItem(common_local_url('invite'),
                                _('Invite'));
            }
            $action->menuItem(common_local_url('logout'),
                            _('Logout'));
        }
        else {
            if (!common_config('site', 'closed')) {
                $action->menuItem(common_local_url('register'),
                                _('Register'));
            }
            $action->menuItem(common_local_url('login'),
                            _('Login'));
        }
        $action->menuItem(common_local_url('doc', array('title' => 'help')),
                        _('Help'));
        if ($user || !common_config('site', 'private')) {
            $action->menuItem(common_local_url('peoplesearch'),
                            _('Search'));
        }
        $action->elementEnd('ul');
    }


    function onStartShowNoticeFormData($form)
    {
        if (!$this->serveMobile) {
            return true;
        }

        $form->out->element('textarea', array('id' => 'notice_data-text',
                                              'cols' => 35,
                                              'rows' => 4,
                                              'name' => 'status_textarea'),
                            ($form->content) ? $form->content : '');

        $contentLimit = Notice::maxContent();

        $form->out->element('script', array('type' => 'text/javascript'),
                            'maxLength = ' . $contentLimit . ';');

        if ($contentLimit > 0) {
            $form->out->element('div', array('id' => 'notice_text-count'),
                                $contentLimit);
        }

        if (common_config('attachments', 'uploads')) {
            $form->out->element('label', array('for' => 'notice_data-attach'),_('Attach'));
            $form->out->element('input', array('id' => 'notice_data-attach',
                                               'type' => 'file',
                                               'name' => 'attach',
                                               'title' => _('Attach a file')));
            $form->out->hidden('MAX_FILE_SIZE', common_config('attachments', 'file_quota'));
        }
        if ($form->action) {
            $form->out->hidden('notice_return-to', $form->action, 'returnto');
        }
        $form->out->hidden('notice_in-reply-to', $form->inreplyto, 'inreplyto');

        return false;
    }


    function onStartShowAside($action)
    {
        if ($this->serveMobile) {
            return false;
        }
    }


    function onStartShowScripts($action)
    {

    }


    function _common_path($relative, $ssl=false)
    {
        $pathpart = (common_config('site', 'path')) ? common_config('site', 'path')."/" : '';

        if (($ssl && (common_config('site', 'ssl') === 'sometimes'))
            || common_config('site', 'ssl') === 'always') {
            $proto = 'https';
            if (is_string(common_config('site', 'sslserver')) &&
                mb_strlen(common_config('site', 'sslserver')) > 0) {
                $serverpart = common_config('site', 'sslserver');
            } else {
                $serverpart = common_config('site', 'mobileserver');
            }
        } else {
            $proto = 'http';
            $serverpart = common_config('site', 'mobileserver');
        }

        return $proto.'://'.$serverpart.'/'.$pathpart.$relative;
    }
}


?>
