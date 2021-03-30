(function ($, Drupal) {
    'use strict';

    $(document).ready( function () {
        $('#mn-cup-table').DataTable({
            'pageLength': 50,
            'order': [0, 'desc'],
        });
    } );

  })(jQuery, Drupal);