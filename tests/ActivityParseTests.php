<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

// XXX: we should probably have some common source for this stuff

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('STATUSNET', true);

require_once INSTALLDIR . '/lib/common.php';

class ActivityParseTests extends PHPUnit_Framework_TestCase
{
    public function testExample1()
    {
        global $_example1;
        $dom = DOMDocument::loadXML($_example1);
        $act = new Activity($dom->documentElement);

        $this->assertFalse(empty($act));

        $this->assertEquals($act->time, 1243860840);
        $this->assertEquals($act->verb, ActivityVerb::POST);

        $this->assertFalse(empty($act->object));
        $this->assertEquals($act->object->title, 'Punctuation Changeset');
        $this->assertEquals($act->object->type, 'http://versioncentral.example.org/activity/changeset');
        $this->assertEquals($act->object->summary, 'Fixing punctuation because it makes it more readable.');
        $this->assertEquals($act->object->id, 'tag:versioncentral.example.org,2009:/change/1643245');
    }

    public function testExample3()
    {
        global $_example3;
        $dom = DOMDocument::loadXML($_example3);

        $feed = $dom->documentElement;

        $entries = $feed->getElementsByTagName('entry');

        $entry = $entries->item(0);

        $act = new Activity($entry, $feed);

        $this->assertFalse(empty($act));
        $this->assertEquals($act->time, 1071340202);
        $this->assertEquals($act->link, 'http://example.org/2003/12/13/atom03.html');

        $this->assertEquals($act->verb, ActivityVerb::POST);

        $this->assertFalse(empty($act->actor));
        $this->assertEquals($act->actor->type, ActivityObject::PERSON);
        $this->assertEquals($act->actor->title, 'John Doe');
        $this->assertEquals($act->actor->id, 'mailto:johndoe@example.com');

        $this->assertFalse(empty($act->object));
        $this->assertEquals($act->object->type, ActivityObject::NOTE);
        $this->assertEquals($act->object->id, 'urn:uuid:1225c695-cfb8-4ebb-aaaa-80da344efa6a');
        $this->assertEquals($act->object->title, 'Atom-Powered Robots Run Amok');
        $this->assertEquals($act->object->summary, 'Some text.');
        $this->assertEquals($act->object->link, 'http://example.org/2003/12/13/atom03.html');

        $this->assertFalse(empty($act->context));

        $this->assertTrue(empty($act->target));

        $this->assertEquals($act->entry, $entry);
        $this->assertEquals($act->feed, $feed);
    }

    public function testExample4()
    {
        global $_example4;
        $dom = DOMDocument::loadXML($_example4);

        $entry = $dom->documentElement;

        $act = new Activity($entry);

        $this->assertFalse(empty($act));
        $this->assertEquals(1266547958, $act->time);
        $this->assertEquals('http://example.net/notice/14', $act->link);

        $this->assertFalse(empty($act->context));
        $this->assertEquals('http://example.net/notice/12', $act->context->replyToID);
        $this->assertEquals('http://example.net/notice/12', $act->context->replyToUrl);
        $this->assertEquals('http://example.net/conversation/11', $act->context->conversation);
        $this->assertEquals(array('http://example.net/user/1'), $act->context->attention);

        $this->assertFalse(empty($act->object));
        $this->assertEquals($act->object->content,
                            '@<span class="vcard"><a href="http://example.net/user/1" class="url"><span class="fn nickname">evan</span></a></span> now is the time for all good men to come to the aid of their country. #<span class="tag"><a href="http://example.net/tag/thetime" rel="tag">thetime</a></span>');

        $this->assertFalse(empty($act->actor));
    }

    public function testExample5()
    {
        global $_example5;
        $dom = DOMDocument::loadXML($_example5);

        $feed = $dom->documentElement;

        // @todo Test feed elements

        $entries = $feed->getElementsByTagName('entry');
        $entry = $entries->item(0);

        $act = new Activity($entry, $feed);

        // Post
        $this->assertEquals($act->verb, ActivityVerb::POST);
        $this->assertFalse(empty($act->context));

        // Actor w/Portable Contacts stuff
        $this->assertFalse(empty($act->actor));
        $this->assertEquals($act->actor->type, ActivityObject::PERSON);
        $this->assertEquals($act->actor->title, 'Test User');
        $this->assertEquals($act->actor->id, 'http://example.net/mysite/user/3');
        $this->assertEquals($act->actor->link, 'http://example.net/mysite/testuser');
        $this->assertEquals(
            $act->actor->avatar,
            'http://example.net/mysite/avatar/3-96-20100224004207.jpeg'
        );
        $this->assertEquals($act->actor->displayName, 'Test User');

        $poco = $act->actor->poco;
        $this->assertEquals($poco->preferredUsername, 'testuser');
        $this->assertEquals($poco->address->formatted, 'San Francisco, CA');
        $this->assertEquals($poco->urls[0]->type, 'homepage');
        $this->assertEquals($poco->urls[0]->value, 'http://example.com/blog.html');
        $this->assertEquals($poco->urls[0]->primary, 'true');
    }

}

$_example1 = <<<EXAMPLE1
<?xml version='1.0' encoding='UTF-8'?>
<entry xmlns='http://www.w3.org/2005/Atom' xmlns:activity='http://activitystrea.ms/spec/1.0/'>
  <id>tag:versioncentral.example.org,2009:/commit/1643245</id>
  <published>2009-06-01T12:54:00Z</published>
  <title>Geraldine committed a change to yate</title>
  <content type="xhtml">Geraldine just committed a change to yate on VersionCentral</content>
  <link rel="alternate" type="text/html"
        href="http://versioncentral.example.org/geraldine/yate/commit/1643245" />
  <activity:verb>http://activitystrea.ms/schema/1.0/post</activity:verb>
  <activity:verb>http://versioncentral.example.org/activity/commit</activity:verb>
  <activity:object>
    <activity:object-type>http://versioncentral.example.org/activity/changeset</activity:object-type>
    <id>tag:versioncentral.example.org,2009:/change/1643245</id>
    <title>Punctuation Changeset</title>
    <summary>Fixing punctuation because it makes it more readable.</summary>
    <link rel="alternate" type="text/html" href="..." />
  </activity:object>
</entry>
EXAMPLE1;

$_example2 = <<<EXAMPLE2
<?xml version='1.0' encoding='UTF-8'?>
<entry xmlns='http://www.w3.org/2005/Atom' xmlns:activity='http://activitystrea.ms/spec/1.0/'>
  <id>tag:photopanic.example.com,2008:activity01</id>
  <title>Geraldine posted a Photo on PhotoPanic</title>
  <published>2008-11-02T15:29:00Z</published>
  <link rel="alternate" type="text/html" href="/geraldine/activities/1" />
  <activity:verb>
  http://activitystrea.ms/schema/1.0/post
  </activity:verb>
  <activity:object>
    <id>tag:photopanic.example.com,2008:photo01</id>
    <title>My Cat</title>
    <published>2008-11-02T15:29:00Z</published>
    <link rel="alternate" type="text/html" href="/geraldine/photos/1" />
    <activity:object-type>
      tag:atomactivity.example.com,2008:photo
    </activity:object-type>
    <source>
      <title>Geraldine's Photos</title>
      <link rel="self" type="application/atom+xml" href="/geraldine/photofeed.xml" />
      <link rel="alternate" type="text/html" href="/geraldine/" />
    </source>
  </activity:object>
  <content type="html">
     &lt;p&gt;Geraldine posted a Photo on PhotoPanic&lt;/p&gt;
     &lt;img src="/geraldine/photo1.jpg"&gt;
  </content>
</entry>
EXAMPLE2;

$_example3 = <<<EXAMPLE3
<?xml version="1.0" encoding="utf-8"?>

<feed xmlns="http://www.w3.org/2005/Atom">

    <title>Example Feed</title>
    <subtitle>A subtitle.</subtitle>
    <link href="http://example.org/feed/" rel="self" />
    <link href="http://example.org/" />
    <id>urn:uuid:60a76c80-d399-11d9-b91C-0003939e0af6</id>
    <updated>2003-12-13T18:30:02Z</updated>
    <author>
        <name>John Doe</name>
        <email>johndoe@example.com</email>
    </author>

    <entry>
        <title>Atom-Powered Robots Run Amok</title>
        <link href="http://example.org/2003/12/13/atom03" />
        <link rel="alternate" type="text/html" href="http://example.org/2003/12/13/atom03.html"/>
        <link rel="edit" href="http://example.org/2003/12/13/atom03/edit"/>
        <id>urn:uuid:1225c695-cfb8-4ebb-aaaa-80da344efa6a</id>
        <updated>2003-12-13T18:30:02Z</updated>
        <summary>Some text.</summary>
    </entry>

</feed>
EXAMPLE3;

$_example4 = <<<EXAMPLE4
<?xml version='1.0' encoding='UTF-8'?>
<entry xmlns="http://www.w3.org/2005/Atom" xmlns:thr="http://purl.org/syndication/thread/1.0" xmlns:georss="http://www.georss.org/georss" xmlns:activity="http://activitystrea.ms/spec/1.0/" xmlns:ostatus="http://ostatus.org/schema/1.0">
 <title>@evan now is the time for all good men to come to the aid of their country. #thetime</title>
 <summary>@evan now is the time for all good men to come to the aid of their country. #thetime</summary>
<author>
 <name>spock</name>
 <uri>http://example.net/user/2</uri>
</author>
<activity:actor>
 <activity:object-type>http://activitystrea.ms/schema/1.0/person</activity:object-type>
 <id>http://example.net/user/2</id>
 <title>spock</title>
 <link type="image/png" rel="avatar" href="http://example.net/theme/identica/default-avatar-profile.png"></link>
</activity:actor>
 <link rel="alternate" type="text/html" href="http://example.net/notice/14"/>
 <id>http://example.net/notice/14</id>
 <published>2010-02-19T02:52:38+00:00</published>
 <updated>2010-02-19T02:52:38+00:00</updated>
 <link rel="related" href="http://example.net/notice/12"/>
 <thr:in-reply-to ref="http://example.net/notice/12" href="http://example.net/notice/12"></thr:in-reply-to>
 <link rel="ostatus:conversation" href="http://example.net/conversation/11"/>
 <link rel="ostatus:attention" href="http://example.net/user/1"/>
 <content type="html">@&lt;span class=&quot;vcard&quot;&gt;&lt;a href=&quot;http://example.net/user/1&quot; class=&quot;url&quot;&gt;&lt;span class=&quot;fn nickname&quot;&gt;evan&lt;/span&gt;&lt;/a&gt;&lt;/span&gt; now is the time for all good men to come to the aid of their country. #&lt;span class=&quot;tag&quot;&gt;&lt;a href=&quot;http://example.net/tag/thetime&quot; rel=&quot;tag&quot;&gt;thetime&lt;/a&gt;&lt;/span&gt;</content>
 <category term="thetime"></category>
</entry>
EXAMPLE4;

$_example5 = <<<EXAMPLE5
<?xml version="1.0" encoding="UTF-8"?>
<feed xml:lang="en-US" xmlns="http://www.w3.org/2005/Atom" xmlns:thr="http://purl.org/syndication/thread/1.0" xmlns:georss="http://www.georss.org/georss" xmlns:activity="http://activitystrea.ms/spec/1.0/" xmlns:poco="http://portablecontacts.net/spec/1.0" xmlns:ostatus="http://ostatus.org/schema/1.0">
 <id>3</id>
 <title>testuser timeline</title>
 <subtitle>Updates from testuser on Zach Dev!</subtitle>
 <logo>http://example.net/mysite/avatar/3-96-20100224004207.jpeg</logo>
 <updated>2010-02-24T06:38:49+00:00</updated>
<author>
 <name>testuser</name>
 <uri>http://example.net/mysite/user/3</uri>

</author>
 <link href="http://example.net/mysite/testuser" rel="alternate" type="text/html"/>
 <link href="http://example.net/mysite/api/statuses/user_timeline/3.atom" rel="self" type="application/atom+xml"/>
 <link href="http://example.net/mysite/main/sup#3" rel="http://api.friendfeed.com/2008/03#sup" type="application/json"/>
 <link href="http://example.net/mysite/main/push/hub" rel="hub"/>
 <link href="http://example.net/mysite/main/salmon/user/3" rel="salmon"/>
<activity:subject>
 <activity:object-type>http://activitystrea.ms/schema/1.0/person</activity:object-type>
 <id>http://example.net/mysite/user/3</id>
 <title>Test User</title>
 <link rel="alternate" type="text/html" href="http://example.net/mysite/testuser"/>
 <link type="image/jpeg" rel="avatar" href="http://example.net/mysite/avatar/3-96-20100224004207.jpeg"/>
 <georss:point>37.7749295 -122.4194155</georss:point>

<poco:preferredUsername>testuser</poco:preferredUsername>
<poco:displayName>Test User</poco:displayName>
<poco:note>Just another test user.</poco:note>
<poco:address>
 <poco:formatted>San Francisco, CA</poco:formatted>
</poco:address>
<poco:urls>
 <poco:type>homepage</poco:type>
 <poco:value>http://example.com/blog.html</poco:value>
 <poco:primary>true</poco:primary>

</poco:urls>
</activity:subject>
<entry>
 <title>Hey man, is that Freedom Code?! #freedom #hippy</title>
 <summary>Hey man, is that Freedom Code?! #freedom #hippy</summary>
<author>
 <name>testuser</name>
 <uri>http://example.net/mysite/user/3</uri>
</author>
<activity:actor>
 <activity:object-type>http://activitystrea.ms/schema/1.0/person</activity:object-type>
 <id>http://example.net/mysite/user/3</id>
 <title>Test User</title>
 <link rel="alternate" type="text/html" href="http://example.net/mysite/testuser"/>
 <link type="image/jpeg" rel="avatar" href="http://example.net/mysite/avatar/3-96-20100224004207.jpeg"/>
 <georss:point>37.7749295 -122.4194155</georss:point>

<poco:preferredUsername>testuser</poco:preferredUsername>
<poco:displayName>Test User</poco:displayName>
<poco:note>Just another test user.</poco:note>
<poco:address>
 <poco:formatted>San Francisco, CA</poco:formatted>
</poco:address>
<poco:urls>
 <poco:type>homepage</poco:type>
 <poco:value>http://example.com/blog.html</poco:value>
 <poco:primary>true</poco:primary>

</poco:urls>
</activity:actor>
 <link rel="alternate" type="text/html" href="http://example.net/mysite/notice/7"/>
 <id>http://example.net/mysite/notice/7</id>
 <published>2010-02-24T00:53:06+00:00</published>
 <updated>2010-02-24T00:53:06+00:00</updated>
 <link rel="ostatus:conversation" href="http://example.net/mysite/conversation/7"/>
 <content type="html">Hey man, is that Freedom Code?! #&lt;span class=&quot;tag&quot;&gt;&lt;a href=&quot;http://example.net/mysite/tag/freedom&quot; rel=&quot;tag&quot;&gt;freedom&lt;/a&gt;&lt;/span&gt; #&lt;span class=&quot;tag&quot;&gt;&lt;a href=&quot;http://example.net/mysite/tag/hippy&quot; rel=&quot;tag&quot;&gt;hippy&lt;/a&gt;&lt;/span&gt;</content>
 <georss:point>37.8313160 -122.2852473</georss:point>

</entry>
</feed>
EXAMPLE5;
