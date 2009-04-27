// update the local timeline from a Comet server
//

var updater = function()
{
     var _cometd;

     return {
          init: function(server, timeline)
          {
               _cometd = $.cometd; // Uses the default Comet object
               _cometd.setLogLevel('debug');
               _cometd.init(server);
               _cometd.subscribe(timeline, receive);
               $(window).unload(leave);
          }
     }

     function leave()
     {
          _cometd.disconnect();
     }

     function receive(message)
     {
          var noticeItem = makeNoticeItem(message.data);
          $("#notices_primary .notices").prepend(noticeItem, true);
          $("#notices_primary .notice:first").css({display:"none"});
          $("#notices_primary .notice:first").fadeIn(2500);
          NoticeHover();
          NoticeReply();
     }

     function makeNoticeItem(data)
     {
          user = data['user'];
          html = data['html'].replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>');

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
               "<dl class=\"timestamp\">"+
               "<dt>Published</dt>"+
               "<dd>"+
               "<a rel=\"bookmark\" href=\""+data['url']+"\" >"+
               "<abbr class=\"published\" title=\""+data['created_at']+"\">a few seconds ago</abbr>"+
               "</a> "+
               "</dd>"+
               "</dl>"+
               "<dl class=\"device\">"+
               "<dt>From</dt> "+
               "<dd>"+data['source']+"</dd>"+
               "</dl>"+
               "</div>"+
               "<div class=\"notice-options\">"+
               "</div>"+
               "</li>";
          return ni;
     }
}();

