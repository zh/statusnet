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
     * @dataProvider fileTypeCases
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

    static public function fileTypeCases()
    {
        $files = array(
            'http://www.flickr.com/photos/brionv/5172500179/' => 'photo',
            'http://twitpic.com/36adw6' => 'photo',
            'http://yfrog.com/fy42747177j' => 'photo',
            'http://identi.ca/attachment/34437400' => 'photo',

            'http://www.youtube.com/watch?v=eUgLR232Cnw' => 'video',
            'http://vimeo.com/9283184' => 'video',

            'http://en.wikipedia.org/wiki/File:Wiki.png' => 'link', // @fixme in future there may be a native provider -- will change to 'photo'
            'http://leuksman.com/log/2010/10/29/statusnet-0-9-6-release/' => 'none',
        );

        $dataset = array();
        foreach ($files as $url => $type) {
            $dataset[] = array($url, $type);
        }
        return $dataset;
    }

}

