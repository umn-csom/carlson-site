<?php

/**
 * @file
 * Post update functions for the Carlson General module.
 */

use Drupal\carlson_general\Service\RedundantLinkTitleCleaner;

/**
 * Removes redundant link title attributes from mega menu blocks.
 */
function carlson_general_post_update_remove_redundant_link_titles(&$sandbox = NULL) {
  /** @var \Drupal\carlson_general\Service\RedundantLinkTitleCleaner $cleaner */
  $cleaner = \Drupal::service('carlson_general.redundant_link_title_cleaner');

  $analysis = $cleaner->analyze();
  if (empty($analysis)) {
    return t('No redundant link title attributes were found.');
  }

  $cleaner->applyChanges($analysis);

  $block_count = count($analysis);
  $attribute_total = 0;
  $messages = [];
  foreach ($analysis as $item) {
    $attribute_total += count($item['changes']);

     $messages[] = t('Block "@label" (@uuid):', [
       '@label' => $item['label'],
       '@uuid' => $item['uuid'],
     ]);

    foreach ($item['changes'] as $change) {
      $messages[] = t('  - Removed title "@title" from link text "@text" (href: @href)', [
        '@title' => $change['removed_title'],
        '@text' => $change['text'],
        '@href' => $change['href'],
      ]);
    }
  }

  if ($messages) {
    \Drupal::messenger()->addMessage(implode(PHP_EOL, $messages));
  }

  return t('Removed @attributes redundant link title attribute(s) across @blocks block(s).', [
    '@attributes' => $attribute_total,
    '@blocks' => $block_count,
  ]);
}
