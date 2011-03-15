var reorder = function(class) {
    console.log("QQQ Enter reorder");

    var divs = $.find('div[class=' + class + ']');
    console.log('divs length = ' + divs.length);

    $(divs).find('a').hide();

    $(divs).each(function(i, div) {
        console.log("ROW " + i);
        $(div).find('a.remove_row').show();
        replaceIndex(rowIndex(div), i);
    });

    $(divs).last().find('a.add_row').show();

    if (divs.length == 1) {
        $(divs).find('a.remove_row').hide();
    }

};

var rowIndex = function(div) {
    var idstr = $(div).attr('id');
    var id = idstr.match(/\d+/);
    console.log("id = " + id);
    return id;
};

var rowCount = function(class) {
    var divs = $.find('div[class=' + class + ']');
    return divs.length;
};

var replaceIndex = function(elem, oldIndex, newIndex) {
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

var resetRow = function(elem) {
    $(elem).find('input').attr('value', '');
    $(elem).find("select option[value='office']").attr("selected", true);
}

var addRow = function() {
    var div = $(this).closest('div');
    var id = $(div).attr('id');
    var class = $(div).attr('class');
    var index = id.match(/\d+/);
    console.log("Current row = " + index + ', class = ' + class);
    var tr = $(this).closest('tr');
    var newtr = $(tr).clone();
    var newIndex = parseInt(index) + 1;
    replaceIndex(newtr, index, newIndex);
    resetRow(newtr);
    $(tr).after(newtr);
    reorder(class);
};

var removeRow = function() {
    var div = $(this).closest('div');
    var id = $(div).attr('id');
    var class = $(div).attr('class');

    cnt = rowCount(class);
    console.debug("removeRow - cnt = " + cnt);
    if (cnt > 1) {
        var target = $(this).closest('tr');
        target.remove();
        reorder(class);
    }
};

var init = function() {
    reorder('phone-edit');
    reorder('experience-edit');
    reorder('education-edit');
    reorder('im-edit');
}

$(document).ready(

function() {
    init();
    $('.add_row').live('click', addRow);
    $('.remove_row').live('click', removeRow);
}

);