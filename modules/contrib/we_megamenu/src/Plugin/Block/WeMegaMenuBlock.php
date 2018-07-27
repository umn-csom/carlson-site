<?php

namespace Drupal\we_megamenu\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\we_megamenu\WeMegaMenuBuilder;

/**
 * Provides a 'Drupal 8 Mega Menu' Block.
 *
 * @Block(
 *   id = "we_megamenu_block",
 *   admin_label = @Translation("Drupal 8 Mega Menu"),
 *   category = @Translation("Drupal 8 Mega Menu"),
 *   deriver = "Drupal\we_megamenu\Plugin\Derivative\WeMegaMenuBlock",
 * )
 */
class WeMegaMenuBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      '#theme' => 'we_megamenu_frontend',
      '#menu_name' => $this->getDerivativeId(),
      '#blocks' => WeMegaMenuBuilder::getAllBlocks(),
      '#block_theme' => \Drupal::config('system.theme')->get('default'),
      '#attached' => [
        'library' => [
          'we_megamenu/form.we-mega-menu-frontend',
        ],
      ],
      '#cache' => [
        'max-age' => 0,
      ]
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * Default cache is disabled. 
   * 
   * @param array $form
   *   Public function buildConfigurationForm array form.
   * @param \Drupal\we_megamenu\Plugin\Block\FormStateInterface $form_state
   *   Public function buildConfigurationForm form_state.
   *
   * @return int
   *   Public function buildConfigurationForm int.
  */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $rebuild_form = parent::buildConfigurationForm($form, $form_state);
    $rebuild_form['cache']['max_age']['#default_value'] = 0;
    return $rebuild_form;
  }
}