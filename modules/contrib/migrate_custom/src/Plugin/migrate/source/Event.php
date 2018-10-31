<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\Event.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract event from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "custom_event"
 * )
 */
class Event extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    // If it's required use 'join', if not use 'leftjoin'.
    $query = $this->select('node', 'f');

    // Selections.
    $query->leftjoin('field_data_field_event_description', 'a', 'a.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_teaser', 'b', 'b.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_id', 'c', 'c.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_category', 'd', 'd.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_cost', 'g', 'g.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_register', 'h', 'h.entity_id = f.nid');
    $query->leftjoin('field_data_field_google_map', 'i', 'i.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_location', 'j', 'j.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_contact_name', 'k', 'k.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_contact_phone', 'l', 'l.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_contact_email', 'm', 'm.entity_id = f.nid');
    $query->leftjoin('field_data_field_ec_section', 'n', 'n.entity_id = f.nid');

    // Field Mappings.
    $query->fields('f', array_keys( $this->baseFields() ) );

    // Condition.
    $query->condition('f.type', 'carlson_events');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();

    $fields['event_teaser'] = $this->t('event_teaser');
    $fields['event_id'] = $this->t('event_id');
    $fields['event_cost'] = $this->t('event_cost');
    $fields['google_map'] = $this->t('google_map');
    $fields['event_location'] = $this->t('event_location');

    $fields['body'] = $this->t('body');
    $fields['event_description'] = $this->t('event_description');

    $fields['event_register'] = $this->t('event_register');
    $fields['link'] = $this->t('link');

    $fields['event_contact_name'] = $this->t('event_contact_name');
    $fields['full_name'] = $this->t('full_name');

    $fields['event_contact_phone'] = $this->t('event_contact_phone');
    $fields['phone'] = $this->t('phone');

    $fields['event_contact_email'] = $this->t('event_contact_email');
    $fields['email'] = $this->t('email');

    $fields['ec_section'] = $this->t('ec_section');
    $fields['section'] = $this->t('section');

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
    
    // alias
    $alias = $this->_setAliasPath( $nid );
    if ( !empty($alias) ) {
      $row->setSourceProperty('alias', '/' . $alias);
    }

    // event_description to body
    $result = $this->_getCustomField( 'event_description', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('body', $record->field_event_description_value );
      $row->setSourceProperty('body/0/value', $record->field_event_description_value );
    }

    // ec_section to section
    $result = $this->_getEntityReference( 'ec_section', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('ec_section', $record->field_ec_section_target_id );
      $row->setSourceProperty('section', $record->field_ec_section_target_id );
    }

    // event_teaser to event_teaser
    $result = $this->_getCustomField( 'event_teaser', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_teaser', $record->field_event_teaser_value );
    }

    // event_teaser to event_teaser
    $result = $this->_getCustomField( 'event_id', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_id', $record->field_event_id_value );
    }

    // event_teaser to event_teaser
    $result = $this->_getCustomField( 'event_id', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_id', $record->field_event_id_value );
    }

    // event_category to event_channels
    $result = $this->_getTaxonomyId( 'event_category', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_category', $record->field_event_category_tid );
      $row->setSourceProperty('event_channels', $record->field_event_category_tid );
    }

    // event_cost to event_cost
    $result = $this->_getCustomField( 'event_cost', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_cost', $record->field_event_cost_value );
    }

    // event_register to link
    $result = $this->_getUrlField( 'event_register', $nid );
    foreach ($result as $record) {
      $url = $record->field_event_register_url;
      if ( strpos($url, 'http') === false ) {
        $url = ('https://' . $url );
      }

      $row->setSourceProperty('event_register', $url );
      $row->setSourceProperty('link', $url );
    }

    // google_map to google_map
    $result = $this->_getCustomField( 'google_map', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('google_map', $record->field_google_map_value );
    }

    // event_location to event_location
    $result = $this->_getCustomField( 'event_location', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_location', $record->field_event_location_value );
    }

    // event_contact_name to full_name
    $result = $this->_getCustomField( 'event_contact_name', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_contact_name', $record->field_event_contact_name_value );
      $row->setSourceProperty('full_name', $record->field_event_contact_name_value );
    }

    // event_contact_phone to phone
    $result = $this->_getCustomField( 'event_contact_phone', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_contact_phone', $record->field_event_contact_phone_value );
      $row->setSourceProperty('phone', $record->field_event_contact_phone_value );
    }

    // event_contact_email to email
    $result = $this->_getEmailField( 'event_contact_email', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('event_contact_email', $record->field_event_contact_email_email );
      $row->setSourceProperty('email', $record->field_event_contact_email_email );
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
    return 'event';
  }

  /**
   * Private Methods.
   */
  private function _setAliasPath($nid) {
    $query = $this->select('url_alias', 'ua')->fields('ua', ['alias']);
    $query->condition('ua.source', 'node/' . $nid);
    return $query->execute()->fetchField();
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

  private function _getDateField($value, $nid) {
    $result = $this->getDatabase()->query('
      SELECT
        fld.field_' . $value . '_value, 
        fld.field_' . $value . '_value2
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
}
?>
