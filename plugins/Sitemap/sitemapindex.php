<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Generate sitemap index
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
 * Show the sitemap index
 *
 * @category Sitemap
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class SitemapindexAction extends Action
{
    /**
     * handle the action
     *
     * @param array $args unused.
     *
     * @return void
     */

    function handle($args)
    {
        header('Content-Type: text/xml; charset=UTF-8');
        $this->startXML();

        $this->elementStart('sitemapindex', array('xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9'));

        $this->showUserSitemaps();
        $this->showNoticeSitemaps();

        $this->elementEnd('sitemapindex');

        $this->endXML();
    }

    function showUserSitemaps()
    {
        $userCounts = $this->getUserCounts();

        foreach ($userCounts as $dt => $cnt) {
            $cnt = $cnt+0;
            assert($cnt != 0);
            $n = (int)$cnt / (int)SitemapPlugin::USERS_PER_MAP;
            if (($cnt % SitemapPlugin::USERS_PER_MAP) != 0) {
                $n++;
            }
            for ($i = 1; $i <= $n; $i++) {
                $this->showSitemap('user', $dt, $i);
            }
        }
    }

    function showNoticeSitemaps()
    {
        $noticeCounts = $this->getNoticeCounts();

        foreach ($noticeCounts as $dt => $cnt) {
            assert($cnt != 0);
            $n = $cnt / SitemapPlugin::NOTICES_PER_MAP;
            if ($cnt % SitemapPlugin::NOTICES_PER_MAP) {
                $n++;
            }
            for ($i = 1; $i <= $n; $i++) {
                $this->showSitemap('notice', $dt, $i);
            }
        }
    }

    function getUserCounts()
    {
        $userCounts = User::cacheGet('sitemap:user:counts');

        if ($userCounts === false) {

            $user = new User();

            $user->selectAdd();
            $user->selectAdd('date(created) as regdate, count(*) as regcount');
            $user->groupBy('regdate');

            $user->find();

            $userCounts = array();

            while ($user->fetch()) {
                $userCounts[$user->regdate] = $user->regcount;
            }

            User::cacheSet('sitemap:user:counts', $userCounts);
        }

        return $userCounts;
    }

    function getNoticeCounts()
    {
        $noticeCounts = Notice::cacheGet('sitemap:notice:counts');

        if ($noticeCounts === false) {

            $notice = new Notice();

            $notice->selectAdd();
            $notice->selectAdd('date(created) as postdate, count(*) as postcount');
            $notice->groupBy('postdate');

            $notice->find();

            $noticeCounts = array();

            while ($notice->fetch()) {
                $noticeCounts[$notice->postdate] = $notice->postcount;
            }

            Notice::cacheSet('sitemap:notice:counts', $noticeCounts);
        }

        return $noticeCounts;
    }

    function showSitemap($prefix, $dt, $i)
    {
        list($y, $m, $d) = explode('-', $dt);

        $this->elementStart('sitemap');
        $this->element('loc', null, common_local_url($prefix.'sitemap',
                                                     array('year' => $y,
                                                           'month' => $m,
                                                           'day' => $d,
                                                           'index' => $i)));

        $begdate = strtotime("$y-$m-$d 00:00:00");
        $enddate = $begdate + (24 * 60 * 60);

        if ($enddate < time()) {
            $this->element('lastmod', null, date(DATE_W3C, $enddate));
        }

        $this->elementEnd('sitemap');
    }
}
