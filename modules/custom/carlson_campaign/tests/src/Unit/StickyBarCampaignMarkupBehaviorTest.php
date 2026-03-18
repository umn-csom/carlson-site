<?php

declare(strict_types=1);

namespace Drupal\Tests\carlson_campaign\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Verifies sticky bar template and behavior hooks for tracking.
 */
final class StickyBarCampaignMarkupBehaviorTest extends TestCase {

  /**
   * Gets sticky bar template contents.
   */
  private function getTemplateContents(): string {
    $template_path = __DIR__ .
      '/../../../templates/csm-sticky-bar-campaign.html.twig';
    $contents = file_get_contents($template_path);

    $this->assertNotFalse($contents, 'Failed to read sticky template.');

    return (string) $contents;
  }

  /**
   * Gets sticky bar JavaScript contents.
   */
  private function getJsContents(): string {
    $js_path = __DIR__ . '/../../../js/carlson-campaign.sticky-bar-campaign.js';
    $contents = file_get_contents($js_path);

    $this->assertNotFalse($contents, 'Failed to read sticky JavaScript.');

    return (string) $contents;
  }

  /**
   * Sticky markup should include required element and GTM classes.
   */
  public function testTemplateContainsStickyBarAndTrackingClasses(): void {
    $contents = $this->getTemplateContents();

    $this->assertStringContainsString('<aside', $contents);
    $this->assertStringContainsString('campaign-sticky-bar', $contents);
    $this->assertStringContainsString(
      'campaign-sticky-bar--{{ sticky_position|clean_class }}',
      $contents,
    );
    $this->assertStringContainsString(
      'campaign-sticky-bar--{{ sticky_color_scheme|clean_class }}',
      $contents,
    );
    $this->assertStringContainsString('campaign-sticky-bar-close', $contents);
    $this->assertStringContainsString('campaign-sticky-bar-text', $contents);
    $this->assertStringContainsString('id="{{ sticky_id }}"', $contents);
  }

  /**
   * Sticky script should include required interaction handlers and payloads.
   */
  public function testJavaScriptContainsStickyInteractionHandlers(): void {
    $contents = $this->getJsContents();

    $this->assertStringContainsString('campaign:interaction', $contents);
    $this->assertStringContainsString(
      "setStickyState(storageKey, 'dismissed')",
      $contents,
    );
    $this->assertStringContainsString(
      "setStickyState(storageKey, 'converted')",
      $contents,
    );
    $this->assertStringContainsString('action_name', $contents);
    $this->assertStringContainsString('action_text', $contents);
    $this->assertStringContainsString('campaign_key', $contents);
    $this->assertStringContainsString('variant_name', $contents);
    $this->assertStringContainsString("'Sticky Bar Close'", $contents);
    $this->assertStringContainsString('campaign-sticky-bar-text a', $contents);
  }

}
