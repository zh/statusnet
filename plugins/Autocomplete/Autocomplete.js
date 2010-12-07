$(document).ready(function(){
    function fullName(row) {
        if (typeof row.fullname == "string" && row.fullname != '') {
            return row.nickname + ' (' + row.fullname + ')';
        } else {
            return row.nickname;
        }
    }
            $('#notice_data-text').autocomplete($('address .url')[0].href+'/plugins/Autocomplete/autocomplete.json', {
                multiple: true,
                multipleSeparator: " ",
                minChars: 1,
                formatItem: function(row, i, max){
                    row = eval("(" + row + ")");
                    return fullName(row);
                },
                formatMatch: function(row, i, max){
                    row = eval("(" + row + ")");
                    return row.nickname;
                },
                formatResult: function(row){
                    row = eval("(" + row + ")");
                    switch(row.type)
                    {
                        case 'user':
                            return '@' + row.nickname;
                        case 'group':
                            return '!' + row.nickname;
                    }
                }
            });
});
