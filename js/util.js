$(document).ready(function(){
        // count character on keyup
        function counter(){ 
            var maxLength     = 140; 
            var currentLength = $("#status_textarea").val().length;
            var remaining = 140 - currentLength;
            var counter = $("#counter");
            counter.text(remaining);

            if(remaining <= 0) {
                counter.attr("class", "toomuch");
                } else {
                counter.attr("class", "");
                }
        }

        if ($("#status_textarea").length) {
            $("#status_textarea").bind("keyup", counter);
        }

});

