<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * UAP (Universal Ad Package) plugin
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
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Outputs the following ad types (based on UAP):
 * Medium Rectangle 300x250
 * Rectangle        180x150
 * Leaderboard      728x90
 * Wide Skyscraper  160x600
 *
 * Any number of ad types can be used. Enable all using example:
 * addPlugin('UAP', array(
 *  'MediumRectangle' => '<script type="text/javascript">var foo = 1;</script>',
 *  'Rectangle' => '<script type="text/javascript">var bar = 2;</script>',
 *  'Leaderboard' => '<script type="text/javascript">var baz = 2;</script>',
 *  'WideSkyscraper' => '<script type="text/javascript">var bbq = 4;</script>'
 *  )
 * );
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class UAPPlugin extends Plugin
{
    public $MediumRectangle = null;
    public $Rectangle = null;
    public $Leaderboard = null;
    public $WideSkyscraper = null;

    function __construct($uap = array())
    {
        $this->uap = $uap;

        parent::__construct();
    }

    function onInitializePlugin()
    {
        foreach($this->uap as $key => $value) {
            switch(strtolower($key)) {
                case 'MediumRectangle': default:
                    $this->MediumRectangle = $value;
                    break;
                case 'Rectangle':
                    $this->Rectangle = $value;
                    break;
                case 'Leaderboard':
                    $this->Leaderboard = $value;
                    break;
                case 'WideSkyscraper':
                    $this->WideSkyscraper = $value;
                    break;
            }
        }
    }

    function onEndShowStatusNetStyles($action)
    {
        $action->cssLink(common_path('plugins/UAP/uap.css'),
                         null, 'screen, projection, tv');
        return true;
    }

    //MediumRectangle ad
    function onStartShowAside($action)
    {
        if (!$this->MediumRectangle) {
            return true;
        }

        $this->showAd($action, array('id' => 'ad_medium-rectangle'), 
                               $this->MediumRectangle);

        return true;
    }

/*
    //Rectangle ad
    function onEndShowSiteNotice($action)
    {
        if (!$this->Rectangle) {
            return true;
        }

        $this->showAd($action, array('id' => 'ad_rectangle'), 
                               $this->Rectangle);

        return true;
    }
*/

    //Leaderboard and Rectangle ad
    function onStartShowHeader($action)
    {
        if ($this->Leaderboard) {
            $this->showAd($action, array('id' => 'ad_leaderboard'), 
                                   $this->Leaderboard);
        }

        if ($this->Rectangle) {
            $this->showAd($action, array('id' => 'ad_rectangle'), 
                                   $this->Rectangle);
        }

        return true;
    }

    //WideSkyscraper ad
    function onEndShowAside($action)
    {
        if (!$this->WideSkyscraper) {
            return true;
        }

        $this->showAd($action, array('id' => 'ad_wide-skyscraper'), 
                               $this->WideSkyscraper);

        return true;
    }

    //Output ad container
    function showAd($action, $attr=array(), $value)
    {
        $classes = ($attr['class']) ? $attr['class'].' ' : '';

        $action->elementStart('div', array('id' => $attr['id'],
                                           'class' => $classes.'ad'));
        $action->raw($value);
        $action->elementEnd('div');
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'UAP',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Sarven Capadisli',
                            'homepage' => 'http://status.net/wiki/Plugin:UAP',
                            'rawdescription' =>
                            _m('Outputs ad placements based on Universal Ad Package'));
        return true;
    }
}
