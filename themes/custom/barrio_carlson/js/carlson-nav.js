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

            if ($('.container.centennial').length == 0) {
                if (top > 0) {
                    $('.carlson-header').addClass('sticky');
                } else {
                    $('.carlson-header').removeClass('sticky');
                }
            } else {
                if (top > 0) {
                    $('.carlson-header .we-mega-menu-ul').hide();
                    $('.carlson-header').slideUp();
                } else {
                    $('.carlson-header').slideDown('400', function () {
                        $('.carlson-header .we-mega-menu-ul').show();
                    });
                }
            }
        }
    }

    function resetTopMenu() {
        $('.carlson-nav .navbar .we-mega-menu-ul').each(function () {
            var inc = $(this).children().length;
            $(this).children('li').each(function () {
                $(this).css('z-index', inc--);
            });
        });
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
        $('.carlson-nav .navbar .we-mega-menu-submenu').each(function() {
            $(this).removeClass('show');
            $(this).css('top', '-99999px');
        });
    }

    function setDefault() {
        var $elm = $('.carlson-nav .navbar .we-mega-menu-submenu li.we-mega-menu-li.dropdown-menu.default');
        $elm.children().last().children().children().children().children().addClass('show');

        var offset = $('.carlson-nav .navbar').offset().top;
        var add = (($(window).width() < 1200) ? 100 : 110);
        offset = ((offset + add) - $(window).scrollTop());
        $elm.children().last().children().children().children().children().css('top', offset + 'px');
    }

    function resetDefaultAll() {
        var $elm = $('.carlson-nav .navbar .we-mega-menu-submenu li.we-mega-menu-li.dropdown-menu.default2');
        $elm.each(function() {
            $(this).removeClass('default2');
            $(this).addClass('default');
        });
    }

    function setDefaultAll() {
        var $elm = $('.carlson-nav .navbar .we-mega-menu-submenu li.we-mega-menu-li.dropdown-menu.default');
        $elm.each(function() {
            $(this).children().last().children().children().children().children().addClass('show');
            var offset = $('.carlson-nav .navbar').offset().top;
            var add = (($(window).width() < 1200) ? 100 : 110);
            offset = ((offset + add) - $(window).scrollTop());
            $(this).children().last().children().children().children().children().css('top', offset + 'px');
        });
    }

    function hideDefault() {
        var $elm = $('.carlson-nav .navbar .we-mega-menu-submenu li.we-mega-menu-li.dropdown-menu.default');
        $elm.children().last().children().children().children().children().removeClass('show');
        $elm.children().last().children().children().children().children().css('top', '-99999px');
        hideDefaultAll();
    }

    function hideDefaultAll() {
        var $elm = $('.carlson-nav .navbar .we-mega-menu-submenu li.we-mega-menu-li.dropdown-menu.default');
        $elm.each(function() {
            $(this).children().last().children().children().children().children().removeClass('show');
            $(this).children().last().children().children().children().children().css('top', '-99999px');
        });
    }

    function setup() {
        var isDesktop = (($(window).width() > 1024) ? true : false);
        var innerDrawer = false;
        var rolloutTimer = null, resetDefault = null;

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
                if( $(this).parent().attr('data-level') !== '2' ) {
                    innerDrawer = false;
                    clearTimeout(rolloutTimer);
                    resetDrawers();
                    resetSubUl();
                    resetTopMenu();
                    $(this).parent().css('z-index', '99');

                    if( $(this).parent().hasClass('default') ) {
                        setDefault();
                    }

                    if( $(this).parent().hasClass('third-tier') ) {
                        resetDefaultAll();
                        setDefaultAll();
                    }

                    var offset = $('.carlson-nav .navbar').offset().top;
                    var add = (($(window).width() < 1200) ? 30 : 35);
                    offset = ((offset + add) - $(window).scrollTop());
                    $(this).parent().children().last().css('top', offset + 'px');
                    $(this).parent().children().last().addClass('show');
                }
            });

            $('.carlson-nav .navbar .we-mega-menu-li.dropdown-menu a').mouseleave(function (e) {
                e.preventDefault();
                if( $(this).parent().attr('data-level') !== '2' ) {
                    hideDefault();
                    $(this).parent().children().last().removeClass('show');
                    $(this).parent().children().last().css('top', '-99999px');
                    resetDrawers();
                    if(!innerDrawer) {
                        $('body').css('overflow', 'inherit');
                    }

                    if( $(this).parent().hasClass('third-tier') ) {
                        resetDefaultAll();
                        setDefaultAll();
                    }
                }
            });

            $('.carlson-nav .navbar .we-mega-menu-submenu').mousemove(function (e) {
                e.preventDefault();
                if(innerDrawer) {
                    clearTimeout(rolloutTimer);
                    rolloutTimer = setTimeout(function() {
                        resetDrawers();
                        innerDrawer = false;
                        clearTimeout(rolloutTimer);
                    }, 3000);
                } else {
                    $(this).addClass('show');
                    var offset = $('.carlson-nav .navbar').offset().top;
                    var add = (($(window).width() < 1200) ? 30 : 35);
                    offset = ((offset + add) - $(window).scrollTop());
                    $(this).css('top', offset + 'px');
                }
            });

            $(window).scroll(function() {
                resetDrawers();
            });

            $('.carlson-nav .navbar .we-mega-menu-submenu .we-mega-menu-submenu-inner').mouseleave(function (e) {
                e.preventDefault();
                innerDrawer = true;
                $(this).parent().removeClass('show');
                $(this).parent().css('top', '-99999px');
                hideDefault();
                $('body').css('overflow', 'inherit');
            });

            $('.carlson-nav .navbar .we-mega-menu-submenu .we-mega-menu-submenu-inner').mousemove(function (e) {
                e.preventDefault();
                innerDrawer = false;
            });

            resetSubUl();

            $('.carlson-nav .navbar .we-mega-menu-li .we-mega-menu-submenu .we-mega-menu-li').mousemove(function (e) {
                e.preventDefault();
                hideDefault();
                resetSubUl();
                $(this).css('z-index', 9995);
                var $elm = $(this).children().last().children().children().children().children();
                if( !$elm.hasClass('show')) {
                    $elm.addClass('show');
                }

                var offset = $('.carlson-nav .navbar').offset().top;
                var add = (($(window).width() < 1200) ? 100 : 110);
                offset = ((offset + add) - $(window).scrollTop());
                $(this).children().last().children().children().children().children().css('top', offset + 'px');
            });

            $('.carlson-nav .navbar .we-mega-menu-li .we-mega-menu-submenu .we-mega-menu-li').mouseover(function (e) {
                e.preventDefault();
                if (typeof $(this).attr('data-level') !== 'undefined') {
                    if( $(this).attr('data-level') === '2' ) {
                        var $elm = $(this).parent().parent().parent().parent().parent().parent().children().first();
                        if( !$elm.hasClass('selected') ) {
                            $elm.addClass('selected');
                        }
                    }
                }
            });

            $('.carlson-nav .navbar .we-mega-menu-li .we-mega-menu-submenu .we-mega-menu-li').mouseleave(function (e) {
                e.preventDefault();
                resetSubUl();
                $(this).css('z-index', 'inherit');
                $(this).children().last().children().children().children().children().removeClass('show');
                $(this).children().last().children().children().children().children().css('top', '-4000px');

                if( $(this).hasClass('default') ) {
                    $(this).removeClass('default');
                    $(this).addClass('default2');
                }
            });

            $('.carlson-nav .navbar .we-mega-menu-li .we-mega-menu-submenu').mouseleave(function (e) {
                e.preventDefault();
                $(this).parent().children().first().removeClass('selected');
            });

            setDefault();
            resetDefaultAll();
            setDefaultAll();
            resetTopMenu();
        }
    }

    // For the sticky header.
    // $(window).on('wheel', function() {
    //     setSticky();
    // });

    // // For touch move.
    // $('body').on({
    //     'touchmove': function(e) {
    //         setSticky();
    //     }
    // });

    // Scroll top init.
    $(window).resize(setup);
    //setSticky();
    setup();

  })(jQuery, Drupal);
