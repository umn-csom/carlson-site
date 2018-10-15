/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.bootstrap_barrio_subtheme = {
    attach: function (context, settings) {

      $('.page-node-1 .carousel').wrap('<div class="col-12 col-md-10 col-lg-8" />');
      $('.path-node .node--type-page').addClass('row');

var classes = ['random1','random2', 'random3']; //add as many classes as u want
var randomnumber = Math.floor(Math.random()*classes.length);
$('body').addClass(classes[randomnumber]);

$('.path-galerias #block-sass-starterkit-content').append("<div id='blueimp-gallery' class='blueimp-gallery'><div class='slides' /><h3 class='title' /><a class='prev'>‹</a><a class='next'>›</a><a class='close'>×</a><a class='play-pause' /><ol class='indicator' /></div>");
$('.view-galerias').attr('id', 'links');

$('.triple .view-content').addClass('ml-sm-1 mr-sm-1');

// document.getElementById('links').onclick = function (event) {
//     event = event || window.event;
//     var target = event.target || event.srcElement,
//         link = target.src ? target.parentNode : target,
//         options = {index: link, event: event},
//         links = this.getElementsByTagName('a');
//     blueimp.Gallery(links, options);
// };



    }
  };


    $(window).on('scroll', function () {
        if ($(window).scrollTop() >= 10) {
            $('.carlson-header, .umnhf-campus-tc').addClass('compressed');
        } else {
            $('.carlson-header, .umnhf-campus-tc').removeClass('compressed');
        }
    });

    $('.we-megamenu-nolink').on('click', function(e) {
        $(this).addClass('slide-left');
        $(this).next().addClass('slide-in');
        var label = $(this).html();
        $(this).next().prepend('<span class="mobile-third-tier-menu__label">' + label + '</span>');
        $(this).next().prepend('<hr class="mobile-third-tier-menu__hr" />');
        $(this).next().prepend('<button class="button mobile-third-tier-menu__back-btn">BACK</button>');
        $('.we-mega-menu-ul').addClass('slide-left');
    });

    $(document).on('click', '.overlay', function(e) {
        $('.we-mega-menu-ul').removeClass('slide-left');
    });

    $(document).on('click', '.mobile-third-tier-menu__back-btn', function(e) {
        e.preventDefault();
        var self = $(this);
        self.parent().removeClass('slide-in');
        self.parent().addClass('fade-out');

        setTimeout(function() {
            self.parent().prev().removeClass('slide-left');
            $('.we-mega-menu-ul').removeClass('slide-left');
            self.parent().removeClass('fade-out');
            $('.mobile-third-tier-menu__hr').remove();
            $('.mobile-third-tier-menu__label').remove();
            self.remove();
        }, 800);
    });

    $('.carlson-nav .navbar .we-mega-menu-li').mouseenter(function() {
        $('body').css('overflow', 'hidden');

        var offset = $('.carlson-nav .navbar').offset().top;
        offset = ( ( offset + 15 ) - $(window).scrollTop() );
        $('.carlson-nav .navbar .we-mega-menu-submenu').css('top', offset + 'px');
    });

    $('.carlson-nav .navbar .we-mega-menu-li').mouseleave(function() {
        $('body').css('overflow', 'inherit');
    });

    $('.carlson-nav .navbar .we-mega-menu-submenu').mousemove(function() {
        $('body').css('overflow', 'hidden');

        var offset = $('.carlson-nav .navbar').offset().top;
        offset = ( ( offset + 15 ) - $(window).scrollTop() );
        $(this).css('top', offset + 'px');
    });

    $('.carlson-nav .navbar .we-mega-menu-submenu').mouseleave(function() {
        $('body').css('overflow', 'inherit');
    });

    $('.subul').each(function() {
        var inc = $(this).children().length;
        $(this).children('li').each(function() {
            $(this).css('z-index', inc--);
        });
    });

    $('.carlson-nav .navbar .we-mega-menu-li .we-mega-menu-submenu .we-mega-menu-li').mousemove(function() {
        $(this).css('z-index', 9995);
        $(this).children().last().children().children().children().children().addClass('show');
    });

    $('.carlson-nav .navbar .we-mega-menu-li .we-mega-menu-submenu .we-mega-menu-li').mouseleave(function() {
        $(this).css('z-index', 'inherit');
        $(this).children().last().children().children().children().children().removeClass('show');
    });

})(jQuery, Drupal);
