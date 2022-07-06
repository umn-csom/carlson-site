<?php

namespace Drupal\mn_cup_table\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Hello' Block.
 *
 * @Block(
 *   id = "mn_cup_table_block",
 *   admin_label = @Translation("MN Cup Table Block"),
 *   category = @Translation("MN Cup Table Block"),
 * )
 */
class MNCupTableBlock extends BlockBase
{

    // /**
    //  * {@inheritdoc}
    //  */
    // public function defaultConfiguration() {
    //   return ['label_display' => FALSE];
    // }

    /**
     * {@inheritdoc}
     */
    public function build()
    {
        $renderable = [
        '#theme' => 'filterable_table',
        '#title' => 'Filterable Table',
        '$description' => 'Filterable Table'
        ];

        return $renderable;
    }

}