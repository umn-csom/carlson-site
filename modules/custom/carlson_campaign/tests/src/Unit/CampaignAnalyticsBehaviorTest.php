<?php

declare(strict_types=1);

namespace Drupal\Tests\carlson_campaign\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the campaign analytics bridge payload contract.
 */
final class CampaignAnalyticsBehaviorTest extends TestCase {

  /**
   * Gets analytics bridge JavaScript contents.
   */
  private function getJsContents(): string {
    $js_path = __DIR__ . '/../../../js/carlson-campaign.analytics.js';
    $contents = file_get_contents($js_path);

    $this->assertNotFalse($contents, 'Failed to read analytics JavaScript.');

    return (string) $contents;
  }

  /**
   * Analytics bridge should define GTM event names and payload fields.
   */
  public function testAnalyticsBridgeContainsExpectedEventPayloadKeys(): void {
    $contents = $this->getJsContents();

    $this->assertStringContainsString('campaign:interaction', $contents);
    $this->assertStringContainsString(
      'window.dataLayer = window.dataLayer || [];',
      $contents,
    );
    $this->assertStringContainsString('campaign_view', $contents);
    $this->assertStringContainsString('campaign_convert', $contents);
    $this->assertStringContainsString('campaign_dismiss', $contents);
    $this->assertStringContainsString('campaign_decline', $contents);
    $this->assertStringContainsString('campaign_page_path', $contents);
    $this->assertStringContainsString('campaign_key', $contents);
    $this->assertStringContainsString('campaign_variant_name', $contents);
    $this->assertStringContainsString('campaign_placement', $contents);
    $this->assertStringContainsString('campaign_cta_text', $contents);
    $this->assertStringContainsString('campaign_cta_url', $contents);
    $this->assertStringContainsString('campaign_dismiss_type', $contents);
    $this->assertStringContainsString('campaign_action_id', $contents);
    $this->assertStringContainsString('campaign_action_index', $contents);
  }

}
