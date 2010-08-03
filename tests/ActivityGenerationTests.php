<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

// XXX: we should probably have some common source for this stuff

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('STATUSNET', true);

require_once INSTALLDIR . '/lib/common.php';

class ActivityGenerationTests extends PHPUnit_Framework_TestCase
{
    var $author1 = null;
    var $author2 = null;

    var $targetUser1 = null;
    var $targetUser2 = null;

    var $targetGroup1 = null;
    var $targetGroup2 = null;

    public function setUp()
    {
        $authorNick1 = 'activitygenerationtestsuser' . common_good_rand(16);
        $authorNick2 = 'activitygenerationtestsuser' . common_good_rand(16);

        $targetNick1 = 'activitygenerationteststarget' . common_good_rand(16);
        $targetNick2 = 'activitygenerationteststarget' . common_good_rand(16);

        $groupNick1 = 'activitygenerationtestsgroup' . common_good_rand(16);
        $groupNick2 = 'activitygenerationtestsgroup' . common_good_rand(16);

        $this->author1 = User::register(array('nickname' => $authorNick1,
                                              'email' => $authorNick1 . '@example.net',
                                              'email_confirmed' => true));

        $this->author2 = User::register(array('nickname' => $authorNick2,
                                              'email' => $authorNick2 . '@example.net',
                                              'email_confirmed' => true));

        $this->targetUser1 = User::register(array('nickname' => $targetNick1,
                                                  'email' => $targetNick1 . '@example.net',
                                                  'email_confirmed' => true));

        $this->targetUser2 = User::register(array('nickname' => $targetNick2,
                                                  'email' => $targetNick2 . '@example.net',
                                                  'email_confirmed' => true));

    }

    public function testBasicNoticeActivity()
    {
        $notice = $this->_fakeNotice();

        $entry = $notice->asAtomEntry(true);

        echo $entry;

        $element = $this->_entryToElement($entry, false);

        $this->assertEquals($notice->uri, ActivityUtils::childContent($element, 'id'));
        $this->assertEquals($notice->content, ActivityUtils::childContent($element, 'title'));
        $this->assertEquals($notice->rendered, ActivityUtils::childContent($element, 'content'));
        $this->assertEquals(strtotime($notice->created), strtotime(ActivityUtils::childContent($element, 'published')));
        $this->assertEquals(strtotime($notice->created), strtotime(ActivityUtils::childContent($element, 'updated')));
        $this->assertEquals(ActivityVerb::POST, ActivityUtils::childContent($element, 'verb', Activity::SPEC));
        $this->assertEquals(ActivityObject::NOTE, ActivityUtils::childContent($element, 'object-type', Activity::SPEC));
    }

    public function testNamespaceFlag()
    {
        $notice = $this->_fakeNotice();

        $entry = $notice->asAtomEntry(true);

        $element = $this->_entryToElement($entry, false);

        $this->assertTrue($element->hasAttribute('xmlns'));
        $this->assertTrue($element->hasAttribute('xmlns:thr'));
        $this->assertTrue($element->hasAttribute('xmlns:georss'));
        $this->assertTrue($element->hasAttribute('xmlns:activity'));
        $this->assertTrue($element->hasAttribute('xmlns:media'));
        $this->assertTrue($element->hasAttribute('xmlns:poco'));
        $this->assertTrue($element->hasAttribute('xmlns:ostatus'));
        $this->assertTrue($element->hasAttribute('xmlns:statusnet'));

        $entry = $notice->asAtomEntry(false);

        $element = $this->_entryToElement($entry, true);

        $this->assertFalse($element->hasAttribute('xmlns'));
        $this->assertFalse($element->hasAttribute('xmlns:thr'));
        $this->assertFalse($element->hasAttribute('xmlns:georss'));
        $this->assertFalse($element->hasAttribute('xmlns:activity'));
        $this->assertFalse($element->hasAttribute('xmlns:media'));
        $this->assertFalse($element->hasAttribute('xmlns:poco'));
        $this->assertFalse($element->hasAttribute('xmlns:ostatus'));
        $this->assertFalse($element->hasAttribute('xmlns:statusnet'));
    }

    public function testReplyActivity()
    {
        $this->assertTrue(FALSE);
    }

    public function testMultipleReplyActivity()
    {
        $this->assertTrue(FALSE);
    }

    public function testGroupPostActivity()
    {
        $this->assertTrue(FALSE);
    }

    public function testMultipleGroupPostActivity()
    {
        $this->assertTrue(FALSE);
    }

    public function testRepeatActivity()
    {
        $this->assertTrue(FALSE);
    }

    public function testTaggedActivity()
    {
        $this->assertTrue(FALSE);
    }

    public function testGeotaggedActivity()
    {
        $this->assertTrue(FALSE);
    }

    public function tearDown()
    {
        $this->author1->delete();
        $this->author2->delete();
        $this->targetUser1->delete();
        $this->targetUser2->delete();
    }

    private function _fakeNotice($user = null, $text = null)
    {
        if (empty($user)) {
            $user = $this->author1;
        }

        if (empty($text)) {
            $text = "fake-o text-o " . common_good_rand(32);
        }

        return Notice::saveNew($user->id, $text, 'test', array('uri' => null));
    }

    private function _entryToElement($entry, $namespace = false)
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>'."\n\n";
        $xml .= '<feed';
        if ($namespace) {
            $xml .= ' xmlns="http://www.w3.org/2005/Atom"';
            $xml .= ' xmlns:thr="http://purl.org/syndication/thread/1.0"';
            $xml .= ' xmlns:georss="http://www.georss.org/georss"';
            $xml .= ' xmlns:activity="http://activitystrea.ms/spec/1.0/"';
            $xml .= ' xmlns:media="http://purl.org/syndication/atommedia"';
            $xml .= ' xmlns:poco="http://portablecontacts.net/spec/1.0"';
            $xml .= ' xmlns:ostatus="http://ostatus.org/schema/1.0"';
            $xml .= ' xmlns:statusnet="http://status.net/schema/api/1/"';
        }
        $xml .= '>' . "\n" . $entry . "\n" . '</feed>' . "\n";
        $doc = DOMDocument::loadXML($xml);
        $feed = $doc->documentElement;
        $entries = $feed->getElementsByTagName('entry');

        return $entries->item(0);
    }
}
