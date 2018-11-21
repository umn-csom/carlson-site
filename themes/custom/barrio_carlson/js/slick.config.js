(function ($, Drupal, window, document) {

  Drupal.behaviors.barrio_carlsonSlickConfig = {
    attach: function (context, settings) {

      $('.carlson-slideshow').each(function() {
        var classId = $(this).attr('rel');

        $('.' + classId + ' .slider-for').slick({
          slidesToShow: 1,
          slidesToScroll: 1,
          arrows: true,
          dots: false,
          fade: false,
          infinite: true,
          centerMode: true,
          asNavFor: ('.' + classId + ' .slider-nav' ),
          draggable: true,
          variableWidth: false,
          centerPadding: '0',
          responsive: [
              {
                breakpoint: 640,
                settings: {
                  arrows: false,
                  dots: false,
                  centerMode: true,
                  slidesToShow: 1,
                }
              }
            ]
          });

        $('.' + classId + ' .slider-nav').slick({
          slidesToShow: 1,
          slidesToScroll: 1,
          asNavFor: ( '.' + classId + ' .slider-for' ),
          dots: false,
          arrows: false,
          infinite: true,
          centerMode: false,
          centerPadding: '0',
          focusOnSelect: true,
          draggable: false,
          variableWidth: true,
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
