<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\Student.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract student from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "custom_student"
 * )
 */
class Student extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    // If it's required use 'join', if not use 'leftjoin'.
    $query = $this->select('node', 'f');

    // Selections.
    $query->leftjoin('field_data_body', 'n', 'n.entity_id = f.nid');
    $query->leftjoin('field_data_field_ec_section', 'j', 'j.entity_id = f.nid');
    $query->leftjoin('field_data_field_first_name', 'a', 'a.entity_id = f.nid');
    $query->leftjoin('field_data_field_last_name', 'b', 'b.entity_id = f.nid');
    $query->leftjoin('field_data_field_featured', 'c', 'c.entity_id = f.nid');
    $query->leftjoin('field_data_field_ec_pull_quote', 'd', 'd.entity_id = f.nid');
    $query->leftjoin('field_data_field_student_undergrad', 'e', 'e.entity_id = f.nid');
    $query->leftjoin('field_data_field_student_carlson_activities', 'g', 'g.entity_id = f.nid');
    $query->leftjoin('field_data_field_student_interests', 'h', 'h.entity_id = f.nid');
    $query->leftjoin('field_data_field_student_intl', 'i', 'i.entity_id = f.nid');
    $query->leftjoin('field_data_field_student_major', 'k', 'k.entity_id = f.nid');
    $query->leftjoin('field_data_field_favorite_thing_carlson', 'l', 'l.entity_id = f.nid');
    $query->leftjoin('field_data_field_advice_incoming_students', 'm', 'm.entity_id = f.nid');
    $query->leftjoin('field_data_field_home', 'n', 'n.entity_id = f.nid');
    $query->leftjoin('field_data_field_student_internship', 'o', 'o.entity_id = f.nid');
    $query->leftjoin('field_data_field_student_experience', 'p', 'p.entity_id = f.nid');

    // Field Mappings.
    $query->fields('f', array_keys( $this->baseFields() ) );

    // Condition.
    $query->condition('f.type', 'student');
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

    $fields['first_name'] = $this->t('first_name');
    $fields['last_name'] = $this->t('last_name');

    $fields['featured'] = $this->t('featured');
    $fields['profile_featured'] = $this->t('profile_featured');

    $fields['ec_quote'] = $this->t('ec_quote');
    $fields['profile_quote'] = $this->t('profile_quote');

    $fields['student_undergrad'] = $this->t('student_undergrad');
    $fields['profile_undergrad'] = $this->t('profile_undergrad');

    $fields['student_carlson_activities'] = $this->t('student_carlson_activities');
    $fields['profile_carlson_activities'] = $this->t('profile_carlson_activities');

    $fields['student_interests'] = $this->t('student_interests');
    $fields['profile_hobbies'] = $this->t('profile_hobbies');

    $fields['student_intl'] = $this->t('student_intl');
    $fields['profile_intl'] = $this->t('profile_intl');

    $fields['favorite_thing_carlson'] = $this->t('favorite_thing_carlson');
    $fields['profile_favorite'] = $this->t('profile_favorite');

    $fields['advice_incoming_students'] = $this->t('advice_incoming_students');
    $fields['profile_advice'] = $this->t('profile_advice');

    $fields['student_internship'] = $this->t('student_internship');
    $fields['profile_internship'] = $this->t('profile_internship');

    $fields['student_experience'] = $this->t('student_experience');
    $fields['profile_experience'] = $this->t('profile_experience');

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

    // featured to profile_featured
    $result = $this->_getCustomField( 'featured', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('featured', $record->field_featured_value );
      $row->setSourceProperty('profile_featured', $record->field_featured_value );
    }

    // ec_pull_quote to profile_quote
    $result = $this->_getCustomField( 'ec_pull_quote', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ec_pull_quote', $record->field_ec_pull_quote_value );
      $row->setSourceProperty('profile_quote', $record->field_ec_pull_quote_value );
    }

    // student_undergrad to profile_undergrad
    $result = $this->_getCustomField( 'student_undergrad', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('student_undergrad', $record->field_student_undergrad_value );
      $row->setSourceProperty('profile_undergrad', $record->field_student_undergrad_value );
    }

    // student_carlson_activities to profile_carlson_activities
    $result = $this->_getCustomField( 'student_carlson_activities', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('student_carlson_activities', $record->field_student_carlson_activities_value );
      $row->setSourceProperty('profile_carlson_activities', $record->field_student_carlson_activities_value );
    }

    // student_interests to profile_hobbies
    $result = $this->_getCustomField( 'student_interests', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('student_interests', $record->field_student_interests_value );
      $row->setSourceProperty('profile_hobbies', $record->field_student_interests_value );
    }

    // student_interests to profile_hobbies
    $result = $this->_getCustomField( 'student_intl', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('student_intl', $record->field_student_intl_value );
      $row->setSourceProperty('profile_intl', $record->field_student_intl_value );
    }

    // favorite_thing_carlson to profile_favorite
    $result = $this->_getCustomField( 'favorite_thing_carlson', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('favorite_thing_carlson', $record->field_favorite_thing_carlson_value );
      $row->setSourceProperty('profile_favorite', $record->field_favorite_thing_carlson_value );
    }

    // advice_incoming_students to profile_advice
    $result = $this->_getCustomField( 'advice_incoming_students', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('advice_incoming_students', $record->field_advice_incoming_students_value );
      $row->setSourceProperty('profile_advice', $record->field_advice_incoming_students_value );
    }

    // home to profile_hometown
    $result = $this->_getCustomField( 'home', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('home', $record->field_home_value );
      $row->setSourceProperty('profile_hometown', $record->field_home_value );
    }

    // student_internship to profile_internship
    $result = $this->_getCustomField( 'student_internship', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('student_internship', $record->field_student_internship_value );
      $row->setSourceProperty('profile_internship', $record->field_student_internship_value );
    }

    // student_experience to profile_experience
    $result = $this->_getCustomField( 'student_experience', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('student_experience', $record->field_student_experience_value );
      $row->setSourceProperty('profile_experience', $record->field_student_experience_value );
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
    return 'student';
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
