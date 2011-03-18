/**
 * modplus.js
 * (c) 2010 StatusNet, Inc
 */

$(function() {
    $('.notice .author').live('mouseenter', function(e) {
        var notice = $(this).closest('.notice');
        var popup = notice.find('.remote-profile-options');
        if (popup.length) {
            popup.fadeIn();
        }
    });
    $('.notice').live('mouseleave', function(e) {
        var notice = $(this);
        var popup = notice.find('.remote-profile-options');
        if (popup.length) {
            popup.fadeOut();
        }
    });
});
