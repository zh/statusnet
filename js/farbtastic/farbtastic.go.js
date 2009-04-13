$(document).ready(function() {
    var f = $.farbtastic('#color-picker');
    var colors = $('#settings_design_color input');

    colors
        .each(function () { f.linkTo(this); })
        .focus(function() {
            f.linkTo(this);
        });
});
