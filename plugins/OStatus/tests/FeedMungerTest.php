<?php

if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../../..'));
define('STATUSNET', true);
define('LACONICA', true);

require_once INSTALLDIR . '/lib/common.php';
require_once INSTALLDIR . '/plugins/FeedSub/feedsub.php';

require_once 'XML/Feed/Parser.php';

class FeedMungerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider profileProvider
     *
     */
    public function testProfiles($xml, $expected)
    {
        $feed = new XML_Feed_Parser($xml, false, false, true);
        
        $munger = new FeedMunger($feed);
        $profile = $munger->profile();

        foreach ($expected as $field => $val) {
            $this->assertEquals($expected[$field], $profile->$field, "profile->$field");
        }
    }

    static public function profileProvider()
    {
        return array(
                     array(self::samplefeed(),
                           array('nickname' => 'leŭksman', // @todo does this need to be asciified?
                                 'fullname' => 'leŭksman',
                                 'bio' => 'reticula, electronica, & oddities',
                                 'homepage' => 'http://leuksman.com/log')));
    }

    /**
     * @dataProvider noticeProvider
     *
     */
    public function testNotices($xml, $entryIndex, $expected)
    {
        $feed = new XML_Feed_Parser($xml, false, false, true);
        $entry = $feed->getEntryByOffset($entryIndex);

        $munger = new FeedMunger($feed);
        $notice = $munger->noticeFromEntry($entry);

        $this->assertTrue(mb_strlen($notice) <= Notice::maxContent());
        $this->assertEquals($expected, $notice);
    }

    static public function noticeProvider()
    {
        return array(
                     array('<rss version="2.0"><channel><item><title>A fairly short title</title><link>http://example.com/short/link</link></item></channel></rss>', 0,
                           'New post: "A fairly short title" http://example.com/short/link'),
                     // Requires URL shortening ...
                     array('<rss version="2.0"><channel><item><title>A fairly short title</title><link>http://example.com/but/a/very/long/link/indeed/this/is/far/too/long/for/mere/humans/to/comprehend/oh/my/gosh</link></item></channel></rss>', 0,
                           'New post: "A fairly short title" http://ur1.ca/g2o1'),
                     array('<rss version="2.0"><channel><item><title>A fairly long title in this case, which will have to get cut down at some point alongside its very long link. Really who even makes titles this long? It\'s just ridiculous imo...</title><link>http://example.com/but/a/very/long/link/indeed/this/is/far/too/long/for/mere/humans/to/comprehend/oh/my/gosh</link></item></channel></rss>', 0,
                           'New post: "A fairly long title in this case, which will have to get cut down at some point alongside its very long li…" http://ur1.ca/g2o1'),
                     // Some real sample feeds
                     array(self::samplefeed(), 0,
                           'New post: "Compiling PHP on Snow Leopard" http://leuksman.com/log/2009/11/12/compiling-php-on-snow-leopard/'),
                     array(self::samplefeedBlogspot(), 0,
                           'New post: "I love posting" http://briontest.blogspot.com/2009/11/i-love-posting.html'),
                     array(self::samplefeedBlogspot(), 1,
                           'New post: "Hey dude" http://briontest.blogspot.com/2009/11/hey-dude.html'),
        );
    }

    static protected function samplefeed()
    {
        $xml = '<' . '?xml version="1.0" encoding="UTF-8"?' . ">\n";
        $samplefeed = $xml . <<<END
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	>

<channel>
	<title>leŭksman</title>
	<atom:link href="http://leuksman.com/log/feed/" rel="self" type="application/rss+xml" />
	<link>http://leuksman.com/log</link>
	<description>reticula, electronica, &#38; oddities</description>

	<lastBuildDate>Thu, 12 Nov 2009 17:44:42 +0000</lastBuildDate>
	<generator>http://wordpress.org/?v=2.8.6</generator>
	<language>en</language>
	<sy:updatePeriod>hourly</sy:updatePeriod>
	<sy:updateFrequency>1</sy:updateFrequency>
			<item>

		<title>Compiling PHP on Snow Leopard</title>
		<link>http://leuksman.com/log/2009/11/12/compiling-php-on-snow-leopard/</link>
		<comments>http://leuksman.com/log/2009/11/12/compiling-php-on-snow-leopard/#comments</comments>
		<pubDate>Thu, 12 Nov 2009 17:44:42 +0000</pubDate>
		<dc:creator>brion</dc:creator>
				<category><![CDATA[apple]]></category>

		<category><![CDATA[devel]]></category>

		<guid isPermaLink="false">http://leuksman.com/log/?p=649</guid>
		<description><![CDATA[If you&#8217;ve been having trouble compiling your own PHP installations on Mac OS X 10.6, here&#8217;s the secret to making it not suck! After running the configure script, edit the generated Makefile and make these fixes:

Find the EXTRA_LIBS definition and add -lresolv to the end
Find the EXE_EXT definition and remove .dSYM

Standard make and make install [...]]]></description>
			<content:encoded><![CDATA[<p>If you&#8217;ve been having trouble compiling your own PHP installations on Mac OS X 10.6, here&#8217;s the secret to making it not suck! After running the configure script, edit the generated Makefile and make these fixes:</p>
<ul>
<li>Find the <strong>EXTRA_LIBS</strong> definition and add <strong>-lresolv</strong> to the end</li>
<li>Find the <strong>EXE_EXT</strong> definition and remove <strong>.dSYM</strong></li>
</ul>
<p>Standard make and make install should work from here&#8230;</p>
<p>For reference, here&#8217;s the whole configure line I currently use; MySQL is installed from the downloadable installer; other deps from MacPorts:</p>
<p>&#8216;./configure&#8217; &#8216;&#8211;prefix=/opt/php52&#8242; &#8216;&#8211;with-mysql=/usr/local/mysql&#8217; &#8216;&#8211;with-zlib&#8217; &#8216;&#8211;with-bz2&#8242; &#8216;&#8211;enable-mbstring&#8217; &#8216;&#8211;enable-exif&#8217; &#8216;&#8211;enable-fastcgi&#8217; &#8216;&#8211;with-xmlrpc&#8217; &#8216;&#8211;with-xsl&#8217; &#8216;&#8211;with-readline=/opt/local&#8217; &#8211;without-iconv &#8211;with-gd &#8211;with-png-dir=/opt/local &#8211;with-jpeg-dir=/opt/local &#8211;with-curl &#8211;with-gettext=/opt/local &#8211;with-mysqli=/usr/local/mysql/bin/mysql_config &#8211;with-tidy=/opt/local &#8211;enable-pcntl &#8211;with-openssl</p>
]]></content:encoded>
			<wfw:commentRss>http://leuksman.com/log/2009/11/12/compiling-php-on-snow-leopard/feed/</wfw:commentRss>
		<slash:comments>0</slash:comments>
		</item>
</channel>
</rss>
END;
        return $samplefeed;
    }
    
    static protected function samplefeedBlogspot()
    {
        return <<<END
<?xml version='1.0' encoding='UTF-8'?><?xml-stylesheet href="http://www.blogger.com/styles/atom.css" type="text/css"?><feed xmlns='http://www.w3.org/2005/Atom' xmlns:openSearch='http://a9.com/-/spec/opensearchrss/1.0/' xmlns:georss='http://www.georss.org/georss'><id>tag:blogger.com,1999:blog-7780083508531697167</id><updated>2009-11-19T12:56:11.233-08:00</updated><title type='text'>Brion's Cool Test Blog</title><subtitle type='html'></subtitle><link rel='http://schemas.google.com/g/2005#feed' type='application/atom+xml' href='http://briontest.blogspot.com/feeds/posts/default'/><link rel='self' type='application/atom+xml' href='http://www.blogger.com/feeds/7780083508531697167/posts/default'/><link rel='alternate' type='text/html' href='http://briontest.blogspot.com/'/><link rel='hub' href='http://pubsubhubbub.appspot.com/'/><author><name>brion</name><uri>http://www.blogger.com/profile/12932299467049762017</uri><email>noreply@blogger.com</email></author><generator version='7.00' uri='http://www.blogger.com'>Blogger</generator><openSearch:totalResults>2</openSearch:totalResults><openSearch:startIndex>1</openSearch:startIndex><openSearch:itemsPerPage>25</openSearch:itemsPerPage><entry><id>tag:blogger.com,1999:blog-7780083508531697167.post-8456671879000290677</id><published>2009-11-19T12:55:00.000-08:00</published><updated>2009-11-19T12:56:11.241-08:00</updated><title type='text'>I love posting</title><content type='html'>It's pretty awesome, if you like that sort of thing.&lt;div class="blogger-post-footer"&gt;&lt;img width='1' height='1' src='https://blogger.googleusercontent.com/tracker/7780083508531697167-8456671879000290677?l=briontest.blogspot.com' alt='' /&gt;&lt;/div&gt;</content><link rel='replies' type='application/atom+xml' href='http://briontest.blogspot.com/feeds/8456671879000290677/comments/default' title='Post Comments'/><link rel='replies' type='text/html' href='http://briontest.blogspot.com/2009/11/i-love-posting.html#comment-form' title='0 Comments'/><link rel='edit' type='application/atom+xml' href='http://www.blogger.com/feeds/7780083508531697167/posts/default/8456671879000290677'/><link rel='self' type='application/atom+xml' href='http://www.blogger.com/feeds/7780083508531697167/posts/default/8456671879000290677'/><link rel='alternate' type='text/html' href='http://briontest.blogspot.com/2009/11/i-love-posting.html' title='I love posting'/><author><name>brion</name><uri>http://www.blogger.com/profile/12932299467049762017</uri><email>noreply@blogger.com</email><gd:extendedProperty xmlns:gd='http://schemas.google.com/g/2005' name='OpenSocialUserId' value='05912464053145602436'/></author><thr:total xmlns:thr='http://purl.org/syndication/thread/1.0'>0</thr:total></entry><entry><id>tag:blogger.com,1999:blog-7780083508531697167.post-8202296917897346633</id><published>2009-11-18T13:52:00.001-08:00</published><updated>2009-11-18T13:52:48.444-08:00</updated><title type='text'>Hey dude</title><content type='html'>testingggggggggg&lt;div class="blogger-post-footer"&gt;&lt;img width='1' height='1' src='https://blogger.googleusercontent.com/tracker/7780083508531697167-8202296917897346633?l=briontest.blogspot.com' alt='' /&gt;&lt;/div&gt;</content><link rel='replies' type='application/atom+xml' href='http://briontest.blogspot.com/feeds/8202296917897346633/comments/default' title='Post Comments'/><link rel='replies' type='text/html' href='http://briontest.blogspot.com/2009/11/hey-dude.html#comment-form' title='0 Comments'/><link rel='edit' type='application/atom+xml' href='http://www.blogger.com/feeds/7780083508531697167/posts/default/8202296917897346633'/><link rel='self' type='application/atom+xml' href='http://www.blogger.com/feeds/7780083508531697167/posts/default/8202296917897346633'/><link rel='alternate' type='text/html' href='http://briontest.blogspot.com/2009/11/hey-dude.html' title='Hey dude'/><author><name>brion</name><uri>http://www.blogger.com/profile/12932299467049762017</uri><email>noreply@blogger.com</email><gd:extendedProperty xmlns:gd='http://schemas.google.com/g/2005' name='OpenSocialUserId' value='05912464053145602436'/></author><thr:total xmlns:thr='http://purl.org/syndication/thread/1.0'>0</thr:total></entry></feed>
END;
    }
}
