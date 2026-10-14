<?php

namespace Drupal\Tests\csv_importer\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests importing translations of existing content.
 *
 * @group csv_importer
 *
 * @coversDefaultClass \Drupal\csv_importer\Plugin\ImporterBase
 */
class ImporterTranslationTest extends ImporterKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'text',
    'node',
    'language',
    'content_translation',
    'csv_importer',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['language', 'content_translation']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    ConfigurableLanguage::createFromLangcode('es')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->container->get('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);

  }

  /**
   * Create the source node the translations are attached to.
   *
   * @return \Drupal\node\NodeInterface
   *   The source node.
   */
  protected function createSource(): Node {
    $node = Node::create([
      'nid' => 1000,
      'type' => 'page',
      'title' => 'Source EN',
      'langcode' => 'en',
    ]);
    $node->enforceIsNew(TRUE);
    $node->save();

    return $node;
  }

  /**
   * Tests that a row with a langcode adds a translation.
   *
   * @covers ::add
   */
  public function testTranslationIsAdded(): void {
    $this->createSource();

    $results = $this->batch([
      0 => ['nid', 'title', 'langcode'],
      1 => ['1000', 'Translation ES', 'es'],
    ]);

    $this->assertCount(1, $results['updated'] ?? []);
    $this->assertCount(1, $results['translations'] ?? []);

    $node = $this->reload(1000);

    $this->assertTrue($node->hasTranslation('es'));
    $this->assertEquals('Translation ES', $node->getTranslation('es')->getTitle());
  }

  /**
   * Tests that adding a translation leaves the source language untouched.
   *
   * @covers ::add
   */
  public function testSourceLanguageIsPreserved(): void {
    $this->createSource();

    $this->batch([
      0 => ['nid', 'title', 'langcode'],
      1 => ['1000', 'Translation ES', 'es'],
    ]);

    $node = $this->reload(1000);

    $this->assertEquals('Source EN', $node->getTranslation('en')->getTitle());
  }

  /**
   * Tests that several languages import from a single CSV.
   *
   * @covers ::add
   */
  public function testMultipleTranslations(): void {
    $this->createSource();

    $results = $this->batch([
      0 => ['nid', 'title', 'langcode'],
      1 => ['1000', 'Translation ES', 'es'],
      2 => ['1000', 'Translation FR', 'fr'],
    ]);

    $this->assertCount(2, $results['translations'] ?? []);

    $node = $this->reload(1000);

    $langcodes = array_keys($node->getTranslationLanguages());
    sort($langcodes);

    $this->assertEquals(['en', 'es', 'fr'], $langcodes);
    $this->assertEquals('Translation ES', $node->getTranslation('es')->getTitle());
    $this->assertEquals('Translation FR', $node->getTranslation('fr')->getTitle());
  }

  /**
   * Tests that an existing translation is updated rather than duplicated.
   *
   * @covers ::add
   */
  public function testExistingTranslationIsUpdated(): void {
    $node = $this->createSource();
    $node->addTranslation('es', ['title' => 'Old ES translation'])->save();

    $this->batch([
      0 => ['nid', 'title', 'langcode'],
      1 => ['1000', 'New ES translation', 'es'],
    ]);

    $node = $this->reload(1000);

    $langcodes = array_keys($node->getTranslationLanguages());
    sort($langcodes);

    $this->assertEquals(['en', 'es'], $langcodes);
    $this->assertEquals('New ES translation', $node->getTranslation('es')->getTitle());
  }

  /**
   * Tests that an unknown langcode falls back to the default language.
   *
   * @covers ::add
   */
  public function testUnknownLangcodeFallsBackToDefault(): void {
    $this->createSource();

    $results = $this->batch([
      0 => ['nid', 'title', 'langcode'],
      1 => ['1000', 'Unknown langcode', 'zz'],
    ]);

    $this->assertCount(0, $results['translations'] ?? []);

    $node = $this->reload(1000);

    $this->assertFalse($node->hasTranslation('zz'));
    $this->assertEquals('Unknown langcode', $node->getTranslation('en')->getTitle());
  }

  /**
   * Tests that a row without an id creates content in its own language.
   *
   * Translations are attached to an existing entity, so a row that has no id
   * is a creation rather than a translation.
   *
   * @covers ::add
   */
  public function testRowWithoutIdCreatesUntranslatedContent(): void {
    $results = $this->batch([
      0 => ['title', 'langcode'],
      1 => ['New node ES', 'es'],
    ]);

    $this->assertCount(1, $results['added'] ?? []);

    $node = $this->reload((int) reset($results['added']));

    $this->assertEquals('es', $node->language()->getId());
    $this->assertEquals(['es'], array_keys($node->getTranslationLanguages()));
  }

}
