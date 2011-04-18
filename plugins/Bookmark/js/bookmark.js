var Bookmark = {

    // Special XHR that sends in some code to be run
    // when the full bookmark form gets loaded
    BookmarkXHR: function(form)
    {
        SN.U.FormXHR(form, Bookmark.InitBookmarkForm);
        return false;
    },

    // Special initialization function just for the
    // second step in the bookmarking workflow
    InitBookmarkForm: function() {
        SN.Init.CheckBoxes();
        $('fieldset fieldset label').inFieldLabels({ fadeOpacity:0 });
    }
}

$(document).ready(function() {

    // Stop normal live event stuff
    $('form.ajax').die();
    $('form.ajax input[type=submit]').die();

    // Make the bookmark submit super special
    $('#form_initial_bookmark').bind('submit', function(e) {
        Bookmark.BookmarkXHR($(this));
        e.stopPropagation();
        return false;
    });

    // Restore live event stuff to other forms & submit buttions
    SN.Init.AjaxForms();

});
