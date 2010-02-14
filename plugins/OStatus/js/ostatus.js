SN.U.DialogBox = {
    Subscribe: function(a) {
        var f = a.parent().find('#form_ostatus_connect');
        if (f.length > 0) {
            f.show();
        }
        else {
            $.ajax({
                type: 'GET',
                dataType: 'xml',
                url: a[0].href+'&ajax=1',
                beforeSend: function(formData) {
                    a.addClass('processing');
                },
                error: function (xhr, textStatus, errorThrown) {
                    alert(errorThrown || textStatus);
                },
                success: function(data, textStatus, xhr) {
                    if (typeof($('form', data)[0]) != 'undefined') {
                        a.after(document._importNode($('form', data)[0], true));

                        var form = a.parent().find('#form_ostatus_connect');

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

                        form.find('#acct').focus();
                    }

                    a.removeClass('processing');
                }
            });
        }
    }
};

SN.Init.Subscribe = function() {
    $('.entity_subscribe a').live('click', function() { SN.U.DialogBox.Subscribe($(this)); return false; });
};

$(document).ready(function() {
    if ($('.entity_subscribe .entity_remote_subscribe').length > 0) {
        SN.Init.Subscribe();
    }
});
