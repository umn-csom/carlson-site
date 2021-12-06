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
    }
  };
}(jQuery, Drupal));
