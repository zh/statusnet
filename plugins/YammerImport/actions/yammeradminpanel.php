<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Yammer import administration panel
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
 * @category  Settings
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class YammeradminpanelAction extends AdminPanelAction
{
    private $runner;

    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        return _m('Yammer Import');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        return _m('Yammer import tool');
    }

    function prepare($args)
    {
        $ok = parent::prepare($args);

        $this->init_auth = $this->trimmed('init_auth');
        $this->verify_token = $this->trimmed('verify_token');
        $this->runner = YammerRunner::init();

        return $ok;
    }

    function handle($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->checkSessionToken();
            if ($this->init_auth) {
                $url = $this->runner->requestAuth();
                $form = new YammerAuthVerifyForm($this, $this->runner);
                return $this->showAjaxForm($form);
            } else if ($this->verify_token) {
                $this->runner->saveAuthToken($this->verify_token);
                
                // Haho! Now we can make THE FUN HAPPEN
                $this->runner->startBackgroundImport();
                
                $form = new YammerProgressForm($this, $this->runner);
                return $this->showAjaxForm($form);
            } else {
                throw new ClientException('Invalid POST');
            }
        } else {
            return parent::handle($args);
        }
    }

    function showAjaxForm($form)
    {
        $this->startHTML('text/xml;charset=utf-8');
        $this->elementStart('head');
        $this->element('title', null, _m('Yammer import'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $form->show();
        $this->elementEnd('body');
        $this->elementEnd('html');
    }

    /**
     * Show the Yammer admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $this->elementStart('fieldset');

        switch($this->runner->state())
        {
            case 'init':
                $form = new YammerAuthInitForm($this, $this->runner);
                break;
            case 'requesting-auth':
                $form = new YammerAuthVerifyForm($this, $this->runner);
                break;
            default:
                $form = new YammerProgressForm($this, $this->runner);
        }
        $form->show();

        $this->elementEnd('fieldset');
    }

    function showStylesheets()
    {
        parent::showStylesheets();
        $this->cssLink('plugins/YammerImport/css/admin.css', null, 'screen, projection, tv');
    }

    function showScripts()
    {
        parent::showScripts();
        $this->script('plugins/YammerImport/js/yammer-admin.js');
    }
}
