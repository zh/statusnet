$(document).ready(function(){
    $.getJSON($('address .url')[0].href+'/api/statuses/friends.json?user_id=' + current_user['id'] + '&lite=true&callback=?',
        function(friends){
            $('#notice_data-text').autocomplete(friends, {
                multiple: true,
                multipleSeparator: " ",
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
});
