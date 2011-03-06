<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for editing a peopletag
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
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/form.php';

/**
 * Form for editing a peopletag
 *
 * @category Form
 * @package  StatusNet
 * @author   Shashi Gowda <connect2shashi@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      GroupEditForm
 */

class SearchProfileForm extends Form
{
    var $peopletag;

    function __construct($out, Profile_list $peopletag)
    {
        parent::__construct($out);
        $this->peopletag = $peopletag;
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */

    function id()
    {
        return 'form_peopletag-add-' . $this->peopletag->id;
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */

    function formClass()
    {
        return 'form_peopletag_edit_user_search';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('profilecompletion');
    }

    /**
     * Name of the form
     *
     * @return void
     */

    function formLegend()
    {
        $this->out->element('legend', null, sprintf(_('Search and list people')));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $fields = array('fulltext'    => 'Everything',
                        'nickname'    => 'Nickname',
                        'fullname'    => 'Fullname',
                        'description' => 'Description',
                        'location'    => 'Location',
                        'uri'         => 'Uri (Remote users)');
        

        $this->out->hidden('peopletag_id', $this->peopletag->id);
        $this->out->input('q', null);
        $this->out->dropdown('field', _('Search in'), $fields,
                        _('Choose a field to search'), false, 'fulltext');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _('Search'));
    }
}

class UntagButton extends Form
{
    var $profile;
    var $peopletag;

    function __construct($out, Profile $profile, Profile_list $peopletag)
    {
        parent::__construct($out);
        $this->profile = $profile;
        $this->peopletag = $peopletag;
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */

    function id()
    {
        return 'form_peopletag-' . $this->peopletag->id . '-remove-' . $this->profile->id;
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */

    function formClass()
    {
        return 'form_user_remove_peopletag';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('removepeopletag');
    }

    /**
     * Name of the form
     *
     * @return void
     */

    function formLegend()
    {
        $this->out->element('legend', null, sprintf(_('Untag %s as %s'),
            $this->profile->nickname, $this->peopletag->tag));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->out->hidden('peopletag_id', $this->peopletag->id);
        $this->out->hidden('tagged', $this->profile->id);
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _('Remove'));
    }
}


class TagButton extends Form
{
    var $profile;
    var $peopletag;

    function __construct($out, Profile $profile, Profile_list $peopletag)
    {
        parent::__construct($out);
        $this->profile = $profile;
        $this->peopletag = $peopletag;
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */

    function id()
    {
        return 'form_peopletag-' . $this->peopletag->id . '-add-' . $this->profile->id;
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */

    function formClass()
    {
        return 'form_user_add_peopletag';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('addpeopletag');
    }

    /**
     * Name of the form
     *
     * @return void
     */

    function formLegend()
    {
        $this->out->element('legend', null, sprintf(_('Tag %s as %s'),
            $this->profile->nickname, $this->peopletag->tag));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        UntagButton::formData();
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _('Add'));
    }
}

class TaggedProfileItem extends Widget
{
    var $profile=null;

    function __construct($out=null, $profile)
    {
        parent::__construct($out);
        $this->profile = $profile;
    }

    function show()
    {
        $this->out->elementStart('a', array('class' => 'url',
                                            'href' => $this->profile->profileurl,
                                            'title' => $this->profile->getBestName()));
        $avatar = $this->profile->getAvatar(AVATAR_MINI_SIZE);
        $this->out->element('img', array('src' => (($avatar) ? $avatar->displayUrl() :
                                         Avatar::defaultImage(AVATAR_MINI_SIZE)),
                                         'width' => AVATAR_MINI_SIZE,
                                         'height' => AVATAR_MINI_SIZE,
                                         'class' => 'avatar photo',
                                         'alt' => $this->profile->getBestName()));
        $this->out->element('span', 'fn nickname', $this->profile->nickname);
        $this->out->elementEnd('a');
    }
}
