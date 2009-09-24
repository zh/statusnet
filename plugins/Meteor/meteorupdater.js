// Update the local timeline from a Meteor server
// XXX: If @a is subscribed to @b, @a should get @b's notices in @a's Personal timeline.
//      Do Replies timeline.

var MeteorUpdater = function()
{
     return {

          init: function(server, port, timeline)
          {
               Meteor.callbacks["process"] = function(data) {
                    RealtimeUpdate.receive(JSON.parse(data));
               };

               Meteor.host = server;
               Meteor.port = port;
               Meteor.joinChannel(timeline, 0);
               Meteor.connect();
          }
     }
}();

