/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

  $(document).on('click', '.sticky-menu__label', function(e) {
    e.preventDefault();
    console.log('here');
    var $this = $(this);
    $this.toggleClass('sticky-menu__label--opened');
    $('.sticky-menu__nav').toggleClass('sticky-menu__nav--closed');
  });
  
  })(jQuery, Drupal);  