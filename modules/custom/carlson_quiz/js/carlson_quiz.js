/**
 * @file
 * carlsonQuiz.
 */

(function ($, Drupal) {

    'use strict';

    Drupal.behaviors.carlsonQuiz = {
        attach: function (context, settings) {

            var numbers = [
            'zero',
            'one',
            'two',
            'three',
            'four',
            'five'
            ];

            var count = 0;

            $('[data-toggle="popover"]').popover({ trigger: "manual" , html: true})
            .on(
                "mouseenter", function () {
                    var _this = this;
                    $(this).popover("show");
                    $(".popover").on(
                        "mouseleave", function () {
                            $(_this).popover('hide');
                        }
                    );
                }
            ).on(
                "mouseleave", function () {
                    var _this = this;
                    setTimeout(
                        function () {
                            if (!$(".popover:hover").length) {
                                $(_this).popover("hide");
                            }
                        }, 300
                    );
                }
            );

            $('.quiz--answer', context).on(
                'click', function (e) {
                    var checkbox = $(this).find('input');
                    var label = $(this).find('label');
                    if(e.target !== checkbox[0] && e.target !== label[0] && !checkbox.is(':disabled')) {
                        if(checkbox.attr('type') === 'radio') {
                            checkbox[0].checked = true;
                        }
                        else {
                            checkbox[0].checked = !checkbox[0].checked;
                        }
                    }
                    checkbox.trigger('change');
                    $(this).closest('.quiz--question').find('.quiz--answer').removeClass('checked');
                    $(this).closest('.quiz--question').find('.quiz--answer input:checked').closest('.quiz--answer').addClass('checked');

                    var questions = $('.quiz--question.required', context);
                    if(questions.length > 0) {
                        var progress = 0;
                        questions.each(
                            function () {
                                if($(this).data('limit') !== 'undefined'  
                                    && (($(this).data('limit-type') === 'at_least')  || $(this).data('limit-type') === 'exactly')
                                ) {
                                    if($(this).find('.quiz--answer.checked').length >= $(this).data('limit')) {
                                          progress++;
                                    }
                                }
                                else if($(this).find('.quiz--answer.checked').length > 0) {
                                    progress++;
                                }
                            }
                        );
                        var progressPercent = Math.floor(progress*100/questions.length)
                        $('.quiz--progress-bar--progress-number', context).text(progressPercent);
                        $('.quiz--progress-bar--bar', context)[0].style.setProperty('--seek-width', progressPercent + '%');
                    }

                    let num_checked = $(this).closest('.quiz--question').find('.quiz--answer.checked').length;
                    if(num_checked > 0) {
                        let limit_type = $(this).closest('.quiz--question').data('limit-type');
                        if ((limit_type === 'at_least' || limit_type === 'exactly')
                            && num_checked < $(this).closest('.quiz--question').data('limit')){
                            $(this).closest('.quiz--question').removeClass('checked');
                        } else {
                            $(this).closest('.quiz--question').addClass('checked');
                        }
                    }
                    else {
                        $(this).closest('.quiz--question').removeClass('checked');
                    }

                    if($(this).closest('.quiz--question').data('limit') !== 'undefined'  
                        && ($(this).closest('.quiz--question').data('limit-type') === 'up_to' || $(this).closest('.quiz--question').data('limit-type') === 'exactly')
                    ) {
                        if($(this).closest('.quiz--question').find('.quiz--answer input:checked').length >= $(this).closest('.quiz--question').data('limit')) {
                            $(this).closest('.quiz--question').find('.quiz--answer input').not(":checked").attr("disabled",true);
                            $(this).closest('.quiz--question').find('.quiz--answer input').not(":checked").closest('.quiz--answer').addClass("disabled");
                        }
                        else {
                            $(this).closest('.quiz--question').find('.quiz--answer input').not(":checked").removeAttr('disabled');
                            $(this).closest('.quiz--question').find('.quiz--answer input').not(":checked").closest('.quiz--answer').removeClass("disabled");
                        }
                    }

                }
            );

            // $('.quiz--question--prev, .quiz--question--next', context).on(
            //     'click', function (event) {
            //         if (this.hash !== "") {
            //             event.preventDefault();
            //             var hash = this.hash;
            //             $('html, body').animate(
            //                 {
            //                     scrollTop: $(hash).offset().top + $("body").scrollTop() - 200
            //                 }, 800, function () {
            //                 }
            //             );
            //         }
            //     }
            // );

            // $('.quiz--main-img--inner', context).stick_in_parent(
            //     {
            //         parent: '.quiz--main-img',
            //         offset_top: 75
            //     }
            // );

            var offset = $(window).outerWidth() > 991 ? $(window).outerHeight() - $('.quiz--progress-bar', context).first().outerHeight() : $('#carlson-navbar', context).outerHeight();
            $('.quiz--progress-bar', context).stick_in_parent(
                {
                    parent: '.node__content',
                    spacer: '.quiz--progress-bar--spacer',
                    offset_top: offset
                }
            );

            $(window).on(
                'resize orientationchange', function () {
                    var newOffset = $(window).outerWidth() > 991 ? $(window).outerHeight() - $('.quiz--progress-bar', context).first().outerHeight() : $('#carlson-navbar', context).outerHeight();
                    if(offset !== newOffset) {
                        offset = newOffset;
                        $('.quiz--progress-bar', context).trigger("sticky_kit:detach");
                        $('.quiz--progress-bar', context).stick_in_parent(
                            {
                                parent: '.node__content',
                                spacer: '.quiz--progress-bar--spacer',
                                offset_top: offset
                            }
                        );
                    }
                }
            );

            document.addEventListener(
                'invalid', function (e) {
                    $('html, body').animate({scrollTop: $('.quiz--question:not(.checked)', context).first().offset().top + $("body").scrollTop() - 200 }, 0);
                    $('.quiz--question:not(.checked)', context).first().find('.quiz--answer').first().find('button.quiz--answer--popover-btn').popover('show');
                }, true
            );

            $('.quiz--question', context).each(
                function () {
                    var requiredError = 'Please select an answer before proceeding';
                    if($(this).data('limit') !== 'undefined' && $(this).data('limit-type') === 'at_least') {
                        requiredError = 'Please select ' + $(this).data('limit') + ' or more answers before proceeding';
                    }
                    if($(this).data('limit') !== 'undefined' && $(this).data('limit-type') === 'exactly') {
                        requiredError = 'Please select ' + $(this).data('limit') + ' answers before proceeding';
                    }
                    $(this).find('.quiz--answer').first().append('<button type="button" class="quiz--answer--popover-btn border-0 p-0 order-last" data-toggle="popover" data-trigger="hover" data-placement="bottom" data-content="'+ requiredError +'"><span class="sr-only">Required</span></button>');
                }
            );

            window.addEventListener(
                'load', function () {
                    $('.quiz--answer--popover-btn', context).popover();

                    var forms = document.getElementsByClassName('webform-submission-form');

                    var validation = Array.prototype.filter.call(
                        forms, function (form) {
                            form.addEventListener(
                                'submit', function (event) {
                                    let submitting = true;
                                    if (form.checkValidity() === false) {
                                        event.preventDefault();
                                        event.stopPropagation();
                                        submitting = false;
                                    }
                                    else if($('.quiz--question.required[data-limit-type="at_least"]', context).length > 0) {
                                        $('.quiz--question.required[data-limit-type="at_least"]', context).each(
                                            function () {
                                                if($(this).find('.quiz--answer input:checked').length < $(this).data('limit')) {
                                                        event.preventDefault();
                                                        event.stopPropagation();
                                                        submitting = false;
                                                        $('html, body').animate({scrollTop: $(this).offset().top  + $("body").scrollTop() - 200 }, 0);
                                                        $(this).find('.quiz--answer').first().find('button.quiz--answer--popover-btn').popover('show');
                                                        return false;
                                                }
                                            }
                                        );
                                    }
                                    else if($('.quiz--question.required[data-limit-type="exactly"]', context).length > 0) {
                                        $('.quiz--question.required[data-limit-type="exactly"]', context).each(
                                            function () {
                                                if($(this).find('.quiz--answer input:checked').length < $(this).data('limit')) {
                                                      event.preventDefault();
                                                      event.stopPropagation();
                                                      submitting = false;
                                                      $('html, body').animate({scrollTop: $(this).offset().top + $("body").scrollTop() - 200 }, 0);
                                                      $(this).find('.quiz--answer').first().find('button.quiz--answer--popover-btn').popover('show');
                                                      return false;
                                                }
                                            }
                                        );
                                    }
                                    if (submitting) {

                                        var utm_fields = [
                                            'utm_source',
                                            'utm_medium',
                                            'utm_term',
                                            'utm_content',
                                            'utm_campaign'
                                        ]

                                        var url_string = window.location.href;
                                        var url = new URL(url_string);

                                        utm_fields.forEach(utm_type => {
                                            let url_value = url.searchParams.get(utm_type);
                                            $('[name='+utm_type+']').val(url_value);
                                        })

                                        form.classList.add('was-validated');
                                        let submit_button = $(this).find(':submit')
                                        submit_button.attr('disabled', true);
                                        setInterval(
                                            function () {
                                                count++;
                                                var dots = new Array(count % 10).join('.');
                                                submit_button.val("Submitting" + dots);
                                            }, 500
                                        );
                                    }
                                }, false
                            );
                        }
                    );
                }, false
            );

            $(document).on(
                'click','body *',function () {
                    $('button.quiz--answer--popover-btn', context).popover('hide');
                }
            );

        }
    };
}(jQuery, Drupal));
