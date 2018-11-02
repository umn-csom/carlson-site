/**
 * @file
 * Homepage Program Finder
 *
 */
(function ($, Drupal) {
    'use strict';

    window.programFinder = (function () {

        var _config = {
            "actions": [
                { "path": "1", "label": "Degree", "goto": "/node/95816" }
                , {
                    "path": "2", "label": "Non-Degree",
                    "actions": [
                        { "path": "2/3", "label": "Undergrad", "goto": "/node/95816" }
                        , {
                            "path": "2/4", "label": "MBA",
                            "actions": [
                                { "path": "2/4/5", "label": "Full-Time MBA", "goto": "/node/95816" }
                                , { "path": "2/4/6", "label": "Part-Time MBA", "goto": "/node/95816" }
                                , { "path": "2/4/7", "label": "Online MBA", "goto": "/node/95816" }
                                , { "path": "2/4/8", "label": "Executive MBA-Minneapolis", "goto": "/node/95816" }
                                , { "path": "2/4/9", "label": "Executive MBA-Vienna", "goto": "/node/95816" }
                                , { "path": "2/4/10", "label": "Executive MBA-China", "goto": "/node/95816" }
                                , { "path": "2/4/11", "label": "Dual Degree", "goto": "/node/95816" }
                            ]
                        }
                    ]
                }
            ]
        };

        var _bind = function () {

            if ($('.program-finder').length > 0) {
                var template = _.template($('#carlson-tpl-program-finder-dropdown').html());

                $('.program-finder__choices').html(template(programFinder.config));

                $('.program-finder__choice').each(function () {
                    $(this).find('.program-finder__choice--option-label').first().addClass('active');
                });
            }

        };

        var _choose = function (elem) {
            $('.program-finder__choice--option-label').removeClass('active');
            var $chosen = $(elem);
            $chosen.addClass('active');
            var $dropdown = $(elem).closest('.program-finder__choice');
            $dropdown.toggleClass('open');
            if (!$dropdown.hasClass('open')) {
                $dropdown.next('.program-finder_choice').remove();
                var selectedVal = $(elem).closest('.program-finder__choice--option').val() + "";
                console.log('selected value', selectedVal);
                //if the value is a path, let's decide if a new dropdown or a goto link is needed
                if (selectedVal != 0) {
                    console.log('do something');
                    //find the node we're looking at
                    //if goto, set link and activate the button
                    //if actions, add a dropdown to the page with the options
                }
            }
        };

        return {
            config: _config,
            bind: _bind,
            choose: _choose
        }
    }());

    programFinder.bind();

})(jQuery, Drupal);