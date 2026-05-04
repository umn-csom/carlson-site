<?php

namespace Drupal\carlson_general\CachePreload;

/**
 * Renders CSM-226 cache preload audit evidence as Markdown.
 *
 * The report is the human-readable artifact. The CSV is intentionally narrower
 * and contains only the sortable cache-tag frequency table.
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
      $this->codeList($report['current_preload_tags']),
      '',
      '## HTTP Header Sample',
    ];

    $with_debug_headers = 0;
    $with_view_markers = 0;
    foreach ($report['http_results'] as $row) {
      $with_debug_headers += !empty($row['tags']) ? 1 : 0;
      $with_view_markers += !empty($row['view_markers']) ? 1 : 0;
    }
    $lines[] = '- Paths sampled: `' . count($report['paths']) . '`';
    $lines[] = '- Responses with `x-drupal-cache-tags`: `'
      . $with_debug_headers
      . '`';
    $lines[] = '- Responses with View markers: `' . $with_view_markers . '`';
    if (!$report['options']['skip-http'] && $with_debug_headers === 0) {
      $lines[] = '- No cache-tag headers were observed. Enable local '
        . 'cacheability debug headers before using the HTTP coverage section '
        . 'for decisions.';
    }
    $lines[] = '';
    $lines[] = '| Path | Status | Source | View markers | '
      . 'Candidate tags seen |';
    $lines[] = '| --- | ---: | --- | --- | --- |';
    foreach ($report['http_results'] as $row) {
      $seen = array_values(array_intersect(
        $report['candidate_tags'],
        $row['tags']
      ));
      $lines[] = '| `' . $row['path'] . '` | `' . $row['status'] . '` | ' .
        $this->escapeTable($row['source']) . ' | ' .
        $this->codeList($row['view_markers'] ?? []) . ' | ' .
        $this->codeList($seen) . ' |';
    }

    $lines[] = '';
    $lines[] = '## Observed Cache Tag Frequency';
    $lines[] = '- Unique observed tags: `'
      . ($report['tag_frequency']['unique_tags'] ?? 0)
      . '`';
    $lines[] = '- Pages with cache-tag headers: `'
      . ($report['tag_frequency']['pages_with_headers'] ?? 0)
      . '`';
    if (!empty($report['options']['frequency-csv'])) {
      $lines[] = '- CSV export: `' . $report['options']['frequency-csv'] . '`';
    }
    $lines[] = '';
    $lines[] = '| Cache tag | Pages | Sample paths |';
    $lines[] = '| --- | ---: | --- |';
    foreach (($report['tag_frequency']['tags'] ?? []) as $tag => $info) {
      $lines[] = '| `' . $tag . '` | `' . $info['page_count'] . '` | '
        . $this->codeList($info['sample_paths'])
        . ' |';
    }

    $lines[] = '';
    $lines[] = '## Automated Candidate Review';
    $lines[] = '- Coverage threshold: `'
      . ($report['candidate_review']['coverage_threshold'] ?? 0)
      . '%`';
    $lines[] = '- Candidate tags measured individually: '
      . $this->codeList($report['candidate_probe_tags'] ?? []);
    $lines[] = '';
    $lines[] = '| Cache tag | Coverage | Classification | Reason | '
      . 'Measurement | Recommendation |';
    $lines[] = '| --- | ---: | --- | --- | --- | --- |';
    foreach (($report['candidate_review']['rows'] ?? []) as $row) {
      $coverage = $row['page_count'] . '/'
        . $row['pages_with_headers'] . ' ('
        . $row['coverage'] . '%)';
      $lines[] = '| `' . $row['tag'] . '` | `' . $coverage . '` | `'
        . $row['classification'] . '` | '
        . $this->escapeTable($row['reason']) . ' | '
        . $this->escapeTable($row['measurement']) . ' | '
        . $this->escapeTable($row['recommendation']) . ' |';
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
    $lines[] = '## Checksum Query Measurement';
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
    $lines[] = '- Add a new static preload tag only if it appears across '
      . 'representative requests and lowers cachetag query count in the local '
      . 'measurement.';
    $lines[] = '- Do not add broad tags such as `rendered`, `http_response`, '
      . '`node_view`, or `block_view` solely because they are frequent; they '
      . 'are often grouped with page-specific tags.';
    $lines[] = '';

    return implode(PHP_EOL, $lines);
  }

  /**
   * Write observed cache-tag frequency data to CSV.
   *
   * @param array $report
   *   Collected audit data.
   * @param string $path
   *   Destination CSV path or stream wrapper URI.
   */
  public function writeFrequencyCsv(array $report, string $path): void {
    $handle = fopen($path, 'w');
    if (!$handle) {
      throw new \RuntimeException("Unable to write CSV report: {$path}");
    }

    fputcsv($handle, ['cache_tag', 'page_count', 'sample_paths']);
    foreach (($report['tag_frequency']['tags'] ?? []) as $tag => $info) {
      fputcsv($handle, [
        $tag,
        $info['page_count'],
        implode(' ', $info['sample_paths']),
      ]);
    }

    fclose($handle);
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
