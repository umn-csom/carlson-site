/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {
    'use strict';

  $(document).on('click', '.sticky-menu__label', function(e) {
    e.preventDefault();
    var $this = $(this);
    $this.toggleClass('sticky-menu__label--opened');
    $('.sticky-menu__nav').toggleClass('sticky-menu__nav--closed');
  });

  $(document).on('click', '.sticky-menu__item--has-submenu', function(e) {
    e.preventDefault();
    var $this = $(this);
    $this.toggleClass('sticky-menu__item--opened');
    $this.find('ul.subnav').toggleClass('subnav--opened');
  });

  
  })(jQuery, Drupal);  