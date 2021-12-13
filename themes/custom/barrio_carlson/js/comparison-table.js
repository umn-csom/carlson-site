/**
 * @file
 * Global utilities.
 *
 */
 (function ($, Drupal) {
    'use strict';
    var table_array = [
        {
            id: "full_time",
            display: "Full Time",
            avg_gmat: 690,
            avg_gre: 320,
            gpa_avg: 3.4,
            application_deadline: "Round 1: October 1, <br> Round 2: December 1, <br> Round 3: February 1"
        },
        
        {
            id: "part_time",
            display: "Part Time",
            avg_gmat: 690,
            avg_gre: 320,
            gpa_avg: 3.4,
            application_deadline: "Round 1: October 1, <br> Round 2: December 1, <br> Round 3: February 1"
        },
        
        {
            id: "thing",
            display: "Thing Time",
            avg_gmat: 690,
            avg_gre: 320,
            gpa_avg: 3.4,
            application_deadline: "Round 1: October 1, <br> Round 2: December 1, <br> Round 3: February 1"
        }
    ]

    function render_table() {
        let table = document.getElementById('example');

        let rowLength = table.rows.length;

        for(let i=0; i<rowLength; i+=1){
            let row = table.rows[i];

            console.log(row.id);


            for(let j=0; j<table_array.length; j+=1) {
                console.log(table_array[j][row.id]);

                let cell = row.cells[j+1];

                cell.innerHTML = table_array[j][row.id];
            }
        }


    }

    function add_table() {

        render_table()
    }

    function delete_table() {

        render_table()
    }

    $( document ).ready( function() {
        if ($('.comparison-menu__select').length > 0) {
            table_array.forEach(element => {
                let o = new Option(element.display, element.id);
                $('#select').append($(o));
            });
        }

        console.log("wow!!");
        render_table();
    });



  })(jQuery, Drupal);