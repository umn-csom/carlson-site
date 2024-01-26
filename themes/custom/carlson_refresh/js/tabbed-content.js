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

    $('.accordion .btn-link').click(function(){
        const cardElement = $(this).closest('.card');
        // Remove active class from all cards
        $('.accordion .card').removeClass('active');
        // Check if the clicked button has the 'collapsed' class
        if($(this).hasClass('collapsed')){
            // Add 'collapsed' class to the parent .card
            cardElement.addClass('active');
        } else {
            // Remove 'collapsed' class from the parent .card
            cardElement.removeClass('active');
        }
    });


})(jQuery, Drupal);