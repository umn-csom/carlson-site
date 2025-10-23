<?php

namespace Drupal\carlson_general\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\block_content\BlockContentInterface;

/**
 * Provides utilities for removing redundant link title attributes.
 */
class RedundantLinkTitleCleaner {

  /**
   * Block content storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $blockStorage;

  /**
   * Constructs the cleaner service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->blockStorage = $entityTypeManager->getStorage('block_content');
  }

  /**
   * Analyses menu content blocks for redundant link title attributes.
   *
   * @param string[] $uuid_filters
   *   Optional list of block UUIDs to limit processing.
   *
   * @return array
   *   A list of analysis results. Each item contains:
   *   - id: The block entity ID.
   *   - uuid: The block UUID.
   *   - label: The block label.
   *   - format: The body text format.
   *   - updated_body: The HTML string with redundant titles removed.
   *   - changes: An array of link changes with keys:
   *       - href: The link href attribute.
   *       - text: The visible link text.
   *       - removed_title: The title attribute that was removed.
   *       - before: The link markup before modification.
   *       - after: The link markup after modification.
   */
  public function analyze(array $uuid_filters = []): array {
    $query = $this->blockStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'menu_content');

    if (!empty($uuid_filters)) {
      $query->condition('uuid', $uuid_filters, 'IN');
    }

    $block_ids = $query->execute();
    if (empty($block_ids)) {
      return [];
    }

    /** @var \Drupal\block_content\BlockContentInterface[] $blocks */
    $blocks = $this->blockStorage->loadMultiple($block_ids);
    $results = [];

    foreach ($blocks as $block) {
      $analysis = $this->processBlock($block);
      if ($analysis['changes']) {
        $results[] = [
          'id' => $block->id(),
          'uuid' => $block->uuid(),
          'label' => $block->label(),
          'format' => $block->get('body')->format,
          'updated_body' => $analysis['updated_body'],
          'changes' => $analysis['changes'],
        ];
      }
    }

    return $results;
  }

  /**
   * Applies the analysed changes to the relevant block content entities.
   *
   * @param array $analysis
   *   The analysis data returned by ::analyze().
   */
  public function applyChanges(array $analysis): void {
    if (empty($analysis)) {
      return;
    }

    foreach ($analysis as $item) {
      $block = $this->blockStorage->load($item['id']);
      if (!$block instanceof BlockContentInterface) {
        continue;
      }
      $block->set('body', [
        'value' => $item['updated_body'],
        'format' => $item['format'],
      ]);
      $block->save();
    }
  }

  /**
   * Processes a block and gathers redundant title information.
   */
  protected function processBlock(BlockContentInterface $block): array {
    $body_value = $block->get('body')->value;
    if ($body_value === NULL || $body_value === '') {
      return [
        'changes' => [],
        'updated_body' => $body_value,
      ];
    }

    $dom = Html::load($body_value);
    $changes = [];

    foreach ($dom->getElementsByTagName('a') as $link_element) {
      $title_attribute = $link_element->getAttribute('title');
      if ($title_attribute === '') {
        continue;
      }

      if (!$this->isRedundant($title_attribute, $link_element->textContent)) {
        continue;
      }

      $before_markup = $link_element->ownerDocument->saveHTML($link_element);
      $href = $link_element->getAttribute('href');
      $clean_title = $this->normalizeString($title_attribute, FALSE);
      $clean_text = $this->normalizeString($link_element->textContent, FALSE);

      $link_element->removeAttribute('title');
      $after_markup = $link_element->ownerDocument->saveHTML($link_element);

      $changes[] = [
        'href' => $href,
        'text' => $clean_text,
        'removed_title' => $clean_title,
        'before' => $before_markup,
        'after' => $after_markup,
      ];
    }

    return [
      'changes' => $changes,
      'updated_body' => Html::serialize($dom),
    ];
  }

  /**
   * Determines whether the title attribute is redundant with the link text.
   */
  protected function isRedundant(string $title, string $text): bool {
    $normalized_title = $this->normalizeString($title);
    $normalized_text = $this->normalizeString($text);

    if ($normalized_title === '' || $normalized_text === '') {
      return FALSE;
    }

    return $normalized_title === $normalized_text;
  }

  /**
   * Normalizes a string for comparison or logging.
   *
   * @param string $value
   *   The input string.
   * @param bool $for_comparison
   *   TRUE to return a lowercase string suitable for comparisons, FALSE to
   *   return a trimmed/collapsed version retaining original casing.
   */
  protected function normalizeString(string $value, bool $for_comparison = TRUE): string {
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $collapsed = preg_replace('/\s+/u', ' ', trim($decoded));

    if ($for_comparison) {
      return mb_strtolower($collapsed);
    }

    return $collapsed;
  }

}
