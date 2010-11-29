<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('STATUSNET', true);
define('LACONICA', true);

require_once INSTALLDIR . '/lib/common.php';

/**
 * Test cases for nickname validity and normalization.
 */
class NicknameTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider
     *
     */
    public function testBasic($input, $expected)
    {
        $matches = array();
        // First problem: this is all manual, wtf!
        if (preg_match('/^([' . NICKNAME_FMT . ']{1,64})$/', $input, $matches)) {
            $norm = common_canonical_nickname($matches[1]);
            $this->assertEquals($expected, $norm, "normalized input nickname: $input -> $norm");
        } else {
            $this->assertEquals($expected, false, "invalid input nickname: $input");
        }
    }

    static public function provider()
    {
        return array(
                     array('evan', 'evan'),
                     array('Evan', 'evan'),
                     array('EVAN', 'evan'),
                     array('ev_an', 'evan'),
                     array('ev.an', 'evan'),
                     array('ev/an', false),
                     array('ev an', false),
                     array('ev-an', false),
                     array('évan', false), // so far...
                     array('Évan', false), // so far...
                     array('evan1', 'evan1'),
                     array('evan_1', 'evan1'),
                     );
    }
}
