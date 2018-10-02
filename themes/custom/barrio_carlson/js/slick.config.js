(function ($, Drupal, window, document) {

  Drupal.behaviors.barrio_carlsonSlickConfig = {
    attach: function (context, settings) {

       $('.slider-for').slick({
          slidesToShow: 1,
          slidesToScroll: 1,
          arrows: false,
          fade: false,
          asNavFor: '.slider-nav'
        });
        $('.slider-nav').slick({
          slidesToShow: 5,
          slidesToScroll: 1,
          asNavFor: '.slider-for',
          dots: false,
          arrows: true,
          centerMode: false,
          focusOnSelect: true
        });

    }
  };

} (jQuery, Drupal, this, this.document));