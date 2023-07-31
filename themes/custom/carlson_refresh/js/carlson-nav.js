/**
 * @file
 * Global utilities.
 * *
 */
(function ($, Drupal) {

   'use strict';

   // ------------------------------------------------------- //
   // Multi Level dropdowns
   // ------------------------------------------------------ //
   $( document ).ready( function () {
    $( '.dropdown-menu a.dropdown-toggle' ).on( 'click', function ( e ) {
        var $el = $( this );
        $el.toggleClass('active-dropdown');
        var $parent = $( this ).offsetParent( ".dropdown-menu" );
        if ( !$( this ).next().hasClass( 'show' ) ) {
            $( this ).parents( '.dropdown-menu' ).first().find( '.show' ).removeClass( "show" );
        }
        var $subMenu = $( this ).next( ".dropdown-menu" );
        $subMenu.toggleClass( 'show' );

        $( this ).parent( "li" ).toggleClass( 'show' );

        $( this ).parents( 'li.nav-item.dropdown.show' ).on( 'hidden.bs.dropdown', function ( e ) {
            $( '.dropdown-menu .show' ).removeClass( "show" );
            $el.removeClass('active-dropdown');
        } );

         if ( !$parent.parent().hasClass( 'navbar-nav' ) ) {
            $el.next().css( { "top": $el[0].offsetTop, "left": $parent.outerWidth() - 4 } );
        }

        return false;
    } );

    $( '.navbar-toggler' ).on( 'click', function(e) {
        var $curr_target = $(this).data("target");
        var $curr_ariaex = $(this).attr("aria-expanded");
        var $carl_target = $("#navbar-primary > nav > div > button").data("target");
        var $carl_ariaex = $("#navbar-primary > nav > div > button").attr("aria-expanded");
        var $alum_target = $("#navbar-secondary > nav > div > button").data("target");
        var $alum_ariaex = $("#navbar-secondary > nav > div > button").attr("aria-expanded");
        console.log("Curr Target = " + $curr_target);
        console.log("Curr AriaEx = " + $curr_ariaex);
        console.log("Carl Target = " + $carl_target);
        console.log("Carl AriaEx = " + $carl_ariaex);
        console.log("Alum Target = " + $alum_target);
        console.log("Alum AriaEx = " + $alum_ariaex);
        if (
            $curr_target == "#navbarAlumni" &&
            $carl_ariaex == "true") {
                console.log("Retracting Carlson");
                $('#navbar-primary > nav > div > button').click();
        }
        if (
            $curr_target == "#navbarCarlson" &&
            $alum_ariaex == "true") {
                console.log("Retracting Alumni");
                $('#navbar-secondary > nav > div > button').click();
        }
    });
} );


 })(jQuery, Drupal);
