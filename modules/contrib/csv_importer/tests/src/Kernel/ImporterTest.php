<?php

namespace Drupal\Tests\csv_importer\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests CSV import data preparation, field mapping and the batch add process.
 *
 * @group csv_importer
 *
 * @coversDefaultClass \Drupal\csv_importer\Plugin\ImporterBase
 */
class ImporterTest extends ImporterKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'text',
    'link',
    'telephone',
    'datetime',
    'options',
    'taxonomy',
    'node',
    'csv_importer',
  ];

  /**
   * A taxonomy term used as an entity-reference target.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    $this->term = Term::create(['vid' => 'tags', 'name' => 'Tag one']);
    $this->term->save();

    $this->createField('field_text', 'text_long', [], FieldStorageConfig::CARDINALITY_UNLIMITED);
    $this->createField('field_int', 'integer');
    $this->createField('field_decimal', 'decimal');
    $this->createField('field_float', 'float');
    $this->createField('field_bool', 'boolean');
    $this->createField('field_email', 'email');
    $this->createField('field_phone', 'telephone');
    $this->createField('field_link', 'link');
    $this->createField('field_date', 'datetime');
    $this->createField('field_list', 'list_string', [
      'allowed_values' => ['a' => 'Option A', 'b' => 'Option B'],
    ]);
    $this->createField('field_ref', 'entity_reference', [
      'target_type' => 'taxonomy_term',
    ]);

  }

  /**
   * Create a field on the page node type.
   *
   * @param string $name
   *   The field name.
   * @param string $type
   *   The field type.
   * @param array $storage_settings
   *   Optional field storage settings.
   * @param int $cardinality
   *   Field cardinality (defaults to single value).
   */
  protected function createField(string $name, string $type, array $storage_settings = [], int $cardinality = 1): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $type,
      'settings' => $storage_settings,
      'cardinality' => $cardinality,
    ])->save();

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();
  }

  /**
   * Prepare data and create the first node from a CSV matrix.
   *
   * @param array $csv
   *   The CSV matrix.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   */
  protected function import(array $csv): Node {
    $data = $this->createImporter($csv)->data();
    $node = Node::create($data['content'][1] + ['type' => 'page']);
    $node->save();

    return $node;
  }

  /**
   * Tests that data() maps CSV columns and filters to configured fields.
   *
   * @covers ::data
   */
  public function testDataMapsAndFiltersFields(): void {
    $importer = $this->createImporter([
      0 => ['title', 'sticky'],
      1 => ['First node', '1'],
      2 => ['Second node', '0'],
    ], ['fields' => ['title']]);

    $data = $importer->data();

    $this->assertCount(2, $data['content']);
    $this->assertSame('First node', $data['content'][1]['title']);
    $this->assertArrayNotHasKey('sticky', $data['content'][1]);
  }

  /**
   * Tests that data() does not error on non-UTF-8 input.
   *
   * Regression for mb_detect_encoding() returning FALSE.
   *
   * @covers ::data
   */
  public function testDataHandlesUndetectableEncoding(): void {
    $importer = $this->createImporter([
      0 => ['title'],
      1 => ["Caf\xe9"],
    ], ['fields' => ['title']]);

    $data = $importer->data();

    $this->assertTrue(mb_check_encoding($data['content'][1]['title'], 'UTF-8'));
  }

  /**
   * Tests that scalar core field types import with correct values.
   */
  public function testScalarFieldTypes(): void {
    $node = $this->import([
      0 => [
        'title',
        'field_int',
        'field_decimal',
        'field_float',
        'field_bool',
        'field_email',
        'field_phone',
        'field_date',
        'field_list',
      ],
      1 => [
        'Typed node',
        '42',
        '12.50',
        '3.14',
        '1',
        'user@example.com',
        '+15551234567',
        '2020-01-15T10:30:00',
        'a',
      ],
    ]);

    $this->assertSame('Typed node', $node->getTitle());
    $this->assertSame('42', $node->get('field_int')->value);
    $this->assertEquals(12.5, $node->get('field_decimal')->value);
    $this->assertEquals(3.14, $node->get('field_float')->value);
    $this->assertEquals(1, $node->get('field_bool')->value);
    $this->assertSame('user@example.com', $node->get('field_email')->value);
    $this->assertSame('+15551234567', $node->get('field_phone')->value);
    $this->assertSame('2020-01-15T10:30:00', $node->get('field_date')->value);
    $this->assertSame('a', $node->get('field_list')->value);
  }

  /**
   * Tests that a link field imports via the field|property syntax.
   */
  public function testLinkFieldType(): void {
    $node = $this->import([
      0 => ['title', 'field_link|uri'],
      1 => ['Link node', 'https://example.com'],
    ]);

    $this->assertSame('https://example.com', $node->get('field_link')->uri);
  }

  /**
   * Tests that an entity reference field imports via the target_id property.
   */
  public function testEntityReferenceFieldType(): void {
    $node = $this->import([
      0 => ['title', 'field_ref|target_id'],
      1 => ['Reference node', (string) $this->term->id()],
    ]);

    $this->assertEquals($this->term->id(), $node->get('field_ref')->target_id);
  }

  /**
   * Tests that the values()/multiple() syntax fills a multi-value field.
   */
  public function testMultiValueField(): void {
    $node = $this->import([
      0 => ['title', 'field_text'],
      1 => ['Multi node', 'values(Text 1 value+Text 2 value)'],
    ]);

    $values = array_column($node->get('field_text')->getValue(), 'value');
    $this->assertSame(['Text 1 value', 'Text 2 value'], $values);
  }

  /**
   * Tests that attach() accepts a stream-wrapper URI.
   *
   * @covers ::attach
   */
  public function testAttachAcceptsStreamWrapperUri(): void {
    $uri = 'public://csv_importer_test.txt';
    file_put_contents($uri, 'attachment contents');

    $importer = $this->createImporter([0 => ['title'], 1 => ['x']]);
    $result = $this->invoke($importer, 'attach', [$uri]);

    $file = $this->container->get('entity_type.manager')
      ->getStorage('file')
      ->load($result);
    $this->assertNotNull($file);
    $this->assertSame('csv_importer_test.txt', $file->getFilename());
  }

  /**
   * Tests that attach() rejects a bare local filesystem path.
   *
   * Regression for arbitrary local file disclosure.
   *
   * @covers ::attach
   */
  public function testAttachRejectsLocalPath(): void {
    $tmp = tempnam(sys_get_temp_dir(), 'csv_importer_');
    file_put_contents($tmp, 'secret');

    $importer = $this->createImporter([0 => ['title'], 1 => ['x']]);
    $result = $this->invoke($importer, 'attach', [$tmp]);

    $this->assertSame($tmp, $result);

    unlink($tmp);
  }

  /**
   * Tests that the batch counts all rows as added when none pre-exist.
   *
   * @covers ::add
   */
  public function testBatchAddAllNew(): void {
    $results = $this->batch($this->csv());

    $this->assertCount(3, $results['added'] ?? []);
    $this->assertCount(0, $results['updated'] ?? []);
    $this->assertEquals('Test page 3 updated', Node::load(1010)->getTitle());
  }

  /**
   * Tests that an existing id is updated rather than added.
   *
   * Regression for the batch overrun that miscounted the last row.
   *
   * @covers ::add
   */
  public function testBatchUpdateExisting(): void {
    $node = Node::create(['nid' => 1010, 'type' => 'page', 'title' => 'Original']);
    $node->enforceIsNew(TRUE);
    $node->save();

    $results = $this->batch($this->csv());

    $this->assertCount(2, $results['added'] ?? []);
    $this->assertCount(1, $results['updated'] ?? []);
    $this->assertEquals('Test page 3 updated', Node::load(1010)->getTitle());
  }

  /**
   * The sample CSV matrix mirroring tests/files/sample.csv.
   *
   * @return array
   *   The CSV matrix.
   */
  protected function csv(): array {
    return [
      0 => ['nid', 'title', 'field_text', 'langcode'],
      1 => ['1000', 'Test page 1', 'values(Text 1 value+Text 2 value)'],
      2 => ['1001', 'Test page 2', 'multiple(Text 3 value+Text 4 value)'],
      3 => ['1010', 'Test page 3 updated', 'multiple(Text 5 value+Text 6 value)'],
    ];
  }

}
