/**
 * @file
 * Tabbed Content utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

    $('.ee-program__date--details--view-only-btn').click(function () {
        var $details = $(this).closest('.ee-program__date--details');
        console.log('details length: ' + $details.length, 'VO button length: ' + $details.find('.ee-program__date--details--view-only-btn').length, 'action button length: ' + $details.find('.ee-program__date--details--action-btn').length);
        $details.find('.ee-program__date--details--view-only-btn').hide();
        $details.find('.ee-program__date--details--action-btn').addClass('do-show');
        return false;
    });

})(jQuery, Drupal);