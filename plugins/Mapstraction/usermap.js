$(document).ready(function() {
     notices = [];
     $(".notice").each(function(){
        notice = getNoticeFromElement($(this));
        if(notice['geo'])
            notices.push(notice);
     });
     if($("#map_canvas").length && notices.length>0)
     {
        showMapstraction($("#map_canvas"), notices);
     }

     $('a.geo').click(function(){
        noticeElement = $(this).closest(".notice");
        notice = getNoticeFromElement(noticeElement);

        $.fn.jOverlay.options = {
            color : '#000',
            opacity : '0.6',
            zIndex : 99,
            center : false,
            bgClickToClose : true,
            autoHide : true,
            css : {'max-width':'542px', 'top':'5%', 'left':'32.5%'}
        };
        html="<div id='map_canvas_popup' class='gray smallmap' style='width: 542px; height: 500px' />";
        html+="<button class='close'>&#215;</button>";
        html+=$("<div/>").append($(this).clone()).html();
        $().jOverlay({ "html": html });
        $('#jOverlayContent').show();
        $('#jOverlayContent button').click($.closeOverlay);
        
        showMapstraction($("#map_canvas_popup"), notice);

        return false;
     });
});

function getMicroformatValue(element)
{
    if(element[0].tagName.toLowerCase() == 'abbr'){
        return element.attr('title');
    }else{
        return element.text();
    }
}

function getNoticeFromElement(noticeElement)
{
    notice = {};
    if(noticeElement.find(".latitude").length){
        notice['geo']={'coordinates': [
            parseFloat(getMicroformatValue(noticeElement.find(".latitude"))),
            parseFloat(getMicroformatValue(noticeElement.find(".longitude")))] };
    }
    notice['user']={
        'profile_image_url': noticeElement.find("img.avatar").attr('src'),
        'profile_url': noticeElement.find(".author a.url").attr('href'),
        'screen_name': noticeElement.find(".author .nickname").text()
    };
    notice['html']=noticeElement.find(".entry-content").html();
    notice['url']=noticeElement.find("a.timestamp").attr('href');
    notice['created_at']=noticeElement.find("abbr.published").text();
    return notice;
}

function showMapstraction(element, notices) {
     if(element instanceof jQuery) element = element[0];
     if(! $.isArray(notices)) notices = [notices];
     var mapstraction = new mxn.Mapstraction(element, _provider);

     var minLat = 181.0;
     var maxLat = -181.0;
     var minLon = 181.0;
     var maxLon = -181.0;

     for (var i in notices)
     {
          var n = notices[i];

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

          mkr.setIcon(n['user']['profile_image_url']);
          mkr.setInfoBubble('<a href="'+ n['user']['profile_url'] + '">' + n['user']['screen_name'] + '</a>' + ' ' + n['html'] +
                            '<br/><a href="'+ n['url'] + '">'+ n['created_at'] + '</a>');

          mapstraction.addMarker(mkr);
     }

     bounds = new mxn.BoundingBox(minLat, minLon, maxLat, maxLon);

     mapstraction.setBounds(bounds);
}
