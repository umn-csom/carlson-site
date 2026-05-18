#!/usr/bin/env python3
"""CSM-219 page component delta profiler.

Runs one page in three variants:

- normal: full page render.
- skip_nav: same URL with csm219_skip_nav=1, which the Carlson theme uses
  locally to bypass navbar_primary/navbar_secondary rendering.
- skip_nav_node_regions: same URL with csm219_skip_nav=1 and
  csm219_skip_node_regions=1, which locally bypasses theme-added node region
  blocks.
- skip_nav_node_regions_offcanvas_active_trail: same URL with nav and node
  regions bypassed, and responsive off-canvas rebuilt without forcing the
  entire menu tree open.
- skip_nav_node_regions_offcanvas_depth_3 and
  skip_nav_node_regions_offcanvas_depth_4: same active-trail replacement with
  a profiling-only max-depth cap.
- skip_nav_node_regions_offcanvas: same URL with nav, node regions, and
  responsive menu off-canvas output bypassed.

The script captures both XHProf call attribution and SQL slow-log query totals.
Run from docroot/sites/carlsonschool.umn.edu.
"""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import json
import os
import re
import shlex
import shutil
import subprocess
import time
from pathlib import Path


BASE_URL = "https://carlsonschool.ddev.site"
DRUSH_ALIAS = "@carlsonschool.ddev"
WEB_CONTAINER = os.environ.get("CSM219_WEB_CONTAINER", "ddev-umn-d9-web")
DB_CONTAINER = os.environ.get("CSM219_DB_CONTAINER", "ddev-umn-d9-db")
SLOW_LOG_FILE = "/tmp/csm219-nav-delta-slow.log"

QUERY_RE = re.compile(
    r"# Query_time: ([0-9.]+)\s+Lock_time: ([0-9.]+)\s+"
    r"Rows_sent: ([0-9]+)\s+Rows_examined: ([0-9]+)"
)
STRING_RE = re.compile(r"'([^'\\]|\\.)*'")
HEX_RE = re.compile(r"0x[0-9A-Fa-f]+")
NUM_RE = re.compile(r"(?<![A-Za-z_])[0-9]+(?:\.[0-9]+)?")
SPACE_RE = re.compile(r"\s+")
IN_RE = re.compile(r"IN \((?:\?,\s*)+\?\)", re.I)


def tool(name: str) -> str:
    found = shutil.which(name)
    if not found:
        raise SystemExit(f"Required command not found on PATH: {name}")
    return found


def env_with_paths() -> dict[str, str]:
    env = os.environ.copy()
    env["PATH"] = "/opt/homebrew/bin:/usr/local/bin:" + env.get("PATH", "")
    return env


def run(cmd: list[str], cwd: Path, check: bool = True) -> str:
    proc = subprocess.run(
        cmd,
        cwd=str(cwd),
        env=env_with_paths(),
        text=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
    )
    if check and proc.returncode != 0:
        raise RuntimeError(
            f"Command failed ({proc.returncode}): {' '.join(cmd)}\n{proc.stdout}"
        )
    return proc.stdout or ""


def ddev(cwd: Path, *args: str, check: bool = True) -> str:
    return run([tool("ddev"), *args], cwd, check=check)


def docker(cwd: Path, *args: str, check: bool = True) -> str:
    return run([tool("docker"), *args], cwd, check=check)


def drush(cwd: Path, *args: str, check: bool = True) -> str:
    return ddev(cwd, "drush", DRUSH_ALIAS, *args, check=check)


def db_exec(cwd: Path, sql: str, check: bool = True) -> str:
    return docker(
        cwd,
        "exec",
        DB_CONTAINER,
        "sh",
        "-lc",
        "client=$(command -v mariadb || command -v mysql) && "
        f'"$client" -uroot -proot -e {shlex.quote(sql)}',
        check=check,
    )


def db_shell(cwd: Path, cmd: str, check: bool = True) -> str:
    return docker(cwd, "exec", DB_CONTAINER, "sh", "-lc", cmd, check=check)


def stage_file_proxy_enabled(cwd: Path) -> bool:
    output = drush(
        cwd,
        "php:eval",
        "echo \\Drupal::moduleHandler()->moduleExists('stage_file_proxy') ? '1' : '0';",
    )
    return output.strip().startswith("1")


DEFAULT_VARIANTS = (
    "normal",
    "skip_nav",
    "skip_nav_node_regions",
    "skip_nav_node_regions_offcanvas_active_trail",
    "skip_nav_node_regions_offcanvas",
)
VALID_VARIANTS = DEFAULT_VARIANTS + (
    "skip_nav_node_regions_offcanvas_depth_3",
    "skip_nav_node_regions_offcanvas_depth_4",
)


def selected_variants(value: str) -> tuple[str, ...]:
    if value == "default":
        return DEFAULT_VARIANTS
    if value == "depth":
        return (
            "skip_nav_node_regions",
            "skip_nav_node_regions_offcanvas_active_trail",
            "skip_nav_node_regions_offcanvas_depth_3",
            "skip_nav_node_regions_offcanvas_depth_4",
            "skip_nav_node_regions_offcanvas",
        )

    variants = tuple(item.strip() for item in value.split(",") if item.strip())
    unknown = [item for item in variants if item not in VALID_VARIANTS]
    if unknown:
        raise SystemExit(
            "Unknown variant(s): "
            + ", ".join(unknown)
            + "\nValid variants: "
            + ", ".join(VALID_VARIANTS)
        )
    if not variants:
        raise SystemExit("At least one variant is required.")
    return variants


def url_for(path: str, variant: str, profile_token: str | None = None) -> str:
    separator = "&" if "?" in path else "?"
    url = f"{BASE_URL}{path}"
    if profile_token is None:
        profile_token = f"nav-delta-{variant}-{int(time.time() * 1000)}"
    if profile_token:
        url = f"{url}{separator}csm219_profile={profile_token}"
    if variant in (
        "skip_nav",
        "skip_nav_node_regions",
        "skip_nav_node_regions_offcanvas_active_trail",
        "skip_nav_node_regions_offcanvas_depth_3",
        "skip_nav_node_regions_offcanvas_depth_4",
        "skip_nav_node_regions_offcanvas",
    ):
        url += "&csm219_skip_nav=1"
    if variant in (
        "skip_nav_node_regions",
        "skip_nav_node_regions_offcanvas_active_trail",
        "skip_nav_node_regions_offcanvas_depth_3",
        "skip_nav_node_regions_offcanvas_depth_4",
        "skip_nav_node_regions_offcanvas",
    ):
        url += "&csm219_skip_node_regions=1"
    if variant in (
        "skip_nav_node_regions_offcanvas_active_trail",
        "skip_nav_node_regions_offcanvas_depth_3",
        "skip_nav_node_regions_offcanvas_depth_4",
    ):
        url += "&csm219_offcanvas_active_trail=1"
    if variant == "skip_nav_node_regions_offcanvas_depth_3":
        url += "&csm219_offcanvas_max_depth=3"
    if variant == "skip_nav_node_regions_offcanvas_depth_4":
        url += "&csm219_offcanvas_max_depth=4"
    if variant == "skip_nav_node_regions_offcanvas":
        url += "&csm219_skip_responsive_offcanvas=1"
    return url


def curl_page(
    cwd: Path,
    url: str,
    timeout: int,
    no_cache_headers: bool = True,
) -> dict[str, str]:
    cmd = [
        "curl",
        "--max-time",
        str(timeout),
        "-k",
        "-sS",
        "-D",
        "-",
        "-o",
        "/dev/null",
        "-w",
        "\n__CURL__ http_code=%{http_code} time_total=%{time_total} "
        "time_starttransfer=%{time_starttransfer} size_download=%{size_download}\n",
    ]
    if no_cache_headers:
        cmd.extend(["-H", "Cache-Control: no-cache", "-H", "Pragma: no-cache"])
    cmd.append(url)
    output = run(cmd, cwd, check=False)
    _, _, curl_line = output.rpartition("\n__CURL__ ")
    metrics: dict[str, str] = {}
    for token_value in curl_line.strip().split():
        if "=" in token_value:
            key, value = token_value.split("=", 1)
            metrics[key] = value
    return metrics


def latest_xhprof_file(cwd: Path) -> str:
    output = docker(
        cwd,
        "exec",
        WEB_CONTAINER,
        "sh",
        "-lc",
        "ls -1t /tmp/xhprof/*.xhprof 2>/dev/null | head -1",
        check=False,
    )
    return output.strip().splitlines()[0] if output.strip() else ""


def xhprof_files(cwd: Path) -> dict[str, float]:
    output = docker(
        cwd,
        "exec",
        WEB_CONTAINER,
        "sh",
        "-lc",
        "find /tmp/xhprof -maxdepth 1 -name '*.xhprof' -type f "
        "-printf '%T@ %p\\n' 2>/dev/null | sort -nr",
        check=False,
    )
    files: dict[str, float] = {}
    for line in output.splitlines():
        mtime, _, path = line.partition(" ")
        if path:
            files[path] = float(mtime)
    return files


def newest_new_xhprof_file(cwd: Path, before: set[str]) -> str:
    for _ in range(50):
        current = xhprof_files(cwd)
        new_files = {
            path: mtime for path, mtime in current.items() if path not in before
        }
        if new_files:
            return max(new_files.items(), key=lambda item: item[1])[0]
        time.sleep(0.1)
    return ""


def xhprof_summary(cwd: Path, profile_file: str) -> dict:
    php = r'''
require_once "/var/xhprof/xhprof_lib/utils/xhprof_lib.php";
$file = $argv[1];
$raw = unserialize(file_get_contents($file));
$flat = xhprof_compute_flat_info($raw, $totals);
uasort($flat, fn($a, $b) => ($b["excl_wt"] ?? 0) <=> ($a["excl_wt"] ?? 0));
$top = [];
foreach ($flat as $fn => $m) {
  if ($fn === "main()") {
    continue;
  }
  $top[] = [
    "fn" => $fn,
    "excl_ms" => round(($m["excl_wt"] ?? 0) / 1000, 1),
  ];
  if (count($top) >= 8) {
    break;
  }
}
$groups = [
  "curl_exec" => ["needle" => "==>curl_exec", "ct" => 0, "ms" => 0],
  "group_entity_access" => ["needle" => "==>group_entity_access", "ct" => 0, "ms" => 0],
  "simple_megamenu" => [
    "needle" => "==>Drupal\\simple_megamenu\\TwigExtension\\SimpleMegaMenuTwigExtension::viewMegaMenu",
    "ct" => 0,
    "ms" => 0,
  ],
  "block_visibility_group" => [
    "needle" => "==>Drupal\\block_visibility_groups\\Plugin\\Condition\\ConditionGroup::evaluate",
    "ct" => 0,
    "ms" => 0,
  ],
];
foreach ($raw as $edge => $m) {
  foreach ($groups as $key => &$group) {
    if (str_contains($edge, $group["needle"])) {
      $group["ct"] += $m["ct"] ?? 0;
      $group["ms"] += ($m["wt"] ?? 0) / 1000;
    }
  }
}
foreach ($groups as &$group) {
  unset($group["needle"]);
  $group["ms"] = round($group["ms"], 1);
}
echo json_encode([
  "file" => $file,
  "total_ms" => round(($totals["wt"] ?? 0) / 1000, 1),
  "top_exclusive" => $top,
  "groups" => $groups,
], JSON_UNESCAPED_SLASHES);
'''
    output = docker(
        cwd,
        "exec",
        WEB_CONTAINER,
        "php",
        "-r",
        php,
        profile_file,
    )
    return json.loads(output)


def parse_slow_log(text: str) -> list[dict]:
    entries: list[dict] = []
    current: dict | None = None
    sql_lines: list[str] = []

    def finish() -> None:
        nonlocal current, sql_lines
        if current is not None:
            sql_text = " ".join(line.strip() for line in sql_lines if line.strip())
            if sql_text and not sql_text.upper().startswith("SET TIMESTAMP"):
                current["sql"] = sql_text
                entries.append(current)
        current = None
        sql_lines = []

    for line in text.splitlines():
        match = QUERY_RE.match(line)
        if match:
            finish()
            current = {
                "query_s": float(match.group(1)),
                "rows_sent": int(match.group(3)),
                "rows_examined": int(match.group(4)),
            }
            continue
        if current is None or line.startswith("#") or line.startswith("SET timestamp="):
            continue
        sql_lines.append(line)
    finish()
    return entries


def fingerprint(query: str) -> str:
    query = STRING_RE.sub("'?'", query)
    query = HEX_RE.sub("0x?", query)
    query = NUM_RE.sub("?", query)
    query = SPACE_RE.sub(" ", query).strip()
    query = IN_RE.sub("IN (?)", query)
    return query


def family_name(sql: str) -> str:
    lowered = sql.lower()
    if "group_relationship_field_data" in lowered and "plugin_id" in lowered:
        return "group_access"
    if "path_alias" in lowered:
        return "path_alias"
    if "menu_tree" in lowered:
        return "menu_tree"
    if "cache_entity" in lowered:
        return "cache_entity"
    if "cache_config" in lowered:
        return "cache_config"
    if "cache_menu" in lowered:
        return "cache_menu"
    if "node_revision" in lowered:
        return "node_revision"
    return "other"


def sql_summary(
    cwd: Path,
    url: str,
    timeout: int,
    no_cache_headers: bool = True,
) -> dict:
    db_exec(cwd, "SET GLOBAL slow_query_log=OFF;")
    db_exec(
        cwd,
        f"SET GLOBAL log_output='FILE'; "
        f"SET GLOBAL slow_query_log_file='{SLOW_LOG_FILE}'; "
        "SET GLOBAL long_query_time=0.000000;",
    )
    db_shell(cwd, f": > {SLOW_LOG_FILE}")
    db_exec(cwd, "SET GLOBAL slow_query_log=ON;")
    metrics = curl_page(cwd, url, timeout, no_cache_headers=no_cache_headers)
    db_exec(cwd, "SET GLOBAL slow_query_log=OFF;")
    log_text = db_shell(cwd, f"cat {SLOW_LOG_FILE}", check=False)

    entries = [
        item
        for item in parse_slow_log(log_text)
        if item.get("sql") and "mysql.slow_log" not in item.get("sql", "")
    ]
    families: dict[str, dict[str, float | int]] = {}
    for entry in entries:
        family = family_name(fingerprint(entry["sql"]))
        data = families.setdefault(
            family,
            {"count": 0, "total_s": 0.0, "rows_examined": 0},
        )
        data["count"] = int(data["count"]) + 1
        data["total_s"] = float(data["total_s"]) + float(entry["query_s"])
        data["rows_examined"] = int(data["rows_examined"]) + int(entry["rows_examined"])

    return {
        "http_code": metrics.get("http_code", ""),
        "request_total_s": float(metrics.get("time_total", 0) or 0),
        "request_ttfb_s": float(metrics.get("time_starttransfer", 0) or 0),
        "query_count": len(entries),
        "query_total_s": round(sum(float(item["query_s"]) for item in entries), 6),
        "rows_examined": sum(int(item["rows_examined"]) for item in entries),
        "families": families,
    }


def warm_url(cwd: Path, url: str, timeout: int, warm_requests: int) -> None:
    for _ in range(warm_requests):
        curl_page(cwd, url, timeout, no_cache_headers=False)


def profile_xhprof_variant(
    cwd: Path,
    path: str,
    variant: str,
    timeout: int,
    rebuild_cache: bool = True,
    warm_requests: int = 0,
    no_cache_headers: bool = True,
) -> dict:
    if rebuild_cache:
        drush(cwd, "cache:rebuild")
    profile_token = None
    if warm_requests:
        profile_token = f"nav-delta-warm-{variant}"
    url = url_for(path, variant, profile_token=profile_token)
    warm_url(cwd, url, timeout, warm_requests)
    before_files = set(xhprof_files(cwd))
    ddev(cwd, "xhprof", "on")
    metrics = curl_page(cwd, url, timeout, no_cache_headers=no_cache_headers)
    profile_file = newest_new_xhprof_file(cwd, before_files)
    if not profile_file:
        profile_file = latest_xhprof_file(cwd)
    ddev(cwd, "xhprof", "off", check=False)
    summary = xhprof_summary(cwd, profile_file) if profile_file else {}
    return {
        "variant": variant,
        "url": url,
        "http_code": metrics.get("http_code", ""),
        "request_total_s": float(metrics.get("time_total", 0) or 0),
        "request_ttfb_s": float(metrics.get("time_starttransfer", 0) or 0),
        "xhprof": summary,
    }


def profile_sql_variant(
    cwd: Path,
    path: str,
    variant: str,
    timeout: int,
    rebuild_cache: bool = True,
    warm_requests: int = 0,
    no_cache_headers: bool = True,
) -> dict:
    if rebuild_cache:
        drush(cwd, "cache:rebuild")
    ddev(cwd, "xhprof", "off", check=False)
    profile_token = None
    if warm_requests:
        profile_token = f"nav-delta-warm-{variant}"
    url = url_for(path, variant, profile_token=profile_token)
    warm_url(cwd, url, timeout, warm_requests)
    data = sql_summary(cwd, url, timeout, no_cache_headers=no_cache_headers)
    data["variant"] = variant
    data["url"] = url
    return data


def family_value(sql: dict, name: str, key: str) -> float | int:
    return sql.get("families", {}).get(name, {}).get(key, 0)


def write_outputs(
    cwd: Path,
    label: str,
    path: str,
    rows: list[dict],
    cache_mode: str,
    warm_requests: int,
    no_cache_headers: bool,
) -> tuple[Path, Path]:
    stamp = dt.datetime.now().strftime("%Y-%m-%d-%H%M%S")
    slug = re.sub(r"[^a-z0-9]+", "-", label.lower()).strip("-")[:48]
    csv_path = cwd / f"csm-219-nav-delta-{slug}-{stamp}.csv"
    md_path = cwd / f"csm-219-nav-delta-{slug}-{stamp}.md"

    fields = [
        "variant",
        "http_code",
        "xhprof_request_total_s",
        "xhprof_total_ms",
        "curl_exec_ms",
        "curl_exec_ct",
        "group_entity_access_ms",
        "group_entity_access_ct",
        "simple_megamenu_ms",
        "simple_megamenu_ct",
        "block_visibility_group_ms",
        "block_visibility_group_ct",
        "sql_request_total_s",
        "query_count",
        "query_total_s",
        "rows_examined",
        "group_access_queries",
        "group_access_s",
        "path_alias_queries",
        "path_alias_s",
        "menu_tree_queries",
        "menu_tree_s",
        "top_exclusive",
        "url",
        "xhprof_file",
    ]
    with csv_path.open("w", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)

    row_by_variant = {row["variant"]: row for row in rows}

    if cache_mode == "warm":
        cache_note = (
            f"Cache mode: warm; {warm_requests} warmup request(s) were made "
            "for each measured URL before XHProf and SQL measurement."
        )
    else:
        cache_note = (
            "Cache mode: cold; caches were rebuilt before each measured "
            "variant."
        )
    header_note = "Cache-Control no-cache headers were sent."
    if not no_cache_headers:
        header_note = "Cache-Control no-cache headers were not sent."

    lines = [
        f"# CSM-219 component delta: {label}",
        "",
        f"Path: `{path}`",
        "",
        "Stage File Proxy was disabled for this run when requested by the CLI option.",
        "",
        cache_note,
        "",
        header_note,
        "",
        "## Variant Totals",
        "",
        "| Variant | HTTP | XHProf request s | XHProf total ms | SQL request s | Queries | SQL total s | Group access | Path alias | Menu tree | Simple megamenu ms |",
        "|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|",
    ]
    for row in rows:
        lines.append(
            f"| {row['variant']} | {row['http_code']} | "
            f"{float(row['xhprof_request_total_s']):.3f} | "
            f"{float(row['xhprof_total_ms']):.1f} | "
            f"{float(row['sql_request_total_s']):.3f} | "
            f"{int(row['query_count'])} | "
            f"{float(row['query_total_s']):.3f} | "
            f"{int(row['group_access_queries'])} | "
            f"{int(row['path_alias_queries'])} | "
            f"{int(row['menu_tree_queries'])} | "
            f"{float(row['simple_megamenu_ms']):.1f} |"
        )
    lines.extend(["", "## Deltas", ""])

    delta_pairs = [
        ("normal", "skip_nav", "Nav bypass"),
        ("skip_nav", "skip_nav_node_regions", "Node region bypass after nav"),
        (
            "skip_nav_node_regions",
            "skip_nav_node_regions_offcanvas_active_trail",
            "Responsive off-canvas active trail after nav and node regions",
        ),
        (
            "skip_nav_node_regions_offcanvas_active_trail",
            "skip_nav_node_regions_offcanvas_depth_3",
            "Responsive off-canvas max depth 3 after active-trail build",
        ),
        (
            "skip_nav_node_regions_offcanvas_active_trail",
            "skip_nav_node_regions_offcanvas_depth_4",
            "Responsive off-canvas max depth 4 after active-trail build",
        ),
        (
            "skip_nav_node_regions_offcanvas_active_trail",
            "skip_nav_node_regions_offcanvas",
            "Remaining responsive off-canvas output after active-trail build",
        ),
        (
            "skip_nav_node_regions",
            "skip_nav_node_regions_offcanvas",
            "Responsive off-canvas bypass after nav and node regions",
        ),
        ("normal", "skip_nav_node_regions", "Combined bypass"),
        (
            "normal",
            "skip_nav_node_regions_offcanvas",
            "Combined bypass with responsive off-canvas",
        ),
    ]
    for before, after, delta_label in delta_pairs:
        if before not in row_by_variant or after not in row_by_variant:
            continue
        source = row_by_variant[before]
        target = row_by_variant[after]

        def delta(key: str) -> float:
            return float(target[key] or 0) - float(source[key] or 0)

        lines.extend(
            [
                f"### {delta_label}",
                "",
                f"- XHProf request total: {delta('xhprof_request_total_s'):+.3f}s",
                f"- XHProf total: {delta('xhprof_total_ms'):+.1f}ms",
                f"- SQL request total: {delta('sql_request_total_s'):+.3f}s",
                f"- Query count: {int(delta('query_count')):+d}",
                f"- SQL total: {delta('query_total_s'):+.3f}s",
                f"- Group access queries: {int(delta('group_access_queries')):+d}",
                f"- Path alias queries: {int(delta('path_alias_queries')):+d}",
                f"- Menu tree queries: {int(delta('menu_tree_queries')):+d}",
                "- Simple megamenu inclusive XHProf time: "
                f"{delta('simple_megamenu_ms'):+.1f}ms",
                "",
            ]
        )
    lines.extend(["", "## Top Exclusive Functions", ""])
    for row in rows:
        lines.append(f"### {row['variant']}")
        lines.append("")
        lines.append(row["top_exclusive"])
        lines.append("")
    lines.append(f"Raw CSV: `{csv_path.name}`")
    md_path.write_text("\n".join(lines) + "\n")
    return csv_path, md_path


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--path", required=True, help="Page path to profile.")
    parser.add_argument("--label", default="page", help="Human label for output files.")
    parser.add_argument("--timeout", type=int, default=60, help="Curl timeout per request.")
    parser.add_argument("--skip-start", action="store_true", help="Do not run ddev start.")
    parser.add_argument(
        "--variants",
        default="default",
        help=(
            "Comma-separated variants, or 'default'/'depth'. "
            "Valid variants: " + ", ".join(VALID_VARIANTS)
        ),
    )
    parser.add_argument(
        "--cache-mode",
        choices=("cold", "warm"),
        default="cold",
        help="Use cold cache rebuilds or warm each URL before measurement.",
    )
    parser.add_argument(
        "--warm-requests",
        type=int,
        default=2,
        help="Warmup requests per URL when --cache-mode=warm.",
    )
    parser.add_argument(
        "--warm-no-cache-headers",
        action="store_true",
        help=(
            "In warm mode, still send Cache-Control no-cache headers during "
            "measured requests to bypass page cache but keep render/cache bins warm."
        ),
    )
    parser.add_argument(
        "--without-stage-file-proxy",
        action="store_true",
        help="Temporarily uninstall stage_file_proxy during the run, then restore it.",
    )
    args = parser.parse_args()
    variants = selected_variants(args.variants)
    warm_requests = args.warm_requests if args.cache_mode == "warm" else 0
    rebuild_per_variant = args.cache_mode == "cold"
    no_cache_headers = (
        args.cache_mode == "cold" or args.warm_no_cache_headers
    )

    cwd = Path.cwd()
    if not (cwd / "config" / "sync").is_dir():
        print("Run this script from docroot/sites/carlsonschool.umn.edu.")
        return 1

    if not args.skip_start:
        ddev(cwd, "start")
    drush(cwd, "status", "--fields=bootstrap,db-status,uri", "--format=list")

    restore_stage_file_proxy = False
    rows: list[dict] = []
    try:
        if args.without_stage_file_proxy and stage_file_proxy_enabled(cwd):
            restore_stage_file_proxy = True
            drush(cwd, "pm:uninstall", "stage_file_proxy", "-y")
            drush(cwd, "cache:rebuild")

        if args.cache_mode == "warm":
            drush(cwd, "cache:rebuild")

        xhprof_rows = {
            variant: profile_xhprof_variant(
                cwd,
                args.path,
                variant,
                args.timeout,
                rebuild_cache=rebuild_per_variant,
                warm_requests=warm_requests,
                no_cache_headers=no_cache_headers,
            )
            for variant in variants
        }
        sql_rows = {
            variant: profile_sql_variant(
                cwd,
                args.path,
                variant,
                args.timeout,
                rebuild_cache=rebuild_per_variant,
                warm_requests=warm_requests,
                no_cache_headers=no_cache_headers,
            )
            for variant in variants
        }

        for variant in variants:
            xh = xhprof_rows[variant]
            sql = sql_rows[variant]
            groups = xh["xhprof"].get("groups", {})
            top = "; ".join(
                f"{item['excl_ms']}ms {item['fn']}"
                for item in xh["xhprof"].get("top_exclusive", [])[:6]
            )
            rows.append(
                {
                    "variant": variant,
                    "http_code": xh["http_code"],
                    "xhprof_request_total_s": f"{xh['request_total_s']:.6f}",
                    "xhprof_total_ms": f"{xh['xhprof'].get('total_ms', 0):.1f}",
                    "curl_exec_ms": f"{groups.get('curl_exec', {}).get('ms', 0):.1f}",
                    "curl_exec_ct": groups.get("curl_exec", {}).get("ct", 0),
                    "group_entity_access_ms": f"{groups.get('group_entity_access', {}).get('ms', 0):.1f}",
                    "group_entity_access_ct": groups.get("group_entity_access", {}).get("ct", 0),
                    "simple_megamenu_ms": f"{groups.get('simple_megamenu', {}).get('ms', 0):.1f}",
                    "simple_megamenu_ct": groups.get("simple_megamenu", {}).get("ct", 0),
                    "block_visibility_group_ms": f"{groups.get('block_visibility_group', {}).get('ms', 0):.1f}",
                    "block_visibility_group_ct": groups.get("block_visibility_group", {}).get("ct", 0),
                    "sql_request_total_s": f"{sql['request_total_s']:.6f}",
                    "query_count": sql["query_count"],
                    "query_total_s": f"{sql['query_total_s']:.6f}",
                    "rows_examined": sql["rows_examined"],
                    "group_access_queries": family_value(sql, "group_access", "count"),
                    "group_access_s": f"{family_value(sql, 'group_access', 'total_s'):.6f}",
                    "path_alias_queries": family_value(sql, "path_alias", "count"),
                    "path_alias_s": f"{family_value(sql, 'path_alias', 'total_s'):.6f}",
                    "menu_tree_queries": family_value(sql, "menu_tree", "count"),
                    "menu_tree_s": f"{family_value(sql, 'menu_tree', 'total_s'):.6f}",
                    "top_exclusive": top,
                    "url": xh["url"],
                    "xhprof_file": xh["xhprof"].get("file", ""),
                }
            )
    finally:
        db_exec(
            cwd,
            "SET GLOBAL slow_query_log=OFF; SET GLOBAL long_query_time=10.000000;",
            check=False,
        )
        ddev(cwd, "xhprof", "off", check=False)
        if restore_stage_file_proxy:
            drush(cwd, "pm:enable", "stage_file_proxy", "-y", check=False)
            drush(cwd, "cache:rebuild", check=False)

    csv_path, md_path = write_outputs(
        cwd,
        args.label,
        args.path,
        rows,
        args.cache_mode,
        warm_requests,
        no_cache_headers,
    )
    print(f"Generated:\n- {csv_path}\n- {md_path}")
    for row in rows:
        print(
            f"{row['variant']}: xhprof={row['xhprof_request_total_s']}s "
            f"sql_request={row['sql_request_total_s']}s "
            f"queries={row['query_count']} "
            f"group={row['group_access_queries']} "
            f"path_alias={row['path_alias_queries']} "
            f"menu_tree={row['menu_tree_queries']}"
        )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
