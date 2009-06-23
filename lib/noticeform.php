<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Form for posting a notice
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
 * @category  Form
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/form.php';

/**
 * Form for posting a notice
 *
 * Frequently-used form for posting a notice
 *
 * @category Form
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      HTMLOutputter
 */

class NoticeForm extends Form
{
    /**
     * Current action, used for returning to this page.
     */

    var $action = null;

    /**
     * Pre-filled content of the form
     */

    var $content = null;

    /**
     * The current user
     */

    var $user = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     output channel
     * @param string        $action  action to return to, if any
     * @param string        $content content to pre-fill
     */

    function __construct($out=null, $action=null, $content=null, $user=null)
    {
        parent::__construct($out);

        $this->action  = $action;
        $this->content = $content;
        
        if ($user) {
            $this->user = $user;
        } else {
            $this->user = common_current_user();
        }

        if (common_config('attachments', 'uploads')) {
            $this->enctype = 'multipart/form-data';
        }
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'form_notice';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('newnotice');
    }


    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        $this->out->element('legend', null, _('Send a notice'));
    }


    /**
     * Data elements
     *
     * @return void
     */

    function formData()
    {
        $this->out->element('label', array('for' => 'notice_data-text'),
                            sprintf(_('What\'s up, %s?'), $this->user->nickname));
        // XXX: vary by defined max size
        $this->out->element('textarea', array('id' => 'notice_data-text',
                                              'cols' => 35,
                                              'rows' => 4,
                                              'name' => 'status_textarea'),
                            ($this->content) ? $this->content : '');
        $this->out->elementStart('dl', 'form_note');
        $this->out->element('dt', null, _('Available characters'));
        $this->out->element('dd', array('id' => 'notice_text-count'),
                            '140');
        $this->out->elementEnd('dl');
        if (common_config('attachments', 'uploads')) {
            $this->out->element('label', array('for' => 'notice_data-attach'),_('Attach'));
            $this->out->element('input', array('id' => 'notice_data-attach',
                                               'type' => 'file',
                                               'name' => 'attach',
                                               'title' => _('Attach a file')));
            $this->out->hidden('MAX_FILE_SIZE', common_config('attachments', 'file_quota'));
        }
        if ($this->action) {
            $this->out->hidden('notice_return-to', $this->action, 'returnto');
        }
        $this->out->hidden('notice_in-reply-to', $this->action, 'inreplyto');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->element('input', array('id' => 'notice_action-submit',
                                           'class' => 'submit',
                                           'name' => 'status_submit',
                                           'type' => 'submit',
                                           'value' => _('Send')));
    }
}
