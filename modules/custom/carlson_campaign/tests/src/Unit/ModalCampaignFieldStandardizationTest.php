<?php

declare(strict_types=1);

namespace Drupal\Tests\carlson_campaign\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies campaign field standardization in modal runtime code.
 */
final class ModalCampaignFieldStandardizationTest extends TestCase {

  /**
   * Gets carlson_campaign.module contents.
   */
  private function getModuleFileContents(): string {
    $module_path = __DIR__ . '/../../../carlson_campaign.module';
    $contents = file_get_contents($module_path);

    $this->assertNotFalse($contents, 'Failed to read module file.');

    return (string) $contents;
  }

  /**
   * Shared campaign fields should be used for reusable behavior/content.
   */
  public function testSharedCampaignFieldLookupsAreUsed(): void {
    $contents = $this->getModuleFileContents();

    $this->assertStringContainsString('field_campaign_text', $contents);
    $this->assertStringContainsString('field_campaign_dismiss_days', $contents);
    $this->assertStringContainsString('field_campaign_session_key', $contents);
    $this->assertStringContainsString(
      "'campaign-modal-' . \$campaign_id",
      $contents,
    );
  }

  /**
   * Legacy shared modal field lookups should no longer drive runtime logic.
   */
  public function testLegacySharedModalFieldLookupsAreRemoved(): void {
    $contents = $this->getModuleFileContents();

    $this->assertStringNotContainsString('field_modal_body', $contents);
    $this->assertStringNotContainsString('field_modal_dismiss_days', $contents);
    $this->assertStringNotContainsString('field_modal_session_key', $contents);
  }

}
