 /**
 * @file
 * Iframe resizing functions
 *
 */
(function ($, Drupal) {
    'use strict';

    $( document ).ready( function() {
        $(".iframe-container iframe").on("load", function() {
            console.log($(this).contents().find("body").height());
            $(this).height( $(this).contents().find("body").height() );
        })

        $( window ).resize( function() {
            $(".iframe-container iframe").each( function() {
                console.log($(this).contents().find("body").height());
                $(this).height( $(this).contents().find("body").height() );
            })
        })
    });

  })(jQuery, Drupal);