/**
 * @file
 * Faculty Profile Modal.
 */
 (function ($, Drupal) {
    'use strict';

    $('#faculty-profile__modal').on("click", function() {
        $('#faculty-profile__confirm').attr('open', function(index, attr) {
            return attr == true ? false : true;
        });
        $('#faculty-profile__confirm').toggleClass('modal-show');
        $('#faculty-profile__modal').toggleClass('modal-show');
    });

    $('#faculty-profile__close').on("click", function() {
        $('#faculty-profile__confirm').attr('open', false);
        $('#faculty-profile__confirm').removeClass('modal-show');
        $('#faculty-profile__modal').removeClass('modal-show');
    })

})(jQuery, Drupal);
