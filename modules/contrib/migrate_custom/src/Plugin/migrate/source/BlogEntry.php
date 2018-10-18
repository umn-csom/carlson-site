<?php
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\BlogEntry.
 */

namespace Drupal\migrate_custom\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Extract blog entry from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "custom_blog"
 * )
 */
class BlogEntry extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {

    // If it's required use 'join', if not use 'leftjoin'.
    $query = $this->select('node', 'f');

    // Selections.
    $query->leftjoin('field_data_body', 'n', 'n.entity_id = f.nid');
    $query->leftjoin('field_data_field_blog_by_line', 'j', 'j.entity_id = f.nid');
    $query->leftjoin('field_data_field_mba_blog_categories', 'k', 'k.entity_id = f.nid');
    $query->leftjoin('field_data_field_news_teaser', 'p', 'p.entity_id = f.nid');

    // Field Mappings.
    $query->fields('f', array_keys( $this->baseFields() ) );

    $or = $query->orConditionGroup();
    $or->condition('f.type', 'blog_post');
    $or->condition('f.type', 'full_time_mba_blog_post');
    $or->condition('f.type', 'cemba_blog_post');
    $or->condition('f.type', 'dean_s_blog_post');
    $or->condition('f.type', 'holmes_center_entreed_blog_post');
    $or->condition('f.type', 'ma_hrir_blog');
    $query->condition($or);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();

    $fields['body'] = $this->t('body');
    $fields['blog_by_line'] = $this->t('blog_by_line');

    $fields['blog_categories'] = $this->t('blog_categories');
    $fields['mba_blog_categories'] = $this->t('mba_blog_categories');

    $fields['news_teaser'] = $this->t('news_teaser');
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

    // body to body
    $result = $this->_getBody( $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('body', $record->body_value );
      $row->setSourceProperty('body/0/value', $record->body_value );
    }

    // blog_by_line
    $result = $this->_getCustomField( 'blog_by_line', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('blog_by_line', $record->field_blog_by_line_value );
    }

    // type to blog_group
    $result = $this->_getCustomField( 'blog_group', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('blog_group', $record->type );
    }

    // mba_blog_categories to blog_categories
    $result = $this->_getTaxonomyId( 'mba_blog_categories', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('mba_blog_categories', $record->field_mba_blog_categories_tid );
      $row->setSourceProperty('blog_categories', $record->field_mba_blog_categories_tid );
    }

    // news_teaser to field_teaser
    $result = $this->_getCustomField( 'news_teaser', $nid );
    foreach ($result as $record) {
      $row->setSourceProperty('news_teaser', $record->field_news_teaser_value );
      $row->setSourceProperty('teaser', $record->field_news_teaser_value );
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
    return 'blog_entry';
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
