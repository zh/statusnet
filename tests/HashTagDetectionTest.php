<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once INSTALLDIR . '/lib/common.php';

class HashTagDetectionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider
     *
     */
    public function testProduction($content, $expected)
    {
        $rendered = common_render_text($content);
        $this->assertEquals($expected, $rendered);
    }

    static public function provider()
    {
        return array(
                     array('hello',
                           'hello'),
                     array('#hello',
                           '<a href="/tag/hello">hello</a>'),
                     );
    }
}

