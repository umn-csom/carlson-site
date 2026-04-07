<?php

/**
 * @file
 * CSM-266: Export a sitewide inventory of pages that contain Webforms.
 *
 * This report covers the known structured patterns in this site:
 * - Direct Webform reference fields on nodes.
 * - Paragraph-based Webform embeds whose root parent is a node.
 * - Webform blocks with request path visibility.
 *
 * Usage:
 * - ddev drush @carlsonschool.ddev scr
 *   docroot/sites/carlsonschool.umn.edu/modules/custom/carlson_general/scripts/export_webform_page_inventory.php
 * - ddev drush @carlsonschool.ddev scr
 *   docroot/sites/carlsonschool.umn.edu/modules/custom/carlson_general/scripts/export_webform_page_inventory.php
 *   > docroot/sites/carlsonschool.umn.edu/csm-266-webform-page-inventory.csv
 */

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;

if (!class_exists('\Drupal') || !\Drupal::hasService('entity_type.manager')) {
  fwrite(STDERR, "Drupal is not bootstrapped.\n");
  return;
}

$base_url = 'https://carlsonschool.umn.edu';
$entity_type_manager = \Drupal::entityTypeManager();
$field_manager = \Drupal::service('entity_field.manager');
$alias_manager = \Drupal::service('path_alias.manager');

$node_storage = $entity_type_manager->getStorage('node');
$block_storage = $entity_type_manager->getStorage('block');
$webform_storage = $entity_type_manager->getStorage('webform');

$rows = [];
$field_map = $field_manager->getFieldMapByFieldType('webform');
$paragraph_webform_fields = [];

if (!empty($field_map['node'])) {
  foreach ($field_map['node'] as $field_name => $info) {
    foreach ($info['bundles'] as $bundle) {
      $node_ids = $node_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $bundle)
        ->exists($field_name)
        ->sort('nid')
        ->execute();

      foreach ($node_storage->loadMultiple($node_ids) as $node) {
        if (!$node instanceof NodeInterface || $node->get($field_name)->isEmpty()) {
          continue;
        }

        $webform = $node->get($field_name)->entity;
        if (!$webform) {
          continue;
        }

        $page = build_node_page_data($node, $base_url, $alias_manager);
        $row_key = implode('|', [
          'node_webform_field',
          $node->id(),
          $field_name,
          $webform->id(),
        ]);

        add_report_row($rows, $row_key, $page + [
          'pattern' => 'node_webform_field',
          'webform_id' => $webform->id(),
          'webform_title' => $webform->label(),
          'source_field' => $field_name,
          'source_reference' => 'node:' . $node->id(),
          'is_exact_url' => 'yes',
          'notes' => 'Direct Webform reference field on node.',
        ]);
      }
    }
  }
}

if (!empty($field_map['paragraph'])) {
  foreach ($field_map['paragraph'] as $field_name => $info) {
    foreach ($info['bundles'] as $bundle) {
      $paragraph_webform_fields[$bundle][$field_name] = $field_name;
    }
  }

  $node_ids = $node_storage->getQuery()
    ->accessCheck(FALSE)
    ->sort('nid')
    ->execute();

  foreach ($node_storage->loadMultiple($node_ids) as $node) {
    $page = build_node_page_data($node, $base_url, $alias_manager);

    foreach (find_current_webform_paragraphs($node, $paragraph_webform_fields) as $paragraph_data) {
      $paragraph = $paragraph_data['paragraph'];
      $field_name = $paragraph_data['field_name'];
      $webform = $paragraph_data['webform'];
      $row_key = implode('|', [
        'paragraph_webform',
        $node->id(),
        $field_name,
        $webform->id(),
      ]);

      add_report_row($rows, $row_key, $page + [
        'pattern' => 'paragraph_webform',
        'webform_id' => $webform->id(),
        'webform_title' => $webform->label(),
        'source_field' => $field_name,
        'source_reference' => 'node:' . $node->id(),
        'is_exact_url' => 'yes',
        'notes' => 'Current Webform paragraph referenced by this node.',
      ]);
    }
  }
}

/** @var \Drupal\block\BlockInterface[] $blocks */
$blocks = $block_storage->loadMultiple();

foreach ($blocks as $block) {
  if (!$block instanceof ConfigEntityInterface || $block->getPluginId() !== 'webform_block') {
    continue;
  }

  if (method_exists($block, 'status') && !$block->status()) {
    continue;
  }

  $configuration = $block->getPlugin()->getConfiguration();
  $webform_id = $configuration['webform_id'] ?? '';
  if ($webform_id === '') {
    continue;
  }

  $webform = $webform_storage->load($webform_id);
  $visibility = $block->getVisibility();
  $path_list = trim($visibility['request_path']['pages'] ?? '');
  $path_patterns = $path_list === ''
    ? ['']
    : preg_split('/\R+/', $path_list);

  foreach ($path_patterns as $path_pattern) {
    $path_pattern = trim($path_pattern);
    $expanded_nodes = expand_block_path_pattern_to_nodes(
      $path_pattern,
      $alias_manager,
      $node_storage
    );

    if ($expanded_nodes) {
      foreach ($expanded_nodes as $expanded_node) {
        $page = build_node_page_data($expanded_node, $base_url, $alias_manager);
        $row_key = implode('|', [
          'webform_block',
          $block->id(),
          $expanded_node->id(),
          $webform_id,
        ]);

        add_report_row($rows, $row_key, $page + [
          'pattern' => 'webform_block',
          'webform_id' => $webform_id,
          'webform_title' => $webform ? $webform->label() : '',
          'source_field' => 'webform_block',
          'source_reference' => 'block:' . $block->id(),
          'is_exact_url' => 'yes',
          'notes' => 'Webform block expanded from request path visibility pattern.',
        ]);
      }

      continue;
    }

    $resolved_node = NULL;
    $is_exact_url = 'no';
    $page = [
      'nid' => '',
      'bundle' => '',
      'status' => 'enabled',
      'page_title' => $block->label(),
      'relative_url_or_path_pattern' => $path_pattern,
      'production_url' => '',
    ];

    if ($path_pattern !== '' && strpos($path_pattern, '*') === FALSE) {
      $is_exact_url = 'yes';
      $resolved_node = resolve_node_from_path(
        $path_pattern,
        $alias_manager,
        $node_storage
      );

      if ($resolved_node) {
        $page = build_node_page_data($resolved_node, $base_url, $alias_manager);
      }
      else {
        $page['production_url'] = $base_url . $path_pattern;
      }
    }

    $row_key = implode('|', [
      'webform_block',
      $block->id(),
      $path_pattern,
      $webform_id,
    ]);

    add_report_row($rows, $row_key, $page + [
      'pattern' => 'webform_block',
      'webform_id' => $webform_id,
      'webform_title' => $webform ? $webform->label() : '',
      'source_field' => 'webform_block',
      'source_reference' => 'block:' . $block->id(),
      'is_exact_url' => $is_exact_url,
      'notes' => $is_exact_url === 'yes'
        ? 'Webform block on an exact request path.'
        : 'Webform block request path visibility pattern.',
    ]);
  }
}

uasort($rows, static function (array $a, array $b): int {
  $left = implode('|', [
    $a['pattern'],
    $a['relative_url_or_path_pattern'],
    $a['webform_id'],
    $a['source_reference'],
  ]);
  $right = implode('|', [
    $b['pattern'],
    $b['relative_url_or_path_pattern'],
    $b['webform_id'],
    $b['source_reference'],
  ]);
  return strcmp($left, $right);
});

$output = fopen('php://output', 'w');
fputcsv($output, [
  'pattern',
  'nid',
  'bundle',
  'status',
  'page_title',
  'relative_url_or_path_pattern',
  'production_url',
  'is_exact_url',
  'webform_id',
  'webform_title',
  'source_field',
  'source_reference',
  'instance_count',
  'notes',
]);

foreach ($rows as $row) {
  fputcsv($output, [
    $row['pattern'],
    $row['nid'],
    $row['bundle'],
    $row['status'],
    $row['page_title'],
    $row['relative_url_or_path_pattern'],
    $row['production_url'],
    $row['is_exact_url'],
    $row['webform_id'],
    $row['webform_title'],
    $row['source_field'],
    $row['source_reference'],
    $row['instance_count'],
    $row['notes'],
  ]);
}

fclose($output);

/**
 * Builds normalized page metadata for a node-backed page.
 */
function build_node_page_data(
  NodeInterface $node,
  string $base_url,
  $alias_manager
): array {
  $relative_url = $alias_manager->getAliasByPath('/node/' . $node->id());

  return [
    'nid' => $node->id(),
    'bundle' => $node->bundle(),
    'status' => $node->isPublished() ? 'published' : 'unpublished',
    'page_title' => $node->label(),
    'relative_url_or_path_pattern' => $relative_url,
    'production_url' => $base_url . $relative_url,
  ];
}

/**
 * Adds or increments a normalized report row.
 */
function add_report_row(array &$rows, string $key, array $row): void {
  if (!isset($rows[$key])) {
    $row['instance_count'] = 1;
    $rows[$key] = $row;
    return;
  }

  $rows[$key]['instance_count']++;
}

/**
 * Finds Webform paragraphs currently referenced by a node.
 */
function find_current_webform_paragraphs(
  NodeInterface $node,
  array $paragraph_webform_fields
): array {
  $paragraphs = [];
  $visited = [];

  foreach (walk_referenced_paragraphs($node, $visited) as $paragraph) {
    $bundle = $paragraph->bundle();
    if (empty($paragraph_webform_fields[$bundle])) {
      continue;
    }

    foreach ($paragraph_webform_fields[$bundle] as $field_name) {
      if (!$paragraph->hasField($field_name) || $paragraph->get($field_name)->isEmpty()) {
        continue;
      }

      $webform = $paragraph->get($field_name)->entity;
      if (!$webform) {
        continue;
      }

      $paragraphs[] = [
        'paragraph' => $paragraph,
        'field_name' => $field_name,
        'webform' => $webform,
      ];
    }
  }

  return $paragraphs;
}

/**
 * Walks an entity's current paragraph tree.
 *
 * @return \Drupal\Core\Entity\EntityInterface[]
 *   Referenced paragraph entities in the current render tree.
 */
function walk_referenced_paragraphs(
  EntityInterface $entity,
  array &$visited
): array {
  $paragraphs = [];

  foreach ($entity->getFieldDefinitions() as $field_name => $definition) {
    if ($definition->getType() !== 'entity_reference_revisions' || $entity->get($field_name)->isEmpty()) {
      continue;
    }

    foreach ($entity->get($field_name)->referencedEntities() as $child) {
      if (!$child instanceof EntityInterface || $child->getEntityTypeId() !== 'paragraph') {
        continue;
      }

      $visited_key = $child->id() . ':' . $child->getRevisionId();
      if (isset($visited[$visited_key])) {
        continue;
      }

      $visited[$visited_key] = TRUE;
      $paragraphs[] = $child;
      $paragraphs = array_merge($paragraphs, walk_referenced_paragraphs($child, $visited));
    }
  }

  return $paragraphs;
}

/**
 * Resolves an exact path or alias to a node when possible.
 */
function resolve_node_from_path(
  string $path,
  $alias_manager,
  $node_storage
): ?NodeInterface {
  $system_path = $path;

  if (strpos($path, '/node/') !== 0) {
    $system_path = $alias_manager->getPathByAlias($path);
  }

  if (!preg_match('#^/node/(\d+)$#', $system_path, $matches)) {
    return NULL;
  }

  $node = $node_storage->load($matches[1]);
  return $node instanceof NodeInterface ? $node : NULL;
}

/**
 * Expands a simple wildcard block path pattern to node-backed pages.
 *
 * Supported patterns are suffix wildcards such as `/grownorth*` and
 * `/node/102416*`. For unsupported patterns, an empty array is returned so the
 * caller can preserve the raw pattern row instead.
 *
 * @return \Drupal\node\NodeInterface[]
 *   Node entities keyed by node ID.
 */
function expand_block_path_pattern_to_nodes(
  string $path_pattern,
  $alias_manager,
  $node_storage
): array {
  if ($path_pattern === '' || substr($path_pattern, -1) !== '*') {
    return [];
  }

  $nodes = [];
  $prefix = substr($path_pattern, 0, -1);

  if (preg_match('#^/node/(\d+)$#', $prefix, $matches)) {
    $node = $node_storage->load($matches[1]);
    if ($node instanceof NodeInterface) {
      $nodes[$node->id()] = $node;
    }
  }

  if ($prefix !== '' && strpos($prefix, '/node/') !== 0) {
    $results = \Drupal::database()->query(
      "SELECT path
       FROM path_alias
       WHERE alias LIKE :prefix",
      [':prefix' => $prefix . '%']
    );

    foreach ($results as $result) {
      if (!preg_match('#^/node/(\d+)$#', $result->path, $matches)) {
        continue;
      }

      $node = $node_storage->load($matches[1]);
      if ($node instanceof NodeInterface) {
        $nodes[$node->id()] = $node;
      }
    }
  }

  return $nodes;
}
