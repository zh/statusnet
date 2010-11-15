<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('STATUSNET', true);

require_once INSTALLDIR . '/lib/common.php';

class oEmbedTest extends PHPUnit_Framework_TestCase
{

    public function setup()
    {
        //$this->old_oohembed = common_config('oohembed', 'endpoint');
    }

    public function tearDown()
    {
        //$GLOBALS['config']['attachments']['supported'] = $this->old_attachments_supported;
    }

    /**
     * @dataProvider fallbackSources
     *
     */
    public function testoEmbed($url, $expectedType)
    {
        try {
            $data = oEmbedHelper::getObject($url);
            $this->assertEquals($expectedType, $data->type);
        } catch (Exception $e) {
            if ($expectedType == 'none') {
                $this->assertEquals($expectedType, 'none', 'Should not have data for this URL.');
            } else {
                throw $e;
            }
        }
    }

    /**
     * Sample oEmbed targets for sites we know ourselves...
     * @return array
     */
    static public function knownSources()
    {
        $sources = array(
            array('http://www.flickr.com/photos/brionv/5172500179/', 'photo'),
            array('http://yfrog.com/fy42747177j', 'photo'),
            array('http://twitpic.com/36adw6', 'photo'),
        );
        return $sources;
    }

    /**
     * Sample oEmbed targets that can be found via discovery.
     * Includes also knownSources() output.
     *
     * @return array
     */
    static public function discoverableSources()
    {
        $sources = array(
            array('http://identi.ca/attachment/34437400', 'photo'),

            array('http://www.youtube.com/watch?v=eUgLR232Cnw', 'video'),
            array('http://vimeo.com/9283184', 'video'),

            // Will fail discovery:
            array('http://leuksman.com/log/2010/10/29/statusnet-0-9-6-release/', 'none'),
        );
        return array_merge(self::knownSources(), $sources);
    }

    /**
     * Sample oEmbed targets that can be found via oohembed.com.
     * Includes also discoverableSources() output.
     *
     * @return array
     */
    static public function fallbackSources()
    {
        $sources = array(
            array('http://en.wikipedia.org/wiki/File:Wiki.png', 'link'), // @fixme in future there may be a native provider -- will change to 'photo'
        );
        return array_merge(self::discoverableSources(), $sources);
    }
}
