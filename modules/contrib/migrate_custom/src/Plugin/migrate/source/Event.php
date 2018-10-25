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
    $query->leftjoin('field_data_field_event_date', 'e', 'e.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_cost', 'g', 'g.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_register', 'h', 'h.entity_id = f.nid');
    $query->leftjoin('field_data_field_google_map', 'i', 'i.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_location', 'j', 'j.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_contact_name', 'k', 'k.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_contact_phone', 'l', 'l.entity_id = f.nid');
    $query->leftjoin('field_data_field_event_contact_email', 'm', 'm.entity_id = f.nid');

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
    $fields['event_category'] = $this->t('event_category');
    $fields['event_channels'] = $this->t('event_channels');
    $fields['event_date'] = $this->t('event_date');
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
  
}
?>
