/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

    function setExpandedMenuHeight(height) {
        var mainHeight = $('.carlson-nav .region-we-mega-menu .main').height();
        var halfHeight = (mainHeight / 2);

        setTimeout(function () {
            if (height > halfHeight) {
                $('.carlson-nav .region-we-mega-menu .navbar .container-fluid').css('min-height', (height + mainHeight + 75 + 'px'));
            } else {
                $('.carlson-nav .region-we-mega-menu .navbar .container-fluid').css('min-height', '600px');
            }
        }, 500);
    }

    function setSticky() {
        var isSticky = false;
        if (!$('html').hasClass((!isSticky) ? 'lock-screen' : 'lock-screen-sticky')) {
            var top = $(window).scrollTop();
            isSticky = (top > 0) ? true : false;

            if (top > 0) {
                $('.carlson-header').addClass('sticky');
            } else {
                $('.carlson-header').removeClass('sticky');
            }
        }
    }

    function resetSubUl() {
        $('.subul').each(function () {
            var inc = $(this).children().length;
            $(this).children('li').each(function () {
                $(this).css('z-index', inc--);
            });
        });
    }

    function resetDrawers() {
        $('body').css('overflow', 'inherit');
        $('.carlson-nav .navbar .we-mega-menu-submenu').each(function() {
            $(this).removeClass('show');
            $(this).css('top', '-99999px');
        })
    }

    function setup() {
        var isDesktop = (($(window).width() > 1024) ? true : false);
        var innerDrawer = false;
        var rolloutTimer = null;

        if (!isDesktop) {
            $('.we-megamenu-nolink').on('click', function (e) {
                $(this).addClass('slide-left');
                $(this).next().addClass('slide-in');
                var label = $(this).html();
                $(this).next().prepend('<span class="mobile-third-tier-menu__label">' + label + '</span>');
                $(this).next().prepend('<hr class="mobile-third-tier-menu__hr" />');
                $(this).next().prepend('<button class="button mobile-third-tier-menu__back-btn">BACK</button>');
                $('.we-mega-menu-ul').addClass('slide-left');
            });

            $('.third-tier a.we-mega-menu-li').on('click', function (e) {
                setExpandedMenuHeight($(this).next().height());
            });

            $('.we-mega-menu-li a.we-mega-menu-li').on('click', function (e) {
                setExpandedMenuHeight($(this).next().height());
            });

            $(document).on('click', '.overlay', function (e) {
                $('.we-mega-menu-ul').removeClass('slide-left');
            });

            $(document).on('click', '.mobile-third-tier-menu__back-btn', function (e) {
                e.preventDefault();
                var self = $(this);
                self.parent().removeClass('slide-in');
                self.parent().addClass('fade-out');

                setTimeout(function () {
                    self.parent().prev().removeClass('slide-left');
                    $('.we-mega-menu-ul').removeClass('slide-left');
                    self.parent().removeClass('fade-out');
                    $('.mobile-third-tier-menu__hr').remove();
                    $('.mobile-third-tier-menu__label').remove();
                    self.remove();
                }, 800);
            });
        } else {
            $('html').removeClass('lock-screen');
            $('.navbar-we-mega-menu').removeClass('we-mobile-megamenu-active');
            $('.btn-close').remove();
            $('.overlay').remove();
            $('.container-fluid').css('min-height', '0');

            $('.carlson-nav .navbar .we-mega-menu-li.dropdown-menu a').mouseenter(function (e) {
                e.preventDefault();
                $('body').css('overflow', 'hidden');
                innerDrawer = false;
                clearTimeout(rolloutTimer);
                resetDrawers();
                resetSubUl();

                var offset = $('.carlson-nav .navbar').offset().top;
                var add = (($(window).width() < 1200) ? 25 : 53);
                offset = ((offset + add) - $(window).scrollTop());
                $(this).parent().children().last().css('top', offset + 'px');
                $(this).parent().children().last().addClass('show');
            });

            $('.carlson-nav .navbar .we-mega-menu-li.dropdown-menu a').mouseleave(function (e) {
                e.preventDefault();
                $(this).parent().children().last().removeClass('show');
                $(this).parent().children().last().css('top', '-99999px');
                resetDrawers();
                $('body').css('overflow', 'inherit');
            });

            $('.carlson-nav .navbar .we-mega-menu-submenu').mousemove(function (e) {
                e.preventDefault();
                $('body').css('overflow', 'hidden');
                resetDrawers();

                if(innerDrawer) {
                    rolloutTimer = setTimeout(function() {
                        resetDrawers();
                        innerDrawer = false;
                        clearTimeout(rolloutTimer);
                    }, 3000);
                } else {
                    $(this).addClass('show');
                    var offset = $('.carlson-nav .navbar').offset().top;
                    var add = (($(window).width() < 1200) ? 25 : 53);
                    offset = ((offset + add) - $(window).scrollTop());
                    $(this).css('top', offset + 'px');
                }
            });

            $('.carlson-nav .navbar .we-mega-menu-submenu .we-mega-menu-submenu-inner').mouseleave(function (e) {
                e.preventDefault();
                innerDrawer = true;
                $(this).parent().removeClass('show');
                $(this).parent().css('top', '-99999px');
                $('body').css('overflow', 'inherit');
            });

            $('.carlson-nav .navbar .we-mega-menu-submenu .we-mega-menu-submenu-inner').mousemove(function (e) {
                e.preventDefault();
                innerDrawer = false;
            });

            resetSubUl();

            $('.carlson-nav .navbar .we-mega-menu-li .we-mega-menu-submenu .we-mega-menu-li').mousemove(function (e) {
                e.preventDefault();
                resetSubUl();
                $(this).css('z-index', 9995);
                $(this).children().last().children().children().children().children().addClass('show');

                var offset = $('.carlson-nav .navbar').offset().top;
                var add = (($(window).width() < 1200) ? 75 : 100);
                offset = ((offset + add) - $(window).scrollTop());
                $(this).children().last().children().children().children().children().css('top', offset + 'px');
            });

            $('.carlson-nav .navbar .we-mega-menu-li .we-mega-menu-submenu .we-mega-menu-li').mouseleave(function (e) {
                e.preventDefault();
                resetSubUl();
                $(this).css('z-index', 'inherit');
                $(this).children().last().children().children().children().children().removeClass('show');
                $(this).children().last().children().children().children().children().css('top', '-4000px');
            });
        }
    }

    // For the sticky header.
    $(window).on('wheel', function() {
        setSticky();
    });

    // For touch move.
    $('body').on({
        'touchmove': function(e) {
            setSticky();
        }
    });

    // Scroll top init.
    $(window).resize(setup);
    setSticky();
    setup();

  })(jQuery, Drupal);
