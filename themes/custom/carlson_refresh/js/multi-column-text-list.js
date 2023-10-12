(function (Drupal, $) {
Drupal.behaviors.multiColumnTextList = {
  attach: function () {

    function csom_multi_column_text_list() {
      $(".csom-multi-column-text-list").each(function () {
        var collapsed_height = 240;
        var list = $(this);
        var fullHeight = csom_multi_column_text_list_height(list);
        list.removeClass("expand");
        list.removeClass("open");
        list.removeClass("closed");
        list.height("auto");
        if (list.height() > collapsed_height * 2) {
          list.addClass("expand closed");
          list.height(collapsed_height);
        }

        // Prevent attaching handler again.
        if (list.data("click-init")) {
          return true;
        }
        list.data("click-init", true);

        $(".more-less", list).on("click", function () {
          if (list.hasClass("closed")) {
            list.animate(
              {
                height: csom_multi_column_text_list_height(list),
              },
              200
            );
          } else if (list.hasClass("open")) {
            list.animate(
              {
                height: collapsed_height + "px",
              },
              200
            );
          }
          list.toggleClass("open closed");
        });
      });
    }

    function csom_multi_column_text_list_height(list) {
      return list[0].scrollHeight + 20 + "px";
    }

    $(window).on("resize orientationchange", function () {
      csom_multi_column_text_list();
    });

    csom_multi_column_text_list();

  }
};

})(Drupal, jQuery);
