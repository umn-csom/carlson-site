<?php

namespace Drupal\carlson_general\CachePreload;

/**
 * Renders CSM-226 cache preload audit evidence as Markdown.
 */
final class CachePreloadAuditReporter {

  /**
   * Render a Markdown report.
   *
   * @param array $report
   *   Collected audit data.
   *
   * @return string
   *   Markdown report.
   */
  public function render(array $report): string {
    $lines = [
      '# CSM-226 Cache Preload Phase 2 Audit',
      '',
      'Generated: ' . date('c'),
      'Base URL: `' . $report['options']['base-url'] . '`',
      '',
      '## Current Preload Tags',
      '- Core/default tags: '
        . $this->codeList($report['core_preload_tags'] ?? []),
      '- Site settings tags: '
        . $this->codeList($report['site_preload_tags'] ?? []),
      '- Effective tags: '
        . $this->codeList($report['effective_preload_tags'] ?? []),
      '',
      '## Final Preload Recommendation',
      '- Decision rule: '
        . ($report['preload_recommendations']['decision_rule'] ?? ''),
      '- Tags to add now: '
        . $this->codeList(
          $report['preload_recommendations']['add_tags'] ?? []
        ),
      '',
    ];
    if (!empty($report['preload_recommendations']['rows'])) {
      $lines[] = '| Cache tag | Warm paths | Lookup groups | Measurement | '
        . 'Decision | Sample paths |';
      $lines[] = '| --- | ---: | ---: | --- | --- | --- |';
      foreach ($report['preload_recommendations']['rows'] as $row) {
        $lines[] = '| `' . $row['tag'] . '` | `'
          . $row['path_count'] . '` | `'
          . $row['group_count'] . '` | '
          . $this->escapeTable($row['measurement']) . ' | '
          . $this->escapeTable($row['decision']) . ' | '
          . $this->codeList(array_slice($row['paths'], 0, 8)) . ' |';
      }
      $lines[] = '';
    }
    else {
      $lines[] = '- No non-preloaded stable tag repeated across the captured '
        . 'warm lookup groups.';
      $lines[] = '';
    }
    if (!empty($report['preload_recommendations']['already_preloaded'])) {
      $lines[] = '- Repeated tags already covered by the effective preload '
        . 'list: '
        . $this->countMapList(
          $report['preload_recommendations']['already_preloaded']
        );
      $lines[] = '';
    }

    $lines[] = '';
    $lines[] = '## Warm Request Lookup Groups';
    $lines[] = 'This is the preload evidence. It warms selected paths, '
      . 'captures the next anonymous request, and reports whether the same '
      . 'stable tag appears across multiple `cachetags` lookup groups.';
    $lines[] = '';
    if (empty($report['warm_lookup_capture']['enabled'])) {
      $lines[] = '- ' . (
        $report['warm_lookup_capture']['unsupported']
        ?? 'Lookup capture skipped.'
      );
    }
    else {
      $lines[] = '- Warmups per path: `'
        . ($report['options']['lookup-warmups'] ?? 0)
        . '`';
      $lines[] = '- Paths captured: `'
        . count($report['warm_lookup_capture']['paths'] ?? [])
        . '`';
      $lines[] = '';
      $lines[] = '| Path | Status | Drupal cache | Lookup groups | '
        . 'Largest group | Repeated non-preloaded stable tags | '
        . 'Repeated preloaded tags | Recommendation |';
      $lines[] = '| --- | ---: | --- | ---: | ---: | --- | --- | --- |';
      foreach (($report['warm_lookup_capture']['paths'] ?? []) as $row) {
        $lines[] = '| `' . $row['path'] . '` | `'
          . ($row['status'] ?? 0) . '` | `'
          . (($row['cache'] ?? '') ?: 'n/a') . '` | `'
          . ($row['lookup_group_count'] ?? 0) . '` | `'
          . ($row['largest_group_tag_count'] ?? 0) . '` | '
          . $this->countRowList($row['candidate_tags'] ?? []) . ' | '
          . $this->countRowList($row['repeated_preloaded_tags'] ?? []) . ' | '
          . $this->escapeTable($row['recommendation'] ?? '') . ' |';
      }
    }

    $lines[] = '';
    $lines[] = '## Cache Table Evidence';
    $lines[] = '- Cache tables inspected: `'
      . count($report['cache_evidence']['tables'])
      . '`';
    $lines[] = '';
    $lines[] = '| Tag | Cache entries | Top tables |';
    $lines[] = '| --- | ---: | --- |';
    foreach ($report['cache_evidence']['tags'] as $tag => $info) {
      $lines[] = '| `' . $tag . '` | `' . $info['total'] . '` | ' .
        $this->countList($info['tables'], 5) . ' |';
    }

    $lines[] = '';
    $lines[] = '## Synthetic Cache-Read Measurement';
    $lines[] = 'This is supporting evidence only. It reads sampled cache table '
      . 'entries inside the Drush process; it does not replace warm HTTP '
      . 'lookup-group capture for page-level decisions.';
    $lines[] = '- Cache entries read: `' . count($report['cache_reads']) . '`';
    if (isset($report['measurements']['unsupported'])) {
      $lines[] = '- ' . $report['measurements']['unsupported'];
    }
    else {
      $lines[] = '';
      $lines[] = '| Scenario | Preload tags | Reads | Hits | Errors | '
        . 'Cachetag queries |';
      $lines[] = '| --- | --- | ---: | ---: | ---: | ---: |';
      foreach ($report['measurements'] as $scenario => $measurement) {
        $lines[] = '| `' . $scenario . '` | ' .
          $this->codeList($measurement['preload_tags']) . ' | `' .
          $measurement['reads'] . '` | `' . $measurement['hits'] . '` | `' .
          $measurement['errors'] . '` | `' .
          $measurement['cachetag_queries'] . '` |';
      }
    }

    $lines[] = '';
    $lines[] = '## Recommendation Guardrails';
    $lines[] = '- Keep `views_data` and `config:core.extension` when Views '
      . 'metadata appears in representative runtime/cache evidence.';
    $lines[] = '- Add a new static preload tag only when warm requests show '
      . 'the same stable tag across multiple lookup groups and a preload '
      . 'test lowers `cachetags` query count or time.';
    $lines[] = '- Do not add broad tags such as `rendered`, `http_response`, '
      . '`node_view`, or `block_view` solely because they are frequent; they '
      . 'are often grouped with page-specific tags.';
    $lines[] = '';

    return implode(PHP_EOL, $lines);
  }

  /**
   * Render inline code list.
   *
   * @param array $items
   *   Items to render.
   *
   * @return string
   *   Markdown inline-code list.
   */
  private function codeList(array $items): string {
    if (!$items) {
      return '`none`';
    }
    return implode(', ', array_map(
      static fn($item) => '`' . $item . '`',
      $items
    ));
  }

  /**
   * Render an associative count list.
   *
   * @param array $items
   *   Items keyed by label with counts as values.
   * @param int $limit
   *   Maximum number of items to render. Zero means no limit.
   *
   * @return string
   *   Markdown inline-code count list.
   */
  private function countList(array $items, int $limit = 0): string {
    if (!$items) {
      return '`none`';
    }
    arsort($items);
    if ($limit > 0) {
      $items = array_slice($items, 0, $limit, TRUE);
    }

    $parts = [];
    foreach ($items as $item => $count) {
      $parts[] = '`' . $item . '` (`' . $count . '`)';
    }
    return implode(', ', $parts);
  }

  /**
   * Render lookup-capture tag rows.
   *
   * @param array $rows
   *   Rows with tag and group_count keys.
   * @param int $limit
   *   Maximum rows to render.
   *
   * @return string
   *   Markdown inline-code count list.
   */
  private function countRowList(array $rows, int $limit = 8): string {
    if (!$rows) {
      return '`none`';
    }
    $rows = array_slice($rows, 0, $limit);

    $parts = [];
    foreach ($rows as $row) {
      $parts[] = '`' . $row['tag'] . '` (`'
        . $row['group_count'] . ' groups`)';
    }

    return implode(', ', $parts);
  }

  /**
   * Render aggregated recommendation tag rows.
   *
   * @param array $items
   *   Tag info keyed by tag.
   * @param int $limit
   *   Maximum rows to render.
   *
   * @return string
   *   Markdown inline-code count list.
   */
  private function countMapList(array $items, int $limit = 8): string {
    if (!$items) {
      return '`none`';
    }
    $items = array_slice($items, 0, $limit, TRUE);

    $parts = [];
    foreach ($items as $tag => $info) {
      $parts[] = '`' . $tag . '` (`'
        . $info['group_count'] . ' groups`)';
    }

    return implode(', ', $parts);
  }

  /**
   * Escape Markdown table content.
   *
   * @param string $value
   *   Table cell value.
   *
   * @return string
   *   Escaped table cell value.
   */
  private function escapeTable(string $value): string {
    return str_replace('|', '\|', $value);
  }

}
