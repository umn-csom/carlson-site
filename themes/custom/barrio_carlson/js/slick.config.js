(function ($, Drupal, window, document) {

  Drupal.behaviors.barrio_carlsonSlickConfig = {
    attach: function (context, settings) {

      $('.carlson-slideshow').each(function() {
        var classId = $(this).attr('rel');

        $('.' + classId + ' .slider-for').slick({
          slidesToShow: 1,
          slidesToScroll: 1,
          arrows: false,
          dots: false,
          fade: false,
          centerMode: true,
          asNavFor: ('.' + classId + ' .slider-nav' ),
          draggable: true
        });
  
        $('.' + classId + ' .slider-nav').slick({
          slidesToShow: 5,
          slidesToScroll: 1,
          asNavFor: ( '.' + classId + ' .slider-for' ),
          dots: false,
          arrows: true,
          centerMode: false,
          focusOnSelect: true,
          draggable: false
        });
  
        $('.' + classId + ' .slider-nav').on('beforeChange', function(event, slick, currentSlide, nextSlide){
          var caption = $('.' + classId + ' div[data-slick-index="' + nextSlide + '"] .slide__caption').html();
          $('.' + classId + ' .slideshow__main-caption').html( caption );
        });
  
        var captionFirst = $('.' + classId + ' div[data-slick-index="0"] .slide__caption').html();
        $('.' + classId + ' .slideshow__main-caption').html( captionFirst );
      });
    }
  };

} (jQuery, Drupal, this, this.document));