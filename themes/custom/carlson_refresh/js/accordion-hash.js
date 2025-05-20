/**
 * @file
 * Accordion hash support
 *
 */
 (function ($, Drupal) {
    'use strict';

    $(window).on('hashchange', function(e) {
        var hash = $(location).attr('hash');

        $(".collapse").each(function () {
            if($(this).find(hash).length == 1) {
                $(this).collapse('show');
                return false;
            }
        });
    });

    if (window.location.hash) {
        $(window).trigger('hashchange');
    }

})(jQuery, Drupal);
