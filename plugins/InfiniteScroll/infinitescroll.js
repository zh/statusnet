jQuery(document).ready(function($){
  $('notices_primary').infinitescroll({
    nextSelector    : "li.nav_next a",
    loadingImg      : $('address .url')[0].href+'plugins/InfiniteScroll/ajax-loader.gif',
    text            : "<em>Loading the next set of posts...</em>",
    donetext        : "<em>Congratulations, you\'ve reached the end of the Internet.</em>",
    navSelector     : "div.pagination",
    contentSelector : "#notices_primary",
    itemSelector    : "ol.notices"
    },function(){
        NoticeAttachments();
    });
});

