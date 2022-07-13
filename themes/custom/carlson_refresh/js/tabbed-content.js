/**
 * @file
 * Tabbed Content utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

    $('.tabbed-content .nav-tabs > li > a').on('click', function () {
        $(this).closest('.nav-tabs').toggleClass('open');
        if (!$(this).hasClass('active')) {
            $(this).tab('show');
        }
    });

})(jQuery, Drupal);