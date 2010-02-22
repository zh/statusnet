SN.U.DialogBox = {
    Subscribe: function(a) {
        var f = a.parent().find('.form_settings');
        if (f.length > 0) {
            f.show();
        }
        else {
            a[0].href = (a[0].href.match(/[\\?]/) == null) ? a[0].href+'?' : a[0].href+'&';
            $.ajax({
                type: 'GET',
                dataType: 'xml',
                url: a[0].href+'ajax=1',
                beforeSend: function(formData) {
                    a.addClass('processing');
                },
                error: function (xhr, textStatus, errorThrown) {
                    alert(errorThrown || textStatus);
                },
                success: function(data, textStatus, xhr) {
                    if (typeof($('form', data)[0]) != 'undefined') {
                        a.after(document._importNode($('form', data)[0], true));

                        var form = a.parent().find('.form_settings');

                        form
                            .addClass('dialogbox')
                            .append('<button class="close">&#215;</button>');

                        form
                            .find('.submit')
                                .addClass('submit_dialogbox')
                                .removeClass('submit')
                                .bind('click', function() {
                                    form.addClass('processing');
                                });

                        form.find('button.close').click(function(){
                            form.hide();

                            return false;
                        });

                        form.find('#profile').focus();
                    }

                    a.removeClass('processing');
                }
            });
        }
    }
};

SN.Init.Subscribe = function() {
    $('.entity_subscribe .entity_remote_subscribe').live('click', function() { SN.U.DialogBox.Subscribe($(this)); return false; });
};

$(document).ready(function() {
    SN.Init.Subscribe();
});
