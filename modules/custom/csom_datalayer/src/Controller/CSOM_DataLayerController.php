<?php

namespace Drupal\csom_datalayer\Controller;

// This really needed?
use Drupal\Core\Controller\ControllerBase;

class CSOM_DataLayerController extends ControllerBase
{
    public function content()
    {
        return array(
        '#type' => 'markup',
        '#markup' => t('Under Construction'),
        );
    }
}
