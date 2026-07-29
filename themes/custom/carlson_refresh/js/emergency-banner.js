(function($, Drupal, once) {
  Drupal.behaviors.folwellEmergencyBanner = {
    attach: function(context) {
      const elements = once('folwellEmergencyBannerClose', '.folwell-banner__close-element', context);
      elements.forEach(addCloseFunctionality);

      function addCloseFunctionality(element) {
        $(element).on('click', function () {
          $(this).closest('.folwell-banner').slideUp();
        });
      }
    }
}
})(jQuery, Drupal, once);
