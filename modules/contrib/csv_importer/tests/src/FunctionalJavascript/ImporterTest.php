<?php

namespace Drupal\Tests\csv_importer\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\language\Traits\LanguageTestTrait;

/**
 * Tests the CSV Importer functionality using JavaScript.
 *
 * @group csv_importer
 */
class ImporterTest extends WebDriverTestBase {

  use LanguageTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'csv_importer',
    'node',
    'field',
    'text',
    'file',
    'user',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $account = $this->drupalCreateUser([
      'administer site configuration',
      'administer users',
      'administer nodes',
      'access user profiles',
      'access csv importer',
    ]);

    $this->drupalLogin($account);

    $content_type = [
      'type' => 'page',
      'name' => 'Basic page',
      'description' => 'A page content type.',
    ];

    NodeType::create($content_type)->save();

    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'type' => 'text_long',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();

    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('node', 'field_text'),
      'bundle' => 'page',
      'label' => 'Text',
      'settings' => ['display_summary' => TRUE],
    ])->save();

    \Drupal::service('entity_display.repository')->getFormDisplay('node', 'page', 'default')->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('node', 'page', 'default')->save();

    static::createLanguageFromLangcode('fr');
    static::enableBundleTranslation('node', 'page');
    static::setFieldTranslatable('node', 'page', 'field_text', TRUE);
  }

  /**
   * Tests that the CSV Importer page is accessible.
   */
  public function testPageLoad() {
    $this->drupalGet('/admin/content/csv-importer');
    $this->assertSession()->pageTextContains('Import CSV');
    $this->assertSession()->fieldExists('Select entity type');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('Select entity bundle');
    $this->assertSession()->fieldExists('Select delimiter');
    $this->assertSession()->fieldExists('Select CSV file');
  }

  /**
   * Tests the CSV file upload (add) process.
   */
  public function testFileUploadAddProcess() {
    $this->drupalGet('/admin/content/csv-importer');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Select entity bundle', 'Basic page');
    $this->getSession()->getPage()->selectFieldOption('Select delimiter', ',');

    $module_path = \Drupal::service('extension.list.module')->getPath('csv_importer');
    $file_path = $this->root . '/' . $module_path . '/tests/files/sample.csv';
    $this->assertFileExists($file_path, 'The CSV file exists and is accessible.');
    $this->getSession()->getPage()->attachFileToField('Select CSV file', $file_path);
    $this->getSession()->getPage()->pressButton('Import');

    if ($this->getSession()->wait(5000)) {
      $this->assertSession()->pageTextContains('3 new content added, 0 updated and translations created for 0 content.');
    }
  }

  /**
   * Tests the CSV file upload (update) process.
   */
  public function testFileUploadUpdateProcess() {
    // Create a node.
    $node = Node::create([
      'nid' => 1010,
      'type' => 'page',
      'title' => 'Original page 1',
      'field_text' => [
        ['value' => 'Original body value 1'],
        ['value' => 'Original body value 2'],
      ],
    ]);
    $node->enforceIsNew(TRUE);
    $node->save();

    // Assert the node is created correctly.
    $created_node = Node::load(1010);
    $this->assertEquals('Original page 1', $created_node->getTitle());
    $this->assertEquals('Original body value 1', $created_node->get('field_text')->get(0)->value);
    $this->assertEquals('Original body value 2', $created_node->get('field_text')->get(1)->value);

    $this->drupalGet('/admin/content/csv-importer');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Select entity bundle', 'Basic page');
    $this->getSession()->getPage()->selectFieldOption('Select delimiter', ',');

    $module_path = \Drupal::service('extension.list.module')->getPath('csv_importer');
    $file_path = $this->root . '/' . $module_path . '/tests/files/sample.csv';
    $this->assertFileExists($file_path, 'The CSV file exists and is accessible.');
    $this->getSession()->getPage()->attachFileToField('Select CSV file', $file_path);
    $this->getSession()->getPage()->pressButton('Import');

    if ($this->getSession()->wait(5000)) {
      $this->assertSession()->pageTextContains('2 new content added, 1 updated and translations created for 0 content.');

      \Drupal::entityTypeManager()->getStorage('node')->resetCache([1010]);

      $updated_node = Node::load(1010);
      $this->assertEquals('Test page 3 updated', $updated_node->getTitle());
      $this->assertEquals('Text 5 value', $updated_node->get('field_text')->get(0)->value);
      $this->assertEquals('Text 6 value', $updated_node->get('field_text')->get(1)->value);
    }
  }

  /**
   * Tests the CSV file upload process for multiple fields.
   */
  public function testFileUploadWithMultipleField() {
    $this->drupalGet('/admin/content/csv-importer');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Select entity bundle', 'Basic page');
    $this->getSession()->getPage()->selectFieldOption('Select delimiter', ',');

    $module_path = \Drupal::service('extension.list.module')->getPath('csv_importer');
    $file_path = $this->root . '/' . $module_path . '/tests/files/sample.csv';
    $this->assertFileExists($file_path, 'The CSV file exists and is accessible.');
    $this->getSession()->getPage()->attachFileToField('Select CSV file', $file_path);
    $this->getSession()->getPage()->pressButton('Import');

    if ($this->getSession()->wait(5000)) {
      $this->assertSession()->pageTextContains('3 new content added, 0 updated and translations created for 0 content.');

      $entity_storage = \Drupal::entityTypeManager();
      $node = $entity_storage->getStorage('node')->load(1000);
      $body_values = $node->get('field_text')->getValue();
      $this->assertEquals('Text 1 value', $body_values[0]['value']);
      $this->assertEquals('Text 2 value', $body_values[1]['value']);

      $node = $entity_storage->getStorage('node')->load(1001);
      $body_values = $node->get('field_text')->getValue();
      $this->assertEquals('Text 3 value', $body_values[0]['value']);
      $this->assertEquals('Text 4 value', $body_values[1]['value']);
    }
  }

  /**
   * Tests the CSV Importer translation functionality.
   */
  public function testTranslationImport() {
    $node = Node::create([
      'nid' => 1011,
      'type' => 'page',
      'title' => 'Original page 1',
      'field_text' => [
        ['value' => 'Original body value 1'],
        ['value' => 'Original body value 2'],
      ],
    ]);
    $node->enforceIsNew(TRUE);
    $node->save();

    $this->assertNotEmpty(
      \Drupal::languageManager()->getLanguage('fr'),
      'Failed to add the French language (fr).'
    );

    $this->drupalGet('/admin/content/csv-importer');
    $this->getSession()->getPage()->selectFieldOption('Select entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Select entity bundle', 'Basic page');
    $this->getSession()->getPage()->selectFieldOption('Select delimiter', ',');

    $module_path = \Drupal::service('extension.list.module')->getPath('csv_importer');
    $file_path = $this->root . '/' . $module_path . '/tests/files/sample_translation.csv';
    $this->assertFileExists($file_path, 'The CSV file exists and is accessible.');
    $this->getSession()->getPage()->attachFileToField('Select CSV file', $file_path);
    $this->getSession()->getPage()->pressButton('Import');

    if ($this->getSession()->wait(5000)) {
      $this->assertSession()->pageTextContains('0 new content added, 1 updated and translations created for 1 content.');

      \Drupal::entityTypeManager()->getStorage('node')->resetCache([1011]);
      $original_node = Node::load(1011);
      $translated_node = $original_node->getTranslation('fr');

      $this->assertEquals('Titre français traduit', $translated_node->getTitle());
      $this->assertEquals('Valeur du texte 7', $translated_node->get('field_text')->get(0)->value);
      $this->assertEquals('Valeur du texte 8', $translated_node->get('field_text')->get(1)->value);
    }
  }

}
