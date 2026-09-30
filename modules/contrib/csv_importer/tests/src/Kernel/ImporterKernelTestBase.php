<?php

namespace Drupal\Tests\csv_importer\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\csv_importer\Plugin\ImporterInterface;

/**
 * Base class for CSV importer kernel tests.
 */
abstract class ImporterKernelTestBase extends KernelTestBase {

  /**
   * The entity type imported by the test.
   *
   * @var string
   */
  protected $entityType = 'node';

  /**
   * The entity type bundle imported by the test.
   *
   * @var string
   */
  protected $entityTypeBundle = 'page';

  /**
   * The importer plugin manager.
   *
   * @var \Drupal\csv_importer\Plugin\ImporterManager
   */
  protected $importerManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->importerManager = $this->container->get('plugin.manager.importer');
  }

  /**
   * Create an importer plugin for the given CSV matrix.
   *
   * @param array $csv
   *   The CSV matrix, including the header row.
   * @param array $overrides
   *   Configuration overriding the defaults built from the CSV.
   *
   * @return \Drupal\csv_importer\Plugin\ImporterInterface
   *   The importer plugin.
   */
  protected function createImporter(array $csv, array $overrides = []): ImporterInterface {
    $entity_fields = $this->container->get('entity_field.manager')
      ->getFieldDefinitions($this->entityType, $this->entityTypeBundle);

    return $this->importerManager->createInstance('importer:' . $this->entityType, $overrides + [
      'csv' => $csv,
      'entity_type' => $this->entityType,
      'entity_type_bundle' => $this->entityTypeBundle,
      'fields' => array_keys($entity_fields),
    ]);
  }

  /**
   * Drive the full batch over a CSV matrix and return the batch results.
   *
   * @param array $csv
   *   The CSV matrix.
   *
   * @return array
   *   The batch results array (added/updated/translations keys).
   */
  protected function batch(array $csv): array {
    $importer = $this->createImporter($csv);
    $contents = $importer->data()['content'];

    // Mimic Drupal's batch runner: call the operation until it reports
    // finished, guarding against an infinite loop.
    $context = ['results' => [], 'finished' => 0];
    $guard = 0;
    do {
      $importer->add($contents, $context);
    } while ($context['finished'] < 1 && ++$guard < 100);

    return $context['results'];
  }

  /**
   * Reload an entity with a fresh storage cache.
   *
   * @param int|string $id
   *   The entity id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The reloaded entity, or NULL if it no longer exists.
   */
  protected function reload($id) {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($this->entityType);
    $storage->resetCache([$id]);

    return $storage->load($id);
  }

  /**
   * Invoke a protected method on an object via reflection.
   *
   * @param object $object
   *   The object to invoke on.
   * @param string $method
   *   The method name.
   * @param array $args
   *   The method arguments.
   *
   * @return mixed
   *   The method return value.
   */
  protected function invoke(object $object, string $method, array $args = []) {
    $reflection = new \ReflectionMethod($object, $method);
    $reflection->setAccessible(TRUE);
    return $reflection->invokeArgs($object, $args);
  }

}
