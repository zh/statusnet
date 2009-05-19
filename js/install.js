$(document).ready(function(){
    $.ajax({url:'check-fancy',
        type:'GET',
        success:function(data, textStatus) {
            $('#fancy-enable').attr('checked', true);
            $('#fancy-disable').attr('checked', false);
            $('#fancy-form_guide').text(data);
        },
        error:function(XMLHttpRequest, textStatus, errorThrown) {
            $('#fancy-enable').attr('checked', false);
            $('#fancy-disable').attr('checked', true);
            $('#fancy-enable').attr('disabled', true);
            $('#fancy-disable').attr('disabled', true);
            $('#fancy-form_guide').text("Fancy URL support detection failed, disabling this option. Make sure you renamed htaccess.sample to .htaccess.");
        }
    });
});

