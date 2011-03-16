var SN_EXTENDED = SN_EXTENDED || {};

SN_EXTENDED.reorder = function(class) {
    console.log("QQQ Enter reorder");

    var divs = $.find('div[class=' + class + ']');
    console.log('divs length = ' + divs.length);

    $(divs).find('a').hide();

    $(divs).each(function(i, div) {
        console.log("ROW " + i);
        $(div).find('a.remove_row').show();
        SN_EXTENDED.replaceIndex(SN_EXTENDED.rowIndex(div), i);
    });

    $this = $(divs).last().closest('tr');
    $this.addClass('supersizeme');

    $(divs).last().find('a.add_row').show();

    if (divs.length == 1) {
        $(divs).find('a.remove_row').hide();
    }

};

SN_EXTENDED.rowIndex = function(div) {
    var idstr = $(div).attr('id');
    var id = idstr.match(/\d+/);
    console.log("id = " + id);
    return id;
};

SN_EXTENDED.rowCount = function(class) {
    var divs = $.find('div[class=' + class + ']');
    return divs.length;
};

SN_EXTENDED.replaceIndex = function(elem, oldIndex, newIndex) {
    $(elem).find('*').each(function() {
        $.each(this.attributes, function(i, attrib) {
            var regexp = /extprofile-.*-\d.*/;
            var value = attrib.value;
            var match = value.match(regexp);
            if (match !== null) {
                attrib.value = value.replace("-" + oldIndex, "-" + newIndex);
                console.log('match: oldIndex = ' + oldIndex + ' newIndex = ' + newIndex + ' name = ' + attrib.name + ' value = ' + attrib.value);
            }
        });
    });
}

SN_EXTENDED.resetRow = function(elem) {
    $(elem).find('input').attr('value', '');
    $(elem).find("select option[value='office']").attr("selected", true);
}

SN_EXTENDED.addRow = function() {
    var div = $(this).closest('div');
    var id = $(div).attr('id');
    var class = $(div).attr('class');
    var index = id.match(/\d+/);
    console.log("Current row = " + index + ', class = ' + class);
    var trold = $(this).closest('tr');
    var tr = $(trold).removeClass('supersizeme');
    var newtr = $(tr).clone();
    var newIndex = parseInt(index) + 1;
    SN_EXTENDED.replaceIndex(newtr, index, newIndex);
    SN_EXTENDED.resetRow(newtr);
    $(tr).after(newtr);
    SN_EXTENDED.reorder(class);
};

SN_EXTENDED.removeRow = function() {
    var div = $(this).closest('div');
    var id = $(div).attr('id');
    var class = $(div).attr('class');

    cnt = SN_EXTENDED.rowCount(class);
    console.debug("removeRow - cnt = " + cnt);
    if (cnt > 1) {
        var target = $(this).closest('tr');
        target.remove();
        SN_EXTENDED.reorder(class);
    }
};

$(document).ready(

function() {

    var multifields = ["phone-item", "experience-item", "education-item", "im-item"];

    for (f in multifields) {
        SN_EXTENDED.reorder(multifields[f]);
    }

    $("input#extprofile-manager").autocomplete({
        source: 'finduser',
        minLength: 2 });

    $('.add_row').live('click', SN_EXTENDED.addRow);
    $('.remove_row').live('click', SN_EXTENDED.removeRow);

});
