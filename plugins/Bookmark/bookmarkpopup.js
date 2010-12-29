$(document).ready(
    function() {
	var form = $('#form_new_bookmark');
        form.append('<input type="hidden" name="ajax" value="1"/>');
        form.ajaxForm({dataType: 'xml',
		       timeout: '60000',
                       beforeSend: function(formData) {
			   form.addClass('processing');
			   form.find('#submit').addClass('disabled');
		       },
                       error: function (xhr, textStatus, errorThrown) {
			   form.removeClass('processing');
			   form.find('#submit').removeClass('disabled');
			   self.close();
		       },
                       success: function(data, textStatus) {
			   form.removeClass('processing');
			   form.find('#submit').removeClass('disabled');
                           self.close();
                       }});

    }
);