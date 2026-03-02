<?php

declare(strict_types=1);

namespace Drupal\Tests\carlson_campaign\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies modal template and client behavior hooks for tracking.
 */
final class ModalCampaignMarkupBehaviorTest extends TestCase {

  /**
   * Gets modal template contents.
   */
  private function getTemplateContents(): string {
    $template_path = __DIR__ . '/../../../templates/csm-modal-campaign.html.twig';
    $contents = file_get_contents($template_path);

    $this->assertNotFalse($contents, 'Failed to read modal template.');

    return (string) $contents;
  }

  /**
   * Gets modal JavaScript contents.
   */
  private function getJsContents(): string {
    $js_path = __DIR__ . '/../../../js/carlson-campaign.modal-campaign.js';
    $contents = file_get_contents($js_path);

    $this->assertNotFalse($contents, 'Failed to read modal JavaScript.');

    return (string) $contents;
  }

  /**
   * Modal markup should include required dialog and GTM classes.
   */
  public function testTemplateContainsDialogAndTrackingClasses(): void {
    $contents = $this->getTemplateContents();

    $this->assertStringContainsString('<dialog', $contents);
    $this->assertStringContainsString('campaign-modal', $contents);
    $this->assertStringContainsString('campaign-banner-backdrop', $contents);
    $this->assertStringContainsString('campaign-banner-close', $contents);
    $this->assertStringContainsString('campaign-banner-acknowledge', $contents);
    $this->assertStringContainsString('campaign-modal-decline', $contents);
    $this->assertStringContainsString('id="{{ modal_id }}"', $contents);
  }

  /**
   * Modal script should include required interaction handlers.
   */
  public function testJavaScriptContainsDialogInteractionHandlers(): void {
    $contents = $this->getJsContents();

    $this->assertStringContainsString('campaign:interaction', $contents);
    $this->assertStringContainsString("addEventListener('cancel'", $contents);
    $this->assertStringContainsString("closeWithAction('dismissed'", $contents);
    $this->assertStringContainsString("closeWithAction('acknowledged'", $contents);
    $this->assertStringContainsString("closeWithAction('declined'", $contents);
    $this->assertStringContainsString('modalElement.showModal()', $contents);
    $this->assertStringContainsString('DEFAULT_DELAY_SECONDS', $contents);
    $this->assertStringContainsString('config.delaySeconds * 1000', $contents);
    $this->assertStringContainsString(
      'Dismissed via keyboard ESC key',
      $contents,
    );
  }

}
