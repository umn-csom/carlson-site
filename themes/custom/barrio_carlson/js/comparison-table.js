/**
 * @file
 * Global utilities.
 *
 */
 (function ($, Drupal) {
    'use strict';
    var array = [
        {
            id: "full_time",
            display: "Full Time",
            avg_gmat: 690,
            avg_gre: 320,
            gpa_avg: 3.4,
            application_deadline: "Round 1: October 1, \n Round 2: December 1, \n Round 3: February 1"
        },
        
        {
            id: "part_time",
            display: "Part Time",
            avg_gmat: 690,
            avg_gre: 320,
            gpa_avg: 3.4,
            application_deadline: "Round 1: October 1, \n Round 2: December 1, \n Round 3: February 1"
        },
        
        {
            id: "thing",
            display: "Thing Time",
            avg_gmat: 690,
            avg_gre: 320,
            gpa_avg: 3.4,
            application_deadline: "Round 1: October 1, \n Round 2: December 1, \n Round 3: February 1"
        }
    ]

    $( document ).ready( function() {
        if ($('.comparison-menu__select').length > 0) {
            array.forEach(element => {
                let o = new Option(element.display, element.id);
                $('#select').append($(o));
            });
        }

    });



  })(jQuery, Drupal);