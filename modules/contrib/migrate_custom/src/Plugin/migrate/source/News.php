<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\News.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract news from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "custom_news"
 * )
 */
class News extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    // If it's required use 'join', if not use 'leftjoin'.
    $query = $this->select('node', 'f');

    // Selections.
    $query->leftjoin('field_data_body', 'n', 'n.entity_id = f.nid');
    $query->leftjoin('field_data_field_news_in_the_media_link', 'k', 'k.entity_id = f.nid');
    $query->leftjoin('field_data_field_in_the_media_source', 's', 's.entity_id = f.nid');
    $query->leftjoin('field_data_field_sidebar_content', 'c', 'c.entity_id = f.nid');
    $query->leftjoin('field_data_field_news_categories', 't', 't.entity_id = f.nid');
    // $query->leftjoin('field_data_field_featured_news', 'g', 'g.entity_id = f.nid');

    // Field Mappings.
    $query->fields('f', array_keys( $this->baseFields() ) );

    // Condition.
    $query->condition('f.type', 'news');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
    $fields['body'] = $this->t('body');

    $fields['news_in_the_media_link'] = $this->t('news_in_the_media_link');
    $fields['in_the_media'] = $this->t('in_the_media');

    $fields['in_the_media_source'] = $this->t('in_the_media_source');
    $fields['sidebar_content'] = $this->t('sidebar_content');

    $fields['news_categories'] = $this->t('news_categories');
    $fields['news_channels'] = $this->t('news_channels');

    // $fields['featured_news'] = $this->t('featured_news');
    // $fields['news_channel_features'] = $this->t('news_channel_features');

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('nid');
    $title = $row->getSourceProperty('title');
    $row->setSourceProperty('status', 0);

    if(!$title) {
      $row->setSourceProperty('title', 'unknown');
    }

    // body to body
    $result = $this->_getBody( $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('body', $record->body_value );
      $row->setSourceProperty('body/0/value', $record->body_value );
    }

    // news_in_the_media_link to in_the_media
    $result = $this->_getUrlField( 'news_in_the_media_link', $nid );
    foreach ($result as $record) {
      $url = $record->field_news_in_the_media_link_url;
      if ( strpos($url, 'http') === false) {
        $url = ('https://' . $url );
      }

      $row->setSourceProperty('news_in_the_media_link', $url );
      $row->setSourceProperty('in_the_media', $url );
    }

    // in_the_media_source to in_the_media_source
    $result = $this->_getCustomField( 'in_the_media_source', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('in_the_media_source', $record->field_in_the_media_source_value );
    }

    // sidebar_content to sidebar_content
    $result = $this->_getCustomField( 'sidebar_content', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('sidebar_content', $record->field_sidebar_content_value );
    }

    // news_categories to news_channels
    $result = $this->_getTaxonomyId( 'news_categories', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('news_categories', $record->field_news_categories_tid );
      $row->setSourceProperty('news_channels', $record->field_news_categories_tid );
    }

    // featured_news to news_channel_features
    // $result = $this->_getCustomField( 'featured_news', $nid );
    // foreach ($result as $record) {
    //   $row->setSourceProperty('news_categories', $record->field_featured_news_value );
    //   $row->setSourceProperty('news_channel_features/value', $record->field_featured_news_value );
    // }

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
    return 'news';
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
