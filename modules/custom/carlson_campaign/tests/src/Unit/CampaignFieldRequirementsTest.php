<?php

declare(strict_types=1);

namespace Drupal\Tests\carlson_campaign\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies campaign field requirements for modal and sticky bundles.
 */
final class CampaignFieldRequirementsTest extends TestCase {

  /**
   * Gets a config file from config/sync.
   */
  private function getConfigContents(string $config_name): string {
    $config_path = __DIR__ .
      '/../../../../../../../config/sync/' . $config_name;
    $contents = file_get_contents($config_path);

    $this->assertNotFalse($contents, 'Failed to read config: ' . $config_name);

    return (string) $contents;
  }

  /**
   * Sticky bundle config should exist and use the expected machine name.
   */
  public function testStickyBarBundleConfigExists(): void {
    $contents = $this->getConfigContents(
      'block_content.type.sticky_bar_campaign.yml',
    );

    $this->assertStringContainsString('id: sticky_bar_campaign', $contents);
    $this->assertStringContainsString(
      "label: 'Sticky Bar Campaign'",
      $contents,
    );
  }

  /**
   * Modal shared fields should be required with updated defaults.
   */
  public function testModalSharedFieldsAreRequired(): void {
    $delay_contents = $this->getConfigContents(
      'field.field.block_content.modal_campaign.' .
      'field_campaign_delay_seconds.yml',
    );
    $dismiss_contents = $this->getConfigContents(
      'field.field.block_content.modal_campaign.' .
      'field_campaign_dismiss_days.yml',
    );
    $session_contents = $this->getConfigContents(
      'field.field.block_content.modal_campaign.field_campaign_session_key.yml',
    );

    $this->assertStringContainsString('required: true', $delay_contents);
    $this->assertStringContainsString("label: Delay", $delay_contents);
    $this->assertStringContainsString("suffix: ' seconds'", $delay_contents);
    $this->assertStringContainsString('value: 5', $delay_contents);
    $this->assertStringContainsString('required: true', $dismiss_contents);
    $this->assertStringContainsString('value: 1', $dismiss_contents);
    $this->assertStringContainsString('min: 0', $dismiss_contents);
    $this->assertStringContainsString('required: true', $session_contents);
  }

  /**
   * Sticky fields should include required delay, frequency, and session values.
   */
  public function testStickySharedAndDelayFieldsAreConfigured(): void {
    $dismiss_contents = $this->getConfigContents(
      'field.field.block_content.sticky_bar_campaign.' .
      'field_campaign_dismiss_days.yml',
    );
    $session_contents = $this->getConfigContents(
      'field.field.block_content.sticky_bar_campaign.' .
      'field_campaign_session_key.yml',
    );
    $delay_contents = $this->getConfigContents(
      'field.field.block_content.sticky_bar_campaign.' .
      'field_campaign_delay_seconds.yml',
    );

    $this->assertStringContainsString('required: true', $dismiss_contents);
    $this->assertStringContainsString('value: 1', $dismiss_contents);
    $this->assertStringContainsString('required: true', $session_contents);
    $this->assertStringContainsString('required: true', $delay_contents);
    $this->assertStringContainsString("label: Delay", $delay_contents);
    $this->assertStringContainsString("suffix: ' seconds'", $delay_contents);
    $this->assertStringContainsString('value: 5', $delay_contents);
  }

}
