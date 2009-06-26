/** Init for Farbtastic library and page setup
 *
 * @package   Laconica
 * @author Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */
$(document).ready(function() {
    function UpdateColors(S) {
        C = $(S).val();
        switch (parseInt(S.id.slice(-1))) {
            case 1: default:
                $('html, body').css({'background-color':C});
                break;
            case 2:
                $('#content, #site_nav_local_views .current a').css({'background-color':C});
                break;
            case 3:
                $('#aside_primary').css({'background-color':C});
                break;
            case 4:
                $('html body').css({'color':C});
                break;
            case 5:
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

    function InitFarbtastic() {
        $('#settings_design_color').append('<div id="color-picker"></div>');
        $('#color-picker').hide();

        f = $.farbtastic('#color-picker', SynchColors);
        swatches = $('#settings_design_color .swatch');

        swatches
            .each(SynchColors)
            .blur(function() {
                tv = $(this).val();
                $(this).val(tv.toUpperCase());
                (tv.length == 4) ? ((tv[0] == '#') ? $(this).val('#'+tv[1]+tv[1]+tv[2]+tv[2]+tv[3]+tv[3]) : '') : '';
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
    InitFarbtastic();
    $('#form_settings_design').bind('reset', function(){
        setTimeout(function(){
            swatches.each(function(){UpdateColors(this);});
            $('#color-picker').remove();
            swatches.unbind();
            InitFarbtastic();
        },10);
    });

    $('#design_background-image_off').focus(function() {
        $('body').css({'background-image':'none'});
    });
    $('#design_background-image_on').focus(function() {
        $('body').css({'background-image':'url('+$('#design_background-image_onoff img')[0].src+')'});
    });

    $('#design_background-image_repeat').click(function() {
        ($(this)[0].checked) ? $('body').css({'background-repeat':'repeat'}) : $('body').css({'background-repeat':'no-repeat'});
    });
});
