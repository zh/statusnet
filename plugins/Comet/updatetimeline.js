// update the local timeline from a Comet server
//

var updater = function()
{
    var _handshook = false;
    var _connected = false;
    var _cometd;

    return {
        init: function()
        {
            _cometd = $.cometd; // Uses the default Comet object
            _cometd.init(_timelineServer);
            _cometd.subscribe(_timeline, this, receive);
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
         var noticeList = $('ul.notices');
    }
}();
