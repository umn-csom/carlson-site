/**
 * @file
 * Tabbed Content utilities.
 *
 */
 (function ($, Drupal) {
    'use strict';

    $('#faculty-profile__modal').on("click", function() {
        $('#faculty-profile__confirm').attr('open', true);
    });

})(jQuery, Drupal);
