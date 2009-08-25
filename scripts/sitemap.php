#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'f:d:u:';

$helptext = <<<END_OF_SITEMAP_HELP
Script for creating sitemaps files per http://sitemaps.org/

    -f <indexfile>   Use <indexfile> as output file
    -d <outputdir>   Use <outputdir> for new sitemaps
    -u <outputurl>   Use <outputurl> as root for URLs

END_OF_SITEMAP_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';

$output_paths = parse_args();

standard_map();
notices_map();
user_map();
index_map();

// ------------------------------------------------------------------------------
// Main functions: get data out and turn them into sitemaps
// ------------------------------------------------------------------------------

// Generate index sitemap of all other sitemaps.
function index_map()
{
    global $output_paths;
    $output_dir = $output_paths['output_dir'];
    $output_url = $output_paths['output_url'];

    foreach (glob("$output_dir*.xml") as $file_name) {

        // Just the file name please.
        $file_name = preg_replace("|$output_dir|", '', $file_name);

        $index_urls .= sitemap(
                           array(
                                 'url' => $output_url . $file_name,
                                 'changefreq' => 'daily'
                                 )
                           );
    }

    write_file($output_paths['index_file'], sitemapindex($index_urls));
}

// Generate sitemap of standard site elements.
function standard_map()
{
    global $output_paths;

    $standard_map_urls .= url(
                              array(
                                    'url' => common_local_url('public'),
                                    'changefreq' => 'daily',
                                    'priority' => '1',
                                    )
                              );

    $standard_map_urls .= url(
                              array(
                                    'url' => common_local_url('publicrss'),
                                    'changefreq' => 'daily',
                                    'priority' => '0.3',
                                    )
                              );

    $docs = array('about', 'faq', 'contact', 'im', 'openid', 'openmublog',
        'privacy', 'source', 'badge');

    foreach($docs as $title) {
        $standard_map_urls .= url(
                                  array(
                                        'url' => common_local_url('doc', array('title' => $title)),
                                        'changefreq' => 'monthly',
                                        'priority'   => '0.2',
                                        )
                                  );
    }

    $urlset_path = $output_paths['output_dir'] . 'standard.xml';

    write_file($urlset_path, urlset($standard_map_urls));
}

// Generate sitemaps of all notices.
function notices_map()
{
    global $output_paths;

    $notices = DB_DataObject::factory('notice');

    $notices->query('SELECT id, uri, url, modified FROM notice where is_local = 1');

    $notice_count = 0;
    $map_count = 1;

    while ($notices->fetch()) {

        // Maximum 50,000 URLs per sitemap file.
        if ($notice_count == 50000) {
            $notice_count = 0;
            $map_count++;
        }

        // remote notices have an URL

        if (!$notices->url && $notices->uri) {
            $notice = array(
                        'url'        => ($notices->uri) ? $notices->uri : common_local_url('shownotice', array('notice' => $notices->id)),
                        'lastmod'    => common_date_w3dtf($notices->modified),
                        'changefreq' => 'never',
                        'priority'   => '1',
                        );

            $notice_list[$map_count] .= url($notice);
            $notice_count++;
        }
    }

    // Make full sitemaps from the lists and save them.
    array_to_map($notice_list, 'notice');
}

// Generate sitemaps of all users.
function user_map()
{
    global $output_paths;

    $users = DB_DataObject::factory('user');

    $users->query('SELECT id, nickname FROM user');

    $user_count = 0;
    $map_count = 1;

    while ($users->fetch()) {

        // Maximum 50,000 URLs per sitemap file.
        if ($user_count == 50000) {
            $user_count = 0;
            $map_count++;
        }

        $user_args = array('nickname' => $users->nickname);

        // Define parameters for generating <url></url> elements.
        $user = array(
                      'url'        => common_local_url('showstream', $user_args),
                      'changefreq' => 'daily',
                      'priority'   => '1',
                      );

        $user_rss = array(
                          'url'        => common_local_url('userrss', $user_args),
                          'changefreq' => 'daily',
                          'priority'   => '0.3',
                          );

        $all = array(
                     'url'        => common_local_url('all', $user_args),
                     'changefreq' => 'daily',
                     'priority'   => '1',
                     );

        $all_rss = array(
                         'url'        => common_local_url('allrss', $user_args),
                         'changefreq' => 'daily',
                         'priority'   => '0.3',
                         );

        $replies = array(
                         'url'        => common_local_url('replies', $user_args),
                         'changefreq' => 'daily',
                         'priority'   => '1',
                         );

        $replies_rss = array(
                             'url'        => common_local_url('repliesrss', $user_args),
                             'changefreq' => 'daily',
                             'priority'   => '0.3',
                             );

        $foaf = array(
                      'url'        => common_local_url('foaf', $user_args),
                      'changefreq' => 'weekly',
                      'priority'   => '0.5',
                      );

        // Construct a <url></url> element for each user facet and add it
        // to our existing list of those.
        $user_list[$map_count]        .= url($user);
        $user_rss_list[$map_count]    .= url($user_rss);
        $all_list[$map_count]         .= url($all);
        $all_rss_list[$map_count]     .= url($all_rss);
        $replies_list[$map_count]     .= url($replies);
        $replies_rss_list[$map_count] .= url($replies_rss);
        $foaf_list[$map_count]        .= url($foaf);

        $user_count++;
    }

    // Make full sitemaps from the lists and save them.
    // Possible factoring: put all the lists into a master array, thus allowing
    // calling with single argument (i.e., array_to_map('user')).
    array_to_map($user_list, 'user');
    array_to_map($user_rss_list, 'user_rss');
    array_to_map($all_list, 'all');
    array_to_map($all_rss_list, 'all_rss');
    array_to_map($replies_list, 'replies');
    array_to_map($replies_rss_list, 'replies_rss');
    array_to_map($foaf_list, 'foaf');
}

// ------------------------------------------------------------------------------
// XML generation functions
// ------------------------------------------------------------------------------

// Generate a <url></url> element.
function url($url_args)
{
    $url        = preg_replace('/&/', '&amp;', $url_args['url']); // escape ampersands for XML
    $lastmod    = $url_args['lastmod'];
    $changefreq = $url_args['changefreq'];
    $priority   = $url_args['priority'];

    if (is_null($url)) {
        error("url() arguments require 'url' value.");
    }

    $url_out = "\t<url>\n";
    $url_out .= "\t\t<loc>$url</loc>\n";

    if ($changefreq) {
        $url_out .= "\t\t<changefreq>$changefreq</changefreq>\n";
    }

    if ($lastmod) {
        $url_out .= "\t\t<lastmod>$lastmod</lastmod>\n";
    }

    if ($priority) {
        $url_out .= "\t\t<priority>$priority</priority>\n";
    }

    $url_out .= "\t</url>\n";

    return $url_out;
}

function sitemap($sitemap_args)
{
    $url        = preg_replace('/&/', '&amp;', $sitemap_args['url']); // escape ampersands for XML
    $lastmod    = $sitemap_args['lastmod'];

    if (is_null($url)) {
        error("url() arguments require 'url' value.");
    }

    $sitemap_out = "\t<sitemap>\n";
    $sitemap_out .= "\t\t<loc>$url</loc>\n";

    if ($lastmod) {
        $sitemap_out .= "\t\t<lastmod>$lastmod</lastmod>\n";
    }

    $sitemap_out .= "\t</sitemap>\n";

    return $sitemap_out;
}

// Generate a <urlset></urlset> element.
function urlset($urlset_text)
{
    $urlset = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
      '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" .
      $urlset_text .
      '</urlset>';

    return $urlset;
}

// Generate a <urlset></urlset> element.
function sitemapindex($sitemapindex_text)
{
    $sitemapindex = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
      '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" .
      $sitemapindex_text .
      '</sitemapindex>';

    return $sitemapindex;
}

// Generate a sitemap from an array containing <url></url> elements and write it to a file.
function array_to_map($url_list, $filename_prefix)
{
    global $output_paths;

    if ($url_list) {
        // $map_urls is a long string containing concatenated <url></url> elements.
        while (list($map_idx, $map_urls) = each($url_list)) {
            $urlset_path = $output_paths['output_dir'] . "$filename_prefix-$map_idx.xml";

            write_file($urlset_path, urlset($map_urls));
        }
    }
}

// ------------------------------------------------------------------------------
// Internal functions
// ------------------------------------------------------------------------------

// Parse command line arguments.
function parse_args()
{
    $index_file = get_option_value('f');
    $output_dir = get_option_value('d');
    $output_url = get_option_value('u');

    if (file_exists($output_dir)) {
        if (is_writable($output_dir) === false) {
            error("$output_dir is not writable.");
        }
    }     else {
        error("output directory $output_dir does not exist.");
    }

    $paths = array(
                   'index_file' => $index_file,
                   'output_dir' => trailing_slash($output_dir),
                   'output_url' => trailing_slash($output_url),
                   );

    return $paths;
}

// Ensure paths end with a "/".
function trailing_slash($path)
{
    if (preg_match('/\/$/', $path) == 0) {
        $path .= '/';
    }

    return $path;
}

// Write data to disk.
function write_file($path, $data)
{
    if (is_null($path)) {
        error('No path specified for writing to.');
    }     elseif (is_null($data)) {
        error('No data specified for writing.');
    }

    if (($fh_out = fopen($path,'w')) === false) {
        error("couldn't open $path for writing.");
    }

    if (fwrite($fh_out, $data) === false) {
        error("couldn't write to $path.");
    }
}

// Display an error message and exit.
function error ($error_msg)
{
    if (is_null($error_msg)) {
        $error_msg = 'error() was called without any explanation!';
    }

    echo "Error: $error_msg\n";
    exit(1);
}

?>