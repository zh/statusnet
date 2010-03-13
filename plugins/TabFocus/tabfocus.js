jQuery(function($){
  $('#notice_data-text').bind('keydown',function(e){
    if (e.which==9) {
      setTimeout(function(){  $('#notice_action-submit').focus();  },15);
    }
  });
});
