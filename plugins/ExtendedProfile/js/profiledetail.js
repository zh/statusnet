var removeRow = function() {
    var cnt = rowCount(this);
    var table = $(this).closest('table');
    console.log("row count = " + cnt);
    if (cnt > 1) {
        var target = $(this).closest('tr');
        target.remove();
        reorder(table);
    }
};

var rowCount = function(row) {
    var top = $(row).closest('table');
    var trs = $(top).find('tr');
    return trs.length - 1; // exclude th section header row
};

var reorder = function(table) {
    var trs = $(table).find('tr').has('td');

    $(trs).find('a').hide();

    $(trs).each(function(i, tr) {
        console.log("ROW " + i);
        $(tr).find('a.remove_row').show();
        replaceIndex(rowIndex(tr), i);
    });

    $(trs).last().find('a.add_row').show();

    if (trs.length == 1) {
        $(trs).find('a.remove_row').hide();
    }

};

var rowIndex = function(elem) {
    var idStr = $(elem).find('div').attr('id');
    var id = idStr.match(/\d+/);
    console.log("id = " + id);
};

var replaceIndex = function(elem, oldIndex, newIndex) {
    $(elem).find('*').each(function() {
        $.each(this.attributes, function(i, attrib) {
            var regexp = /extprofile-.*-\d.*/;
            var value = attrib.value;
            var match = value.match(regexp);
            if (match != null) {
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
    var divId = $(this).closest('div').attr('id');
    var index = divId.match(/\d+/);
    console.log("Current row = " + index);
    var tr = $(this).closest('tr');
    var newtr = $(tr).clone();
    var newIndex = parseInt(index) + 1;
    replaceIndex(newtr, index, newIndex);
    resetRow(newtr);
    $(tr).after(newtr);
    console.log("number of rows: " + rowCount(tr));
    reorder($(this).closest('table'));
};

$(document).ready(

function() {
    $('.add_row').live('click', addRow);
    $('.remove_row').live('click', removeRow);
}

);