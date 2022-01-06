/**
 * @file
 * carlsonQuiz.
 *
 */

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.carlsonQuiz = {
    attach: function (context, settings) {

      $('[data-toggle="popover"]').popover({ trigger: "manual" , html: true})
        .on("mouseenter", function () {
        var _this = this;
        $(this).popover("show");
        $(".popover").on("mouseleave", function () {
          $(_this).popover('hide');
        });
      }).on("mouseleave", function () {
        var _this = this;
        setTimeout(function () {
          if (!$(".popover:hover").length) {
            $(_this).popover("hide");
          }
        }, 300);
      });

      $('.quiz--answer', context).on('click', function (e) {
        var checkbox = $(this).find('input');
        var label = $(this).find('label');
        if(e.target !== checkbox[0] && e.target !== label[0]) {
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
          questions.each(function () {
            if($(this).find('.quiz--answer.checked').length > 0) {
              progress++;
            }
          });
          var progressPercent = Math.floor(progress*100/questions.length)
          $('.quiz--progress-bar--progress-number', context).text(progressPercent);
          $('.quiz--progress-bar--bar', context)[0].style.setProperty('--seek-width', progressPercent + '%');
        }

        if($(this).closest('.quiz--question').find('.quiz--answer.checked').length > 0) {
          $(this).closest('.quiz--question').addClass('checked');
        }
        else {
          $(this).closest('.quiz--question').removeClass('checked');
        }
      });

      $('.quiz--question--prev, .quiz--question--next', context).on('click', function (event) {
        if (this.hash !== "") {
          event.preventDefault();
          var hash = this.hash;
          $('html, body').animate({
            scrollTop: $(hash).offset().top - 200
          }, 800, function () {
          });
        }
      });

      $('.quiz--main-img--inner', context).stick_in_parent({
        parent: '.quiz--main-img',
        offset_top: 75
      });

      var offset = $(window).outerWidth() > 991 ? $(window).outerHeight() - $('.quiz--progress-bar', context).first().outerHeight() : $('#carlson-navbar', context).outerHeight();
      $('.quiz--progress-bar', context).stick_in_parent({
        parent: '.node__content',
        spacer: '.quiz--progress-bar--spacer',
        offset_top: offset
      });

      $(window).on('resize orientationchange', function () {
        var newOffset = $(window).outerWidth() > 991 ? $(window).outerHeight() - $('.quiz--progress-bar', context).first().outerHeight() : $('#carlson-navbar', context).outerHeight();
        if(offset !== newOffset) {
          offset = newOffset;
          $('.quiz--progress-bar', context).trigger("sticky_kit:detach");
          $('.quiz--progress-bar', context).stick_in_parent({
            parent: '.node__content',
            spacer: '.quiz--progress-bar--spacer',
            offset_top: offset
          });
        }
      });

      document.addEventListener('invalid', function(e) {
        $('html, body').animate({scrollTop: $('.quiz--question:not(.checked)', context).first().offset().top - 200 }, 0);
      }, true);

    }
  };
}(jQuery, Drupal));
