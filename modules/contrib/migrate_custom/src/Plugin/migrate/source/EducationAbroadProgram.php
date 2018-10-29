<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\EducationAbroadProgram.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract education abroad program from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "custom_eap"
 * )
 */
class EducationAbroadProgram extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    // If it's required use 'join', if not use 'leftjoin'.
    $query = $this->select('node', 'f');

    // Selections.
    $query->leftjoin('field_data_field_ec_section', 'j', 'j.entity_id = f.nid');
    $query->leftjoin('field_data_field_ec_subheader', 'a', 'a.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_dates_info', 'b', 'b.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_status', 'c', 'c.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_host_school', 'd', 'd.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_location_info', 'e', 'e.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_eligibility', 'g', 'g.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_academics', 'h', 'h.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_objective', 'i', 'i.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_schedule', 'k', 'k.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_app_admission', 'l', 'l.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_housing_food', 'm', 'm.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_audience', 'n', 'n.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_predep_classes', 'o', 'o.entity_id = f.nid');
    $query->leftjoin('field_data_field_visa_and_passport', 'p', 'p.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_business_exch', 'q', 'q.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_aid_scholarhips', 'r', 'r.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_cost_estimate', 's', 's.entity_id = f.nid');
    //$query->leftjoin('field_data_field_ea_program_cost_table', 't', 't.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_links', 'u', 'u.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_terms', 'v', 'v.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_duration', 'w', 'w.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_degree_level', 'x', 'x.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_region', 'y', 'y.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_country', 'z', 'z.entity_id = f.nid');
    $query->leftjoin('field_data_field_ea_program_city', 'aa', 'aa.entity_id = f.nid');

    // Field Mappings.
    $query->fields('f', array_keys( $this->baseFields() ) );

    $query->condition('f.type', 'education_abroad_program');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();

    $fields['ec_section'] = $this->t('ec_section');
    $fields['section'] = $this->t('section');

    $fields['ec_subheader'] = $this->t('ec_subheader');
    $fields['short_sub_heading'] = $this->t('short_sub_heading');

    $fields['ea_program_dates_info'] = $this->t('ea_program_dates_info');
    $fields['ea_program_status'] = $this->t('ea_program_status');

    $fields['ea_program_host_school'] = $this->t('ea_program_host_school');
    $fields['ea_program_location_info'] = $this->t('ea_program_location_info');
    $fields['ea_program_eligibility'] = $this->t('ea_program_eligibility');
    $fields['ea_program_academics'] = $this->t('ea_program_academics');
    $fields['ea_program_objective'] = $this->t('ea_program_objective');
    $fields['ea_program_schedule'] = $this->t('ea_program_schedule');
    $fields['ea_program_app_admission'] = $this->t('ea_program_app_admission');
    $fields['ea_program_housing_food'] = $this->t('ea_program_housing_food');
    $fields['ea_program_audience'] = $this->t('ea_program_audience');
    $fields['ea_program_predep_classes'] = $this->t('ea_program_predep_classes');
    $fields['visa_and_passport'] = $this->t('visa_and_passport');
    $fields['ea_program_business_exch'] = $this->t('ea_program_business_exch');
    $fields['ea_program_aid_scholarhips'] = $this->t('ea_program_aid_scholarhips');

    $fields['ea_program_cost_estimate'] = $this->t('ea_program_cost_estimate');
    $fields['ea_cost_summary'] = $this->t('ea_cost_summary');

    $fields['ea_program_cost_table'] = $this->t('ea_program_cost_table');
    $fields['ea_cost_table'] = $this->t('ea_cost_table');

    $fields['ea_links'] = $this->t('ea_links');
    $fields['links'] = $this->t('links');

    $fields['ea_terms'] = $this->t('ea_terms');
    $fields['ea_program_duration'] = $this->t('ea_program_duration');
    $fields['ea_program_degree_level'] = $this->t('ea_program_degree_level');
    $fields['ea_program_region'] = $this->t('ea_program_region');
    $fields['ea_program_country'] = $this->t('ea_program_country');
    $fields['ea_program_city'] = $this->t('ea_program_city');

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

    // ec_subheader to short_sub_heading
    $result = $this->_getCustomField( 'ec_subheader', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ec_subheader', $record->field_ec_subheader_value );
      $row->setSourceProperty('short_sub_heading', $record->field_ec_subheader_value );
    }

    // ea_program_dates_info
    $result = $this->_getCustomField( 'ea_program_dates_info', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_dates_info', $record->field_ea_program_dates_info_value );
    }

    // ea_program_status
    $result = $this->_getEntityReference( 'ea_program_status', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_status', $record->field_ea_program_status_target_id );
    }

    // ea_program_host_school
    $result = $this->_getEntityReference( 'ea_program_host_school', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_host_school', $record->field_ea_program_host_school_target_id );
    }

    // ea_program_location_info
    $result = $this->_getCustomField( 'ea_program_location_info', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_location_info', $record->field_ea_program_location_info_value );
    }

    // ea_program_eligibility
    $result = $this->_getCustomField( 'ea_program_eligibility', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_eligibility', $record->field_ea_program_eligibility_value );
    }

    // ea_program_academics
    $result = $this->_getCustomField( 'ea_program_academics', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_academics', $record->field_ea_program_academics_value );
    }

    // ea_program_objective
    $result = $this->_getCustomField( 'ea_program_objective', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_objective', $record->field_ea_program_objective_value );
    }

    // ea_program_schedule
    $result = $this->_getCustomField( 'ea_program_schedule', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_schedule', $record->field_ea_program_schedule_value );
    }

    // ea_program_app_admission
    $result = $this->_getCustomField( 'ea_program_app_admission', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_app_admission', $record->field_ea_program_app_admission_value );
    }

    // ea_program_housing_food
    $result = $this->_getCustomField( 'ea_program_housing_food', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_housing_food', $record->field_ea_program_housing_food_value );
    }

    // ea_program_audience
    $result = $this->_getCustomField( 'ea_program_audience', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_audience', $record->field_ea_program_audience_value );
    }

    // ea_program_predep_classes
    $result = $this->_getCustomField( 'ea_program_predep_classes', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_predep_classes', $record->field_ea_program_predep_classes_value );
    }

    // visa_and_passport
    $result = $this->_getCustomField( 'visa_and_passport', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('visa_and_passport', $record->field_visa_and_passport_value );
    }

    // ea_program_business_exch
    $result = $this->_getCustomField( 'ea_program_business_exch', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_business_exch', $record->field_ea_program_business_exch_value );
    }

    // ea_program_aid_scholarhips
    $result = $this->_getCustomField( 'ea_program_aid_scholarhips', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_aid_scholarhips', $record->field_ea_program_aid_scholarhips_value );
    }

    // ea_program_cost_estimate
    $result = $this->_getCustomField( 'ea_program_cost_estimate', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_cost_estimate', $record->field_ea_program_cost_estimate_value );
      $row->setSourceProperty('ea_cost_summary', $record->field_ea_program_cost_estimate_value );
    }

    // // ea_program_cost_table to ea_cost_table
    // $result = $this->_getCustomField( 'ea_program_cost_table', $nid );
    // foreach ($result as $record) {
    //   $row->setSourceProperty('ea_program_cost_table', $record->field_ea_program_cost_table_value );
    //   $row->setSourceProperty('ea_cost_table', $record->field_ea_program_cost_table_value );
    // }

    // ec_subheader to short_sub_heading
    $result = $this->_getCustomField( 'ec_subheader', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ec_subheader', $record->field_ec_subheader_value );
      $row->setSourceProperty('short_sub_heading', $record->field_ec_subheader_value );
    }

    $result = $this->_getUrlField( 'ea_links', $nid );
    foreach ($result as $record) {
      $url = $record->field_ea_links_url;
      if ( strpos($url, 'http') === false) {
        $url = ('https://' . $url );
      }

      $row->setSourceProperty('ea_links', $url );
      $row->setSourceProperty('links', $url );
    }

    // ea_terms
    $result = $this->_getEntityReference( 'ea_terms', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_terms', $record->field_ea_terms_target_id );
    }

    // ea_program_duration
    $result = $this->_getEntityReference( 'ea_program_duration', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_duration', $record->field_ea_program_duration_target_id );
    }

    // ea_program_degree_level
    $result = $this->_getEntityReference( 'ea_program_degree_level', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_degree_level', $record->field_ea_program_degree_level_target_id );
    }

    // ea_program_country
    $result = $this->_getEntityReference( 'ea_program_country', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_country', $record->field_ea_program_country_target_id );
    }

    // ea_program_city
    $result = $this->_getEntityReference( 'ea_program_city', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_city', $record->field_ea_program_city_target_id );
    }

    // ea_program_region
    $result = $this->_getEntityReference( 'ea_program_region', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ea_program_region', $record->field_ea_program_region_target_id );
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
    return 'education_abroad_program';
  }

  /**
   * Private Methods.
   */
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
}
?>
