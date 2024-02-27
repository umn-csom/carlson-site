(function ($, once) {
  Drupal.behaviors.datatables = {
    attach: function (context, settings) {
      $.each(settings.datatables, function (selector) {
        $(once('datatables', selector, context)).each(function () {
          // Check if table contains expandable hidden rows.
          var settings = drupalSettings.datatables[selector];
          if (settings.bExpandable) {
            // Insert a "view more" column to the table.
            var nCloneTh = document.createElement('th');
            var nCloneTd = document.createElement('td');
            nCloneTd.innerHTML = '<a href="#" class="datatables-expand datatables-closed">' + Drupal.t('Show Details') + '</a>';

            $(selector + ' thead tr').each(function () {
              this.insertBefore(nCloneTh, this.childNodes[0]);
            });

            $(selector + ' tbody tr').each(function () {
              this.insertBefore(nCloneTd.cloneNode(true), this.childNodes[0]);
            });

            settings.aoColumns.unshift({"bSortable": false});
          }

          var datatable = $(selector).dataTable(settings);

          // Expandable rows:
          if (settings.bExpandable) {
            // Add column headers to table settings.
            var datatables_settings = datatable.fnSettings();
            // Add blank column header for show details column.
            settings.aoColumnHeaders.unshift('');
            // Add column headers to table settings.
            datatables_settings.aoColumnHeaders = settings.aoColumnHeaders;

            /*
             * Add event listener for opening and closing details
             * Note that the indicator for showing which row is open is not controlled by DataTables,
             * rather it is done here
             */
            $('td a.datatables-expand', datatable.fnGetNodes()).each(function () {
              $(this).click(function () {
                var row = this.parentNode.parentNode;
                if (datatable.fnIsOpen(row)) {
                  datatable.fnClose(row);
                  $(this).html(Drupal.t('Show Details'));
                }
                else {
                  datatable.fnOpen(row, Drupal.theme('datatablesExpandableRow', datatable, row), 'details');
                  $(this).html(Drupal.t('Hide Details'));
                }
                return false;
              });
            });
          }

          // Column filtering / search
          if (settings.bFilterColumns) {
            datatable.api().columns().every( function (index) {
              if (settings.aoColumns[index].sFilterColumnType) {
                var column = this;

                var $appendTarget = null;
                var controls = null
                var eventType = null;
                switch (settings.aoColumns[index].sFilterColumnType) {
                  case 'thead_select':
                    $appendTarget = $(column.header());
                    controls = 'select';
                    eventType = 'change';
                    break;
                  case 'thead_input':
                    $appendTarget = $(column.header());
                    controls = 'input';
                    eventType = 'input';
                    break;
                  // @todo: Drupal tables typically don't have a
                  // tfoot, so this doesn't work yet:
                  case 'tfoot_select':
                    $appendTarget = $(column.footer());
                    controls = 'select';
                    eventType = 'change';
                    break;
                  case 'tfoot_input':
                    $appendTarget = $(column.footer());
                    controls = 'input';
                    eventType = 'input';
                    break;
                }

                if ($appendTarget && controls && eventType) {
                  // Indicate this is a filter column and wrap the original value.
                  // Add wrappers for flexible styling:
                  $appendTarget
                  .addClass('filter-column')
                  .wrapInner('<div class="filter-column__title"></div>');
                  if (controls == 'select') {
                    var $controls = $('<select class="filter-column__filter"><option value="">' + settings.sFilterColumnsPlaceholder + '</option></select>')
                    column.data().unique().sort().each( function ( d, j ) {
                      // Strip HTML:
                      d = d.trim();
                      let tmp = document.createElement("DIV");
                      tmp.innerHTML = d;
                      d= tmp.textContent || tmp.innerText || "";
                      $controls.append( '<option value="'+d+'">'+d+'</option>' )
                    });
                  } else if (controls == 'input') {
                    var $controls = $('<input type="search" placeholder="' + settings.sFilterColumnsPlaceholder + '" class="filter-column__filter" />')
                  }

                  $controls.on(eventType, function () {
                    var val = $(this).val().trim();
                    column
                        .search(val, false, true, true )
                        .draw();
                  });
                  $controls.wrap('<div class="filter-column__filter"></div>').appendTo($appendTarget);
                  $appendTarget.wrapInner('<div class="filter-column__title-filter-wrapper"></div>')
                }
              }
            });

          }
        });
      });
    }
  };

  /**
   * Theme an expandable hidden row.
   *
   * @param object
   *   The datatable object.
   * @param array
   *   The row array for which the hidden row is being displayed.
   * @return
   *   The formatted text (html).
   */
   Drupal.theme.datatablesExpandableRow = function (datatable, row) {
    var rowData = datatable.fnGetData(row);
    var settings = datatable.fnSettings();

    var output = '<table style="padding-left: 50px">';
    $.each(rowData, function (index) {
      if (!settings.aoColumns[index].bVisible) {
        output += '<tr><td>' + settings.aoColumnHeaders[index].content + '</td><td style="text-align: left">' + this + '</td></tr>';
      }
    });
    output += '</table>';
    return output;
  };
}(jQuery, once));
