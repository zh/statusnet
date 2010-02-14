<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

// XXX: we should probably have some common source for this stuff

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));
define('STATUSNET', true);

require_once INSTALLDIR . '/lib/common.php';
require_once INSTALLDIR . '/plugins/OStatus/lib/activity.php';

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
