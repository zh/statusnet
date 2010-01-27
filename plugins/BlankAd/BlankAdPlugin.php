<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin for testing ad layout
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
 * @category  Ads
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin for testing ad layout
 *
 * This plugin uses the UAPPlugin framework to output ad content. However,
 * its ad content is just paragraphs with defined background colors. It's
 * mostly useful for debugging theme layout.
 *
 * To use this plugin, set the parameter for the ad size you want to use
 * to the background you want to use. For example, to make a leaderboard
 * that's red:
 *
 *     addPlugin('BlankAd', array('leaderboard' => 'red'));
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @seeAlso  Location
 */

class BlankAdPlugin extends UAPPlugin
{
    /**
     * Show a medium rectangle 'ad'
     *
     * @param Action $action Action being shown
     *
     * @return void
     */

    protected function showMediumRectangle($action)
    {
        $style = 'width: 300px; height: 250px; background-color: ' .
          $this->mediumRectangle;

        $action->element('p', array('style' => $style), '');
    }

    /**
     * Show a rectangle 'ad'
     *
     * @param Action $action Action being shown
     *
     * @return void
     */

    protected function showRectangle($action)
    {
        $style = 'width: 180px; height: 150px; background-color: ' .
          $this->rectangle;

        $action->element('p', array('style' => $style), '');
    }

    /**
     * Show a wide skyscraper ad
     *
     * @param Action $action Action being shown
     *
     * @return void
     */

    protected function showWideSkyscraper($action)
    {
        $style = 'width: 160px; height: 600px; background-color: ' .
          $this->wideSkyscraper;

        $action->element('p', array('style' => $style), '');
    }

    /**
     * Show a leaderboard ad
     *
     * @param Action $action Action being shown
     *
     * @return void
     */

    protected function showLeaderboard($action)
    {
        $style = 'width: 728px; height: 90px; background-color: ' .
          $this->leaderboard;

        $action->element('p', array('style' => $style), '');
    }
}