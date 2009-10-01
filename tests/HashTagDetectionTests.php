<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('STATUSNET', true);
define('LACONICA', true);

require_once INSTALLDIR . '/lib/common.php';

class HashTagDetectionTests extends PHPUnit_Framework_TestCase
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
                     array('#hello people',
                           '#<span class="tag"><a href="' . common_local_url('tag', array('tag' => common_canonical_tag('hello'))) . '" rel="tag">hello</a></span> people'),
                     array('"#hello" people',
                           '&quot;#<span class="tag"><a href="' . common_local_url('tag', array('tag' => common_canonical_tag('hello'))) . '" rel="tag">hello</a></span>&quot; people'),
                     array('say "#hello" people',
                           'say &quot;#<span class="tag"><a href="' . common_local_url('tag', array('tag' => common_canonical_tag('hello'))) . '" rel="tag">hello</a></span>&quot; people'),
                     array('say (#hello) people',
                           'say (#<span class="tag"><a href="' . common_local_url('tag', array('tag' => common_canonical_tag('hello'))) . '" rel="tag">hello</a></span>) people'),
                     array('say [#hello] people',
                           'say [#<span class="tag"><a href="' . common_local_url('tag', array('tag' => common_canonical_tag('hello'))) . '" rel="tag">hello</a></span>] people'),
                     array('say {#hello} people',
                           'say {#<span class="tag"><a href="' . common_local_url('tag', array('tag' => common_canonical_tag('hello'))) . '" rel="tag">hello</a></span>} people'),
                     array('say \'#hello\' people',
                           'say \'#<span class="tag"><a href="' . common_local_url('tag', array('tag' => common_canonical_tag('hello'))) . '" rel="tag">hello</a></span>\' people'),
                     );
    }
}

