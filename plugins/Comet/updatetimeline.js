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
          alert("Received notice.");
          var noticeItem = makeNoticeItem(message.data);
          var noticeList = $('ul.notices');
     }

     function makeNoticeItem(data)
     {
          return '';
     }
}();

