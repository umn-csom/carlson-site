<?php

namespace Drupal\CSOM_DataLayer\Controller;

use Drupal\Core\Controller\ControllerBase;

class CSOM_DataLayerController extends ControllerBase {
  public function content() {
    return array(
      '#type' => 'markup',
      '#markup' => t('Under Construction'),
    );
  }
}