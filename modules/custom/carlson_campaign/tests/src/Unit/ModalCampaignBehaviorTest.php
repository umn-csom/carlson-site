<?php

declare(strict_types=1);

namespace Drupal\Tests\carlson_campaign\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies modal campaign behavior hooks for tracking.
 */
final class ModalCampaignBehaviorTest extends TestCase {

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
   * Modal script should include required interaction handlers and payloads.
   */
  public function testJavaScriptContainsModalInteractionHandlers(): void {
    $contents = $this->getJsContents();

    $this->assertStringContainsString('campaign:interaction', $contents);
    $this->assertStringContainsString(
      "setModalState(storageKey, 'viewed')",
      $contents,
    );
    $this->assertStringContainsString(
      "setModalState(storageKey, 'dismissed')",
      $contents,
    );
    $this->assertStringContainsString(
      "setModalState(storageKey, 'converted')",
      $contents,
    );
    $this->assertStringContainsString(
      "setModalState(storageKey, 'declined')",
      $contents,
    );
    $this->assertStringContainsString('campaign_type', $contents);
    $this->assertStringContainsString('campaign_cta_url', $contents);
    $this->assertStringContainsString('campaign_decline_text', $contents);
    $this->assertStringContainsString('dismiss_type', $contents);
    $this->assertStringContainsString('action_id', $contents);
  }

}
