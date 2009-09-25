$(document).ready(function(){
            $('#notice_data-text').autocomplete($('address .url')[0].href+'/plugins/Autocomplete/autocomplete.json', {
                multiple: true,
                multipleSeparator: " ",
                minChars: 1,
                formatItem: function(row, i, max){
                    row = eval("(" + row + ")");
                    switch(row.type)
                    {
                        case 'user':
                            return row.nickname + ' (' + row.fullname + ')';
                        case 'group':
                            return row.nickname + ' (' + row.fullname + ')';
                    }
                },
                formatMatch: function(row, i, max){
                    row = eval("(" + row + ")");
                    switch(row.type)
                    {
                        case 'user':
                            return row.nickname;
                        case 'group':
                            return row.nickname;
                    }
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
