<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.	 If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

class NoticeList {

    var $notice = NULL;

    function __construct($notice) {
        $this->notice = $notice;
    }

    function show() {

		common_element_start('ul', array('id' => 'notices'));

		$cnt = 0;

		while ($this->notice->fetch() && $cnt <= NOTICES_PER_PAGE) {
			$cnt++;

			if ($cnt > NOTICES_PER_PAGE) {
				break;
			}

            $item = $this->new_list_item($this->notice);
            $item->show();
		}

		common_element_end('ul');

        return $cnt;
	}

    function new_list_item($notice) {
        return new NoticeListItem($notice);
    }
}

class NoticeListItem {

    var $notice = NULL;
    var $profile = NULL;

    function __construct($notice) {
        $this->notice = $notice;
		$this->profile = $notice->getProfile();
    }

	function show() {
        $this->show_start();
        $this->show_fave_form();
        $this->show_author();
        $this->show_content();
        $this->show_start_time_section();
        $this->show_notice_link();
        $this->show_notice_source();
        $this->show_reply_to();
        $this->show_reply_link();
        $this->show_delete_link();
        $this->show_end_time_section();
        $this->show_end();
	}

    function show_start() {
		# XXX: RDFa
		common_element_start('li', array('class' => 'notice_single hentry',
										  'id' => 'notice-' . $this->notice->id));
    }

    function show_fave_form() {
        $user = common_current_user();
		if ($user) {
			if ($user->hasFave($this->notice)) {
				common_disfavor_form($this->notice);
			} else {
				common_favor_form($this->notice);
			}
		}
    }

    function show_author() {
 		common_element_start('span', 'vcard author');
        $this->show_avatar();
        $this->show_nickname();
		common_element_end('span');
    }

    function show_avatar() {
		$avatar = $this->profile->getAvatar(AVATAR_STREAM_SIZE);
		common_element_start('a', array('href' => $this->profile->profileurl));
		common_element('img', array('src' => ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_STREAM_SIZE),
									'class' => 'avatar stream photo',
									'width' => AVATAR_STREAM_SIZE,
									'height' => AVATAR_STREAM_SIZE,
									'alt' =>
									($this->profile->fullname) ? $this->profile->fullname :
									$this->profile->nickname));
		common_element_end('a');
    }

    function show_nickname() {
		common_element('a', array('href' => $this->profile->profileurl,
								  'class' => 'nickname fn url'),
					   $this->profile->nickname);
    }

    function show_content() {
		# FIXME: URL, image, video, audio
		common_element_start('p', array('class' => 'content entry-title'));
		if ($this->notice->rendered) {
			common_raw($this->notice->rendered);
		} else {
			# XXX: may be some uncooked notices in the DB,
			# we cook them right now. This should probably disappear in future
			# versions (>> 0.4.x)
			common_raw(common_render_content($this->notice->content, $this->notice));
		}
		common_element_end('p');
    }

    function show_start_time_section() {
		common_element_start('p', 'time');
    }

    function show_notice_link() {
		$noticeurl = common_local_url('shownotice', array('notice' => $this->notice->id));
		# XXX: we need to figure this out better. Is this right?
		if (strcmp($this->notice->uri, $this->noticeurl) != 0 && preg_match('/^http/', $this->notice->uri)) {
			$noticeurl = $this->notice->uri;
		}
		common_element_start('a', array('class' => 'permalink',
								  'rel' => 'bookmark',
								  'href' => $noticeurl));
		common_element('abbr', array('class' => 'published',
									 'title' => common_date_iso8601($this->notice->created)),
						common_date_string($this->notice->created));
		common_element_end('a');
    }

    function show_notice_source() {
		if ($this->notice->source) {
			common_text(_(' from '));
            $source_name = _($this->notice->source);
            switch ($source) {
             case 'web':
             case 'xmpp':
             case 'mail':
             case 'omb':
             case 'api':
                common_element('span', 'noticesource', $source_name);
                break;
             default:
                $ns = Notice_source::staticGet($source);
                if ($ns) {
                    common_element('a', array('href' => $ns->url),
                                   $ns->name);
                } else {
                    common_element('span', 'noticesource', $source_name);
                }
                break;
            }
		}
    }

    function show_reply_to() {
		if ($this->notice->reply_to) {
			$replyurl = common_local_url('shownotice', array('notice' => $this->notice->reply_to));
			common_text(' (');
			common_element('a', array('class' => 'inreplyto',
									  'href' => $replyurl),
						   _('in reply to...'));
			common_text(')');
		}
    }

    function show_reply_link() {
		common_element_start('a',
							 array('href' => common_local_url('newnotice',
															  array('replyto' => $this->profile->nickname)),
								   'onclick' => 'return doreply("'.$this->profile->nickname.'", '.$this->notice->id.');',
								   'title' => _('reply'),
								   'class' => 'replybutton'));
		common_raw('&rarr;');
		common_element_end('a');
    }

    function show_delete_link() {
        $user = common_current_user();
		if ($user && $this->notice->profile_id == $user->id) {
			$deleteurl = common_local_url('deletenotice', array('notice' => $this->notice->id));
			common_element_start('a', array('class' => 'deletenotice',
											'href' => $deleteurl,
											'title' => _('delete')));
			common_raw('&times;');
			common_element_end('a');
		}
    }

    function show_end_time_section() {
		common_element_end('p');
    }

    function show_end() {
		common_element_end('li');
    }
}
