<?php
namespace Drupal\carlson_general\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ImageAltCheckController extends ControllerBase {

  protected $pagerManager;

  public function __construct($pager_manager) {
    $this->pagerManager = $pager_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pager.manager')
    );
  }

  public function listMissingAlt() {
    $header = [
      ['data' => $this->t('File Name')],
      ['data' => $this->t('Edit')],
    ];

    $rows = [];

    // Create the base query.
    $base_query = Database::getConnection()->select('media__image', 'mi');
    $base_query->join('file_managed', 'fm', 'fm.fid = mi.image_target_id');
    $base_query->join('media_field_data', 'mfd', 'mfd.mid = mi.entity_id');
    $base_query->fields('mi', ['image_target_id']);
    $base_query->fields('fm', ['filename']);
    $base_query->fields('mfd', ['mid']);

    // Add conditions for missing alt text.
    $or_condition = $base_query->orConditionGroup()
      ->condition('mi.image_alt', NULL, 'IS NULL')
      ->condition('mi.image_alt', '', '=');
    $base_query->condition($or_condition);

    // Count the total number of items.
    $count_query = clone $base_query;
    $total_items = $count_query->countQuery()->execute()->fetchField();

    // Debugging: Log total items.
    \Drupal::logger('carlson_general')->debug('Total items found: @total', ['@total' => $total_items]);

    // Determine the current page and items per page.
    $items_per_page = 20;
    $pager = $this->pagerManager->createPager($total_items, $items_per_page);
    $current_page = $pager->getCurrentPage();
    $offset = $current_page * $items_per_page;

    // Debugging: Log current page and offset.
    \Drupal::logger('carlson_general')->debug('Current page: @page, Offset: @offset', [
      '@page' => $current_page,
      '@offset' => $offset,
    ]);

    // Apply range for pagination.
    $base_query->range($offset, $items_per_page);
    $results = $base_query->execute();

    foreach ($results as $record) {
      $rows[] = [
        $record->filename,
        [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Edit'),
            '#url' => Url::fromRoute('entity.media.edit_form', ['media' => $record->mid]),
          ],
        ],
      ];
    }

    // Debugging: Log row count in this page.
    \Drupal::logger('carlson_general')->debug('Rows in current page: @count', ['@count' => count($rows)]);

    return [
        'table' => [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#empty' => $this->t('No images with missing alt text found.'),
        ],
        'pager' => [
          '#type' => 'pager',
          '#attached' => [
            'library' => [
              'core/drupal.pager',
            ],
          ],
        ],
      ];
  }

}