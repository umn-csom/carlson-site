<?php

namespace Drupal\menu_injector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Tags;
use Drupal\Component\Utility\Unicode;

/**
 * Defines a route controller for entity autocomplete form elements.
 */
class AutocompleteController extends ControllerBase {

  /**
   * Handler for autocomplete request.
   */
  public function handleAutocomplete(Request $request, $field_name) {
    $results = [];
    
    // if( isset($field_name) && strlen($field_name) > 0 ) {
    //   $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($field_name);
    //   foreach ($terms as $term) {
    //     $results[] = array(
    //       "value" => $term->tid,
    //       "label" => $term->name
    //     );
    //   }
    // }

    return new JsonResponse($results);
  }

}