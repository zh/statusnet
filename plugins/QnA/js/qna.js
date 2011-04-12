
var QnA = {

    // hide all the 'close' and 'best' buttons for this question

    // @fixme: Should use ID
    close: function(closeButt) {
        notice = $(closeButt).closest('li.hentry.notice.question');
        notice.find('input[name=best],[name=close]').hide();
        notice.find('textarea').hide();
        notice.find('li.notice-answer-placeholder').hide();
        notice.find('#answer-form').hide();
    },

    init: function() {

        var that = this;

        QnA.NoticeInlineAnswerSetup();

        $('input[name=close]').live('click', function() {
            that.close(this);
        });
        $('input[name=best]').live('click', function() {
            that.close(this);
        });



    },

    /**
     * Open up a question's inline answer box.
     *
     * @param {jQuery} notice: jQuery object containing one notice
     */
    NoticeInlineAnswerTrigger: function(notice) {
        console.log('NoticeInlineAnswerTrigger - begin');

        // Find the question notice we're answering...
        var id = $($('.notice_id', notice)[0]).text();
        console.log("parent notice id = " + id);
        var parentNotice = notice;

        // See if the form's already there...
        var answerForm = $('#answer-form', parentNotice);

        if (answerForm) {
            console.log("Found the answer form.");
        } else {
            console.log("Did not find the answer form.");
        }

        var placeholder = parentNotice.find('li.notice-answer-placeholder');

        // Pull the parents threaded list and we'll add on the end of it.
        var list = $('ul.threaded-replies', notice);

        if (list) {
            console.log("Found the " + list.length + " notice place holders.");
        } else {
            console.log("Found the notice answer placeholder");
        }

        if (list.length == 0) {
            console.log("list length = 0 adding <ul>");
            list = $('<ul class="notices threaded-replies xoxo"></ul>');
            notice.append(list);
        } else if (list.length == 2) {
            // remove duplicate ul added by util.js
            list.last().remove();
        }

        var answerItem = $('li.notice-answer', list);

        var nextStep = function() {
            console.log("nextStep - enter");

            // Set focus...
            var text = answerForm.find('textarea');

            if (text.length == 0) {
                throw "No textarea";
            }

            $('body').click(function(e) {
                console.log("got click");

                var openAnswers = $('li.notice-answer');
                    if (openAnswers.length > 0) {
                        var target = $(e.target);
                        openAnswers.each(function() {
                            // Did we click outside this one?
                            var answerItem = $(this);
                            if (answerItem.has(e.target).length == 0) {
                                var textarea = answerItem.find('.notice_data-text:first');
                                var cur = $.trim(textarea.val());
                                // Only close if there's been no edit.
                                if (cur == '' || cur == textarea.data('initialText')) {
                                    var parentNotice = answerItem.closest('li.notice');
                                    answerItem.remove();
                                    parentNotice.find('li.notice-answer-placeholder').show();
                                }
                            }
                        });
                    }
                });

            text.focus();
        };

        placeholder.hide();

        if (answerItem.length > 0) {
            console.log('answerItem length > 0');
            // Update the existing form...
            nextStep();
        } else {

             // Create the answer form entry at the end

             if (answerItem.length == 0) {
                 console.log("QQQQQ no notice-answer li");
                 answerItem = $('<li class="notice-answer"></li>');

                 var intermediateStep = function(formMaster) {
                     console.log("Intermediate step");
                     var formEl = document._importNode(formMaster, true);
                     answerItem.append(formEl);
                     console.log("appending answerItem");
                     list.append(answerItem); // *after* the placeholder
                     console.log("appended answerItem");
                     console.log(answerItem);
                     var form = answerForm = $(formEl);
                     QnA.AnswerFormSetup(form);

                     nextStep();
                 };

                 if (QnA.AnswerFormMaster) {
                     // We've already saved a master copy of the form.
                     // Clone it in!
                     intermediateStep(QnA.AnswerFormMaster);
                 } else {
                     // Fetch a fresh copy of the answer form over AJAX.
                     // Warning: this can have a delay, which looks bad.
                     // @fixme this fallback may or may not work
                     var url = $('#answer-action').attr('value');

                     console.log("fetching new form via HXR");

                     $.get(url, {ajax: 1}, function(data, textStatus, xhr) {
                         intermediateStep($('form', data)[0]);
                     });
                 }
             }
         }
         console.log('NoticeInlineAnswerTrigger - exit');

     },


    AnswerFormSetup: function(form) {
        console.log("AnswerFormSetup - begin");
        if (!form.data('AnswerFormSetup')) {
            form.data('AnswerFormSetup', true);
        }
        console.log("AnswerFormSetup - exit");
    },

    /**
     * Setup function -- DOES NOT apply immediately.
     *
     * Sets up event handlers for inline reply mini-form placeholders.
     * Uses 'live' rather than 'bind', so applies to future as well as present items.
     */
    NoticeInlineAnswerSetup: function() {
        console.log("NoticeInlineAnswerSetup - begin");
        $('li.notice-answer-placeholder input.placeholder')
            .live('focus', function() {
                var notice = $(this).closest('li.notice');
                QnA.NoticeInlineAnswerTrigger(notice);
                return false;
            });
        console.log("NoticeInlineAnswerSetup - exit");
    }

};

$(document).ready(function() {
    QnA.init();
});
