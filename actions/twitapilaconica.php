<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Laconica-only extensions to the Twitter-like API
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 *
 * @category  Twitter
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/twitterapi.php';

/**
 * Laconica-specific API methods
 *
 * This class handles all /laconica/ API methods.
 *
 * @category  Twitter
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

class TwitapilaconicaAction extends TwitterapiAction
{
    /**
     * A version stamp for the API
     *
     * Returns a version number for this version of Laconica, which
     * should make things a bit easier for upgrades.
     * URL: http://identi.ca/api/laconica/version.(xml|json)
     * Formats: xml, json
     *
     * @param array $args    Web arguments
     * @param array $apidata Twitter API data
     *
     * @return void
     *
     * @see ApiAction::process_command()
     */

    function version($args, $apidata)
    {
        parent::handle($args);
        switch ($apidata['content-type']) {
         case 'xml':
            $this->init_document('xml');
            $this->element('version', null, LACONICA_VERSION);
            $this->end_document('xml');
            break;
         case 'json':
            $this->init_document('json');
            print '"'.LACONICA_VERSION.'"';
            $this->end_document('json');
            break;
         default:
            $this->clientError(_('API method not found!'), $code=404);
        }
    }

    /**
     * Dump of configuration variables
     *
     * Gives a full dump of configuration variables for this instance
     * of Laconica, minus variables that may be security-sensitive (like
     * passwords).
     * URL: http://identi.ca/api/laconica/config.(xml|json)
     * Formats: xml, json
     *
     * @param array $args    Web arguments
     * @param array $apidata Twitter API data
     *
     * @return void
     *
     * @see ApiAction::process_command()
     */

    function config($args, $apidata)
    {
        static $keys = array('site' => array('name', 'server', 'theme', 'path', 'fancy', 'language',
                                             'email', 'broughtby', 'broughtbyurl', 'closed',
                                             'inviteonly', 'private'),
                             'license' => array('url', 'title', 'image'),
                             'nickname' => array('featured'),
                             'throttle' => array('enabled', 'count', 'timespan'),
                             'xmpp' => array('enabled', 'server', 'user'));

        parent::handle($args);

        switch ($apidata['content-type']) {
         case 'xml':
            $this->init_document('xml');
            $this->elementStart('config');
            // XXX: check that all sections and settings are legal XML elements
            foreach ($keys as $section => $settings) {
                $this->elementStart($section);
                foreach ($settings as $setting) {
                    $value = common_config($section, $setting);
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    } else if ($value === false) {
                        $value = 'false';
                    } else if ($value === true) {
                        $value = 'true';
                    }
                    $this->element($setting, null, $value);
                }
                $this->elementEnd($section);
            }
            $this->elementEnd('config');
            $this->end_document('xml');
            break;
         case 'json':
            $result = array();
            foreach ($keys as $section => $settings) {
                $result[$section] = array();
                foreach ($settings as $setting) {
                    $result[$section][$setting] = common_config($section, $setting);
                }
            }
            $this->init_document('json');
            $this->show_json_objects($result);
            $this->end_document('json');
            break;
         default:
            $this->clientError(_('API method not found!'), $code=404);
        }
    }

    /**
     * WADL description of the API
     *
     * Gives a WADL description of the API provided by this version of the
     * software.
     *
     * @param array $args    Web arguments
     * @param array $apidata Twitter API data
     *
     * @return void
     *
     * @see ApiAction::process_command()
     */

    function wadl($args, $apidata)
    {
        parent::handle($args);
        $this->serverError(_('API method under construction.'), 501);
    }

    function oembed($args, $apidata)
    {
        parent::handle($args);

        common_debug("in oembed api action");

        $this->auth_user = $apidata['user'];

        $url = $args['url'];
        if( substr(strtolower($url),0,strlen(common_root_url())) == strtolower(common_root_url()) ){
            $path = substr($url,strlen(common_root_url()));

            $r = Router::get();

            $proxy_args = $r->map($path);

            if (!$proxy_args) {
                $this->serverError(_("$path not found"), 404);
            }
            $oembed=array();
            $oembed['version']='1.0';
            $oembed['provider_name']=common_config('site', 'name');
            $oembed['provider_url']=common_root_url();
            switch($proxy_args['action']){
                case 'shownotice':
                    $oembed['type']='link';
                    $id = $proxy_args['notice'];
                    $notice = Notice::staticGet($id);
                    if(empty($notice)){
                        $this->serverError(_("notice $id not found"), 404);
                    }
                    $profile = $notice->getProfile();
                    if (empty($profile)) {
                        $this->serverError(_('Notice has no profile'), 500);
                    }
                    if (!empty($profile->fullname)) {
                        $authorname = $profile->fullname . ' (' . $profile->nickname . ')';
                    } else {
                        $authorname = $profile->nickname;
                    }
                    $oembed['title'] = sprintf(_('%1$s\'s status on %2$s'),
                        $authorname,
                        common_exact_date($notice->created));
                    $oembed['author_name']=$authorname;
                    $oembed['author_url']=$profile->profileurl;
                    $oembed['url']=($notice->url?$notice->url:$notice->uri);
                    $oembed['html']=$notice->rendered;
                    break;
                case 'attachment':
                    $id = $proxy_args['attachment'];
                    $attachment = File::staticGet($id);
                    if(empty($attachment)){
                        $this->serverError(_("attachment $id not found"), 404);
                    }
                    if(empty($attachment->filename) && $file_oembed = File_oembed::staticGet('file_id', $attachment->id)){
                        // Proxy the existing oembed information
                        $oembed['type']=$file_oembed->type;
                        $oembed['provider']=$file_oembed->provider;
                        $oembed['provider_url']=$file_oembed->provider_url;
                        $oembed['width']=$file_oembed->width;
                        $oembed['height']=$file_oembed->height;
                        $oembed['html']=$file_oembed->html;
                        $oembed['title']=$file_oembed->title;
                        $oembed['author_name']=$file_oembed->author_name;
                        $oembed['author_url']=$file_oembed->author_url;
                        $oembed['url']=$file_oembed->url;
                    }else if(substr($attachment->mimetype,0,strlen('image/'))=='image/'){
                        $oembed['type']='photo';
                        //TODO set width and height
                        //$oembed['width']=
                        //$oembed['height']=
                        $oembed['url']=$attachment->url;
                    }else{
                        $oembed['type']='link';
                        $oembed['url']=common_local_url('attachment',
                            array('attachment' => $attachment->id));
                    }
                    if($attachment->title) $oembed['title']=$attachment->title;
                    break;
                default:
                    $this->serverError(_("$path not supported for oembed requests"), 501);
            }

            switch($apidata['content-type']){
                case 'xml':
                    $this->init_document('xml');
                    $this->elementStart('oembed');
                    $this->element('version',null,$oembed['version']);
                    $this->element('type',null,$oembed['type']);
                    if($oembed['provider_name']) $this->element('provider_name',null,$oembed['provider_name']);
                    if($oembed['provider_url']) $this->element('provider_url',null,$oembed['provider_url']);
                    if($oembed['title']) $this->element('title',null,$oembed['title']);
                    if($oembed['author_name']) $this->element('author_name',null,$oembed['author_name']);
                    if($oembed['author_url']) $this->element('author_url',null,$oembed['author_url']);
                    if($oembed['url']) $this->element('url',null,$oembed['url']);
                    if($oembed['html']) $this->element('html',null,$oembed['html']);
                    if($oembed['width']) $this->element('width',null,$oembed['width']);
                    if($oembed['height']) $this->element('height',null,$oembed['height']);
                    if($oembed['cache_age']) $this->element('cache_age',null,$oembed['cache_age']);
                    if($oembed['thumbnail_url']) $this->element('thumbnail_url',null,$oembed['thumbnail_url']);
                    if($oembed['thumbnail_width']) $this->element('thumbnail_width',null,$oembed['thumbnail_width']);
                    if($oembed['thumbnail_height']) $this->element('thumbnail_height',null,$oembed['thumbnail_height']);
                    

                    $this->elementEnd('oembed');
                    $this->end_document('xml');
                    break;
                case 'json':
                    $this->init_document('json');
                    print(json_encode($oembed));
                    $this->end_document('json');
                    break;
                default:
                    $this->serverError(_('content type ' . $apidata['content-type'] . ' not supported'), 501);
            }
            
        }else{
            $this->serverError(_('Only ' . common_root_url() . ' urls over plain http please'), 404);
        }
    }
}
