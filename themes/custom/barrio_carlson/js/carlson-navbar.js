
/*!
 * Bootstrap 4 multi dropdown navbar ( https://bootstrapthemes.co/demo/resource/bootstrap-4-multi-dropdown-navbar/ )
 * Copyright 2017.
 * Licensed under the GPL license
 */
 $( document ).ready( function () {

   $('#carlson-navbar .dropdown-menu a.dropdown-toggle').on('click', function (e) {
      if (!$(this).next().hasClass('show')) {
          $(this).parents('.dropdown-menu').first().find('.show').removeClass("show");
      }
      var $subMenu = $(this).next(".dropdown-menu");
      $subMenu.toggleClass('show');


      $(this).parents('.nav-item.dropdown.show').on('hidden.bs.dropdown', function (e) {
          $('.dropdown-submenu .show').removeClass("show");
      });

      return false;
  });

  $('.dropdown-submenu .dropdown-item').on('click', function (e) {
  		var parentClass = $(this).parents()[1].className.includes('show');
      if	(parentClass) {
      	$('.dropdown-submenu .show').removeClass("show");
      }
  });
});
