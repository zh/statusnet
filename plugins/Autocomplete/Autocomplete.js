$(document).ready(function(){
    $.getJSON($('address .url')[0].href+'/api/statuses/friends.json?user_id=' + current_user['id'] + '&lite=true&callback=?',
        function(friends){
            $('#notice_data-text').autocomplete(friends, {
                multiple: true,
                multipleSeparator: " ",
                minChars: 1,
                formatItem: function(row, i, max){
                    return '@' + row.screen_name + ' (' + row.name + ')';
                },
                formatMatch: function(row, i, max){
                    return '@' + row.screen_name;
                },
                formatResult: function(row){
                    return '@' + row.screen_name;
                }
            });
        }
    );
    $.getJSON($('address .url')[0].href+'/api/laconica/groups/list.json?user_id=' + current_user['id'] + '&callback=?',
        function(groups){
            $('#notice_data-text').autocomplete(groups, {
                multiple: true,
                multipleSeparator: " ",
                minChars: 1,
                formatItem: function(row, i, max){
                    return '!' + row.nickname + ' (' + row.fullname + ')';
                },
                formatMatch: function(row, i, max){
                    return '!' + row.nickname;
                },
                formatResult: function(row){
                    return '!' + row.nickname;
                }
            });
        }
    );
});
