$(document).ready(function() {
    function UpdateColors(S) {
        C = $(S).val();
        switch (parseInt(S.id.slice(-1))) {
            case 0: default:
                $('body').css({'background-color':C});
                break;
            case 1:
                $('#content').css({'background-color':C});
                break;
            case 2:
                $('#aside_primary').css({'background-color':C});
                break;
            case 3:
                $('body').css({'color':C});
                break;
            case 4:
                $('a').css({'color':C});
                break;
        }
    }

    function UpdateFarbtastic(e) {
        f.linked = e;
        f.setColor(e.value);
    }

    function UpdateSwatch(e) {
        $(e).css({"background-color": e.value,
                  "color": f.hsl[2] > 0.5 ? "#000": "#fff"});
    }

    function SynchColors(e) {
        var S = f.linked;
        var C = f.color;

        if (S && S.value && S.value != C) {
            S.value = C;
            UpdateSwatch(S);
            UpdateColors(S);
        }
    }

    function Init() {
        $('#settings_design_color').append('<div id="color-picker"></div>');
        $('#color-picker').hide();

        f = $.farbtastic('#color-picker', SynchColors);
        swatches = $('#settings_design_color .swatch');

        swatches
            .each(SynchColors)
            .blur(function() {
                $(this).val($(this).val().toUpperCase());
             })
            .focus(function() {
                $('#color-picker').show();
                UpdateFarbtastic(this);
            })
            .change(function() {
                UpdateFarbtastic(this);
                UpdateSwatch(this);
                UpdateColors(this);
            }).change();
    }

    var f, swatches;
    Init();
    $('#form_settings_design').bind('reset', function(){
        setTimeout(function(){
            swatches.each(function(){UpdateColors(this);});
            $('#color-picker').remove();
            swatches.unbind();
            Init();
        },10);
    });
});
