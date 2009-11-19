$(document).ready(function() {
     var mapstraction = new mxn.Mapstraction("map_canvas", _provider);

     var minLat = 181.0;
     var maxLat = -181.0;
     var minLon = 181.0;
     var maxLon = -181.0;

     for (var i in _notices)
     {
          var n = _notices[i];

          var lat = n['geo']['coordinates'][0];
          var lon = n['geo']['coordinates'][1];

          if (lat < minLat) {
               minLat = lat;
          }

          if (lat > maxLat) {
               maxLat = lat;
          }

          if (lon < minLon) {
               minLon = lon;
          }

          if (lon > maxLon) {
               maxLon = lon;
          }

          pt = new mxn.LatLonPoint(lat, lon);
          mkr = new mxn.Marker(pt);

          mkr.setLabel();
          mkr.setIcon(n['user']['profile_image_url']);
          mkr.setInfoBubble('<a href="'+ n['user']['profile_url'] + '">' + n['user']['screen_name'] + '</a>' + ' ' + n['html'] +
                            '<br/><a href="'+ n['url'] + '">'+ n['created_at'] + '</a>');

          mapstraction.addMarker(mkr);
     }

     bounds = new mxn.BoundingBox(minLat, minLon, maxLat, maxLon);

     mapstraction.setBounds(bounds);
});
