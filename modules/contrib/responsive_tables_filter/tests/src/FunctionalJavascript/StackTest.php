<?php

namespace Drupal\Tests\responsive_tables_filter\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the responsive_tables_filter filter.
 *
 * @group responsive_tables_filter
 */
class StackTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['filter', 'responsive_tables_filter', 'node'];

  /**
   * {@inheritdoc}
   */
  protected $mode = 'stack';

  /**
   * The test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $webUser;

  /**
   * Specify the theme to be used in testing.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A set up for all tests.
   */
  protected function setUp():void {
    parent::setUp();

    // Create a page content type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // Create a text format and enable the responsive_tables_filter filter.
    $format = FilterFormat::create([
      'format' => 'custom_format',
      'name' => 'Custom format',
      'filters' => [
        'filter_html' => [
          'status' => 1,
          'settings' => [
            'allowed_html' => '<a href> <p> <em> <strong> <cite> <blockquote> <code> <ul> <ol> <li> <dl> <dt> <dd> <br> <h3 id> <table class additional> <th> <tr> <td> <thead> <tbody> <tfoot>',
          ],
        ],
        'filter_responsive_tables_filter' => [
          'status' => 1,
          'settings' => [
            'tablesaw_type' => $this->mode,
          ],
        ],
      ],
    ]);
    $format->save();

    // Create a user with required permissions.
    $this->webUser = $this->drupalCreateUser([
      'access content',
      'create page content',
      'use text format custom_format',
    ]);
    $this->drupalLogin($this->webUser);
  }

  /**
   * Input & output for stack mode on big screens.
   *
   * @var desktopData
   */
  private static $desktopData = [
    '<table class="no-tablesaw"><thead><tr><th>one</th></tr></thead></table>' => '<table class="no-tablesaw"><thead><tr><th>one</th></tr></thead></table>',
    '<table><thead><tr><th>one</th></tr></thead></table>' => '<table class="tablesaw tablesaw-stack" data-tablesaw-mode="stack" data-tablesaw-minimap=""><thead><tr><th role="columnheader" data-tablesaw-priority="persist">one</th></tr></thead></table>',
    '<table class="test"><thead><tr><th>one</th></tr></thead></table>' => '<table class="test tablesaw tablesaw-stack" data-tablesaw-mode="stack" data-tablesaw-minimap=""><thead><tr><th role="columnheader" data-tablesaw-priority="persist">one</th></tr></thead></table>',
    '<table additional="test"><thead><tr><th role="columnheader" data-tablesaw-priority="persist">Header One<th>Header 2<tbody><tr><td>Easily add tables with the WYSIWYG toolbar<td>Encoded characters test öô & , ?<tr><td>Tables respond to display on smaller screens<td>Fully accessible to screen readers</table>' => '<table additional="test" class="tablesaw tablesaw-stack" data-tablesaw-mode="stack" data-tablesaw-minimap=""><thead><tr><th role="columnheader" data-tablesaw-priority="persist">Header One</th><th role="columnheader">Header 2</th></tr></thead><tbody><tr><td><strong class="tablesaw-cell-label" aria-hidden="true">Header One</strong> <span class="tablesaw-cell-content">Easily add tables with the WYSIWYG toolbar</span></td><td><strong class="tablesaw-cell-label" aria-hidden="true">Header 2</strong> <span class="tablesaw-cell-content">Encoded characters test öô &amp; , ?</span></td></tr><tr><td><strong class="tablesaw-cell-label" aria-hidden="true">Header One</strong> <span class="tablesaw-cell-content">Tables respond to display on smaller screens</span></td><td><strong class="tablesaw-cell-label" aria-hidden="true">Header 2</strong> <span class="tablesaw-cell-content">Fully accessible to screen readers</span></td></tr></tbody></table>',
  ];

  /**
   * Input & output for stack mode on small screens.
   *
   * @var mobileData
   */
  private static $mobileData = [
    '<table additional="test"><thead><tr><th role="columnheader" data-tablesaw-priority="persist">Header One<th>Header 2<tbody><tr><td>Easily add tables with the WYSIWYG toolbar<td>Encoded characters test öô & , ?<tr><td>Tables respond to display on smaller screens<td>Fully accessible to screen readers</table>' => '<table additional="test" class="tablesaw tablesaw-stack" data-tablesaw-mode="stack" data-tablesaw-minimap=""><thead><tr><th role="columnheader" data-tablesaw-priority="persist">Header One</th><th role="columnheader">Header 2</th></tr></thead><tbody><tr><td><strong class="tablesaw-cell-label" aria-hidden="true">Header One</strong> <span class="tablesaw-cell-content">Easily add tables with the WYSIWYG toolbar</span></td><td><strong class="tablesaw-cell-label" aria-hidden="true">Header 2</strong> <span class="tablesaw-cell-content">Encoded characters test öô &amp; , ?</span></td></tr><tr><td><strong class="tablesaw-cell-label" aria-hidden="true">Header One</strong> <span class="tablesaw-cell-content">Tables respond to display on smaller screens</span></td><td><strong class="tablesaw-cell-label" aria-hidden="true">Header 2</strong> <span class="tablesaw-cell-content">Fully accessible to screen readers</span></td></tr></tbody></table>',
  ];

  /**
   * Tests the responsive_tables_filter Stack (default) mode on big screens.
   */
  public function testStackDesktop() {
    $page = $this->getSession()->getPage();
    foreach (self::$desktopData as $input => $expected) {
      $settings = [];
      $settings['type'] = 'page';
      $settings['title'] = 'Test Tablesaw Stack Only mode';
      $settings['body'] = [
        'value' => $input,
        'format' => 'custom_format',
      ];
      $node = $this->drupalCreateNode($settings);
      $this->drupalGet('node/' . $node->id());
      $table = $page->find('css', 'table');
      $actual = $table->getOuterHtml();
      // Strip aleatorically-generated ID from comparison.
      $actual = preg_replace('/ id="tablesaw-(\d+)"/', '', $actual);
      $this->assertEquals($expected, $actual);
    }
  }

  /**
   * Tests the responsive_tables_filter Stack (default) mode on small screens.
   */
  public function testStackMobile() {
    $page = $this->getSession()->getPage();
    // Set screen width to 400px.
    $this->getSession()->resizeWindow(400, 3000);
    foreach (self::$mobileData as $input => $expected) {
      $settings = [];
      $settings['type'] = 'page';
      $settings['title'] = 'Test Tablesaw Stack Only mode';
      $settings['body'] = [
        'value' => $input,
        'format' => 'custom_format',
      ];
      $node = $this->drupalCreateNode($settings);
      $this->drupalGet('node/' . $node->id());
      $table = $page->find('css', 'table');
      $actual = $table->getOuterHtml();
      // Strip aleatorically-generated ID from comparison.
      $actual = preg_replace('/ id="tablesaw-(\d+)"/', '', $actual);
      $this->assertEquals($expected, $actual);
    }
  }

}
