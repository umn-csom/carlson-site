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
 * Extract faculty from Drupal 7 database.
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
    $query = $this->select('node', 'f');
    $query->join('field_data_field_first_name', 'n', 'n.entity_id = f.nid');
    $query->join('field_data_field_last_name', 'a', 'a.entity_id = f.nid');
    $query->join('field_data_field_about_me', 'b', 'b.entity_id = f.nid');

    $query->fields('f', array_keys( $this->baseFields() ) );
    $query->fields('n', array('entity_id', 'field_first_name_value'));
    $query->fields('a', array('entity_id', 'field_last_name_value'));
    $query->fields('b', array('entity_id', 'field_about_me_value'));

    $query->condition('f.nid', 0, '>');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    $fields['first_name'] = $this->t('first_name');
    $fields['last_name'] = $this->t('last_name');
    $fields['about_me'] = $this->t('about_me');
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('nid');
    $title = $row->getSourceProperty('title');

    if(!$title) {
      $row->setSourceProperty('title', 'unknown');
    }

    // first_name
    $result = $this->_getCustomField( 'first_name', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('first_name', $record->field_first_name_value );
    }

    // last_name
    $result = $this->_getCustomField( 'last_name', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('last_name', $record->field_last_name_value );
    }

    // about_me
    $result = $this->_getCustomField( 'about_me', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('about_me', $record->field_about_me_value );
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return array(
      'nid' => array(
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
      'nid' => $this->t('nid'),
      'title' => $this->t('title'),
      'status' => $this->t('status'),
      'created' => $this->t('created'),
      'changed' => $this->t('changed'),
      'promote' => $this->t('promote'),
      'sticky' => $this->t('sticky'),
      'uid' => $this->t('uid'),
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

  /**
   * Private Methods
   */
  private function _getCustomField($value, $nid) {
    $result = $this->getDatabase()->query('
      SELECT
        fld.field_' . $value . '_value
      FROM
        {field_data_field_' . $value . '} fld
      WHERE
        fld.entity_id = :nid
    ', array(':nid' => $nid));

    return $result;
  }

}
?>
