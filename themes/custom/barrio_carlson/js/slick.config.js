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
          infinite: true,
          centerMode: true,
          asNavFor: ('.' + classId + ' .slider-nav' ),
          draggable: true,
          customPaging: function(slick,index) {
                return $('.thumbnails').eq(index).find('img').prop('outerHTML');
            },
            responsive: [
              {
                breakpoint: 640,
                settings: {
                  arrows: false,
                  dots: true,
                  centerMode: true,
                  slidesToShow: 1,
                  customPaging: function(slick,index) {
                      return '<button type="button" data-role="none">' + (index + 1) + '</button>';
                    }
                  }
                }
              ]
            });

        $('.' + classId + ' .slider-nav').slick({
          slidesToShow: 5,
          slidesToScroll: 1,
          asNavFor: ( '.' + classId + ' .slider-for' ),
          dots: false,
          arrows: true,
          infinite: true,
          centerMode: false,
          centerPadding: '0px',
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
