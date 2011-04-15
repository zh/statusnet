
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
                        console.log("Found and open answer to close");
                        var target = $(e.target);
                        openAnswers.each(function() {
                            console.log("found an open answer");
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
            console.log('finished dealing with body click');
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
    },

    AnswerFormSetup: function(form) {
        console.log("AnswerFormSetup");
        $('input[type=submit]').live('click', function() {
            console.log("AnswerFormSetup click");
            QnA.FormAnswerXHR(form);
        });
    },


   /**
     * Setup function -- DOES NOT trigger actions immediately.
     *
     * Sets up event handlers for special-cased async submission of the
     * notice-posting form, including some pre-post validation.
     *
     * Unlike FormXHR() this does NOT submit the form immediately!
     * It sets up event handlers so that any method of submitting the
     * form (click on submit button, enter, submit() etc) will trigger
     * it properly.
     *
     * Also unlike FormXHR(), this system will use a hidden iframe
     * automatically to handle file uploads via <input type="file">
     * controls.
     *
     * @fixme tl;dr
     * @fixme vast swaths of duplicate code and really long variable names clutter this function up real bad
     * @fixme error handling is unreliable
     * @fixme cookieValue is a global variable, but probably shouldn't be
     * @fixme saving the location cache cookies should be split out
     * @fixme some error messages are hardcoded english: needs i18n
     * @fixme special-case for bookmarklet is confusing and uses a global var "self". Is this ok?
     *
     * @param {jQuery} form: jQuery object whose first element is a form
     *
     * @access public
     */
    FormAnswerXHR: function(form) {
        console.log("FormAanwerXHR - begin");
        //SN.C.I.NoticeDataGeo = {};
        form.append('<input type="hidden" name="ajax" value="1"/>');
        console.log("appended ajax flag");

        // Make sure we don't have a mixed HTTP/HTTPS submission...
        form.attr('action', SN.U.RewriteAjaxAction(form.attr('action')));
        console.log("rewrote action");

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

        console.log("doing ajax");

        form.ajaxForm({
            dataType: 'xml',
            timeout: '60000',
            beforeSend: function(formData) {
                console.log("beforeSend");
                if (form.find('.notice_data-text:first').val() == '') {
                    form.addClass(SN.C.S.Warning);
                    return false;
                }
                form
                    .addClass(SN.C.S.Processing)
                    .find('.submit')
                        .addClass(SN.C.S.Disabled)
                        .attr(SN.C.S.Disabled, SN.C.S.Disabled);

                SN.U.normalizeGeoData(form);

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
                console.log("FormAnswerHXR success");
                removeFeedback();
                var errorResult = $('#'+SN.C.S.Error, data);
                if (errorResult.length > 0) {
                    showFeedback('error', errorResult.text());
                }
                else {

                    // New notice post was successful. If on our timeline, show it!
                    var notice = document._importNode($('li', data)[0], true);

                    var notices = $('#notices_primary .notices:first');
                    var replyItem = form.closest('li.notice-reply');

                    if (replyItem.length > 0) {
                        console.log("I found a reply li to append to");
                        // If this is an inline reply, remove the form...
                        var list = form.closest('.threaded-replies');
                        var placeholder = list.find('.notice-answer-placeholder');
                        replyItem.remove();

                        var id = $(notice).attr('id');
                        console.log("got notice id " + id);
                        if ($("#"+id).length == 0) {
                            console.log("inserting before placeholder");
                            $(notice).insertBefore(placeholder);
                        } else {
                            // Realtime came through before us...
                        }

                        // ...and show the placeholder form.
                        placeholder.show();
                        console.log('qqqq made it this far')
                    } else if (notices.length > 0 && SN.U.belongsOnTimeline(notice)) {
                        // Not a reply. If on our timeline, show it at the top!
                        if ($('#'+notice.id).length === 0) {
                            console.log("couldn't find a notice id for " + notice.id);
                            var notice_irt_value = form.find('#inreplyto').val();
                            var notice_irt = '#notices_primary #notice-'+notice_irt_value;
                            console.log("notice_irt value = " + notice_irt_value);
                            if($('body')[0].id == 'conversation') {
                                console.log("found conversation");
                                if(notice_irt_value.length > 0 && $(notice_irt+' .notices').length < 1) {
                                    $(notice_irt).append('<ul class="notices"></ul>');
                                }
                                $($(notice_irt+' .notices')[0]).append(notice);
                            }
                            else {
                                console.log("prepending notice")
                                notices.prepend(notice);
                            }
                            $('#'+notice.id)
                                .css({display:'none'})
                                .fadeIn(2500);
                        }
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

                form.find('[name=lat]').val(SN.C.I.NoticeDataGeo.NLat);
                form.find('[name=lon]').val(SN.C.I.NoticeDataGeo.NLon);
                form.find('[name=location_ns]').val(SN.C.I.NoticeDataGeo.NLNS);
                form.find('[name=location_id]').val(SN.C.I.NoticeDataGeo.NLID);
                form.find('[name=notice_data-geo]').attr('checked', SN.C.I.NoticeDataGeo.NDG);
            }
        });
    }

};

$(document).ready(function() {
    QnA.init();
});
