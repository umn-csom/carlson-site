<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\FacultyProfile.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract faculty from Drupal 7 database.
 * 
 * TODO: Figure out how to move over image data.
 *
 * @MigrateSource(
 *   id = "custom_faculty"
 * )
 */
class FacultyProfile extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    // If it's required use 'join', if not use 'leftjoin'.
    $query = $this->select('node', 'f');

    // Selections.
    $query->leftjoin('field_data_field_first_name', 'n', 'n.entity_id = f.nid');
    $query->leftjoin('field_data_field_last_name', 'a', 'a.entity_id = f.nid');
    $query->leftjoin('field_data_field_staff_title', 'b', 'b.entity_id = f.nid');
    $query->leftjoin('field_data_field_office_phone', 'j', 'j.entity_id = f.nid');
    $query->leftjoin('field_data_field_name_middle_initial', 'c', 'c.entity_id = f.nid');
    $query->leftjoin('field_data_field_address_1', 'd', 'd.entity_id = f.nid');
    $query->leftjoin('field_data_field_about_me', 'e', 'e.entity_id = f.nid');
    $query->leftjoin('field_data_field_faculty_profile_owner', 'g', 'g.entity_id = f.nid');
    $query->leftjoin('field_data_field_department', 'h', 'h.entity_id = f.nid');
    $query->leftjoin('field_data_field_faculty_email', 'i', 'i.entity_id = f.nid');
    $query->leftjoin('field_data_field_personal_url', 'k', 'k.entity_id = f.nid');
    $query->leftjoin('field_data_field_faculty_job_candidate', 'l', 'l.entity_id = f.nid');
    $query->leftjoin('field_data_field_faculty_department', 'm', 'm.entity_id = f.nid');
    $query->leftjoin('field_data_field_faculty_degree_program', 'n', 'n.entity_id = f.nid');
    $query->leftjoin('field_data_field_faculty_status', 'o', 'o.entity_id = f.nid');


    // Field Mappings.
    $query->fields('f', array_keys( $this->baseFields() ) );

    $query->condition('f.type', 'faculty');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    $fields['first_name'] = $this->t('first_name');
    $fields['last_name'] = $this->t('last_name');
    $fields['office_phone'] = $this->t('office_phone');
    
    $fields['staff_title'] = $this->t('staff_title');
    $fields['profile_title'] = $this->t('profile_title');
    
    $fields['name_middle_initial'] = $this->t('name_middle_initial');
    $fields['middle_initial'] = $this->t('middle_initial');
    
    $fields['address_1'] = $this->t('address_1');
    $fields['profile_address'] = $this->t('profile_address');

    $fields['about_me'] = $this->t('about_me');
    $fields['body'] = $this->t('body');

    $fields['faculty_profile_owner'] = $this->t('faculty_profile_owner');
    $fields['profile_owner'] = $this->t('profile_owner');

    $fields['department'] = $this->t('department');
    $fields['profile_dept'] = $this->t('profile_dept');

    $fields['faculty_email'] = $this->t('faculty_email');
    $fields['email'] = $this->t('email');

    $fields['personal_url'] = $this->t('personal_url');
    $fields['profile_url'] = $this->t('profile_url');

    $fields['faculty_job_candidate'] = $this->t('faculty_job_candidate');
    $fields['profile_job_candidate'] = $this->t('profile_job_candidate');

    $fields['faculty_department'] = $this->t('faculty_department');
    $fields['profile_admin_dept'] = $this->t('profile_admin_dept');

    $fields['faculty_degree_program'] = $this->t('faculty_degree_program');
    $fields['profile_programs'] = $this->t('profile_programs');

    $fields['faculty_status'] = $this->t('faculty_status');
    $fields['profile_status'] = $this->t('profile_status');

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

    // office_phone
    $result = $this->_getCustomField( 'office_phone', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('office_phone', $record->field_office_phone_value );
    }

    // staff_title to profile_title
    $result = $this->_getCustomField( 'staff_title', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('staff_title', $record->field_staff_title_value );
      $row->setSourceProperty('profile_title', $record->field_staff_title_value );
    }

    // name_middle_initial to middle_initial
    $result = $this->_getCustomField( 'name_middle_initial', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('name_middle_initial', $record->field_name_middle_initial_value );
      $row->setSourceProperty('middle_initial', $record->field_name_middle_initial_value );
    }

    // address_1 to profile_address
    $result = $this->_getCustomField( 'address_1', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('address_1', $record->field_address_1_value );
      $row->setSourceProperty('profile_address', $record->field_address_1_value );
    }

    // about_me to body
    $result = $this->_getCustomField( 'about_me', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('about_me', $record->field_about_me_value );
      $row->setSourceProperty('body/0/value', $record->field_about_me_value );
    }

    // faculty_profile_owner to profile_owner
    $result = $this->_getEntityReference( 'faculty_profile_owner', $nid );
    foreach ($result as $record) {
      $users = $this->_getUsername( $record->field_faculty_profile_owner_target_id );
      foreach ($users as $user) {
        $row->setSourceProperty('faculty_profile_owner', $user->name );
      }
    }

    // department to profile_dept
    $result = $this->_getCustomField( 'department', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('department', $record->field_department_value );
      $row->setSourceProperty('profile_dept', $record->field_department_value );
    }

    // faculty_email to email
    $result = $this->_getEmailField( 'faculty_email', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('faculty_email', $record->field_faculty_email_email );
      $row->setSourceProperty('email', $record->field_faculty_email_email );
    }

    // personal_url to profile_url
    $result = $this->_getUrlField( 'personal_url', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('personal_url', $record->field_personal_url_url );
      $row->setSourceProperty('profile_url', $record->field_personal_url_url );
    }

    // faculty_job_candidate to profile_job_candidate
    $result = $this->_getCustomField( 'faculty_job_candidate', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('faculty_job_candidate', $record->field_faculty_job_candidate_value );
      $row->setSourceProperty('profile_job_candidate', $record->field_faculty_job_candidate_value );
    }

    // faculty_department to profile_admin_dept
    $result = $this->_getTaxonomyId( 'faculty_department', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('faculty_department', $record->field_faculty_department_tid );
      $row->setSourceProperty('profile_admin_dept', $record->field_faculty_department_tid );
    }

    // faculty_degree_program to profile_admin_dept
    $result = $this->_getTaxonomyId( 'faculty_degree_program', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('faculty_degree_program', $record->field_faculty_degree_program_tid );
      $row->setSourceProperty('profile_programs', $record->field_faculty_degree_program_tid );
    }

    // faculty_status to profile_status
    $result = $this->_getTaxonomyId( 'faculty_status', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('faculty_status', $record->field_faculty_status_tid );
      $row->setSourceProperty('profile_status', $record->field_faculty_status_tid );
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
    return 'faculty_profile';
  }

  /**
   * Private Methods.
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

  private function _getUrlField($value, $nid) {
    $result = $this->getDatabase()->query('
      SELECT
        fld.field_' . $value . '_url
      FROM
        {field_data_field_' . $value . '} fld
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

  private function _getUsername($uid) {
    $result = $this->getDatabase()->query('
      SELECT
        fld.name
      FROM
        users fld
      WHERE
        fld.uid = :uid
    ', array(':uid' => $uid));

    return $result;
  }

  private function _getEmailField($value, $nid) {
    $result = $this->getDatabase()->query('
      SELECT
        fld.field_' . $value . '_email
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
