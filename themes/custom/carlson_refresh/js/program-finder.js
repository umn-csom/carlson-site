/**
 * @file
 * Homepage Program Finder
 *
 * configuration object defines how the finder functions
 * - object path should be unique; format is a hierarchy definition (i.e. showing grandparent/parent/child path)
 * on individual object choice,
 * - a goto property defines a page that will be available to go to
 * - any actions property defines a list of dropdown options that will be added to the finder
 * - goto will be executed first, and if absent, the actions property logic will be executed
 */
(function ($, Drupal) {
    'use strict';

    window.programFinder = (function () {

        var _config = {
            "emptyOption": "Choose...",
            "actions": [
                { "path": "1", "label": "All Programs", "goto": "/node/1" },
                { "path": "2", "label": "Undergrad", "goto": "/node/1511" },
                {
                    "path": "3", "label": "MBA", "goto": "/node/41",
                    "emptyOption": "Education...",
                    "actions": [
                        { "path": "2/5", "label": "Full-Time MBA", "goto": "/node/42" }
                        , { "path": "2/6", "label": "Part-Time MBA", "goto": "/node/43" }
                        , { "path": "2/7", "label": "Online MBA", "goto": "/node/93261" }
                        , { "path": "2/8", "label": "Executive MBA - Minneapolis", "goto": "/node/44" }
                        , { "path": "2/9", "label": "Executive MBA - China", "goto": "/node/1226" }
                        , { "path": "2/10", "label": "Executive MBA - Vienna", "goto": "/node/1231" }
                        , { "path": "2/11", "label": "Executive MBA - China, Dual Degree", "goto": "/node/1236" }
                    ]
                },
                {
                    "path": "4", "label": "Specialty Masters", "goto": "/node/84846",
                    "emptyOption": "Education...",
                    "actions": [
                        { "path": "3/13", "label": "Accountancy", "goto": "/node/2" }
                        , { "path": "3/14", "label": "Business Analytics", "goto": "/node/324" }
                        , { "path": "3/16", "label": "Business Taxation", "goto": "/node/80" }
                        , { "path": "3/17", "label": "Finance", "goto": "/node/67726/" }
                        , { "path": "3/18", "label": "Human Resources and Industrial Relation", "goto": "/node/3" }
                        , { "path": "3/19", "label": "Supply Chain Management", "goto": "/node/67781" }
                    ]
                },
                {
                    "path": "5", "label": "PhD",
                    "emptyOption": "Education...",
                    "actions": [
                        { "path": "4/20", "label": "PhD-Business Administration", "goto": "/node/2646" }
                    ]
                },
                { "path": "6", "label": "Executive Education", "goto": "/node/95871" },
            ]
        };

        var _bind = function () {
            if ($('.program-finder').length > 0) {
                var template = _.template($('#carlson-tpl-program-finder-dropdown').html());
                $('.program-finder__choices').html(template(programFinder.config));
            }
        };

        var _choose = function (elem) {
            var $dropdown = $(elem).closest('.program-finder__choice');
            $dropdown.find('.program-finder__choice--option-label').removeClass('active');
            var $chosen = $(elem);
            $chosen.addClass('active');
            $dropdown.toggleClass('open');
            if (!$dropdown.hasClass('open')) {
                $dropdown.parent().nextAll().remove();
                var selectedVal = $(elem).closest('.program-finder__choice--option').attr('data-value');
                if (selectedVal != 0) {
                    _.each(programFinder.config.actions, function (action) { programFinder.searchActionsFor(action, selectedVal); }, selectedVal);
                } else {
                    $('#program-finder__result-go').attr('data-goto', '').attr('disabled', '');
                }
            }
        };

        var _searchActionsFor = function (action, path) {
            if (action.path == path) {
                programFinder.performActionFor(action);
            }
            if (action.hasOwnProperty('actions')) {
                _.each(action.actions, function (action) { programFinder.searchActionsFor(action, path); }, path)
            }
        };

        var _performActionFor = function (action) {
            //console.log('found it!', action);
            if (action.hasOwnProperty('goto')) {
                $('#program-finder__result-go').attr('data-goto', action.goto).removeAttr('disabled');
            } else {
                $('#program-finder__result-go').attr('data-goto', '').attr('disabled', '');
            }
            if (action.hasOwnProperty('actions')) {
                var template = _.template($('#carlson-tpl-program-finder-dropdown').html());
                $('.program-finder__choices').append(template(action));
                if (action.actions.length == 1 && action.actions[0].hasOwnProperty('goto')) {
                    $('#program-finder__result-go').attr('data-goto', action.actions[0].goto).removeAttr('disabled');
                }
            }
        };

        var _go = function (elem) {
            var $goto = $(elem).attr('data-goto');
            if ($goto != undefined && $goto != '') {
                window.location = $goto;
            }
        };

        return {
            config: _config,
            bind: _bind,
            choose: _choose,
            searchActionsFor: _searchActionsFor,
            performActionFor: _performActionFor,
            go: _go
        }
    }());

    programFinder.bind();

})(jQuery, Drupal);