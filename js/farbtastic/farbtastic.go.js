$(document).ready(function() {
    function UpdateColors(e) {
        var S = f.linked;
        var C = f.color;

        if (S && S.value && S.value != C) {
            UpdateSwatch(S);

            switch (parseInt(f.linked.id.slice(-1))) {
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
            S.value = C;
        }
    }

    function UpdateFarbtastic(e) {
        f.linked = e;
        f.setColor(e.value);
    }

    function UpdateSwatch(e) {
        $(e).css({
            "background-color": e.value,
            "color": f.hsl[2] > 0.5 ? "#000": "#fff"
        });
    }

    $('#settings_design_color').append('<div id="color-picker"></div>');
    $('#color-picker').hide();

    var f = $.farbtastic('#color-picker', UpdateColors);
    var swatches = $('#settings_design_color .swatch');

    swatches
        .each(UpdateColors)

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
        }).change()

        ;

});
