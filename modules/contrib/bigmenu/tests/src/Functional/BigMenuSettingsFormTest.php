<?php

namespace Drupal\Tests\bigmenu\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test in big menu settings form.
 *
 * @group bigmenu
 */
class BigMenuSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritDoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'bigmenu',
  ];

  /**
   * Test to test the settings form item.
   */
  public function testSettingsForm(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer site configuration',
    ]));
    $this->drupalGet('admin/config/bigmenu');
    $this->submitForm([
      'max_depth' => 2,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertEquals(2, \Drupal::config('bigmenu.settings')->get('max_depth'));
    // Try a bigger number than 10.
    $this->submitForm([
      'max_depth' => 11,
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('must be lower than or equal to 10');
    $this->assertEquals(2, \Drupal::config('bigmenu.settings')->get('max_depth'));
  }

}
