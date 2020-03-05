<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\Page.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract basic page from Drupal 7 database.
 * 
 * @MigrateSource(
 *   id = "custom_page"
 * )
 */
class Page extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    // If it's required use 'join', if not use 'leftjoin'.
    $query = $this->select('node', 'f');

    // Selections.
    $query->leftjoin('field_data_body', 'n', 'n.entity_id = f.nid');
    $query->leftjoin('field_data_field_ec_section', 'j', 'j.entity_id = f.nid');
    $query->leftjoin('field_data_field_menu_position_rule', 'r', 'r.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_category', 's', 's.entity_id = f.nid');

    // Field Mappings.
    $query->fields('f', array_keys( $this->baseFields() ) );

    $query->condition('f.type', 'ec_basic_page');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    $fields['body'] = $this->t('body');

    $fields['ec_section'] = $this->t('ec_section');
    $fields['section'] = $this->t('section');

    $fields['menu_position_rule'] = $this->t('menu_position_rule');
    $fields['menu_rule'] = $this->t('menu_rule');

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

    // body to body
    $result = $this->_getBody( $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('body', $record->body_value );
      $row->setSourceProperty('body/0/value', $record->body_value );
    }

    // alias
    $alias = $this->_setAliasPath( $nid );
    if ( !empty($alias) ) {
      $row->setSourceProperty('alias', '/' . $alias);
    }

    // ec_section to section
    $result = $this->_getEntityReference( 'ec_section', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('section', $record->field_ec_section_target_id );
    }

    // event_category to event_channels
    $result = $this->_getTaxonomyId( 'event_category', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_category', $record->field_event_category_tid );
      $row->setSourceProperty('event_channels', $record->field_event_category_tid );
    }

    // menu_position_rule to menu_rule
    $result = $this->_getTaxonomyId( 'menu_position_rule', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('menu_position_rule', $record->field_menu_position_rule_tid );
      $row->setSourceProperty('menu_rule', $record->field_menu_position_rule_tid );
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
    return 'page';
  }

  /**
   * Private Methods.
   */
  private function _setAliasPath($nid) {
    $query = $this->select('url_alias', 'ua')->fields('ua', ['alias']);
    $query->condition('ua.source', 'node/' . $nid);
    return $query->execute()->fetchField();
  }

  private function _getBody($nid) {
    $result = $this->getDatabase()->query('
      SELECT
        fld.body_value
      FROM
        {field_data_body} fld
      WHERE
        fld.entity_id = :nid
    ', array(':nid' => $nid));

    return $result;
  }

  private function _getEntityReference($value, $nid) {
    $result = $this->getDatabase()->query('
      SELECT
        fld.field_' . $value . '_target_id
      FROM
        {field_data_field_' . $value . '} fld
      WHERE
        fld.entity_id = :nid
    ', array(':nid' => $nid));

    return $result;
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
