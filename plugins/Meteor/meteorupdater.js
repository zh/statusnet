// update the local timeline from a Meteor server
//

var MeteorUpdater = function()
{
    return {

        init: function(server, port, timeline)
        {
            var screen_name;

            Meteor.callbacks["process"] = function(data) {
                var d = JSON.parse(data);
                screen_name = d['user']['screen_name'];

                if (timeline == 'public' ||
                    $('address .url')[0].href+screen_name+'/all' == window.location.href ||
                    $('address .url')[0].href+screen_name == window.location.href) {
                    RealtimeUpdate.receive(d);
                }
            };

            Meteor.host = server;
            Meteor.port = port;
            Meteor.joinChannel(timeline, 0);
            Meteor.connect();
        }
    }
}();

