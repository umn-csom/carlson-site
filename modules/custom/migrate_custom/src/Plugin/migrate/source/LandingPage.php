<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\LandingPage.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract landing page from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "custom_landing_page"
 * )
 */
class LandingPage extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    // If it's required use 'join', if not use 'leftjoin'.
    $query = $this->select('node', 'f');

    // Selections.
    $query->leftjoin('field_data_field_news_categories', 't', 't.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_category', 'e', 'e.entity_id = f.nid');

    // Field Mappings.
    $query->fields('f', array_keys( $this->baseFields() ) );

    $query->condition('f.type', 'landing_page');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    $fields['data'] = $this->t('data');

    $fields['news_categories'] = $this->t('news_categories');
    $fields['news_channels'] = $this->t('news_channels');

    $fields['event_category'] = $this->t('event_category');
    $fields['event_channels'] = $this->t('event_channels');

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

    // news_categories to news_channels
    $result = $this->_getTaxonomyId( 'news_categories', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('news_categories', $record->field_news_categories_tid );
      $row->setSourceProperty('news_channels', $record->field_news_categories_tid );
    }

    // event_category to event_channels
    $result = $this->_getTaxonomyId( 'event_category', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_category', $record->field_event_category_tid );
      $row->setSourceProperty('event_channels', $record->field_event_category_tid );
    }

    // alias
    $alias = $this->_setAliasPath( $nid );
    if ( !empty($alias) ) {
      $row->setSourceProperty('alias', '/' . $alias);
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
      'type' => $this->t('type'),
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
    return 'landing_page';
  }

  /**
   * Private Methods.
   */
  private function _setAliasPath($nid) {
    $query = $this->select('url_alias', 'ua')->fields('ua', ['alias']);
    $query->condition('ua.source', 'node/' . $nid);
    return $query->execute()->fetchField();
  }

  private function _getTaxonomyId($value, $nid) {
    $result = $this->getDatabase()->query('
      SELECT
        fld.field_' . $value . '_tid
      FROM
        {field_data_field_' . $value . '} fld
      WHERE
        fld.entity_id = :nid
    ', array(':nid' => $nid));
    return $result;
  }
}
?>
