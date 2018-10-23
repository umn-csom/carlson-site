<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\Person.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract person from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "custom_person"
 * )
 */
class Person extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    // If it's required use 'join', if not use 'leftjoin'.
    $query = $this->select('node', 'f');

    // Selections.
    $query->leftjoin('field_data_field_ec_section', 'j', 'j.entity_id = f.nid');
    $query->leftjoin('field_data_field_first_name', 'a', 'a.entity_id = f.nid');
    $query->leftjoin('field_data_field_last_name', 'b', 'b.entity_id = f.nid');
    $query->leftjoin('field_data_field_name_middle_initial', 'c', 'c.entity_id = f.nid');
    $query->leftjoin('field_data_field_additional_title', 'd', 'd.entity_id = f.nid');
    $query->leftjoin('field_data_field_brief_bio', 'e', 'e.entity_id = f.nid');
    $query->leftjoin('field_data_field_about_me', 'g', 'g.entity_id = f.nid');

    // Field Mappings.
    $query->fields('f', array_keys( $this->baseFields() ) );

    $or = $query->orConditionGroup();
    $or->condition('f.type', 'person');
    $or->condition('f.type', 'staff');
    $query->condition($or);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    $fields['data'] = $this->t('data');

    $fields['about_me'] = $this->t('about_me');
    $fields['body'] = $this->t('body');

    $fields['ec_section'] = $this->t('ec_section');
    $fields['section'] = $this->t('section');

    $fields['first_name'] = $this->t('first_name');
    $fields['last_name'] = $this->t('last_name');

    $fields['name_middle_initial'] = $this->t('name_middle_initial');
    $fields['middle_initial'] = $this->t('middle_initial');

    $fields['additional_title'] = $this->t('additional_title');
    $fields['profile_title'] = $this->t('profile_title');

    $fields['brief_bio'] = $this->t('brief_bio');
    $fields['teaser'] = $this->t('teaser');

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
    
    // alias
    $alias = $this->_setAliasPath( $nid );
    if ( !empty($alias) ) {
      $row->setSourceProperty('alias', '/' . $alias);
    }

    // ec_section to section
    $result = $this->_getEntityReference( 'ec_section', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ec_section', $record->field_ec_section_target_id );
      $row->setSourceProperty('section', $record->field_ec_section_target_id );
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

    // name_middle_initial to middle_initial
    $result = $this->_getCustomField( 'name_middle_initial', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('name_middle_initial', $record->field_name_middle_initial_value );
      $row->setSourceProperty('middle_initial', $record->field_name_middle_initial_value );
    }

    // additional_title to profile_title
    $result = $this->_getTitleField( 'additional_title', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('additional_title', $record->field_additional_title_title );
      $row->setSourceProperty('profile_title', $record->field_additional_title_title );
    }

    // carlson_group to profile_group
    $result = $this->_getCustomField( 'carlson_group', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('carlson_group', $record->field_carlson_group_value );
      $row->setSourceProperty('profile_group', $record->field_carlson_group_value );
    }

    // brief_bio to teaser
    $result = $this->_getCustomField( 'brief_bio', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('brief_bio', $record->field_brief_bio_value );
      $row->setSourceProperty('teaser', $record->field_brief_bio_value );
    }

    // about_me to body
    $result = $this->_getCustomField( 'about_me', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('about_me', $record->field_about_me_value );
      $row->setSourceProperty('body/0/value', $record->field_about_me_value );
    }

    // type to blog_group
    $row->setSourceProperty('person_type', $record->type );

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
    return 'person';
  }

  /**
   * Private Methods.
   */
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

  private function _setAliasPath($nid) {
    $query = $this->select('url_alias', 'ua')->fields('ua', ['alias']);
    $query->condition('ua.source', 'node/' . $nid);
    return $query->execute()->fetchField();
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

  private function _getTitleField($value, $nid) {
    $result = $this->getDatabase()->query('
      SELECT
        fld.field_' . $value . '_title
      FROM
        {field_data_field_' . $value . '} fld
      WHERE
        fld.entity_id = :nid
    ', array(':nid' => $nid));

    return $result;
  }
}
?>
