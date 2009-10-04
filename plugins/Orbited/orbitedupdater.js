// Update the local timeline from a Orbited server

var OrbitedUpdater = function()
{
     return {

          init: function(server, port, timeline, username, password)
          {
               // set up stomp client.
               stomp = new STOMPClient();

               stomp.connect(server, port, username, password);
               stomp.subscribe(timeline);

               stomp.onmessageframe = function(frame) {
                    RealtimeUpdate.receive(JSON.parse(frame.body));
               };
          };
     }
}();

