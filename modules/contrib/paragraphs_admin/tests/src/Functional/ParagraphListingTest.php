<?php

namespace Drupal\Tests\paragraphs_admin\Functional;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\paragraphs\Functional\WidgetStable\ParagraphsTestBase;
use Drupal\Tests\paragraphs\FunctionalJavascript\ParagraphsTestBaseTrait;

/**
 * Paragraphs listing class for tests.
 */
class ParagraphListingTest extends ParagraphsTestBase {

  use ParagraphsTestBaseTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'paragraphs',
    'paragraphs_admin',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests file overview with different user permissions.
   */
  public function testParagraphListingPages() {
    $this->addParagraphsType('paragraphed_test');

    // Users without sufficient permissions should not see paragraph listing.
    $basicUser = $this->drupalCreateUser();
    $admin_permissions = ['administer paragraphs'];

    $this->drupalLogin($basicUser);
    $this->drupalGet('admin/content/paragraphs');
    $this->assertSession()->statusCodeEquals(403);

    // Log in with user with right permissions and test listing.
    $this->loginAsAdmin($admin_permissions);
    $this->drupalGet('admin/content/paragraphs');
    $this->assertSession()->pageTextContains('Paragraphs');

    // Create a paragraph.
    $paragraph = $this->createParagraph();
    $pid = $paragraph->id();

    // Check paragraph exists.
    $delete_link = 'paragraph/' . $pid . '/delete';
    $this->drupalGet('admin/content/paragraphs');
    $this->assertSession()->linkByHrefExists($delete_link);

    // Test delete confirmation page.
    $this->clickLink('delete');
    $this->assertSession()->pageTextContains('Are you sure you want to delete the paragraph Orphaned paragraphed_test: ?');

    // Check if cancel button takes it back to paragraphs list.
    $this->getSession()->getPage()->clickLink('Cancel');
    // Check that this is paragraphs list page and this paragraph is
    // still available.
    $this->assertSession()->pageTextContains('Paragraphs');
    $this->assertSession()->linkByHrefExists($delete_link);

    // Finally, test delete paragraph action.
    $this->clickLink('delete');
    $this->getSession()->getPage()->pressButton('Delete');

    // As of now we are not adding any parent entity to the paragraph created
    // for tests so the paragraph label will return type and empty summary
    // therefore checking the success message for orphaned paragraph.
    $this->assertSession()->pageTextContains('The paragraph Orphaned paragraphed_test: has been deleted.');
    // Check that paragraph was deleted and removed from the paragraphs list.
    $this->assertSession()->pageTextContains('Paragraphs');
    $this->assertSession()->linkByHrefNotExists($delete_link);
  }

  /**
   * Creates and saves a test paragraph.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   A paragraph entity.
   */
  protected function createParagraph() {
    // Create a new paragraph entity.
    $paragraph = Paragraph::create([
      'type' => 'paragraphed_test',
      'langcode' => 'en',
    ]);
    $paragraph->save();

    return $paragraph;
  }

}
