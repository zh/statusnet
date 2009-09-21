// Update the local timeline from a Meteor server
// XXX: If @a is subscribed to @b, @a should get @b's notices in @a's Personal timeline.
//      Do Replies timeline.

var MeteorUpdater = function()
{
    return {

        init: function(server, port, timeline)
        {
            Meteor.callbacks["process"] = function(data) {
                var d = JSON.parse(data);

                $user_url = $('address .url')[0].href+d['user']['screen_name'];

                if (timeline == 'public' ||
                    $user_url+'/all' == window.location.href ||
                    $user_url == window.location.href) {

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

