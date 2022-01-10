/**
 * @file
 * Global utilities.
 *
 */
 (function ($, Drupal) {
    'use strict';

    var table_limit = 3;

    var table_array = {
        full_time : {
            id: "full_time",
            display: "Full Time",
            avg_gmat: 690,
            avg_gre: 320,
            gpa_avg: 3.4,
            application_deadline: "Round 1: October 1, <br> Round 2: December 1, <br> Round 3: February 1"
        },
        
        part_time : {
            id: "part_time",
            display: "Part Time",
            avg_gmat: 690,
            avg_gre: 320,
            gpa_avg: 3.4,
            application_deadline: "Round 1: October 1, <br> Round 2: December 1, <br> Round 3: February 1"
        },
        
        thing : {
            id: "thing",
            display: "Thing Time",
            avg_gmat: 690,
            avg_gre: 320,
            gpa_avg: 3.4,
            application_deadline: "Round 1: October 1, <br> Round 2: December 1, <br> Round 3: February 1"
        }
    }

    var render_array = ['part_time', 'full_time'];

    function render_table() {

        let render_length = render_array.length;

        $('#example tr').each(function(index, element) {
            let row_element = element;
            $(this).find('th, td').each(function(index, element) {
                if (index > 0) {
                    let key_thing = render_array[index - 1];
                    if (row_element.id == 'display') {
                        if( index <= render_length) {
                            $( this ).find('.column-header-text').text(table_array[key_thing][row_element.id]);
                            $( this ).removeClass("column-no-content");

                            $('.comparison-menu__mobile-header .column-header_mobile-container .column-header-text__mobile').eq(index-1).text(table_array[key_thing][row_element.id]);
                            $('.comparison-menu__mobile-header .column-header_mobile-container').eq(index-1).removeClass("column-no-content");
                        } else {
                            $( this ).find('.column-header-text').text("-");
                            $( this ).addClass("column-no-content");

                            $('.comparison-menu__mobile-header .column-header_mobile-container .column-header-text__mobile').eq(index-1).text("-");
                            $('.comparison-menu__mobile-header .column-header_mobile-container').eq(index-1).addClass("column-no-content");
                        }
                    } else {
                        if( index <= render_length) {
                            $( this ).html(table_array[key_thing][row_element.id])
                            $( this ).removeClass("column-no-content");
                        } else {
                            $( this ).html("-");
                            $( this ).addClass("column-no-content");
                        }
                    }
                }
            });
        })

        if (render_length >= table_limit) {
            $('#comparison-menu').addClass("full-table");
        } else {
            $('#comparison-menu').removeClass("full-table");
        }

        $('#example').tablesaw().data('tablesaw').refresh();
    }

    function add_table() {
        let select_value = $('#comparison-select').val();
        if (render_array.length < table_limit &&
            select_value &&
            !render_array.includes(select_value) 
        ) {    
            render_array.push($('#comparison-select').val());
        }
        render_table();
    }

    function delete_table(index) {
        if (render_array.length > 0) {
            render_array.splice(index, 1)
        }
        render_table();
    }

    $( document ).ready( function() {
        if ($('.comparison-menu__select').length > 0) {
            for(const [key, value] of Object.entries(table_array)) {
                let o = new Option(value.display, value.id);
                $('#comparison-select').append($(o));
            }

        }

        $('#comparison-add').on("click", add_table);

        $(".column-header-button").on('click', function() {
            delete_table($(this).data('table-index'));
        })

        render_table();
    });

  })(jQuery, Drupal);