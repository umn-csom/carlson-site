/**
 * @file
 * Global utilities.
 */
 (function ($, Drupal) {
    'use strict';
    var label_endpoint = '/comparison-table/labels'
    var table_endpoint = '/comparison-table/feed';
    var table_limit = 3;

    var layout_array = [];
    var table_array = [];
    var render_array = [];

    function render_table()
    {

        let render_length = render_array.length;

        $('#course-table tr').each(
            function (index, element) {
                let row_element = element;
                $(this).find('th, td').each(
                    function (index, element) {
                        if (index > 0) {
                            let key_thing = render_array[index - 1];
                            if (row_element.id == 'title') {
                                if(index <= render_length) {
                                    $(this).find('.column-header-text').text(table_array[key_thing][row_element.id]);

                                    $('.comparison-menu__mobile-header .column-header_mobile-container .column-header-text__mobile').eq(index-1).text(table_array[key_thing][row_element.id]);
                                    $('.comparison-menu__mobile-header .column-header_mobile-container').eq(index-1).removeClass("column-no-content");
                                } else {
                                    $(this).find('.column-header-text').text("-");

                                    $('.comparison-menu__mobile-header .column-header_mobile-container .column-header-text__mobile').eq(index-1).text("-");
                                    $('.comparison-menu__mobile-header .column-header_mobile-container').eq(index-1).addClass("column-no-content");
                                }
                            } else {
                                if (index > render_length) {
                                    $(this).html("-");
                                } else if (row_element.id == 'links') {
                                    $(this).html(
                                        (is_quiz_result() ?
                                        build_request_link(table_array[key_thing]['req_info']) :
                                        build_request_link(table_array[key_thing]['req_alt'])
                                        ) +
                                        build_learn_link(table_array[key_thing]['learn_more'])
                                    )
                                } else {
                                    $(this).html(table_array[key_thing][row_element.id])
                                }
                            }
                        
                            if(index <= render_length) {
                                $(this).removeClass("column-no-content");
                            } else {
                                $(this).addClass("column-no-content");
                            }
                        }
                    }
                );
            }
        )


        $(".quiz-results--result--compare-checkbox").each(
            function (index, element) {
                if (render_array.includes($(this).val()) ) {
                    $(this).prop("checked", true);
                } else {
                    $(this).prop("checked", false);
                }
            }
        )

        $("#comparison-select > option").each(
            function (index, element) {
                if (render_array.includes($(this).val())) {
                    $(this).prop("hidden", true);
                } else {
                    $(this).prop("hidden", false);
                }
            }
        )

        if (render_length >= table_limit) {
            $('#comparison-menu').addClass("full-table");
        } else {
            $('#comparison-menu').removeClass("full-table");
        }

        $('#course-table').tablesaw().data('tablesaw').refresh();

        Drupal.ajax.bindAjaxLinks(document.body)
    }

    function build_request_link(url)
    {
        let link = '';

        if (url) {
            link += '<a href="' + url.replace(/^(entity\:)/,"/") + '" target="_blank" data-dialog-options="{&quot;width&quot;:800}"'
            link += 'class = "btn maroon-solid-button d-block py-3 py-lg-4 mb-3 mb-lg-4 quiz-results--result--req-info result--req-info use-ajax"'
            link += 'data-dialog-type="modal" data-ajax-progress="fullscreen">';
            link += 'Request Info';
            link += '</a>';
        }

        return link;
    }

    function build_learn_link(url)
    {
        let link = '';

        link += '<a href="' + url.replace(/^(entity\:)/,"/") + '" target="_blank" data-dialog-options="{&quot;width&quot;:800}"'
        link += 'class = "btn maroon-outline-button d-block py-3 py-lg-4 quiz-results--result--learn-more result--learn-more"'
        link += '>';
        link += 'Learn More';
        link += '</a>';

        return link;
    }

    function is_quiz_result()
    {
        return $('.quiz-results.quiz-display-table').length > 0
    }

    function add_table()
    {
        let select_value = $('#comparison-select').val();

        add_table_val(select_value);
    }

    function add_table_val(select_value)
    {
        if (render_array.length < table_limit 
            && select_value 
            && !render_array.includes(select_value) 
        ) {    
            render_array.push(select_value);

            let event_name = 'event-select-' + table_array[select_value].code;

            dataLayer.push({'event': event_name})
        }
        render_table();
    } 

    function delete_table(index)
    {
        if (render_array.length > 0) {
            render_array.splice(index, 1)
        }
        render_table();
    }

    $(
        function () {
            if($('#course-table').length > 0) {
                if($('.quiz-results').length > 0) {
                    $('.quiz-results').addClass('quiz-display-table');
                }

                $.when(
                    $.getJSON(label_endpoint),
                    $.getJSON(table_endpoint)
                ).done(
                    function (layout_response, table_response) {
                        layout_array = layout_response[0];
                        table_array = table_response[0];

                        console.log(table_array);
    
                        if ($('.comparison-menu__select').length > 0) {
                            for(const [key, value] of Object.entries(table_array)) {
                                let o = new Option(value.title, key);
                                $('#comparison-select').append($(o));
                            }
                        }


                        let comparison_table = $("#course-table tbody");

                        for (const [key, value] of Object.entries(layout_array[0])) {
                            comparison_table.append(
                                '<tr id="' + key + '">' +
                                '<th scope="row" class="comparison-table__category">' + value +'</td>' +
                                '<td class="column-no-content">-</td>' +
                                '<td class="column-no-content">-</td>' +
                                '<td class="column-no-content">-</td>' +
                                '</tr>'
                            )
                        }

                        //links
                        comparison_table.append(
                            '<tr id="' + 'links' + '">' +
                            '<th scope="row" class="comparison-table__category">' + 'Links' + '</td>' +
                            '<td class="column-no-content">-</td>' +
                            '<td class="column-no-content">-</td>' +
                            '<td class="column-no-content">-</td>' +
                            '</tr>'
                        )

    
                        $(".quiz-results--result--compare-checkbox").each(
                            function (index, element) {
                                let select_code = $(this).val();
    
                                let select_value = table_array.findIndex(
                                    (element) => {
                                        return element['code'] == select_code;
                                    }
                                )
    
                                $(this).val(select_value.toString());
                                add_table_val(select_value.toString());
                            }
                        )
    
                        $(".quiz-results--result--compare-checkbox").on(
                            "click", function () {
                                let render_array_index = render_array.findIndex(
                                    (element) => {
                                    return element == $(this).val();
                                    }
                                )
    
                                if (render_array_index >= 0) {
                                    delete_table(render_array_index);
                                } else {
                                    add_table_val($(this).val());
                                }
                            }
                        )
    
                        $('#comparison-add').on("click", add_table);
    
                        $(".column-header-button").on(
                            'click', function () {
                                delete_table($(this).data('table-index'));
                            }
                        )
    
                        render_table();
                    }
                )
            }
        }
    );

 })(jQuery, Drupal);