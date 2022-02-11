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

    $('#faculty-profile__close').on("click", function() {
        console.log('please close');
        $('#faculty-profile__confirm').attr('open', false);
    })

})(jQuery, Drupal);
