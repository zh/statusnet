<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * List of featured users
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
 * @category  Public
 * @package   Laconica
 * @author    Zach Copley <zach@controlyourself.ca>
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once(INSTALLDIR.'/lib/profilelist.php');
require_once INSTALLDIR.'/lib/publicgroupnav.php';

/**
 * List of featured users
 *
 * @category Public
 * @package  Laconica
 * @author   Zach Copley <zach@controlyourself.ca>
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class FeaturedAction extends Action
{
    var $page = null;

    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            return _('Featured users');
        } else {
            return sprintf(_('Featured users, page %d'), $this->page);
        }
    }

    function handle($args)
    {
        parent::handle($args);

        $this->showPage();
    }

    function showPageNotice()
    {
        $instr = $this->getInstructions();
        $output = common_markup_to_html($instr);
        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    function showLocalNav()
    {
        $nav = new PublicGroupNav($this);
        $nav->show();
    }

    function getInstructions()
    {
        return sprintf(_('A selection of some of the great users on %s'),
                       common_config('site', 'name'));
    }

    function showContent()
    {
        // XXX: Note I'm doing it this two-stage way because a raw query
        // with a JOIN was *not* working. --Zach

        $featured_nicks = common_config('nickname', 'featured');


        if (count($featured_nicks) > 0) {

            $quoted = array();

            foreach ($featured_nicks as $nick) {
                $quoted[] = "'$nick'";
            }

            $user = new User;
            $user->whereAdd(sprintf('nickname IN (%s)', implode(',', $quoted)));
            $user->limit(($this->page - 1) * PROFILES_PER_PAGE, PROFILES_PER_PAGE + 1);
            $user->orderBy(common_database_tablename('user') .'.nickname ASC');

            $user->find();

            $profile_ids = array();

            while ($user->fetch()) {
                $profile_ids[] = $user->id;
            }

            $profile = new Profile;
            $profile->whereAdd(sprintf('profile.id IN (%s)', implode(',', $profile_ids)));
            $profile->orderBy('nickname ASC');

            $cnt = $profile->find();

            if ($cnt > 0) {
                $featured = new ProfileList($profile, null, $this);
                $featured->show();
            }

            $profile->free();

            $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                              $this->page, 'featured');
        }
    }
}
