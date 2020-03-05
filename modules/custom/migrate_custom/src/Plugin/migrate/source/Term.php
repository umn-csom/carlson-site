<?php
 
/**
 * @file
 * Contains \Drupal\migrate_custom\Plugin\migrate\source\Term.
 */
 
namespace Drupal\migrate_custom\Plugin\migrate\source;
 
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
 
/**
 * Drupal 7 taxonomy terms source from database.
 *
 * @todo Support term_relation, term_synonym table if possible.
 *
 * @MigrateSource(
 *   id = "custom_taxonomy_term",
 *   source_provider = "taxonomy"
 * )
 */
class Term extends SqlBase {
 
  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('taxonomy_term_data', 'td')
      ->fields('td', array('tid', 'vid', 'name', 'description', 'weight', 'format'));
    return $query;
  }
 
  /**
   * {@inheritdoc}
   */
  public function fields() {
    return array(
      'tid' => $this->t('The term ID.'),
      'vid' => $this->t('Existing term VID'),
      'name' => $this->t('The name of the term.'),
      'description' => $this->t('The term description.'),
      'weight' => $this->t('Weight'),
      'parent' => $this->t("The Drupal term IDs of the term's parents."),
    );
  }
 
  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Define.
    $tid = $row->getSourceProperty('tid');

    // Find parents for this row.
    $parents = $this->select('taxonomy_term_hierarchy', 'th')
      ->fields('th', array('parent', 'tid'))
      ->condition('tid', $tid)
      ->execute()
      ->fetchCol();
    
    $row->setSourceProperty('parent', $parents);

    // alias
    $alias = $this->_setAliasPath( $tid );
    if ( !empty($alias) ) {
      $row->setSourceProperty('alias', '/' . $alias);
    }

    return parent::prepareRow($row);
  }
 
  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['tid']['type'] = 'integer';
    return $ids;
  }

  /**
   * Private Methods.
   */
  private function _setAliasPath($tid) {
    $query = $this->select('url_alias', 'ua')->fields('ua', ['alias']);
    $query->condition('ua.source', 'taxonomy/term/' . $tid);
    return $query->execute()->fetchField();
  }
}