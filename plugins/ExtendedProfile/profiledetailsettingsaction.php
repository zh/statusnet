<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

class ProfileDetailSettingsAction extends ProfileSettingsAction
{

    function title()
    {
        return _m('Extended profile settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Usage instructions for profile settings.
        return _('You can update your personal profile info here '.
                 'so people know more about you.');
    }

    function showStylesheets() {
        parent::showStylesheets();
        $this->cssLink('plugins/ExtendedProfile/css/profiledetail.css');
        return true;
    }

    function  showScripts() {
        parent::showScripts();
        $this->script('plugins/ExtendedProfile/js/profiledetail.js');
        return true;
    }

    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->show_form(
                _m(
                    'There was a problem with your session token. '
                    .   'Try again, please.'
                  )
            );
            return;
        }

        if ($this->arg('save')) {
            $this->saveDetails();
        } else {
            // TRANS: Message given submitting a form with an unknown action
            $this->showForm(_m('Unexpected form submission.'));
        }
    }

    function showContent()
    {
        $cur = common_current_user();
        $profile = $cur->getProfile();

        $widget = new ExtendedProfileWidget(
            $this,
            $profile,
            ExtendedProfileWidget::EDITABLE
        );
        $widget->show();
    }

    function saveDetails()
    {
        common_debug(var_export($_POST, true));

        $user = common_current_user();

        try {
            $this->saveStandardProfileDetails($user);

            $profile = $user->getProfile();

            $simpleFieldNames = array('title', 'spouse', 'kids');

            foreach ($simpleFieldNames as $name) {
                $value = $this->trimmed('extprofile-' . $name);
                if (!empty($value)) {
                    $this->saveField($user, $name, $value);
                }
            }

            $this->savePhoneNumbers($user);
            $this->saveExperiences($user);

        } catch (Exception $e) {
            $this->showForm($e->getMessage(), false);
            return;
        }

        $this->showForm(_('Details saved.'), true);

    }

    function savePhoneNumbers($user) {
        $phones = $this->findPhoneNumbers();
        $this->removeAll($user, 'phone');
        $i = 0;
        foreach($phones as $phone) {
            if (!empty($phone['value'])) {
                ++$i;
                $this->saveField(
                    $user,
                    'phone',
                    $phone['value'],
                    $phone['rel'],
                    $i
                );
            }
        }
    }

    function findPhoneNumbers() {

        $phones = $this->sliceParams('phone', 2);
        $phoneTuples = array();

        foreach ($phones as $phone) {
            list($number, $rel) = array_values($phone);
            $phoneTuples[] = array(
                'value' => $number,
                'rel'   => $rel
            );
        }

        return $phoneTuples;
    }

    function sliceParams($key, $size) {
        $slice = array();
        $params = $this->findMultiParams($key);
        ksort($params);
        $slice = $this->arraySplit($params, sizeof($params) / $size);
        return $slice;
    }

    function findExperiences() {

        // Form vals look like this:
        // 'extprofile-experience-0'         => 'Bozotronix',
        // 'extprofile-experience-0-current' => 'true'
        // 'extprofile-experience-0-start'   => '1/5/10',
        // 'extprofile-experience-0-end'     => '2/3/11',

        $experiences = $this->sliceParams('experience', 4);
        $expArray = array();

        foreach ($experiences as $exp) {

            common_debug('Experience: ' . var_export($exp, true));

            list($company, $current, $end, $start) = array_values($exp);

            $startTs = strtotime($start);

            if ($startTs === false) {
                $msg = empty($start) ? _m('You must supply a start date.')
                    : sprintf(_m("Invalid start date: %s"), $start);
                throw new Exception($msg);
            }

            $endTs = strtotime($end);

            if ($current === 'false' && $endTs === false) {
                $msg = empty($end) ? _m('You must supply an end date.')
                    : sprintf(_m("Invalid end date: %s"), $end);
                throw new Exception($msg);
            }

            $expArray[] = array(
                'company' => $company,
                'start'   => common_sql_date($startTs),
                'end'     => common_sql_date($endTs),
                'current' => ($current == 'false') ? false : true
            );
        }

        return $expArray;
    }

    function saveExperiences($user) {
        common_debug('save experiences');
        $experiences = $this->findExperiences();

        $this->removeAll($user, 'company');
        $this->removeAll($user, 'start');
        $this->removeAll($user, 'end'); // also stores 'current'

        $i = 0;
        foreach($experiences as $experience) {
            if (!empty($experience['company'])) {
                ++$i;
                $this->saveField(
                    $user,
                    'company',
                    $experience['company'],
                    null,
                    $i
                );

                $this->saveField(
                    $user,
                    'start',
                    null,
                    null,
                    $i,
                    $experience['start']
                );

                // Save "current" employer indicator in rel
                if ($experience['current']) {
                    $this->saveField(
                        $user,
                        'end',
                        null,
                        'current', // rel
                        $i
                    );
                } else {
                    $this->saveField(
                        $user,
                        'end',
                        null,
                        null,
                        $i,
                        $experience['end']
                    );
                }

            }
        }
    }

    function arraySplit($array, $pieces)
    {
        if ($pieces < 2) {
            return array($array);
        }

        $newCount = ceil(count($array) / $pieces);
        $a = array_slice($array, 0, $newCount);
        $b = $this->arraySplit(array_slice($array, $newCount), $pieces - 1);

        return array_merge(array($a), $b);
    }

    function findMultiParams($type) {
        $formVals = array();
        $target   = $type;
        foreach ($_POST as $key => $val) {
            if (strrpos('extprofile-' . $key, $target) !== false) {
                $formVals[$key] = $val;
            }
        }
        return $formVals;
    }

    /**
     * Save an extended profile field as a Profile_detail
     *
     * @param User   $user    the current user
     * @param string $name    field name
     * @param string $value   field value
     * @param string $rel     field rel (type)
     * @param int    $index   index (fields can have multiple values)
     * @param date   $date    related date
     */
    function saveField($user, $name, $value, $rel = null, $index = null, $date = null)
    {
        $profile = $user->getProfile();
        $detail  = new Profile_detail();

        $detail->profile_id  = $profile->id;
        $detail->field_name  = $name;
        $detail->value_index = $index;

        $result = $detail->find(true);

        if (empty($result)) {
            $detial->value_index = $index;
            $detail->rel         = $rel;
            $detail->field_value = $value;
            $detail->date        = $date;
            $detail->created     = common_sql_now();
            $result = $detail->insert();
            if (empty($result)) {
                common_log_db_error($detail, 'INSERT', __FILE__);
                $this->serverError(_m('Could not save profile details.'));
            }
        } else {
            $orig = clone($detail);

            $detail->field_value = $value;
            $detail->rel         = $rel;
            $detail->date        = $date;

            $result = $detail->update($orig);
            if (empty($result)) {
                common_log_db_error($detail, 'UPDATE', __FILE__);
                $this->serverError(_m('Could not save profile details.'));
            }
        }

        $detail->free();
    }

    function removeAll($user, $name)
    {
        $profile = $user->getProfile();
        $detail  = new Profile_detail();
        $detail->profile_id  = $profile->id;
        $detail->field_name  = $name;
        $detail->delete();
        $detail->free();
    }

    /**
     * Save fields that should be stored in the main profile object
     *
     * XXX: There's a lot of dupe code here from ProfileSettingsAction.
     *      Do not want.
     *
     * @param User $user the current user
     */
    function saveStandardProfileDetails($user)
    {
        $fullname  = $this->trimmed('extprofile-fullname');
        $location  = $this->trimmed('extprofile-location');
        $tagstring = $this->trimmed('extprofile-tags');
        $bio       = $this->trimmed('extprofile-bio');

        if ($tagstring) {
            $tags = array_map(
                'common_canonical_tag',
                preg_split('/[\s,]+/', $tagstring)
            );
        } else {
            $tags = array();
        }

        foreach ($tags as $tag) {
            if (!common_valid_profile_tag($tag)) {
                // TRANS: Validation error in form for profile settings.
                // TRANS: %s is an invalid tag.
                throw new Exception(sprintf(_m('Invalid tag: "%s".'), $tag));
            }
        }

        $profile = $user->getProfile();

        $oldTags = $user->getSelfTags();
        $newTags = array_diff($tags, $oldTags);

        if ($fullname    != $profile->fullname
            || $location != $profile->location
            || !empty($newTags)
            || $bio      != $profile->bio) {

            $orig = clone($profile);

            $profile->nickname = $user->nickname;
            $profile->fullname = $fullname;
            $profile->bio      = $bio;
            $profile->location = $location;

            $loc = Location::fromName($location);

            if (empty($loc)) {
                $profile->lat         = null;
                $profile->lon         = null;
                $profile->location_id = null;
                $profile->location_ns = null;
            } else {
                $profile->lat         = $loc->lat;
                $profile->lon         = $loc->lon;
                $profile->location_id = $loc->location_id;
                $profile->location_ns = $loc->location_ns;
            }

            $profile->profileurl = common_profile_url($user->nickname);

            $result = $profile->update($orig);

            if ($result === false) {
                common_log_db_error($profile, 'UPDATE', __FILE__);
                // TRANS: Server error thrown when user profile settings could not be saved.
                $this->serverError(_('Could not save profile.'));
                return;
            }

            // Set the user tags
            $result = $user->setSelfTags($tags);

            if (!$result) {
                // TRANS: Server error thrown when user profile settings tags could not be saved.
                $this->serverError(_('Could not save tags.'));
                return;
            }

            Event::handle('EndProfileSaveForm', array($this));
            common_broadcast_profile($profile);
        }
    }

}
