/**
 * @file
 * Tabbed Content utilities.
 *
 */
 (function ($, Drupal) {
    'use strict';

    $('#faculty-profile__modal').on("click", function() {
        $('#faculty-profile__confirm').attr('open', function(index, attr) {
            return attr == true ? false : true;
        });
        $('#faculty-profile__confirm').toggleClass('safari-show');
    });

    $('#faculty-profile__close').on("click", function() {
        console.log('please close');
        $('#faculty-profile__confirm').attr('open', false);
        $('#faculty-profile__confirm').removeClass('safari-show');
    })

})(jQuery, Drupal);
