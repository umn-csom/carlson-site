<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\ExecutiveEdProgram.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract executive ed program from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "custom_eep"
 * )
 */
class ExecutiveEdProgram extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    // If it's required use 'join', if not use 'leftjoin'.
    $query = $this->select('node', 'f');

    // Selections.
    $query->leftjoin('field_data_body', 'n', 'n.entity_id = f.nid');
    $query->leftjoin('field_data_field_ec_section', 'j', 'j.entity_id = f.nid');
    $query->leftjoin('field_data_field_ee_program_teaser', 't', 't.entity_id = f.nid');
    $query->leftjoin('field_data_field_ee_business_topic', 'v', 'v.entity_id = f.nid');
    $query->leftjoin('field_data_field_ee_level', 'g', 'g.entity_id = f.nid');
    $query->leftjoin('field_data_field_ee_business_challeng', 'x', 'x.entity_id = f.nid');
    $query->leftjoin('field_data_field_ee_program_body', 'w', 'w.entity_id = f.nid');
    $query->leftjoin('field_data_field_ee_program_audience', 'p', 'p.entity_id = f.nid');
    $query->leftjoin('field_data_field_ee_takeaways', 'o', 'o.entity_id = f.nid');
    $query->leftjoin('field_data_field_ee_faculty', 'a', 'a.entity_id = f.nid');
    $query->leftjoin('field_data_field_ee_related_courses', 'u', 'u.entity_id = f.nid');
    $query->leftjoin('field_data_field_menu_position_rule', 'r', 'r.entity_id = f.nid');
    $query->leftjoin('field_data_field_mili_program', 'q', 'q.entity_id = f.nid');

    // Field Mappings.
    $query->fields('f', array_keys( $this->baseFields() ) );

    // Condition.
    $query->condition('f.type', 'executive_ed_program');
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

    $fields['ee_program_teaser'] = $this->t('ee_program_teaser');
    $fields['ee_business_topic'] = $this->t('ee_business_topic');
    $fields['ee_level'] = $this->t('ee_level');

    $fields['ee_business_challeng'] = $this->t('ee_business_challeng');
    $fields['ee_business_challenge'] = $this->t('ee_business_challenge');

    $fields['ee_program_body'] = $this->t('ee_program_body');
    $fields['ee_program_audience'] = $this->t('ee_program_audience');

    $fields['ee_takeaways'] = $this->t('ee_takeaways');
    $fields['ee_benefits'] = $this->t('ee_benefits');

    $fields['ee_faculty'] = $this->t('ee_faculty');
    $fields['ee_featured_faculty'] = $this->t('ee_featured_faculty');

    $fields['ee_faculty'] = $this->t('ee_faculty');
    $fields['ee_featured_faculty'] = $this->t('ee_featured_faculty');

    $fields['ee_related_courses'] = $this->t('ee_related_courses');

    $fields['menu_position_rule'] = $this->t('menu_position_rule');
    $fields['menu_rule'] = $this->t('menu_rule');

    $fields['mili_program'] = $this->t('mili_program');
    $fields['ee_mili_program'] = $this->t('ee_mili_program');

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
      $row->setSourceProperty('ec_section', $record->field_ec_section_target_id );
      $row->setSourceProperty('section', $record->field_ec_section_target_id );
    }

    // ec_section to section
    $result = $this->_getEntityReference( 'ee_business_topic', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ee_business_topic', $record->field_ee_business_topic_target_id );
    }

    // ee_program_teaser
    $result = $this->_getCustomField( 'ee_program_teaser', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ee_program_teaser', $record->field_ee_program_teaser_value );
    }

    // ee_takeaways to ee_benefits
    $result = $this->_getCustomField( 'ee_takeaways', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ee_takeaways', $record->field_ee_takeaways_value );
      $row->setSourceProperty('ee_benefits', $record->field_ee_takeaways_value );
    }

    // ee_program_audience
    $result = $this->_getCustomField( 'ee_program_audience', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ee_program_audience', $record->field_ee_program_audience_value );
    }

    // ee_program_body
    $result = $this->_getCustomField( 'ee_program_body', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ee_program_body', $record->field_ee_program_body_value );
    }

    // mili_program to ee_mili_program
    $result = $this->_getCustomField( 'mili_program', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('mili_program', $record->field_mili_program_value );
      $row->setSourceProperty('ee_mili_program', $record->field_mili_program_value );
    }

    // ee_level
    $result = $this->_getEntityReference( 'ee_level', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ee_level', $record->field_ee_level_target_id );
    }

    // ee_business_challeng to ee_business_challenge
    $result = $this->_getEntityReference( 'ee_business_challeng', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ee_business_challeng', $record->field_ee_business_challeng_target_id );
      $row->setSourceProperty('ee_business_challenge', $record->field_ee_business_challeng_target_id );
    }

    // menu_position_rule to menu_rule
    $result = $this->_getTaxonomyId( 'menu_position_rule', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('menu_position_rule', $record->field_menu_position_rule_tid );
      $row->setSourceProperty('menu_rule', $record->field_menu_position_rule_tid );
    }

    // ee_related_courses
    $result = $this->_getEntityReference( 'ee_related_courses', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ee_related_courses', $record->field_ee_related_courses_target_id );
    }

    // ee_faculty to ee_featured_faculty
    $result = $this->_getEntityReference( 'ee_faculty', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ee_faculty', $record->field_ee_faculty_target_id );
      $row->setSourceProperty('ee_featured_faculty', $record->field_ee_faculty_target_id );
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
    return 'executive_ed_program';
  }

  /**
   * Private Methods.
   */

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

  private function _setAliasPath($nid) {
    $query = $this->select('url_alias', 'ua')->fields('ua', ['alias']);
    $query->condition('ua.source', 'node/' . $nid);
    return $query->execute()->fetchField();
  }

  private function _getVocab($tid) {
    $result = $this->getDatabase()->query('
      SELECT
        fld.name, fld.tid
      FROM
        taxonomy_term_data fld
      WHERE
        fld.tid = :tid
    ', array(':tid' => $tid));

    return $result;
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
