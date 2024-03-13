/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
  'use strict';

  $(".card-flip").toggleClass("flip");

  $('.node__bottom').each(function () {
    if ($(this).find('.node__bottom__col:empty').length > 0){
      $(this).find('.node__bottom__col:not(:empty)').addClass('node__bottom__col--single')
    }
  });

  function tables() {
    const tables = document.querySelectorAll('table');
    if(tables.length) {
      tables.forEach(table => {
        const rows = table.querySelectorAll('tr');
        if(rows.length) {
          rows.forEach(row => {
            const cells = row.querySelectorAll('th, td');
            if(cells.length) {
              cells.forEach((cell) => {
                let cellContent = cell.innerHTML.replace(/(<([^>]+)>)/gi, "").replace("&nbsp;", "").trim();
                if(cellContent === "") {
                  cell.classList.add('empty-cell');
                }
              });
            }
          });
        }
      });
    }
  }

  Drupal.behaviors.bootstrap_barrio_subtheme = {
    attach: function (context, settings) {
      // run test on initial page load
      checkStickySize();

      // run test on resize of the window
      $(window).resize(checkStickySize);

      //Function to the css rule
      function checkStickySize() {
        var wrap_width = $('html').width();
        if (wrap_width >= 992) {
          $('.paid-media__webform--wrapper').sticky({
            topSpacing: 170,
            bottomSpacing: 470
          });
        } else {
          $('.paid-media__webform--wrapper').unstick();
        }
      }

      $(document).ready(function(){
        tables();
      });
    }
  }
})(jQuery, Drupal);
