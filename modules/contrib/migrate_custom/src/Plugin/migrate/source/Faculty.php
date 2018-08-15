<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\Faculty.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract Faculty from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "custom_faculty"
 * )
 */
class Faculty extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('faculty', 'f')
      ->fields('f', array_keys($this->baseFields()))
      ->condition('nid', 0, '>');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('nid');
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return array(
      'faculty_id' => array(
        'type' => 'integer',
        'alias' => 'f',
      ),
    );
  }

  /**
   * Returns the faculty base fields to be migrated.
   *
   * @return array
   *   Associative array having field name as key and description as value.
   */
  protected function baseFields() {
    $fields = array(
      'faculty_id' => $this->t('faculty_id'),
      'title' => $this->t('title'),
      'first_name' => $this->t('first_name'),
      'last_name' => $this->t('last_name'),
    );
    return $fields;

}

  /**
   * {@inheritdoc}
   */
  public function bundleMigrationRequired() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function entityTypeId() {
    return 'faculty';
  }

}
?>
