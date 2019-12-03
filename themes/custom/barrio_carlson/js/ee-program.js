/**
 * @file
 * Tabbed Content utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

    $('.ee-program__date--details--view-only-btn').click(function () {
        var $details = $(this).closest('.ee-program__date--details');
        console.log('details length: ' + $details.length, 'VO button length: ' + $details.find('.ee-program__date--details--view-only-btn').length, 'action button length: ' + $details.find('.ee-program__date--details--action-btn').length);
        $details.find('.ee-program__date--details--view-only-btn').hide();
        $details.find('.ee-program__date--details--action-btn').addClass('do-show');
        return false;
    });

    var eeMenu = {
      init:function(){
        $('.submenu-toggle').click(function(){ var li = $(this).parent('.nav-item'); eeMenu.menuToggle(li); });
      },
      menuToggle:function(li){
        li.toggleClass('open');
        li.find('.menu').slideToggle();
      }
    }
    eeMenu.init();

    var eeSliders = {
      init:function(){
        $('.nav-tabs .nav-link').click(function(e){ e.preventDefault(); eeSliders.tabs($(this));});
        $('.nav-tabs .nav-link[href="#faculty"]').click(function(){ var slider = '#staff-faculty-list .faculty-wrapper'; var slide = '.staff-faculty__wrapper'; eeSliders.build(slider, slide); });
        $('.nav-tabs .nav-link[href="#schedule"]').click(function(){ var slider = '.tab-pane .course_schedule'; var slide = '.paragraph--type--course-schedule-info'; eeSliders.build(slider, slide); });
        eeSliders.build('.tab-pane .course_schedule', '.paragraph--type--course-schedule-info');
      },
      build:function(slider, slide){
        $(slider).slick({
          slide: slide,
          slidesToShow: 3,
          slidesToScroll: 1,
          autoplay: false,
          infinite: false,
          responsive: [
           {
             breakpoint: 993,
             settings: {
               slidesToShow: 2,
               slidesToScroll: 1,
             }
           },
           {
             breakpoint: 768,
             settings: {
               slidesToShow: 1,
               slidesToScroll: 1,
             }
           },
         ]
        });
      },
      tabs:function(tab){
        $('.nav-tabs .nav-link').removeClass('active');
        tab.addClass('active');
        var id = tab.attr('href');
        $('.tab-pane').removeClass('active').addClass('fade');
        $(id).removeClass('fade').addClass('active');
      }
    }
    eeSliders.init();

})(jQuery, Drupal);
