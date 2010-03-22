<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show list of user pages
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
 * @category  Sitemap
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * sitemap for users
 *
 * @category Sitemap
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class NoticesitemapAction extends SitemapAction
{
    const NOTICES_PER_MAP = 25000;

    var $notice = null;

    function prepare($args)
    {
        parent::prepare($args);

        $y = $this->trimmed('year');

        $m = $this->trimmed('month');
        $d = $this->trimmed('day');

        $i = $this->trimmed('index');

        $y += 0;
        $m += 0;
        $d += 0;
        $i += 0;

        $offset = ($i-1) * self::NOTICES_PER_MAP;
        $limit  = self::NOTICES_PER_MAP;

        $this->notice = new Notice();

        $this->notice->whereAdd("created > '$y-$m-$d 00:00:00'");
        $this->notice->whereAdd("created <= '$y-$m-$d 11:59:59'");
        $this->notice->whereAdd('is_local = 1');

        $this->notice->orderBy('id');
        $this->notice->limit($offset, $limit);

        $this->notice->find();

        return true;
    }

    function nextUrl()
    {
        if ($this->notice->fetch()) {
            return array(common_local_url('shownotice', array('notice' => $this->notice->id)),
                         common_date_w3dtf($this->notice->created),
                         null,
                         null);
        } else {
            return null;
        }
    }
}
