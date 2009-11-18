/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, StatusNet, Inc.
 *
 * Add a notice encoded as JSON into the current timeline
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
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

// TODO: i18n

RealtimeUpdate = {
     _userid: 0,
     _replyurl: '',
     _favorurl: '',
     _deleteurl: '',
     _updatecounter: 0,
     _maxnotices: 50,
     _windowhasfocus: true,
     _documenttitle: '',
     _paused:false,
     _queuedNotices:[],

     init: function(userid, replyurl, favorurl, deleteurl)
     {
        RealtimeUpdate._userid = userid;
        RealtimeUpdate._replyurl = replyurl;
        RealtimeUpdate._favorurl = favorurl;
        RealtimeUpdate._deleteurl = deleteurl;

        RealtimeUpdate._documenttitle = document.title;

        $(window).bind('focus', function(){ RealtimeUpdate._windowhasfocus = true; });

        $(window).bind('blur', function() {
          $('#notices_primary .notice').removeClass('mark-top');

          $('#notices_primary .notice:first').addClass('mark-top');

          RealtimeUpdate._updatecounter = 0;
          document.title = RealtimeUpdate._documenttitle;
          RealtimeUpdate._windowhasfocus = false;

          return false;
        });
     },

     receive: function(data)
     {
          id = data.id;

          // Don't add it if it already exists
          if ($("#notice-"+id).length > 0) {
               return;
          }

          if (RealtimeUpdate._paused === false) {
              RealtimeUpdate.purgeLastNoticeItem();

              RealtimeUpdate.insertNoticeItem(data);

              RealtimeUpdate.updateWindowCounter();
          }
          else {
              RealtimeUpdate._queuedNotices.push(data);
          }
     },

     insertNoticeItem: function(data) {
        var noticeItem = RealtimeUpdate.makeNoticeItem(data);
        $("#notices_primary .notices").prepend(noticeItem);
        $("#notices_primary .notice:first").css({display:"none"});
        $("#notices_primary .notice:first").fadeIn(1000);

        SN.U.NoticeReply();
        SN.U.NoticeFavor();
     },

     purgeLastNoticeItem: function() {
        if ($('#notices_primary .notice').length > RealtimeUpdate._maxnotices) {
            $("#notices_primary .notice:last .form_disfavor").unbind('submit');
            $("#notices_primary .notice:last .form_favor").unbind('submit');
            $("#notices_primary .notice:last .notice_reply").unbind('click');
            $("#notices_primary .notice:last").remove();
        }
     },

     updateWindowCounter: function() {
          if (RealtimeUpdate._windowhasfocus === false) {
              RealtimeUpdate._updatecounter += 1;
              document.title = '('+RealtimeUpdate._updatecounter+') ' + RealtimeUpdate._documenttitle;
          }
     },

     makeNoticeItem: function(data)
     {
          user = data['user'];
          html = data['html'].replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g,'"');
          source = data['source'].replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g,'"');

          ni = "<li class=\"hentry notice\" id=\"notice-"+data['id']+"\">"+
               "<div class=\"entry-title\">"+
               "<span class=\"vcard author\">"+
               "<a href=\""+user['profile_url']+"\" class=\"url\">"+
               "<img src=\""+user['profile_image_url']+"\" class=\"avatar photo\" width=\"48\" height=\"48\" alt=\""+user['screen_name']+"\"/>"+
               "<span class=\"nickname fn\">"+user['screen_name']+"</span>"+
               "</a>"+
               "</span>"+
               "<p class=\"entry-content\">"+html+"</p>"+
               "</div>"+
               "<div class=\"entry-content\">"+
               "<a class=\"timestamp\" rel=\"bookmark\" href=\""+data['url']+"\" >"+
               "<abbr class=\"published\" title=\""+data['created_at']+"\">a few seconds ago</abbr>"+
               "</a> "+
               "<span class=\"source\">"+
               "from "+
                "<span class=\"device\">"+source+"</span>"+ // may have a link
               "</span>";
          if (data['in_reply_to_status_id']) {
               ni = ni+" <a class=\"response\" href=\""+data['in_reply_to_status_url']+"\">in context</a>";
          }

          ni = ni+"</div>"+
            "<div class=\"notice-options\">";

          if (RealtimeUpdate._userid != 0) {
               var input = $("form#form_notice fieldset input#token");
               var session_key = input.val();
               ni = ni+RealtimeUpdate.makeFavoriteForm(data['id'], session_key);
               ni = ni+RealtimeUpdate.makeReplyLink(data['id'], data['user']['screen_name']);
               if (RealtimeUpdate._userid == data['user']['id']) {
                    ni = ni+RealtimeUpdate.makeDeleteLink(data['id']);
               }
          }

          ni = ni+"</div>"+
               "</li>";
          return ni;
     },

     makeFavoriteForm: function(id, session_key)
     {
          var ff;

          ff = "<form id=\"favor-"+id+"\" class=\"form_favor\" method=\"post\" action=\""+RealtimeUpdate._favorurl+"\">"+
                "<fieldset>"+
               "<legend>Favor this notice</legend>"+
               "<input name=\"token-"+id+"\" type=\"hidden\" id=\"token-"+id+"\" value=\""+session_key+"\"/>"+
               "<input name=\"notice\" type=\"hidden\" id=\"notice-n"+id+"\" value=\""+id+"\"/>"+
               "<input type=\"submit\" id=\"favor-submit-"+id+"\" name=\"favor-submit-"+id+"\" class=\"submit\" value=\"Favor\" title=\"Favor this notice\"/>"+
                "</fieldset>"+
               "</form>";
          return ff;
     },

     makeReplyLink: function(id, nickname)
     {
          var rl;
          rl = "<a class=\"notice_reply\" href=\""+RealtimeUpdate._replyurl+"?replyto="+nickname+"\" title=\"Reply to this notice\">Reply <span class=\"notice_id\">"+id+"</span></a>";
          return rl;
        },

     makeDeleteLink: function(id)
     {
          var dl, delurl;
          delurl = RealtimeUpdate._deleteurl.replace("0000000000", id);

          dl = "<a class=\"notice_delete\" href=\""+delurl+"\" title=\"Delete this notice\">Delete</a>";

          return dl;
     },

     initActions: function(url, timeline, path)
     {
        var NP = $('#notices_primary');
        NP.prepend('<ul id="realtime_actions"><li id="realtime_pauseplay"></li></ul>');

        RealtimeUpdate._pluginPath = path;

        RealtimeUpdate.initPlayPause();
        RealtimeUpdate.initAddPopup(url, timeline, RealtimeUpdate._pluginPath);
     },

     initPlayPause: function()
     {
        RealtimeUpdate.showPause();
     },

     showPause: function()
     {
        RT_PP = $('#realtime_pauseplay');
        RT_PP.empty();
        RT_PP.append('<button id="realtime_pause" class="pause" title="Pause">Pause</button>');

        RT_P = $('#realtime_pause');
        $('#realtime_pause').css({
            'background':'url('+RealtimeUpdate._pluginPath+'icon_pause.gif) no-repeat 47% 47%',
            'width':'16px',
            'height':'16px',
            'text-indent':'-9999px',
             'border':'none',
             'cursor':'pointer'
        });
        RT_P.bind('click', function() {
            RealtimeUpdate._paused = true;

            RealtimeUpdate.showPlay();
            return false;
        });
     },

     showPlay: function()
     {
        RT_PP = $('#realtime_pauseplay');
        RT_PP.empty();
        RT_PP.append('<button id="realtime_play" class="play" title="Play">Play</button>');

        RT_P = $('#realtime_play');
        RT_P.css({
            'background':'url('+RealtimeUpdate._pluginPath+'icon_play.gif) no-repeat 47% 47%',
            'width':'16px',
            'height':'16px',
            'text-indent':'-9999px',
             'border':'none',
             'cursor':'pointer'
        });
        RT_P.bind('click', function() {
            RealtimeUpdate._paused = false;

            RealtimeUpdate.showPause();

            RealtimeUpdate.showQueuedNotices();

            return false;
        });
     },

     showQueuedNotices: function() {
        $.each(RealtimeUpdate._queuedNotices, function(i, n) {
            RealtimeUpdate.insertNoticeItem(n);
        });

        RealtimeUpdate._queuedNotices = [];
     },

     initAddPopup: function(url, timeline, path)
     {
         var NP = $('#notices_primary');
         NP.css({'position':'relative'});
         NP.prepend('<button id="realtime_timeline" title="Pop up in a window">Pop up</button>');

         var RT = $('#realtime_timeline');
         RT.css({
             'margin':'0 0 11px 0',
             'background':'transparent url('+ path + 'icon_external.gif) no-repeat 0 30%',
             'padding':'0 0 0 20px',
             'display':'block',
             'position':'absolute',
             'top':'-20px',
             'right':'0',
             'border':'none',
             'cursor':'pointer',
             'color':$('a').css('color'),
             'font-weight':'bold',
             'font-size':'1em'
         });
         $('#showstream #notices_primary').css({'margin-top':'18px'});

         RT.bind('click', function() {
             window.open(url,
                         '',
                         'toolbar=no,resizable=yes,scrollbars=yes,status=yes,width=500,height=550');

             return false;
         });
     },

     initPopupWindow: function()
     {
         $('address').hide();
         $('#content').css({'width':'93.5%'});

         $('#form_notice').css({
            'margin':'18px 0 18px 1.795%',
            'width':'93%',
            'max-width':'451px'
         });

         $('#form_notice label[for=notice_data-text], h1').css({'display': 'none'});

         $('.notices li:first-child').css({'border-top-color':'transparent'});

         $('#form_notice label[for="notice_data-attach"], #form_notice #notice_data-attach').css({'top':'0'});

         $('#form_notice #notice_data-attach').css({
            'left':'auto',
            'right':'0'
         });

         $('.notices .entry-title a, .notices .entry-content a').bind('click', function() {
            window.open(this.href, '');
            
            return false;
         });
     }
}

