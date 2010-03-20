<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * An activity
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
 * @category  Feed
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class PoCoURL
{
    const URLS      = 'urls';
    const TYPE      = 'type';
    const VALUE     = 'value';
    const PRIMARY   = 'primary';

    public $type;
    public $value;
    public $primary;

    function __construct($type, $value, $primary = false)
    {
        $this->type    = $type;
        $this->value   = $value;
        $this->primary = $primary;
    }

    function asString()
    {
        $xs = new XMLStringer(true);
        $xs->elementStart('poco:urls');
        $xs->element('poco:type', null, $this->type);
        $xs->element('poco:value', null, $this->value);
        if (!empty($this->primary)) {
            $xs->element('poco:primary', null, 'true');
        }
        $xs->elementEnd('poco:urls');
        return $xs->getString();
    }
}
