/**
 * @file
 * Tabbed Content utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

    var position1, position2, position3, position4, position5 = 0;

    function setStrategicPositions() {
        position1 = $('#platform-section-1').offset().top;
        position2 = $('#platform-section-2').offset().top;
        position3 = $('#platform-section-3').offset().top;
        position4 = $('#platform-section-4').offset().top;
        position5 = $('#platform-section-5').offset().top;
    }

    function highlightStrategic() {
        let currentHeight = $(window).scrollTop();
        let position = 0;
        let text = ''
        if (currentHeight > position1) {
            position = '1';
            text = 'Business Engagement';
        }
        if (currentHeight > position2) {
            position = '2';
            text = 'Program Innovation';
        }
        if (currentHeight > position3) {
            position = '3';
            text = 'Student Experience';
        }
        if (currentHeight > position4) {
            position = '4';
            text = 'Inclusion and Equity';
        }
        if (currentHeight > position5) {
            position = '5';
            text = 'Carlson for Life';
        }

        if (position > 0) {
            $('#nav-message').html('Platform ' + position + ' = ' + text);
            $('.nav-number').removeClass('highlight');
            $('#nav-number-' + position).addClass('highlight');
        }
    }

    if ($('.strategic-plan').length > 0) {

        $(window).resize(setStrategicPositions)
        $(window).scroll(highlightStrategic)
    
        setStrategicPositions();
        highlightStrategic();
    }


})(jQuery, Drupal);
