/**
 * @file
 * Table empty cells.
 *
 */
(function ($, Drupal) {
  'use strict';

  function table_empty_cell() {
    const tables = document.querySelectorAll("table");
    if (tables.length) {
      tables.forEach((table) => {
        const rows = table.querySelectorAll("tr");
        if (rows.length) {
          rows.forEach((row) => {
            const cells = row.querySelectorAll("th, td");
            if (cells.length) {
              cells.forEach((cell) => {
                let cellContent = cell.innerHTML
                  .replace(/(<([^>]+)>)/gi, "")
                  .replace("&nbsp;", "")
                  .trim();
                if (cellContent === "") {
                  cell.classList.add("empty-cell");
                }
              });
            }
          });
        }
      });
    }
  }

  Drupal.behaviors.table_empty_cell = {
    attach: function () {
      $(document).ready(function(){
        table_empty_cell();
      });
    }
  }
})(jQuery, Drupal);
