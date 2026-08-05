(function($, Drupal, once) {
  Drupal.behaviors.folwellEmergencyBanner = {
    attach: function(context) {
      const elements = once('folwellEmergencyBannerClose', '.emergency-campaign-sticky-bar__close', context);
      elements.forEach(addCloseFunctionality);

      function addCloseFunctionality(element) {
        $(element).on('click', function () {
          $(this).closest('.emergency-campaign-sticky-bar').slideUp();
        });
      }
    }
}
})(jQuery, Drupal, once);
