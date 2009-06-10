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

require_once INSTALLDIR.'/lib/noticeform.php';
require_once INSTALLDIR.'/lib/htmloutputter.php';

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

    /**
     * Constructor
     *
     * Just wraps the HTMLOutputter constructor.
     *
     * @param string  $output URI to output to, default = stdout
     * @param boolean $indent Whether to indent output, default true
     *
     * @see XMLOutputter::__construct
     * @see HTMLOutputter::__construct
     */
    function __construct($output='php://output', $indent=true)
    {
        parent::__construct($output, $indent);
    }

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        $this->args =& common_copy_args($argarray);
        return true;
    }

    /**
     * Show page, a template method.
     *
     * @return nothing
     */
    function showPage()
    {
        if (Event::handle('StartShowHTML', array($this))) {
            $this->startHTML();
            Event::handle('EndShowHTML', array($this));
        }
        if (Event::handle('StartShowHead', array($this))) {
            $this->showHead();
            Event::handle('EndShowHead', array($this));
        }
        if (Event::handle('StartShowBody', array($this))) {
            $this->showBody();
            Event::handle('EndShowBody', array($this));
        }
        if (Event::handle('StartEndHTML', array($this))) {
            $this->endHTML();
            Event::handle('EndEndHTML', array($this));
        }
    }

    /**
     * Show head, a template method.
     *
     * @return nothing
     */
    function showHead()
    {
        // XXX: attributes (profile?)
        $this->elementStart('head');
        $this->showTitle();
        $this->showShortcutIcon();
        $this->showStylesheets();
        $this->showScripts();
        $this->showOpenSearch();
        $this->showFeeds();
        $this->showDescription();
        $this->extraHead();
        $this->elementEnd('head');
    }

    /**
     * Show title, a template method.
     *
     * @return nothing
     */
    function showTitle()
    {
        $this->element('title', null,
                       sprintf(_("%s - %s"),
                               $this->title(),
                               common_config('site', 'name')));
    }

    /**
     * Returns the page title
     *
     * SHOULD overload
     *
     * @return string page title
     */

    function title()
    {
        return _("Untitled page");
    }

    /**
     * Show themed shortcut icon
     *
     * @return nothing
     */
    function showShortcutIcon()
    {
        if (is_readable(INSTALLDIR . '/theme/' . common_config('site', 'theme') . '/favicon.ico')) {
            $this->element('link', array('rel' => 'shortcut icon',
                                         'href' => theme_path('favicon.ico')));
        } else {
            $this->element('link', array('rel' => 'shortcut icon',
                                         'href' => common_path('favicon.ico')));
        }

        if (common_config('site', 'mobile')) {
            if (is_readable(INSTALLDIR . '/theme/' . common_config('site', 'theme') . '/apple-touch-icon.png')) {
                $this->element('link', array('rel' => 'apple-touch-icon',
                                             'href' => theme_path('apple-touch-icon.png')));
            } else {
                $this->element('link', array('rel' => 'apple-touch-icon',
                                             'href' => common_path('apple-touch-icon.png')));
            }
        }
    }

    /**
     * Show stylesheets
     *
     * @return nothing
     */
    function showStylesheets()
    {
        if (Event::handle('StartShowStyles', array($this))) {
            if (Event::handle('StartShowLaconicaStyles', array($this))) {
                $this->element('link', array('rel' => 'stylesheet',
                                             'type' => 'text/css',
                                             'href' => theme_path('css/display.css', null) . '?version=' . LACONICA_VERSION,
                                             'media' => 'screen, projection, tv'));
                if (common_config('site', 'mobile')) {
                    $this->element('link', array('rel' => 'stylesheet',
                                                 'type' => 'text/css',
                                                 'href' => theme_path('css/mobile.css', 'base') . '?version=' . LACONICA_VERSION,
                                                 // TODO: "handheld" CSS for other mobile devices
                                                 'media' => 'only screen and (max-device-width: 480px)')); // Mobile WebKit
                }
                $this->element('link', array('rel' => 'stylesheet',
                                             'type' => 'text/css',
                                             'href' => theme_path('css/print.css', 'base') . '?version=' . LACONICA_VERSION,
                                             'media' => 'print'));
                Event::handle('EndShowLaconicaStyles', array($this));
            }
            if (Event::handle('StartShowUAStyles', array($this))) {
                $this->comment('[if IE]><link rel="stylesheet" type="text/css" '.
                               'href="'.theme_path('css/ie.css', 'base').'?version='.LACONICA_VERSION.'" /><![endif]');
                foreach (array(6,7) as $ver) {
                    if (file_exists(theme_file('css/ie'.$ver.'.css', 'base'))) {
                        // Yes, IE people should be put in jail.
                        $this->comment('[if lte IE '.$ver.']><link rel="stylesheet" type="text/css" '.
                                       'href="'.theme_path('css/ie'.$ver.'.css', 'base').'?version='.LACONICA_VERSION.'" /><![endif]');
                    }
                }
                $this->comment('[if IE]><link rel="stylesheet" type="text/css" '.
                               'href="'.theme_path('css/ie.css', null).'?version='.LACONICA_VERSION.'" /><![endif]');
                Event::handle('EndShowUAStyles', array($this));
            }
            Event::handle('EndShowStyles', array($this));
        }
    }

    /**
     * Show javascript headers
     *
     * @return nothing
     */
    function showScripts()
    {
        if (Event::handle('StartShowScripts', array($this))) {
            if (Event::handle('StartShowJQueryScripts', array($this))) {
                $this->element('script', array('type' => 'text/javascript',
                                               'src' => common_path('js/jquery.min.js')),
                               ' ');
                $this->element('script', array('type' => 'text/javascript',
                                               'src' => common_path('js/jquery.form.js')),
                               ' ');
                Event::handle('EndShowJQueryScripts', array($this));
            }
            if (Event::handle('StartShowLaconicaScripts', array($this))) {
                $this->element('script', array('type' => 'text/javascript',
                                               'src' => common_path('js/xbImportNode.js')),
                               ' ');
                $this->element('script', array('type' => 'text/javascript',
                                               'src' => common_path('js/util.js?version='.LACONICA_VERSION)),
                               ' ');
                // Frame-busting code to avoid clickjacking attacks.
                $this->element('script', array('type' => 'text/javascript'),
                               'if (window.top !== window.self) { window.top.location.href = window.self.location.href; }');
                Event::handle('EndShowLaconicaScripts', array($this));
            }
            Event::handle('EndShowScripts', array($this));
        }
    }

    /**
     * Show OpenSearch headers
     *
     * @return nothing
     */
    function showOpenSearch()
    {
        $this->element('link', array('rel' => 'search',
                                     'type' => 'application/opensearchdescription+xml',
                                     'href' =>  common_local_url('opensearch', array('type' => 'people')),
                                     'title' => common_config('site', 'name').' People Search'));
        $this->element('link', array('rel' => 'search', 'type' => 'application/opensearchdescription+xml',
                                     'href' =>  common_local_url('opensearch', array('type' => 'notice')),
                                     'title' => common_config('site', 'name').' Notice Search'));
    }

    /**
     * Show feed headers
     *
     * MAY overload
     *
     * @return nothing
     */

    function showFeeds()
    {
        $feeds = $this->getFeeds();

        if ($feeds) {
            foreach ($feeds as $feed) {
                $this->element('link', array('rel' => $feed->rel(),
                                             'href' => $feed->url,
                                             'type' => $feed->mimeType(),
                                             'title' => $feed->title));
            }
        }
    }

    /**
     * Show description.
     *
     * SHOULD overload
     *
     * @return nothing
     */
    function showDescription()
    {
        // does nothing by default
    }

    /**
     * Show extra stuff in <head>.
     *
     * MAY overload
     *
     * @return nothing
     */
    function extraHead()
    {
        // does nothing by default
    }

    /**
     * Show body.
     *
     * Calls template methods
     *
     * @return nothing
     */
    function showBody()
    {
        $this->elementStart('body', (common_current_user()) ? array('id' => $this->trimmed('action'),
                                                                    'class' => 'user_in')
                            : array('id' => $this->trimmed('action')));
        $this->elementStart('div', array('id' => 'wrap'));
        if (Event::handle('StartShowHeader', array($this))) {
            $this->showHeader();
            Event::handle('EndShowHeader', array($this));
        }
        $this->showCore();
        if (Event::handle('StartShowFooter', array($this))) {
            $this->showFooter();
            Event::handle('EndShowFooter', array($this));
        }
        $this->elementEnd('div');
        $this->elementEnd('body');
    }

    /**
     * Show header of the page.
     *
     * Calls template methods
     *
     * @return nothing
     */
    function showHeader()
    {
        $this->elementStart('div', array('id' => 'header'));
        $this->showLogo();
        $this->showPrimaryNav();
        $this->showSiteNotice();
        if (common_logged_in()) {
            $this->showNoticeForm();
        } else {
            $this->showAnonymousMessage();
        }
        $this->elementEnd('div');
    }

    /**
     * Show configured logo.
     *
     * @return nothing
     */
    function showLogo()
    {
        $this->elementStart('address', array('id' => 'site_contact',
                                             'class' => 'vcard'));
        $this->elementStart('a', array('class' => 'url home bookmark',
                                       'href' => common_local_url('public')));
        if (common_config('site', 'logo') || file_exists(theme_file('logo.png'))) {
            $this->element('img', array('class' => 'logo photo',
                                        'src' => (common_config('site', 'logo')) ? common_config('site', 'logo') : theme_path('logo.png'),
                                        'alt' => common_config('site', 'name')));
        }
        $this->element('span', array('class' => 'fn org'), common_config('site', 'name'));
        $this->elementEnd('a');
        $this->elementEnd('address');
    }

    /**
     * Show primary navigation.
     *
     * @return nothing
     */
    function showPrimaryNav()
    {
        $user = common_current_user();

        $this->elementStart('dl', array('id' => 'site_nav_global_primary'));
        $this->element('dt', null, _('Primary site navigation'));
        $this->elementStart('dd');
        $this->elementStart('ul', array('class' => 'nav'));
        if (Event::handle('StartPrimaryNav', array($this))) {
            if ($user) {
                $this->menuItem(common_local_url('all', array('nickname' => $user->nickname)),
                                _('Home'), _('Personal profile and friends timeline'), false, 'nav_home');
                $this->menuItem(common_local_url('profilesettings'),
                                _('Account'), _('Change your email, avatar, password, profile'), false, 'nav_account');
                if (common_config('xmpp', 'enabled')) {
                    $this->menuItem(common_local_url('imsettings'),
                                    _('Connect'), _('Connect to IM, SMS, Twitter'), false, 'nav_connect');
                } else {
                    $this->menuItem(common_local_url('smssettings'),
                                    _('Connect'), _('Connect to SMS, Twitter'), false, 'nav_connect');
                }
                $this->menuItem(common_local_url('invite'),
                                _('Invite'),
                                sprintf(_('Invite friends and colleagues to join you on %s'),
                                        common_config('site', 'name')),
                                false, 'nav_invitecontact');
                $this->menuItem(common_local_url('logout'),
                                _('Logout'), _('Logout from the site'), false, 'nav_logout');
            }
            else {
                if (!common_config('site', 'closed')) {
                    $this->menuItem(common_local_url('register'),
                                    _('Register'), _('Create an account'), false, 'nav_register');
                }
                $this->menuItem(common_local_url('openidlogin'),
                                _('OpenID'), _('Login with OpenID'), false, 'nav_openid');
                $this->menuItem(common_local_url('login'),
                                _('Login'), _('Login to the site'), false, 'nav_login');
            }
            $this->menuItem(common_local_url('doc', array('title' => 'help')),
                            _('Help'), _('Help me!'), false, 'nav_help');
            $this->menuItem(common_local_url('peoplesearch'),
                            _('Search'), _('Search for people or text'), false, 'nav_search');
            Event::handle('EndPrimaryNav', array($this));
        }
        $this->elementEnd('ul');
        $this->elementEnd('dd');
        $this->elementEnd('dl');
    }

    /**
     * Show site notice.
     *
     * @return nothing
     */
    function showSiteNotice()
    {
        // Revist. Should probably do an hAtom pattern here
        $text = common_config('site', 'notice');
        if ($text) {
            $this->elementStart('dl', array('id' => 'site_notice',
                                            'class' => 'system_notice'));
            $this->element('dt', null, _('Site notice'));
            $this->elementStart('dd', null);
            $this->raw($text);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
    }

    /**
     * Show notice form.
     *
     * MAY overload if no notice form needed... or direct message box????
     *
     * @return nothing
     */
    function showNoticeForm()
    {
        $notice_form = new NoticeForm($this);
        $notice_form->show();
    }

    /**
     * Show anonymous message.
     *
     * SHOULD overload
     *
     * @return nothing
     */
    function showAnonymousMessage()
    {
        // needs to be defined by the class
    }

    /**
     * Show core.
     *
     * Shows local navigation, content block and aside.
     *
     * @return nothing
     */
    function showCore()
    {
        $this->elementStart('div', array('id' => 'core'));
        if (Event::handle('StartShowLocalNavBlock', array($this))) {
            $this->showLocalNavBlock();
            Event::handle('EndShowLocalNavBlock', array($this));
        }
        if (Event::handle('StartShowContentBlock', array($this))) {
            $this->showContentBlock();
            Event::handle('EndShowContentBlock', array($this));
        }
        $this->showAside();
        $this->elementEnd('div');
    }

    /**
     * Show local navigation block.
     *
     * @return nothing
     */
    function showLocalNavBlock()
    {
        $this->elementStart('dl', array('id' => 'site_nav_local_views'));
        $this->element('dt', null, _('Local views'));
        $this->elementStart('dd');
        $this->showLocalNav();
        $this->elementEnd('dd');
        $this->elementEnd('dl');
    }

    /**
     * Show local navigation.
     *
     * SHOULD overload
     *
     * @return nothing
     */
    function showLocalNav()
    {
        // does nothing by default
    }

    /**
     * Show content block.
     *
     * @return nothing
     */
    function showContentBlock()
    {
        $this->elementStart('div', array('id' => 'content'));
        $this->showPageTitle();
        $this->showPageNoticeBlock();
        $this->elementStart('div', array('id' => 'content_inner'));
        // show the actual content (forms, lists, whatever)
        $this->showContent();
        $this->elementEnd('div');
        $this->elementEnd('div');
    }

    /**
     * Show page title.
     *
     * @return nothing
     */
    function showPageTitle()
    {
        $this->element('h1', null, $this->title());
    }

    /**
     * Show page notice block.
     *
     * @return nothing
     */
    function showPageNoticeBlock()
    {
        $this->elementStart('dl', array('id' => 'page_notice',
                                        'class' => 'system_notice'));
        $this->element('dt', null, _('Page notice'));
        $this->elementStart('dd');
        if (Event::handle('StartShowPageNotice', array($this))) {
            $this->showPageNotice();
            Event::handle('EndShowPageNotice', array($this));
        }
        $this->elementEnd('dd');
        $this->elementEnd('dl');
    }

    /**
     * Show page notice.
     *
     * SHOULD overload (unless there's not a notice)
     *
     * @return nothing
     */
    function showPageNotice()
    {
    }

    /**
     * Show content.
     *
     * MUST overload (unless there's not a notice)
     *
     * @return nothing
     */
    function showContent()
    {
    }

    /**
     * Show Aside.
     *
     * @return nothing
     */

    function showAside()
    {
        $this->elementStart('div', array('id' => 'aside_primary',
                                         'class' => 'aside'));
        if (Event::handle('StartShowExportData', array($this))) {
            $this->showExportData();
            Event::handle('EndShowExportData', array($this));
        }
        if (Event::handle('StartShowSections', array($this))) {
            $this->showSections();
            Event::handle('EndShowSections', array($this));
        }
        $this->elementEnd('div');
    }

    /**
     * Show export data feeds.
     *
     * @return void
     */

    function showExportData()
    {
        $feeds = $this->getFeeds();
        if ($feeds) {
            $fl = new FeedList($this);
            $fl->show($feeds);
        }
    }

    /**
     * Show sections.
     *
     * SHOULD overload
     *
     * @return nothing
     */
    function showSections()
    {
        // for each section, show it
    }

    /**
     * Show footer.
     *
     * @return nothing
     */
    function showFooter()
    {
        $this->elementStart('div', array('id' => 'footer'));
        $this->showSecondaryNav();
        $this->showLicenses();
        $this->elementEnd('div');
    }

    /**
     * Show secondary navigation.
     *
     * @return nothing
     */
    function showSecondaryNav()
    {
        $this->elementStart('dl', array('id' => 'site_nav_global_secondary'));
        $this->element('dt', null, _('Secondary site navigation'));
        $this->elementStart('dd', null);
        $this->elementStart('ul', array('class' => 'nav'));
        if (Event::handle('StartSecondaryNav', array($this))) {
            $this->menuItem(common_local_url('doc', array('title' => 'help')),
                            _('Help'));
            $this->menuItem(common_local_url('doc', array('title' => 'about')),
                            _('About'));
            $this->menuItem(common_local_url('doc', array('title' => 'faq')),
                            _('FAQ'));
            $this->menuItem(common_local_url('doc', array('title' => 'privacy')),
                            _('Privacy'));
            $this->menuItem(common_local_url('doc', array('title' => 'source')),
                            _('Source'));
            $this->menuItem(common_local_url('doc', array('title' => 'contact')),
                            _('Contact'));
            $this->menuItem(common_local_url('doc', array('title' => 'badge')),
                            _('Badge'));
            Event::handle('EndSecondaryNav', array($this));
        }
        $this->elementEnd('ul');
        $this->elementEnd('dd');
        $this->elementEnd('dl');
    }

    /**
     * Show licenses.
     *
     * @return nothing
     */
    function showLicenses()
    {
        $this->elementStart('dl', array('id' => 'licenses'));
        $this->showLaconicaLicense();
        $this->showContentLicense();
        $this->elementEnd('dl');
    }

    /**
     * Show Laconica license.
     *
     * @return nothing
     */
    function showLaconicaLicense()
    {
        $this->element('dt', array('id' => 'site_laconica_license'), _('Laconica software license'));
        $this->elementStart('dd', null);
        if (common_config('site', 'broughtby')) {
            $instr = _('**%%site.name%%** is a microblogging service brought to you by [%%site.broughtby%%](%%site.broughtbyurl%%). ');
        } else {
            $instr = _('**%%site.name%%** is a microblogging service. ');
        }
        $instr .= sprintf(_('It runs the [Laconica](http://laconi.ca/) microblogging software, version %s, available under the [GNU Affero General Public License](http://www.fsf.org/licensing/licenses/agpl-3.0.html).'), LACONICA_VERSION);
        $output = common_markup_to_html($instr);
        $this->raw($output);
        $this->elementEnd('dd');
        // do it
    }

    /**
     * Show content license.
     *
     * @return nothing
     */
    function showContentLicense()
    {
        $this->element('dt', array('id' => 'site_content_license'), _('Laconica software license'));
        $this->elementStart('dd', array('id' => 'site_content_license_cc'));
        $this->elementStart('p');
        $this->element('img', array('id' => 'license_cc',
                                    'src' => common_config('license', 'image'),
                                    'alt' => common_config('license', 'title')));
        //TODO: This is dirty: i18n
        $this->text(_('All '.common_config('site', 'name').' content and data are available under the '));
        $this->element('a', array('class' => 'license',
                                  'rel' => 'external license',
                                  'href' => common_config('license', 'url')),
                       common_config('license', 'title'));
        $this->text(_('license.'));
        $this->elementEnd('p');
        $this->elementEnd('dd');
    }

    /**
     * Return last modified, if applicable.
     *
     * MAY override
     *
     * @return string last modified http header
     */
    function lastModified()
    {
        // For comparison with If-Last-Modified
        // If not applicable, return null
        return null;
    }

    /**
     * Return etag, if applicable.
     *
     * MAY override
     *
     * @return string etag http header
     */
    function etag()
    {
        return null;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */

    function isReadOnly($args)
    {
        return false;
    }

    /**
     * Returns query argument or default value if not found
     *
     * @param string $key requested argument
     * @param string $def default value to return if $key is not provided
     *
     * @return boolean is read only action?
     */
    function arg($key, $def=null)
    {
        if (array_key_exists($key, $this->args)) {
            return $this->args[$key];
        } else {
            return $def;
        }
    }

    /**
     * Returns trimmed query argument or default value if not found
     *
     * @param string $key requested argument
     * @param string $def default value to return if $key is not provided
     *
     * @return boolean is read only action?
     */
    function trimmed($key, $def=null)
    {
        $arg = $this->arg($key, $def);
        return is_string($arg) ? trim($arg) : $arg;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return boolean is read only action?
     */
    function handle($argarray=null)
    {
        $lm   = $this->lastModified();
        $etag = $this->etag();
        if ($etag) {
            header('ETag: ' . $etag);
        }
        if ($lm) {
            header('Last-Modified: ' . date(DATE_RFC1123, $lm));
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                $ims = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
                if ($lm <= $ims) {
                    $if_none_match = $_SERVER['HTTP_IF_NONE_MATCH'];
                    if (!$if_none_match ||
                        !$etag ||
                        $this->_hasEtag($etag, $if_none_match)) {
                        header('HTTP/1.1 304 Not Modified');
                        // Better way to do this?
                        exit(0);
                    }
                }
            }
        }
    }

    /**
     * Has etag? (private)
     *
     * @param string $etag          etag http header
     * @param string $if_none_match ifNoneMatch http header
     *
     * @return boolean
     */

    function _hasEtag($etag, $if_none_match)
    {
        $etags = explode(',', $if_none_match);
        return in_array($etag, $etags) || in_array('*', $etags);
    }

    /**
     * Boolean understands english (yes, no, true, false)
     *
     * @param string $key query key we're interested in
     * @param string $def default value
     *
     * @return boolean interprets yes/no strings as boolean
     */
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

    /**
     * Server error
     *
     * @param string  $msg  error message to display
     * @param integer $code http error code, 500 by default
     *
     * @return nothing
     */

    function serverError($msg, $code=500)
    {
        $action = $this->trimmed('action');
        common_debug("Server error '$code' on '$action': $msg", __FILE__);
        throw new ServerException($msg, $code);
    }

    /**
     * Client error
     *
     * @param string  $msg  error message to display
     * @param integer $code http error code, 400 by default
     *
     * @return nothing
     */

    function clientError($msg, $code=400)
    {
        $action = $this->trimmed('action');
        common_debug("User error '$code' on '$action': $msg", __FILE__);
        throw new ClientException($msg, $code);
    }

    /**
     * Returns the current URL
     *
     * @return string current URL
     */

    function selfUrl()
    {
        $action = $this->trimmed('action');
        $args   = $this->args;
        unset($args['action']);
        if (array_key_exists('submit', $args)) {
            unset($args['submit']);
        }
        foreach (array_keys($_COOKIE) as $cookie) {
            unset($args[$cookie]);
        }
        return common_local_url($action, $args);
    }

    /**
     * Generate a menu item
     *
     * @param string  $url         menu URL
     * @param string  $text        menu name
     * @param string  $title       title attribute, null by default
     * @param boolean $is_selected current menu item, false by default
     * @param string  $id          element id, null by default
     *
     * @return nothing
     */
    function menuItem($url, $text, $title=null, $is_selected=false, $id=null)
    {
        // Added @id to li for some control.
        // XXX: We might want to move this to htmloutputter.php
        $lattrs = array();
        if ($is_selected) {
            $lattrs['class'] = 'current';
        }

        (is_null($id)) ? $lattrs : $lattrs['id'] = $id;

        $this->elementStart('li', $lattrs);
        $attrs['href'] = $url;
        if ($title) {
            $attrs['title'] = $title;
        }
        $this->element('a', $attrs, $text);
        $this->elementEnd('li');
    }

    /**
     * Generate pagination links
     *
     * @param boolean $have_before is there something before?
     * @param boolean $have_after  is there something after?
     * @param integer $page        current page
     * @param string  $action      current action
     * @param array   $args        rest of query arguments
     *
     * @return nothing
     */
    function pagination($have_before, $have_after, $page, $action, $args=null)
    {
        // Does a little before-after block for next/prev page
        if ($have_before || $have_after) {
            $this->elementStart('div', array('class' => 'pagination'));
            $this->elementStart('dl', null);
            $this->element('dt', null, _('Pagination'));
            $this->elementStart('dd', null);
            $this->elementStart('ul', array('class' => 'nav'));
        }
        if ($have_before) {
            $pargs   = array('page' => $page-1);
            $this->elementStart('li', array('class' => 'nav_prev'));
            $this->element('a', array('href' => common_local_url($action, $args, $pargs),
                                      'rel' => 'prev'),
                           _('After'));
            $this->elementEnd('li');
        }
        if ($have_after) {
            $pargs   = array('page' => $page+1);
            $this->elementStart('li', array('class' => 'nav_next'));
            $this->element('a', array('href' => common_local_url($action, $args, $pargs),
                                      'rel' => 'next'),
                           _('Before'));
            $this->elementEnd('li');
        }
        if ($have_before || $have_after) {
            $this->elementEnd('ul');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
            $this->elementEnd('div');
        }
    }

    /**
     * An array of feeds for this action.
     *
     * Returns an array of potential feeds for this action.
     *
     * @return array Feed object to show in head and links
     */

    function getFeeds()
    {
        return null;
    }
}
