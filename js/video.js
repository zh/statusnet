$('document').ready(function() {
    $('a.media, a.mediamp3').append(' <sup>[PLAY]</sup>');
    $('a.mediamp3').html('').css('display', 'block').css('width', '224px').css('height','24px').flowplayer('../bin/flowplayer-3.0.5.swf');
    $('a.media').click(function() {
        $('<a id="p1i"></a>').attr('href', $(this).attr('href')).flowplayer('../bin/flowplayer-3.0.5.swf').modal({'closeHTML':'<a class="modalCloseImg" title="Close"><img src="x.png" /></a>'});
        return false;
    });
});

