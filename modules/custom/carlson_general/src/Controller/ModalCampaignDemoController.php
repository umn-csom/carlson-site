<?php

namespace Drupal\carlson_general\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides a demo page for the Modal Campaign prototype.
 */
final class ModalCampaignDemoController extends ControllerBase {

  /**
   * Demo page content.
   *
   * @return array
   *   A render array.
   */
  public function content(): array {
    $body = [
      '#type' => 'processed_text',
      '#text' => '<p>This page renders a sample modal campaign for local testing.</p>'
        . '<p>Reset the modal state in your browser console with <code>window.csmModalCampaign.clearState()</code>, then reload.</p>',
      '#format' => 'full_html',
    ];

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container', 'py-5'],
      ],
      'intro' => [
        '#markup' => '<h1>Modal Campaign Demo</h1><p>Modal should open automatically after ~1 second.</p>',
      ],
      'modal' => [
        '#theme' => 'csm_modal_campaign',
        '#campaign_id' => 'csm_modal_campaign_demo',
        '#modal_id' => 'csm-modal-campaign-demo',
        '#eyebrow' => 'CSM Prototype',
        '#title' => 'Modal Campaign',
        '#body' => $body,
        '#cta_url' => 'https://www.example.com/',
        '#cta_text' => 'Example CTA',
        '#decline_text' => 'Maybe Later',
        '#attached' => [
          'library' => [
            'carlson_campaign/carlson-campaign.modal-campaign',
          ],
          'drupalSettings' => [
            'csmModalCampaign' => [
              'campaignId' => 'csm_modal_campaign_demo',
              'modalId' => 'csm-modal-campaign-demo',
              'sessionKey' => 'csm_modal_campaign_demo',
              'dismissDays' => 1,
              'stopOnConvert' => FALSE,
              'delaySeconds' => 0,
            ],
          ],
        ],
        '#cache' => [
          'max-age' => 0,
        ],
      ],
    ];
  }

}
