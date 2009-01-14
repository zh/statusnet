<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Base class for all actions (~views)
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
 * @category  Action
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Base class for all actions
 *
 * This is the base class for all actions in the package. An action is
 * more or less a "view" in an MVC framework.
 *
 * Actions are responsible for extracting and validating parameters; using
 * model classes to read and write to the database; and doing ouput.
 *
 * @category Output
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      HTMLOutputter
 */

class Action extends HTMLOutputter // lawsuit
{
    var $args;

    function Action()
    {
    }

    // For initializing members of the class

    function prepare($argarray)
    {
        $this->args =& common_copy_args($argarray);
        return true;
    }

    function showPage()
    {
        $this->startHTML();
        $this->showHead();
        $this->showBody();
        $this->endHTML();
    }

    function showHead()
    {
        // XXX: attributes (profile?)
        $this->startElement('head');
        $this->showTitle();
        $this->showStylesheets();
        $this->showScripts();
        $this->showOpenSearch();
        $this->showFeeds();
        $this->showDescription();
        $this->extraHead();
        $this->elementElement('head');
    }

    function showTitle()
    {
        $this->element('title', null,
                       sprintf(_("%s - %s"),
                               $this->title(),
                               common_config('site', 'name')));
    }

    // SHOULD overload

    function title()
    {
        return _("Untitled page");
    }

    function showStylesheets()
    {
        common_element('link', array('rel' => 'stylesheet',
                                     'type' => 'text/css',
                                     'href' => theme_path('display.css') . '?version=' . LACONICA_VERSION,
                                     'media' => 'screen, projection, tv'));
        foreach (array(6,7) as $ver) {
            if (file_exists(theme_file('ie'.$ver.'.css'))) {
                // Yes, IE people should be put in jail.
                $xw->writeComment('[if lte IE '.$ver.']><link rel="stylesheet" type="text/css" '.
                                  'href="'.theme_path('ie'.$ver.'.css').'?version='.LACONICA_VERSION.'" /><![endif]');
            }
        }
    }

    function showScripts()
    {
        common_element('script', array('type' => 'text/javascript',
                                       'src' => common_path('js/jquery.min.js')),
                       ' ');
        common_element('script', array('type' => 'text/javascript',
                                       'src' => common_path('js/jquery.form.js')),
                       ' ');
        common_element('script', array('type' => 'text/javascript',
                                       'src' => common_path('js/xbImportNode.js')),
                       ' ');
        common_element('script', array('type' => 'text/javascript',
                                       'src' => common_path('js/util.js?version='.LACONICA_VERSION)),
                       ' ');
    }

    function showOpenSearch()
    {
        common_element('link', array('rel' => 'search', 'type' => 'application/opensearchdescription+xml',
                                     'href' =>  common_local_url('opensearch', array('type' => 'people')),
                                     'title' => common_config('site', 'name').' People Search'));

        common_element('link', array('rel' => 'search', 'type' => 'application/opensearchdescription+xml',
                                     'href' =>  common_local_url('opensearch', array('type' => 'notice')),
                                     'title' => common_config('site', 'name').' Notice Search'));
    }

    // MAY overload

    function showFeeds()
    {
        // does nothing by default
    }

    // SHOULD overload

    function showDescription()
    {
        // does nothing by default
    }

    // MAY overload

    function extraHead()
    {
        // does nothing by default
    }

    function showBody()
    {
        // output body
        // output wrap element
        $this->showHeader();
        $this->showCore();
        $this->showFooter();
    }

    function showHeader()
    {
        // start header div stuff
        $this->showLogo();
        $this->showPrimaryNav();
        $this->showSiteNotice();
        $this->showNoticeForm();
        // end header div stuff
    }

    function showLogo()
    {
        // show the logo here
    }

    function showPrimaryNav()
    {
        $user = common_current_user();
        common_element_start('ul', array('id' => 'nav'));
        if ($user) {
            common_menu_item(common_local_url('all', array('nickname' => $user->nickname)),
                             _('Home'));
        }
        common_menu_item(common_local_url('peoplesearch'), _('Search'));
        if ($user) {
            common_menu_item(common_local_url('profilesettings'),
                             _('Settings'));
            common_menu_item(common_local_url('invite'),
                             _('Invite'));
            common_menu_item(common_local_url('logout'),
                             _('Logout'));
        } else {
            common_menu_item(common_local_url('login'), _('Login'));
            if (!common_config('site', 'closed')) {
                common_menu_item(common_local_url('register'), _('Register'));
            }
            common_menu_item(common_local_url('openidlogin'), _('OpenID'));
        }
        common_menu_item(common_local_url('doc', array('title' => 'help')),
                         _('Help'));
        common_element_end('ul');
    }

    function showSiteNotice()
    {
        // show the site notice here
    }

    // MAY overload if no notice form needed... or direct message box????

    function showNoticeForm()
    {
        // show the notice form here
    }

    function showCore()
    {
        // start core div
        $this->showLocalNav();
        $this->showContentBlock();
        $this->showAside();
        // end core div
    }

    // SHOULD overload

    function showLocalNav()
    {
    }

    function showContentBlock()
    {
        $this->showPageTitle();
        $this->showPageNotice();
        $this->showContent();
    }

    function showPageTitle() {
        $this->element('h1', NULL, $this->title());
    }

    // SHOULD overload (unless there's not a notice)

    function showPageNotice()
    {
        // output page notice div
    }

    // MUST overload

    function showContent()
    {
        // show the actual content (forms, lists, whatever)
    }

    function showAside()
    {
        $this->showExportData();
        $this->showSections();
    }

    // MAY overload if there are feeds

    function showExportData()
    {
        // is there structure to this?
        // list of (visible!) feed links
        // can we reuse list of feeds from showFeeds() ?
    }

    // SHOULD overload

    function showSections() {
        // for each section, show it
    }

    function showFooter()
    {
        // start footer div
        $this->showSecondaryNav();
        $this->showLicenses();
    }

    function showSecondaryNav()
    {
        common_element_start('ul', array('id' => 'nav_sub'));
        common_menu_item(common_local_url('doc', array('title' => 'help')),
                         _('Help'));
        common_menu_item(common_local_url('doc', array('title' => 'about')),
                         _('About'));
        common_menu_item(common_local_url('doc', array('title' => 'faq')),
                         _('FAQ'));
        common_menu_item(common_local_url('doc', array('title' => 'privacy')),
                         _('Privacy'));
        common_menu_item(common_local_url('doc', array('title' => 'source')),
                         _('Source'));
        common_menu_item(common_local_url('doc', array('title' => 'contact')),
                         _('Contact'));
        common_element_end('ul');
    }

    function showLicenses()
    {
        // start license dl
        $this->showLaconicaLicense();
        $this->showContentLicense();
        // end license dl
    }

    function showLaconicaLicense()
    {
        common_element_start('div', 'laconica');
        if (common_config('site', 'broughtby')) {
            $instr = _('**%%site.name%%** is a microblogging service brought to you by [%%site.broughtby%%](%%site.broughtbyurl%%). ');
        } else {
            $instr = _('**%%site.name%%** is a microblogging service. ');
        }
        $instr .= sprintf(_('It runs the [Laconica](http://laconi.ca/) microblogging software, version %s, available under the [GNU Affero General Public License](http://www.fsf.org/licensing/licenses/agpl-3.0.html).'), LACONICA_VERSION);
        $output = common_markup_to_html($instr);
        common_raw($output);
        common_element_end('div');
        // do it
    }

    function showContentLicense()
    {
        common_element_start('div', array('id' => 'footer'));
        common_element('img', array('id' => 'cc',
                                    'src' => $config['license']['image'],
                                    'alt' => $config['license']['title']));
        common_element_start('p');
        common_text(_('Unless otherwise specified, contents of this site are copyright by the contributors and available under the '));
        common_element('a', array('class' => 'license',
                                  'rel' => 'license',
                                  'href' => $config['license']['url']),
                       $config['license']['title']);
        common_text(_('. Contributors should be attributed by full name or nickname.'));
        common_element_end('p');
        common_element_end('div');
    }

    // For comparison with If-Last-Modified
    // If not applicable, return null

    function last_modified()
    {
        return null;
    }

    function etag()
    {
        return null;
    }

    function is_readonly()
    {
        return false;
    }

    function arg($key, $def=null)
    {
        if (array_key_exists($key, $this->args)) {
            return $this->args[$key];
        } else {
            return $def;
        }
    }

    function trimmed($key, $def=null)
    {
        $arg = $this->arg($key, $def);
        return (is_string($arg)) ? trim($arg) : $arg;
    }

    // Note: argarray ignored, since it's now passed in in prepare()

    function handle($argarray=null)
    {

        $lm = $this->last_modified();
        $etag = $this->etag();

        if ($etag) {
            header('ETag: ' . $etag);
        }

        if ($lm) {
            header('Last-Modified: ' . date(DATE_RFC1123, $lm));
            $if_modified_since = $_SERVER['HTTP_IF_MODIFIED_SINCE'];
            if ($if_modified_since) {
                $ims = strtotime($if_modified_since);
                if ($lm <= $ims) {
                    if (!$etag ||
                        $this->_has_etag($etag, $_SERVER['HTTP_IF_NONE_MATCH'])) {
                        header('HTTP/1.1 304 Not Modified');
                        // Better way to do this?
                        exit(0);
                    }
                }
            }
        }
    }

    function _has_etag($etag, $if_none_match)
    {
        return ($if_none_match) && in_array($etag, explode(',', $if_none_match));
    }

    function boolean($key, $def=false)
    {
        $arg = strtolower($this->trimmed($key));

        if (is_null($arg)) {
            return $def;
        } else if (in_array($arg, array('true', 'yes', '1'))) {
            return true;
        } else if (in_array($arg, array('false', 'no', '0'))) {
            return false;
        } else {
            return $def;
        }
    }

    function server_error($msg, $code=500)
    {
        $action = $this->trimmed('action');
        common_debug("Server error '$code' on '$action': $msg", __FILE__);
        common_server_error($msg, $code);
    }

    function client_error($msg, $code=400)
    {
        $action = $this->trimmed('action');
        common_debug("User error '$code' on '$action': $msg", __FILE__);
        common_user_error($msg, $code);
    }

    function self_url()
    {
        $action = $this->trimmed('action');
        $args = $this->args;
        unset($args['action']);
        foreach (array_keys($_COOKIE) as $cookie) {
            unset($args[$cookie]);
        }
        return common_local_url($action, $args);
    }

    function nav_menu($menu)
    {
        $action = $this->trimmed('action');
        common_element_start('ul', array('id' => 'nav_views'));
        foreach ($menu as $menuaction => $menudesc) {
            common_menu_item(common_local_url($menuaction,
                                              isset($menudesc[2]) ? $menudesc[2] : null),
                             $menudesc[0],
                             $menudesc[1],
                             $action == $menuaction);
        }
        common_element_end('ul');
    }

    function common_show_header($pagetitle, $callable=null, $data=null, $headercall=null)
    {
        global $config, $xw;
        global $action; /* XXX: kind of cheating here. */

        common_start_html();

        common_element_start('head');

        if ($callable) {
            if ($data) {
                call_user_func($callable, $data);
            } else {
                call_user_func($callable);
            }
        }
        common_element_end('head');
        common_element_start('body', $action);
        common_element_start('div', array('id' => 'wrap'));
        common_element_start('div', array('id' => 'header'));
        common_nav_menu();
        if ((isset($config['site']['logo']) && is_string($config['site']['logo']) && (strlen($config['site']['logo']) > 0))
            || file_exists(theme_file('logo.png')))
        {
            common_element_start('a', array('href' => common_local_url('public')));
            common_element('img', array('src' => isset($config['site']['logo']) ?
                                        ($config['site']['logo']) : theme_path('logo.png'),
                                        'alt' => $config['site']['name'],
                                        'id' => 'logo'));
            common_element_end('a');
        } else {
            common_element_start('p', array('id' => 'branding'));
            common_element('a', array('href' => common_local_url('public')),
                           $config['site']['name']);
            common_element_end('p');
        }

        common_element('h1', 'pagetitle', $pagetitle);

        if ($headercall) {
            if ($data) {
                call_user_func($headercall, $data);
            } else {
                call_user_func($headercall);
            }
        }
        common_element_end('div');
        common_element_start('div', array('id' => 'content'));
    }

    function common_show_footer()
    {
        global $xw, $config;
        common_element_end('div'); // content div
        common_foot_menu();
        common_element_end('div');
        common_element_end('body');
        common_element_end('html');
        common_end_xml();
    }

    function common_menu_item($url, $text, $title=null, $is_selected=false)
    {
        $lattrs = array();
        if ($is_selected) {
            $lattrs['class'] = 'current';
        }
        common_element_start('li', $lattrs);
        $attrs['href'] = $url;
        if ($title) {
            $attrs['title'] = $title;
        }
        common_element('a', $attrs, $text);
        common_element_end('li');
    }

    // Does a little before-after block for next/prev page

    function pagination($have_before, $have_after, $page, $action, $args=null)
    {
        if ($have_before || $have_after) {
            $this->elementStart('div', array('id' => 'pagination'));
            $this->elementStart('ul', array('id' => 'nav_pagination'));
        }

        if ($have_before) {
            $pargs = array('page' => $page-1);
            $newargs = ($args) ? array_merge($args,$pargs) : $pargs;

            $this->elementStart('li', 'before');
            $this->element('a', array('href' => common_local_url($action, $newargs), 'rel' => 'prev'),
                           _('« After'));
            $this->elementEnd('li');
        }

        if ($have_after) {
            $pargs = array('page' => $page+1);
            $newargs = ($args) ? array_merge($args,$pargs) : $pargs;
            $this->elementStart('li', 'after');
            $this->element('a', array('href' => common_local_url($action, $newargs), 'rel' => 'next'),
                           _('Before »'));
            $this->elementEnd('li');
        }

        if ($have_before || $have_after) {
            $this->elementEnd('ul');
            $this->elementEnd('div');
        }
    }
}
