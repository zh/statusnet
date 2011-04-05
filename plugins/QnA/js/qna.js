
var QnA = {

    // hide all the 'close' and 'best' buttons for this question

    // @fixme: Should use ID
    close: function(closeButt) {
        $(closeButt)
            .closest('li.hentry.notice.question')
            .find('input[name=best],[name=close]')
            .hide();
    },

    init: function() {
        var that = this;
        $('input[name=close]').live('click', function() {
            that.close(this);
        });
        $('input[name=best]').live('click', function() {
            that.close(this);
        });
    }
};

$(document).ready(function() {
    QnA.init();
});
