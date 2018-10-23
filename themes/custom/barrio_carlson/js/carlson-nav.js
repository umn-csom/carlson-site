/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

    var isDesktop = ( ( $(window).width() > 768 ) ? true : false );
    
    if(!isDesktop) {
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
            $('.carlson-nav .navbar .container-fluid').css('min-height', 'inherit');
        });
    
        $('.navbar-toggle').on('click', function(e) {
            $('.carlson-nav .navbar .container-fluid').css('min-height', '1100px');
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
    } else {

        $('.carlson-nav .navbar .we-mega-menu-li').mouseenter(function(e) {
            e.preventDefault();
            $('body').css('overflow', 'hidden');

            var offset = $('.carlson-nav .navbar').offset().top;
            var add = ( ( $(window).width() > 1024 ) ? 35 : 40 );
            offset = ( ( offset + add ) - $(window).scrollTop() );
            $('.carlson-nav .navbar .we-mega-menu-submenu').css('top', offset + 'px');
        });

        $('.carlson-nav .navbar .we-mega-menu-li').mouseleave(function(e) {
            e.preventDefault();
            $('body').css('overflow', 'inherit');
        });

        $('.carlson-nav .navbar .we-mega-menu-submenu').mousemove(function(e) {
            e.preventDefault();
            $('body').css('overflow', 'hidden');

            var offset = $('.carlson-nav .navbar').offset().top;
            var add = ( ( $(window).width() > 1024 ) ? 35 : 40 );
            offset = ( ( offset + add ) - $(window).scrollTop() );
            $(this).css('top', offset + 'px');
        });

        $('.carlson-nav .navbar .we-mega-menu-submenu').mouseleave(function(e) {
            e.preventDefault();
            $('body').css('overflow', 'inherit');
        });

        $('.subul').each(function() {
            var inc = $(this).children().length;
            $(this).children('li').each(function() {
                $(this).css('z-index', inc--);
            });
        });

        $('.carlson-nav .navbar .we-mega-menu-li .we-mega-menu-submenu .we-mega-menu-li').mousemove(function(e) {
            e.preventDefault();
            $(this).css('z-index', 9995);
            $(this).children().last().children().children().children().children().addClass('show');

            var offset = $('.carlson-nav .navbar').offset().top;
            offset = ( ( offset + 100 ) - $(window).scrollTop() );
            $(this).children().last().children().children().children().children().css('top', offset + 'px');
        });

        $('.carlson-nav .navbar .we-mega-menu-li .we-mega-menu-submenu .we-mega-menu-li').mouseleave(function(e) {
            e.preventDefault();
            $(this).css('z-index', 'inherit');
            $(this).children().last().children().children().children().children().removeClass('show');
        });
    }

    // For the sticky header.
    $(window).on("mousewheel", function() {
        var top = $(window).scrollTop();
        console.log(top);
        if( top > 0 ) {
            $('.carlson-header').addClass('sticky');
        } else {
            $('.carlson-header').removeClass('sticky');
        }
    });

    // Scroll top init.
    var top = $(window).scrollTop();
    if( top > 0 ) {
        $('.carlson-header').addClass('sticky');
    } else {
        $('.carlson-header').removeClass('sticky');
    }
  
  })(jQuery, Drupal);  