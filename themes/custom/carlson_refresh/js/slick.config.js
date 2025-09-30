(function ($, Drupal) {

  Drupal.behaviors.carlson_refreshSlickConfig = {
    attach: function (context) {
      once('carlson-slick', '.carlson-slideshow', context).forEach(function (wrapper) {
        var $wrapper = $(wrapper);
        var uniqueClass = $wrapper.attr('rel');

        if (!uniqueClass) {
          return;
        }

        var $mainSlider = $wrapper.find('.slider-for');
        var $navSlider = $wrapper.find('.slider-nav');
        var $captionTarget = $wrapper.find('.slideshow__main-caption');
        var navSelector = '.' + uniqueClass + ' .slider-nav';
        var mainSelector = '.' + uniqueClass + ' .slider-for';

        var mainLabel = $wrapper.data('carousel-label') || Drupal.t('Slideshow');
        var navLabel = $wrapper.data('carousel-nav-label') || Drupal.t('Slide thumbnails');
        var mainInstructions = $wrapper.data('carousel-instructions') || Drupal.t('Use the previous and next buttons to change slides. The thumbnail carousel that follows stays in sync.');
        var navInstructions = $wrapper.data('carousel-nav-instructions') || Drupal.t('Select a thumbnail to load that slide in the main carousel above.');

        function updateCaption(index) {
          var captionHtml = '';
          var $captionSource = $wrapper.find('div[data-slick-index="' + index + '"] .slide__caption').first();

          if ($captionSource.length) {
            captionHtml = $captionSource.html();
          }

          $captionTarget.html(captionHtml);
        }

        var navInstance = null;

        $mainSlider.on('init', function (event, slick) {
          updateCaption(slick.currentSlide || 0);
        });

        $mainSlider.on('afterChange', function (event, slick, currentSlide) {
          updateCaption(currentSlide);
        });

        $navSlider.on('init', function (event, slick) {
          navInstance = slick;
        });

        $navSlider.on('afterChange', function (event, slick, currentSlide) {
          updateCaption(currentSlide);
        });

        function normalizeIndex(index) {
          if (!navInstance || !navInstance.slideCount) {
            return index;
          }
          var slideCount = navInstance.slideCount;
          var normalized = index % slideCount;
          return normalized < 0 ? normalized + slideCount : normalized;
        }

        $navSlider.on('click', '.slick-slide', function (event) {
          var $targetSlide = $(this);

          var rawIndex = parseInt($targetSlide.attr('data-slick-index'), 10);

          if (Number.isNaN(rawIndex)) {
            return;
          }

          $mainSlider.slick('slickGoTo', normalizeIndex(rawIndex));
        });

        $mainSlider.slick({
          slidesToShow: 1,
          slidesToScroll: 1,
          arrows: true,
          dots: false,
          fade: false,
          infinite: true,
          centerMode: true,
          asNavFor: navSelector,
          draggable: true,
          variableWidth: false,
          centerPadding: '0',
          regionLabel: mainLabel,
          instructionsText: mainInstructions,
          arrowsPlacement: 'split',
          responsive: [
            {
              breakpoint: 640,
              settings: {
                centerMode: true,
                slidesToShow: 1
              }
            }
          ]
        });

        $navSlider.slick({
          slidesToShow: 1,
          slidesToScroll: 1,
          asNavFor: mainSelector,
          dots: false,
          arrows: false,
          infinite: true,
          centerMode: false,
          centerPadding: '0',
          draggable: false,
          variableWidth: true,
          regionLabel: navLabel,
          instructionsText: navInstructions
        });
      });
    }
  };

})(jQuery, Drupal);
