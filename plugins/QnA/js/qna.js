
var QnA = {

    // @fixme: Should use ID
    close: function(closeButt) {
        var notice = $(closeButt).closest('li.hentry.notice.question');

        console.log("close");

        notice.find('input#qna-best-answer,#qna-question-close').hide();
        notice.find('textarea').hide();

        var list = notice.find('ul');

        console.log("found this many uls: " + list.length);

        notice.find('ul > li.notice-answer-placeholder').remove();
        notice.find('ul > li.notice-answer').remove();
    },

    init: function() {

        QnA.NoticeInlineAnswerSetup();

        $('form.form_question_show').live('submit', function() {
            QnA.close(this);
        });
        $('form.form_answer_show').live('click', function() {
            QnA.close(this);
        });
        
    },

    /**
     * Open up a question's inline answer box.
     *
     * @param {jQuery} notice: jQuery object containing one notice
     */
    NoticeInlineAnswerTrigger: function(notice) {
        // Find the notice we're replying to...
        var id = $($('.notice_id', notice)[0]).text();

        console.log("NoticeInlineAnswerTrigger - answering notice " + id);

        var parentNotice = notice;

        // Find the threaded replies view we'll be adding to...
        var list = notice.closest('.notices');

        if (list.hasClass('threaded-replies')) {
            console.log("NoticeInlineAnswerTrigger - there's already a threaded-replies ul above me");
            // We're replying to a reply; use reply form on the end of this list.
            // We'll add our form at the end of this; grab the root notice.
            parentNotice = list.closest('.notice');
            console.log("NoticeInlineAnswerTrigger - trying to find the closest .notice above me");
            if (parentNotice.length > 0) {
                console.log("NoticeInlineAnswerTrigger - found that closest .notice - length = " + parentNotice.length);
            }
        } else {
            console.log("NoticeInlineAnswerTrigger - this notice does not have a threaded-reples ul");
            // We're replying to a parent notice; pull its threaded list
            // and we'll add on the end of it. Will add if needed.
            list = $('ul.threaded-replies', notice);
        }
        
        // See if the form's already open...
        var answerForm = $('.qna_answer_form', list);

        var hideReplyPlaceholders = function(notice) {
            // Do we still have a dummy answer placeholder? If so get rid of
            // reply place holders for this question. If the current user hasn't
            // answered the question we want to direct her to providing an
            // answer. She can still reply by hitting the reply button if she
            // really wants to.
            var dummyAnswer = $('ul.qna-dummy', notice);
            if (dummyAnswer.length > 0) {
                console.log("hiding any reply placeholders");
                notice.find('li.notice-reply-placeholder').hide();
            }
        }


        var nextStep = function() {
            console.log("NoticeInlineAnswerTrigger (nextStep) - begin");

            var dummyAnswer = $('ul.qna-dummy', notice);
            dummyAnswer.hide();

             // Set focus...
            var text = answerForm.find('textarea');

            if (text.length == 0) {
                throw "No textarea";
            }

            text.focus();

            console.log("NoticeInlineAnswerTrigger (nextStep) - setting up body click handler to hide open form when clicking away");
            $('body').click(function(e) {
                console.log("body click handler - got click");

                hideReplyPlaceholders(notice);

                var openAnswers = $('li.notice-answer');
                    if (openAnswers.length > 0) {
                        console.log("body click handler - Found one or more open answer forms to close");
                        var target = $(e.target);

                        openAnswers.each(function() {
                            console.log("body click handler - found an open answer form");
                            // Did we click outside this one?
                            var answerItem = $(this);
                            var parentNotice = answerItem.closest('li.notice');
                            
                            if (answerItem.has(e.target).length == 0) {
                                var textarea = answerItem.find('.notice_data-text:first');
                                var cur = $.trim(textarea.val());
                                // Only close if there's been no edit.
                                if (cur == '' || cur == textarea.data('initialText')) {
                                    console.log("body click handler - no text in answer form, closing it");
                                    answerItem.remove();
                                    console.log("body click handler - showing dummy placeholder");
                                    dummyAnswer.show();
                                    
                                } else {
                                    console.log("body click handler - there is text in the answer form, wont close it");
                                }
                            }
                        });
                    }

                console.log('body click handler - exit');
                });
        };

        // See if the form's already open...

        if (answerForm.length > 0 ) {
            console.log("NoticeInlineAnswerTrigger - found an open .notice-answer-form - doing nextStep()");
            nextStep();
        } else {

            console.log("NoticeInlineAnswerTrigger - hiding the dummy placeholder");
            var placeholder = list.find('li.qna-dummy-placeholder').hide();

            // Create the answer form entry at the end

            var answerItem = $('li.notice-answer', list);

            if (answerItem.length > 0) {
                console.log("NoticeInlineAnswerTrigger - Found " + answerItem.length + " answer items (notice-answer li)");
            }

            if (answerItem.length == 0) {
                 console.log("NoticeInlineAnswerTrigger - no answer item (notice-answer li)");
                 answerItem = $('<li class="notice-answer"></li>');

                 var intermediateStep = function(formMaster) {

                     // cache it
                     //if (!QnA.AnswerFormMaster) {
                     //    QnA.AnswerFormMaster = formMaster;
                     //}


                     console.log("NoticeInlineAnswerTrigger - (intermediate) step begin");
                     var formEl = document._importNode(formMaster, true);

                     console.log("NoticeInlineAnswerTrigger - (intermediate step) appending answer form to answer item");
                     $(formEl).data('NoticeFormSetup', true);

                     answerItem.append(formEl);

                     console.log("NoticeInlineAnswerTrigger - (intermediate step) appending answer to replies list, after placeholder");
                     list.prepend(answerItem); // *before* the placeholder
                     var form = answerForm = $(formEl);
                     console.log("NoticeInlineAnswerTrigger - (intermediate step) calling QnA.AnswerFormSetup on the form")
                     QnA.AnswerFormSetup(form);
                     console.log("NoticeInlineAnswerTrigger - (intermediate step) calling nextstep()");
                     nextStep();
                 };

                 if (QnA.AnswerFormMaster) {
                     console.log("NoticeInlineAnswerTrigger - found a cached copy of the answer form");
                     // We've already saved a master copy of the form.
                     // Clone it in!
                     console.log("NoticeInlineAnswerTrigger - calling intermediateStep with cached form");
                     intermediateStep(QnA.AnswerFormMaster);
                 } else {
                     // Fetch a fresh copy of the answer form over AJAX.
                     // Warning: this can have a delay, which looks bad.
                     // @fixme this fallback may or may not work
                     var url = $('#answer-action').attr('value');

                     console.log("NoticeInlineAnswerTrigger - fetching new form via HXR");

                     $.get(url, {ajax: 1}, function(data, textStatus, xhr) {
                         console.log("NoticeInlineAnswerTrigger - got a new form via HXR, calling intermediateStep");
                         intermediateStep($('form', data)[0]);
                     });
                 }
             }
         }
         console.log('NoticeInlineAnswerTrigger - exit');

     },

    /**
     * Setup function -- DOES NOT apply immediately.
     *
     * Sets up event handlers for inline reply mini-form placeholders.
     * Uses 'live' rather than 'bind', so applies to future as well as present items.
     */
    NoticeInlineAnswerSetup: function() {
        console.log("NoticeInlineAnswerSetup - begin");
        $('li.qna-dummy-placeholder input.placeholder')
            .live('focus', function() {
                var notice = $(this).closest('li.notice');
                QnA.NoticeInlineAnswerTrigger(notice);
                return false;
            });
        console.log("NoticeInlineAnswerSetup - exit");
    },

    AnswerFormSetup: function(form) {
        console.log("AnswerFormSetup");
        form.find('textarea').focus();

        if (!form.data('NoticeFormSetup')) {
            alert('gargargar');
        }

        if (!form.data('AnswerFormSetup')) {
            //SN.U.NoticeLocationAttach(form);
            QnA.FormAnswerXHR(form);
            //SN.U.FormNoticeEnhancements(form);
            //SN.U.NoticeDataAttach(form);
            form.data('NoticeFormSetup', true);
        }
    },

   /**
     * Setup function -- DOES NOT trigger actions immediately.
     *
     * Sets up event handlers for special-cased async submission of the
     * answer-posting form, including some pre-post validation.
     *
     * @fixme geodata
     * @fixme refactor and unify with FormNoticeXHR in util.js
     *
     * @param {jQuery} form: jQuery object whose first element is a form
     *
     * @access public
     */
    FormAnswerXHR: function(form) {
        console.log("FormAanwerXHR - begin");
        //SN.C.I.NoticeDataGeo = {};
        form.append('<input type="hidden" name="ajax" value="1"/>');
        console.log("FormAnswerXHR - appended ajax flag to form");

        // Make sure we don't have a mixed HTTP/HTTPS submission...
        form.attr('action', SN.U.RewriteAjaxAction(form.attr('action')));
        console.log("FormAnswerXHR rewrote action so we don't have a mixed HTTP/HTTPS submission");

        /**
         * Show a response feedback bit under the new-notice dialog.
         *
         * @param {String} cls: CSS class name to use ('error' or 'success')
         * @param {String} text
         * @access private
         */
        var showFeedback = function(cls, text) {
            form.append(
                $('<p class="form_response"></p>')
                    .addClass(cls)
                    .text(text)
            );
        };

        /**
         * Hide the previous response feedback, if any.
         */
        var removeFeedback = function() {
            form.find('.form_response').remove();
        };

        console.log("FormAnswerXHR - doing ajaxForm call");

        form.ajaxForm({
            dataType: 'xml',
            timeout: '60000',

            beforeSend: function(formData) {
                console.log("FormAnswerXHR - beforeSend");
                if (form.find('.notice_data-text:first').val() == '') {
                    form.addClass(SN.C.S.Warning);
                    return false;
                }
                form
                    .addClass(SN.C.S.Processing)
                    .find('.submit')
                        .addClass(SN.C.S.Disabled)
                        .attr(SN.C.S.Disabled, SN.C.S.Disabled);
                return true;
            },
            error: function (xhr, textStatus, errorThrown) {
                form
                    .removeClass(SN.C.S.Processing)
                    .find('.submit')
                        .removeClass(SN.C.S.Disabled)
                        .removeAttr(SN.C.S.Disabled, SN.C.S.Disabled);
                removeFeedback();
                if (textStatus == 'timeout') {
                    // @fixme i18n
                    showFeedback('error', 'Sorry! We had trouble sending your notice. The servers are overloaded. Please try again, and contact the site administrator if this problem persists.');
                }
                else {
                    var response = SN.U.GetResponseXML(xhr);
                    if ($('.'+SN.C.S.Error, response).length > 0) {
                        form.append(document._importNode($('.'+SN.C.S.Error, response)[0], true));
                    }
                    else {
                        if (parseInt(xhr.status) === 0 || jQuery.inArray(parseInt(xhr.status), SN.C.I.HTTP20x30x) >= 0) {
                            form
                                .resetForm()
                                .find('.attach-status').remove();
                            SN.U.FormNoticeEnhancements(form);
                        }
                        else {
                            // @fixme i18n
                            showFeedback('error', '(Sorry! We had trouble sending your notice ('+xhr.status+' '+xhr.statusText+'). Please report the problem to the site administrator if this happens again.');
                        }
                    }
                }
            },
            success: function(data, textStatus) {
                console.log("FormAnswerHXR - success");
                removeFeedback();
                var errorResult = $('#'+SN.C.S.Error, data);
                if (errorResult.length > 0) {
                    showFeedback('error', errorResult.text());
                }
                else {

                    // New notice post was successful. If on our timeline, show it!
                    var notice = document._importNode($('li', data)[0], true);
                    console.log("FormAnswerXHR - loaded the notice, now trying to insert it somewhere");

                    var notices = $('#notices_primary .notices:first');

                    console.log("FormAnswerXHR - looking for the closest notice with a notice-answer li");

                    var answerItem = form.closest('li.notice-answer');
                    var questionItem = form.closest('li.question');
                    var dummyAnswer = form.find('ul.qna-dummy').remove();

                    if (answerItem.length > 0) {
                        console.log("FormAnswerXHR - I found the answer li to append to");
                        // If this is an inline reply, remove the form...
                        console.log("FormAnswerXHR - looking for the closest .threaded-replies ul")
                        var list = form.closest('.threaded-replies');
                        console.log("FormAnswerXHR - search list for the answer placeholder")
                       

                        var id = $(notice).attr('id');
                        console.log("FormAnswerXHR - the new notice id is: " + id);

                        if ($("#"+id).length == 0) {
                            console.log("FormAnswerXHR - the notice is not there already so realtime hasn't inserted it before us");
                            console.log("FormAnswerXHR - inserting new notice before placeholder");
                            $(notice).insertBefore(answerItem);
                            answerItem.remove();

                            SN.U.NoticeInlineReplyPlaceholder(questionItem);
                           
                        } else {
                            // Realtime came through before us...
                        }
                        
                    } else if (notices.length > 0 && SN.U.belongsOnTimeline(notice)) {
                        console.log('FormAnswerXHR - there is at least one notice on the timeline and the new notice should be added to the list');
                        // Not a reply. If on our timeline, show it at the
                        if ($('#'+notice.id).length === 0) {
                            console.log("FormAnswerXHR - The notice is not yet on the timeline.")
                            var notice_irt_value = form.find('#inreplyto').val();
                            console.log("FormAnswerXHR - getting value from #inreplyto inside the form: " + notice_irt_value);
                            var notice_irt = '#notices_primary #notice-'+notice_irt_value;
                            console.log("notice_irt selector = " + notice_irt_value);
                            if($('body')[0].id == 'conversation') {
                                console.log("FormAnswerXHR - we're on a conversation page");
                                if(notice_irt_value.length > 0 && $(notice_irt+' .notices').length < 1) {
                                    $(notice_irt).append('<ul class="notices"></ul>');
                                }
                                console.log("FormAnswerXHR - appending notice after notice_irt selector");
                                $($(notice_irt+' .notices')[0]).append(notice);
                            }
                            else {
                                console.log("FormAnswerXHR prepending notice to top of the notice list");
                                notices.prepend(notice);
                            }
                            $('#'+notice.id)
                                .css({display:'none'})
                                .fadeIn(2500);
                        }

                        // realtime injected the notice first

                    } else {
                        // Not on a timeline that this belongs on?
                        // Just show a success message.
                        // @fixme inline
                        showFeedback('success', $('title', data).text());
                    }

                    //form.resetForm();
                    //SN.U.FormNoticeEnhancements(form);
                }
            },
            complete: function(xhr, textStatus) {
                form
                    .removeClass(SN.C.S.Processing)
                    .find('.submit')
                        .removeAttr(SN.C.S.Disabled)
                        .removeClass(SN.C.S.Disabled);
            }
        });
    }

};

$(document).ready(function() {
    QnA.init();
});
