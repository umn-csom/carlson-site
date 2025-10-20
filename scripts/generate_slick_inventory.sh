#!/usr/bin/env bash

set -euo pipefail

log() {
  echo "[slick-inventory] $*"
}

if ! command -v rg >/dev/null 2>&1; then
  echo "Error: ripgrep (rg) is required but not found in PATH." >&2
  exit 1
fi

if ! command -v ddev >/dev/null 2>&1; then
  echo "Error: ddev is required but not found in PATH." >&2
  exit 1
fi

timestamp="$(date +%Y-%m-%d_%H-%M-%S)"
output_file="${1:-slick-inventory-report-${timestamp}.md}"

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${repo_root}"

write_section() {
  local title="$1"
  echo "## ${title}" >> "${output_file}"
  echo >> "${output_file}"
}

write_command_output() {
  local heading="$1"
  shift
  local cmd=("$@")
  local cmd_display
  cmd_display="$(printf "%q " "${cmd[@]}")"

  log "  ↳ ${heading}"
  log "    $ ${cmd_display}"
  local output
  output="$("${cmd[@]}" 2>&1)" || true

  echo "### ${heading}" >> "${output_file}"
  echo '```bash' >> "${output_file}"
  echo "$ ${cmd_display}" >> "${output_file}"
  echo '```' >> "${output_file}"
  echo >> "${output_file}"
  echo '```text' >> "${output_file}"
  if [[ -n "${output}" ]]; then
    printf "%s\n" "${output}" >> "${output_file}"
  else
    echo "(no output)" >> "${output_file}"
  fi
  echo '```' >> "${output_file}"
  echo >> "${output_file}"

  echo "----- ${heading} -----"
  echo "$ ${cmd_display}"
  if [[ -n "${output}" ]]; then
    printf "%s\n" "${output}"
  else
    echo "(no output)"
  fi
  echo "----------------------"
  echo
}

run_sql() {
  local heading="$1"
  local query="$2"
  write_command_output "${heading}" ddev drush sqlq "${query}"
}

write_static_block() {
  local heading="$1"
  shift
  local lines=("$@")

  log "  ↳ ${heading}"

  echo "### ${heading}" >> "${output_file}"
  echo '```text' >> "${output_file}"
  if [[ ${#lines[@]} -gt 0 ]]; then
    printf "%s\n" "${lines[@]}" >> "${output_file}"
  else
    echo "(no details)" >> "${output_file}"
  fi
  echo '```' >> "${output_file}"
  echo >> "${output_file}"

  echo "----- ${heading} -----"
  if [[ ${#lines[@]} -gt 0 ]]; then
    printf "%s\n" "${lines[@]}"
  else
    echo "(no details)"
  fi
  echo "----------------------"
  echo
}

declare -A paragraph_optionset_map=()
declare -A paragraph_field_map=()
declare -A paragraph_viewmode_map=()
declare -A paragraph_display_map=()
declare -A paragraph_formatter_map=()

declare -A block_optionset_map=()
declare -A block_field_map=()
declare -A block_viewmode_map=()
declare -A block_display_map=()
declare -A block_formatter_map=()

declare -A view_optionset_map=()
declare -A view_source_map=()

mapfile -t slick_display_rows < <(SITE_ROOT="${repo_root}" php <<'PHP'
<?php
declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

$siteRoot = getenv('SITE_ROOT');
$autoload = $siteRoot . '/../../../vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Unable to locate vendor/autoload.php. Skipping config introspection.\n");
    exit(0);
}
require $autoload;

$rows = [];

$displayFiles = glob($siteRoot . '/config/sync/core.entity_view_display.*.yml') ?: [];
foreach ($displayFiles as $file) {
    $data = Yaml::parseFile($file);
    $entityType = $data['targetEntityType'] ?? null;
    $bundle = $data['bundle'] ?? null;
    $viewMode = $data['mode'] ?? 'default';
    if (!$entityType || !$bundle) {
        continue;
    }
    foreach (($data['content'] ?? []) as $fieldName => $component) {
        if (!is_array($component)) {
            continue;
        }
        $settings = $component['settings'] ?? [];
        $optionset = $settings['optionset'] ?? null;
        if ($optionset) {
            $fieldType = $component['type'] ?? '';
            $rows[] = implode('|', [
                $entityType,
                $bundle,
                $viewMode,
                $fieldName,
                $optionset,
                $fieldType,
                basename($file),
            ]);
        }
    }
}

$viewFiles = glob($siteRoot . '/config/sync/views.view.*.yml') ?: [];
foreach ($viewFiles as $file) {
    $data = Yaml::parseFile($file);
    $viewId = $data['id'] ?? basename($file, '.yml');
    foreach (($data['display'] ?? []) as $displayId => $display) {
        $style = $display['display_options']['style'] ?? null;
        if (!is_array($style)) {
            continue;
        }
        $optionset = $style['options']['optionset'] ?? null;
        if (($style['type'] ?? null) === 'slick' && $optionset) {
            $rows[] = implode('|', [
                'view',
                $viewId,
                $displayId,
                'style',
                $optionset,
                'slick',
                basename($file) . ':' . $displayId,
            ]);
        }
    }
}

echo implode(PHP_EOL, $rows);
PHP
) || true

for row in "${slick_display_rows[@]}"; do
  IFS='|' read -r entity_type bundle view_mode field optionset field_type source <<< "${row}"
  case "${entity_type}" in
    paragraph)
      paragraph_optionset_map["${bundle}"]="${optionset}"
      paragraph_field_map["${bundle}"]="${field}"
      paragraph_viewmode_map["${bundle}"]="${view_mode}"
      paragraph_display_map["${bundle}"]="${source}"
      paragraph_formatter_map["${bundle}"]="${field_type}"
      ;;
    block_content)
      block_optionset_map["${bundle}"]="${optionset}"
      block_field_map["${bundle}"]="${field}"
      block_viewmode_map["${bundle}"]="${view_mode}"
      block_display_map["${bundle}"]="${source}"
      block_formatter_map["${bundle}"]="${field_type}"
      ;;
    view)
      key="${bundle}|${view_mode}"
      view_optionset_map["${key}"]="${optionset}"
      view_source_map["${key}"]="${source}"
      ;;
  esac
done

# Detect the theme-driven slideshow paragraph separately because it bypasses
# the entity display optionset pipeline and is initialized entirely in JS.
if [[ -f themes/custom/carlson_refresh/templates/paragraphs/paragraph--slideshow.html.twig ]]; then
  slideshow_count=$(ddev mysql db -Ne "SELECT COUNT(*) FROM paragraphs_item_field_data WHERE type='slideshow'")
  if [[ -n "${slideshow_count}" && "${slideshow_count}" -gt 0 ]]; then
    paragraph_optionset_map["slideshow"]="Theme JS (carlson_refresh/slideshow library)"
    paragraph_field_map["slideshow"]="Rendered via theme JS (slider-for/slider-nav markup)"
    paragraph_viewmode_map["slideshow"]="n/a"
    paragraph_display_map["slideshow"]="themes/custom/carlson_refresh/templates/paragraphs/paragraph--slideshow.html.twig"
    paragraph_formatter_map["slideshow"]="custom_js"
  fi
fi

slick_paragraph_types_array=()
if [[ ${#paragraph_optionset_map[@]} -gt 0 ]]; then
  readarray -t slick_paragraph_types_array < <(printf "%s\n" "${!paragraph_optionset_map[@]}" | sort -u)
fi

echo "# Slick Carousel Discovery Report" > "${output_file}"
echo >> "${output_file}"
echo "_Generated on ${timestamp}_" >> "${output_file}"
echo >> "${output_file}"

log "Collecting configuration references…"
write_section "Configuration references"
write_command_output "Any config mentioning \"slick\"" rg "slick" config/sync -g"*.yml"
write_command_output "Displays pointing at optionsets" rg "optionset:" config/sync -g"*.yml"

log "Scanning theme and custom code for Slick initialisation…"
write_section "Theme and custom code usage"
write_command_output "Direct JS initialisation (.slick()" rg ".slick\(" themes -g"*.js" --glob '!**/*.min.js'
write_command_output "Slick Twig overrides" rg "slick__" themes -g"*.twig"

log "Running database snapshots via Drush…"
write_section "Database usage snapshots"

if [[ ${#slick_paragraph_types_array[@]} -gt 0 ]]; then
  # The SQL filters are populated dynamically from the displays we discovered above
  # so new Slick-enabled paragraph bundles get picked up automatically (including
  # the theme-driven slideshow fallback we append when it exists).
  types_list=$(printf "'%s'," "${slick_paragraph_types_array[@]}")
  types_list="${types_list%,}"

  run_sql "Paragraph type counts" \
"SELECT type, COUNT(*) AS total
FROM paragraphs_item_field_data
WHERE type IN (${types_list})
GROUP BY type
ORDER BY total DESC;"

  run_sql "Nodes containing Slick-configured paragraph types" \
"SELECT n.type AS node_type, p.type AS paragraph_type, COUNT(DISTINCT p.parent_id) AS node_count
FROM paragraphs_item_field_data p
JOIN node_field_data n ON n.nid = p.parent_id
WHERE p.parent_type = 'node' AND p.type IN (${types_list}) AND n.status = 1
GROUP BY n.type, p.type
ORDER BY node_count DESC;"

  write_section "Instance breakdown"
  for paragraph_type in "${slick_paragraph_types_array[@]}"; do
    optionset="${paragraph_optionset_map[${paragraph_type}]:-Unknown}"
    field_name="${paragraph_field_map[${paragraph_type}]:-n/a}"
    formatter="${paragraph_formatter_map[${paragraph_type}]:-n/a}"
    view_mode="${paragraph_viewmode_map[${paragraph_type}]:-default}"
    display_config="${paragraph_display_map[${paragraph_type}]:-n/a}"

    paragraph_total=$(ddev mysql db -Ne "SELECT COUNT(*) FROM paragraphs_item_field_data WHERE type='${paragraph_type}'" 2>/dev/null || echo "N/A")
    node_total=$(ddev mysql db -Ne "SELECT COUNT(DISTINCT parent_id) FROM paragraphs_item_field_data WHERE type='${paragraph_type}' AND parent_type='node'" 2>/dev/null || echo "N/A")

    write_static_block "paragraph.${paragraph_type}" \
      "Optionset: ${optionset}" \
      "Formatter: ${formatter}" \
      "Field: ${field_name}" \
      "View mode: ${view_mode}" \
      "Display config: ${display_config}" \
      "Paragraph count: ${paragraph_total}" \
      "Node count: ${node_total}"

    run_sql "Example page using paragraph '${paragraph_type}'" \
"SELECT n.nid, n.title,
COALESCE(
  (SELECT alias FROM path_alias WHERE path = CONCAT('/node/', n.nid) ORDER BY langcode = n.langcode DESC LIMIT 1),
  CONCAT('/node/', n.nid)
) AS url
FROM paragraphs_item_field_data p
JOIN node_field_data n ON n.nid = p.parent_id AND n.langcode = p.langcode
WHERE p.parent_type = 'node'
  AND p.type = '${paragraph_type}'
  AND n.status = 1
ORDER BY n.changed DESC
LIMIT 1;"
  done
else
  write_command_output "Paragraph type counts" bash -lc "echo 'NO_MATCHES'"
  write_command_output "Nodes containing Slick-configured paragraph types" bash -lc "echo 'NO_MATCHES'"
  write_section "Instance breakdown"
  write_static_block "No Slick-enabled paragraph displays found" "There were no paragraph view displays referencing Slick optionsets."
fi

run_sql "Media slideshow block instances" \
"SELECT COUNT(*) AS media_slideshow_blocks
FROM block_content_field_data
WHERE type = 'media_slideshow';"

if [[ ${#block_optionset_map[@]} -gt 0 ]]; then
  write_section "Block display instances"
  while IFS= read -r block_type; do
    optionset="${block_optionset_map[${block_type}]:-Unknown}"
    field_name="${block_field_map[${block_type}]:-n/a}"
    formatter="${block_formatter_map[${block_type}]:-n/a}"
    view_mode="${block_viewmode_map[${block_type}]:-default}"
    display_config="${block_display_map[${block_type}]:-n/a}"
    block_count=$(ddev mysql db -Ne "SELECT COUNT(*) FROM block_content_field_data WHERE type='${block_type}'" 2>/dev/null || echo "N/A")

    write_static_block "block_content.${block_type}" \
      "Optionset: ${optionset}" \
      "Formatter: ${formatter}" \
      "Field: ${field_name}" \
      "View mode: ${view_mode}" \
      "Display config: ${display_config}" \
      "Block count: ${block_count}" \
      "Sample page: not automatically detected (check block placements or Layout Builder)."
  done < <(printf "%s\n" "${!block_optionset_map[@]}" | sort -u)
fi

if [[ ${#view_optionset_map[@]} -gt 0 ]]; then
  write_section "View displays using Slick"
  while IFS= read -r key; do
    IFS='|' read -r view_id display_id <<< "${key}"
    optionset="${view_optionset_map[${key}]}"
    source="${view_source_map[${key}]}"
    write_static_block "view.${view_id}.${display_id}" \
      "Optionset: ${optionset}" \
      "Display config: ${source}" \
      "Sample page: not automatically detected (locate view block placement)."
  done < <(printf "%s\n" "${!view_optionset_map[@]}" | sort -u)
fi

log "Scanning content fields for explicit Slick markup…"
write_section "Inline content search"

write_command_output "Field values containing Slick carousel markup" bash -lc 'echo "(Skipped automatic inline scan – run a manual search if needed.)"'

log "Report written to ${output_file}"
