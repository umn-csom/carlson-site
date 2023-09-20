<?php

namespace Drupal\ape\Tests;

use Drupal\Tests\BrowserTestBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test cache-control header is set correctly after minimal configuration.
 *
 * @group Advanced Page Expiration
 */
class ApeMinTest extends BrowserTestBase {

  protected $dumpHeaders = TRUE;

  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ape', 'ape_test', 'system'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.performance')
      ->set('cache.page.max_age', 2592000)
      ->save(TRUE);

    $this->config('ape.settings')
      ->set('alternatives', '')
      ->set('exclusions', '')
      ->set('lifetime.alternatives', 60)
      ->set('lifetime.301', 1800)
      ->set('lifetime.302', 600)
      ->set('lifetime.404', 3600)
      ->save();
  }

  /**
   * Test that correct caching is applied.
   */
  public function testApeHeaders() {
    // Check user registration page has global age.
    $this->drupalGet('user/register');
    $this->assertEquals('max-age=2592000, public', $this->getSession()->getResponseHeader('Cache-Control'), 'Global Cache-Control header set.');
  }

}
