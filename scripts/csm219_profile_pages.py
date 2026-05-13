#!/usr/bin/env python3
"""Local CSM-219 profiling runner for Carlson Drupal/DDEV.

This script reproduces the profiling passes used for CSM-219:

- XHProf attribution for cache-busted pages and selected full-cold pages.
- SQL slow-log capture using MariaDB's file slow log, with XHProf disabled.

Run it from docroot/sites/carlsonschool.umn.edu.
"""

from __future__ import annotations

import argparse
import base64
import csv
import datetime as dt
import json
import os
import re
import shutil
import subprocess
import sys
import time
from pathlib import Path


BASE_URL = "https://carlsonschool.ddev.site"
XHPROF_BASE_URL = "https://umn-d9.ddev.site/xhprof/index.php"
DRUSH_ALIAS = "@carlsonschool.ddev"
WEB_CONTAINER = os.environ.get("CSM219_WEB_CONTAINER", "ddev-umn-d9-web")
DB_CONTAINER = os.environ.get("CSM219_DB_CONTAINER", "ddev-umn-d9-db")
SLOW_LOG_FILE = "/tmp/csm219-slow.log"

FULL_PAGES = [
    ("homepage", "/"),
    ("requested executive education courses", "/executive-education/courses"),
    ("requested full-time mba", "/graduate/mba/full-time"),
    ("graduate landing page", "/graduate"),
    ("news hub view", "/news"),
    ("faculty directory view", "/faculty-research/directory-tenured-tenure-track"),
    ("alumni events view", "/alumni/events"),
    ("executive education landing", "/executive-education"),
    ("executive ed program node", "/executive-education/courses/emerging-leaders-bootcamp"),
    ("graduate resource article", "/graduate/resources/what-value-mba-or-masters-degree"),
    ("about page", "/about"),
    ("faculty profile node", "/faculty/bella-yeolim-yoon"),
    ("blog entry node", "/faculty-research/gary-s-holmes-center-entrepreneurship/blog/broshar"),
    ("person node", "/person/richele-butler"),
    ("video node", "/node/107586"),
    ("event node", "/events/20260508-finance-seminar-alexandre-corhay-university-toronto"),
    ("education abroad program", "/education-abroad/programs/i-core-gold-block-prague"),
    ("email/newsletter page", "/faculty-research/institute-research-marketing/newsletter"),
    ("conference speakers view", "/conferences/hr-tomorrow/speakers"),
    ("student node", "/node/129326"),
    ("quiz node", "/em/msmk/i-fit"),
    ("alumni default page", "/alumni/events/1st-tuesday"),
    ("magazine issue", "/discovery/fall-2021"),
    ("webform node", "/node/118271"),
    ("give page", "/give"),
    ("degree finder view", "/graduate/resources/find-degree"),
    ("conference payment reimbursement page", "/conferences/convene/convene-conference-schedule/Payment_Reimbursement"),
]

PRIORITY_PAGES = [
    ("homepage", "/"),
    ("requested executive education courses", "/executive-education/courses"),
    ("requested full-time mba", "/graduate/mba/full-time"),
    ("graduate landing page", "/graduate"),
    ("news hub view", "/news"),
    ("faculty directory view", "/faculty-research/directory-tenured-tenure-track"),
    ("alumni events view", "/alumni/events"),
    ("executive education landing", "/executive-education"),
    ("degree finder view", "/graduate/resources/find-degree"),
]

COLD_PAGES = [
    ("homepage", "/"),
    ("requested executive education courses", "/executive-education/courses"),
    ("requested full-time mba", "/graduate/mba/full-time"),
    ("news hub view", "/news"),
    ("graduate landing page", "/graduate"),
]

PHP_XHPROF_SUMMARY = r'''
require_once "/var/xhprof/xhprof_lib/utils/xhprof_lib.php";
$file = $argv[1];
$raw = unserialize(file_get_contents($file));
$flat = xhprof_compute_flat_info($raw, $totals);
uasort($flat, function ($a, $b) {
  return ($b["excl_wt"] ?? 0) <=> ($a["excl_wt"] ?? 0);
});
$top = [];
foreach ($flat as $fn => $m) {
  if ($fn === "main()") {
    continue;
  }
  $top[] = [
    "fn" => $fn,
    "ct" => $m["ct"] ?? 0,
    "incl_ms" => round(($m["wt"] ?? 0) / 1000, 1),
    "excl_ms" => round(($m["excl_wt"] ?? 0) / 1000, 1),
  ];
  if (count($top) >= 8) {
    break;
  }
}
echo json_encode([
  "total_ms" => round(($totals["wt"] ?? 0) / 1000, 1),
  "total_mem_mb" => round(($totals["mu"] ?? 0) / 1048576, 1),
  "peak_mem_mb" => round(($totals["pmu"] ?? 0) / 1048576, 1),
  "calls" => $totals["ct"] ?? 0,
  "top" => $top,
], JSON_UNESCAPED_SLASHES);
'''

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


def db_exec(cwd: Path, sql: str, check: bool = True) -> str:
    return docker(
        cwd,
        "exec",
        DB_CONTAINER,
        "mariadb",
        "-uroot",
        "-proot",
        "-e",
        sql,
        check=check,
    )


def db_shell(cwd: Path, cmd: str, check: bool = True) -> str:
    return docker(cwd, "exec", DB_CONTAINER, "sh", "-lc", cmd, check=check)


def drush(cwd: Path, *args: str, check: bool = True) -> str:
    return ddev(cwd, "drush", DRUSH_ALIAS, *args, check=check)


def pages_for_set(name: str) -> list[tuple[str, str]]:
    if name == "priority":
        return PRIORITY_PAGES
    return FULL_PAGES


def slug(label: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", label.lower()).strip("-")[:48]


def profile_url(path: str, mode: str, label: str) -> str:
    separator = "&" if "?" in path else "?"
    nonce = f"{mode}-{slug(label)}-{int(time.time() * 1000)}"
    return f"{BASE_URL}{path}{separator}csm219_profile={nonce}"


def curl_page(cwd: Path, url: str, timeout: int) -> tuple[dict[str, str], dict[str, str]]:
    output = run(
        [
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
            "-H",
            "Cache-Control: no-cache",
            "-H",
            "Pragma: no-cache",
            url,
        ],
        cwd,
        check=False,
    )
    header_part, _, curl_line = output.rpartition("\n__CURL__ ")
    metrics: dict[str, str] = {}
    for token_value in curl_line.strip().split():
        if "=" in token_value:
            key, value = token_value.split("=", 1)
            metrics[key] = value

    blocks = [b for b in header_part.replace("\r\n", "\n").split("\n\n") if b.strip()]
    headers: dict[str, str] = {}
    if blocks:
        for line in blocks[-1].split("\n"):
            if ":" in line:
                key, value = line.split(":", 1)
                headers[key.strip().lower()] = value.strip()
    return metrics, headers


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


def xhprof_summary(cwd: Path, profile_file: str) -> dict:
    if not profile_file:
        return {}
    output = docker(
        cwd,
        "exec",
        WEB_CONTAINER,
        "php",
        "-d",
        "auto_prepend_file=",
        "-r",
        PHP_XHPROF_SUMMARY,
        profile_file,
        check=False,
    )
    try:
        return json.loads(output)
    except json.JSONDecodeError:
        return {"parse_error": output[:500]}


def run_xhprof(cwd: Path, page_set: str, timeout: int) -> tuple[Path, Path]:
    stamp = dt.datetime.now().strftime("%Y-%m-%d-%H%M%S")
    csv_path = cwd / f"csm-219-xhprof-profile-results-{stamp}.csv"
    md_path = cwd / f"csm-219-xhprof-profile-summary-{stamp}.md"
    rows: list[dict[str, str]] = []

    ddev(cwd, "xhprof", "off", check=False)
    drush(cwd, "cache:rebuild")
    for _, path in [("homepage", "/"), ("full-time mba", "/graduate/mba/full-time")]:
        curl_page(cwd, profile_url(path, "warm", path), timeout)
    ddev(cwd, "xhprof", "on")

    for label, path in pages_for_set(page_set):
        rows.append(profile_xhprof_page(cwd, "cache_bust", label, path, timeout))

    for label, path in COLD_PAGES:
        ddev(cwd, "xhprof", "off", check=False)
        drush(cwd, "cache:rebuild")
        ddev(cwd, "xhprof", "on")
        rows.append(profile_xhprof_page(cwd, "cold_cr", label, path, timeout))

    write_xhprof_outputs(csv_path, md_path, rows)
    ddev(cwd, "xhprof", "off", check=False)
    return csv_path, md_path


def profile_xhprof_page(
    cwd: Path,
    mode: str,
    label: str,
    path: str,
    timeout: int,
) -> dict[str, str]:
    before = latest_xhprof_file(cwd)
    url = profile_url(path, mode, label)
    metrics, headers = curl_page(cwd, url, timeout)
    after = latest_xhprof_file(cwd)
    if after == before:
        time.sleep(0.2)
        after = latest_xhprof_file(cwd)
    run_id = Path(after).name.replace(".ddev.xhprof", "") if after else ""
    summary = xhprof_summary(cwd, after) if after and after != before else {}
    top = summary.get("top") or []
    top_excl = "; ".join(
        f"{item.get('excl_ms')}ms {item.get('fn')}" for item in top[:5]
    )
    print(
        f"{mode:10} {metrics.get('http_code', '?')} "
        f"total={metrics.get('time_total', '?')}s xhprof={run_id} {label}",
        flush=True,
    )
    return {
        "mode": mode,
        "label": label,
        "path": path,
        "http_code": metrics.get("http_code", ""),
        "ttfb_s": metrics.get("time_starttransfer", ""),
        "total_s": metrics.get("time_total", ""),
        "size_download": metrics.get("size_download", ""),
        "cache_control": headers.get("cache-control", ""),
        "x_drupal_cache": headers.get("x-drupal-cache", ""),
        "x_drupal_dynamic_cache": headers.get("x-drupal-dynamic-cache", ""),
        "x_drupal_cache_max_age": headers.get("x-drupal-cache-max-age", ""),
        "xhprof_run": run_id,
        "xhprof_url": f"{XHPROF_BASE_URL}?run={run_id}&source=ddev" if run_id else "",
        "xhprof_total_ms": str(summary.get("total_ms", "")),
        "xhprof_mem_mb": str(summary.get("total_mem_mb", "")),
        "xhprof_peak_mem_mb": str(summary.get("peak_mem_mb", "")),
        "xhprof_calls": str(summary.get("calls", "")),
        "top_exclusive": top_excl,
        "url": url,
        "xhprof_file": after,
    }


def write_xhprof_outputs(csv_path: Path, md_path: Path, rows: list[dict[str, str]]) -> None:
    fields = [
        "mode",
        "label",
        "path",
        "http_code",
        "ttfb_s",
        "total_s",
        "size_download",
        "cache_control",
        "x_drupal_cache",
        "x_drupal_dynamic_cache",
        "x_drupal_cache_max_age",
        "xhprof_run",
        "xhprof_url",
        "xhprof_total_ms",
        "xhprof_mem_mb",
        "xhprof_peak_mem_mb",
        "xhprof_calls",
        "top_exclusive",
        "url",
        "xhprof_file",
    ]
    with csv_path.open("w", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)

    cache_rows = [row for row in rows if row["mode"] == "cache_bust"]
    cold_rows = [row for row in rows if row["mode"] == "cold_cr"]
    slow_cache = sorted(cache_rows, key=lambda row: number(row["total_s"]), reverse=True)[:12]
    slow_cold = sorted(cold_rows, key=lambda row: number(row["total_s"]), reverse=True)

    lines = [
        "# CSM-219 XHProf profiling summary",
        "",
        "- `cache_bust`: framework caches warm, unique query parameter per request.",
        "- `cold_cr`: `drush cache:rebuild` before each priority request.",
        "- XHProf adds overhead; use these results for call attribution, not production timing.",
        "",
        "## Slowest cache-busted pages",
        "",
        "| Total s | XHProf ms | HTTP | Dynamic | Label | Path | Run |",
        "|---:|---:|---:|---|---|---|---|",
    ]
    for row in slow_cache:
        lines.append(
            f"| {row['total_s']} | {row['xhprof_total_ms']} | {row['http_code']} | "
            f"{row['x_drupal_dynamic_cache'] or '-'} | {row['label']} | "
            f"`{row['path']}` | [{row['xhprof_run']}]({row['xhprof_url']}) |"
        )
    lines += [
        "",
        "## Full-cold priority pages",
        "",
        "| Total s | XHProf ms | HTTP | Dynamic | Label | Path | Run |",
        "|---:|---:|---:|---|---|---|---|",
    ]
    for row in slow_cold:
        lines.append(
            f"| {row['total_s']} | {row['xhprof_total_ms']} | {row['http_code']} | "
            f"{row['x_drupal_dynamic_cache'] or '-'} | {row['label']} | "
            f"`{row['path']}` | [{row['xhprof_run']}]({row['xhprof_url']}) |"
        )
    lines += ["", "## Top Exclusive Hotspots", ""]
    for row in slow_cache[:8] + slow_cold:
        lines.append(f"### {row['mode']} - {row['label']} (`{row['path']}`)")
        lines.append(f"Run: [{row['xhprof_run']}]({row['xhprof_url']})")
        for part in (row["top_exclusive"] or "").split("; "):
            if part:
                lines.append(f"- {part}")
        lines.append("")
    lines.append(f"Raw CSV: `{csv_path.name}`")
    md_path.write_text("\n".join(lines) + "\n")


def number(value: str) -> float:
    try:
        return float(value)
    except ValueError:
        return -1.0


def fingerprint(query: str) -> str:
    query = query.replace("`", "")
    query = SPACE_RE.sub(" ", query).strip()
    query = STRING_RE.sub("'?'", query)
    query = HEX_RE.sub("0x?", query)
    query = NUM_RE.sub("?", query)
    query = IN_RE.sub("IN (?)", query)
    return query[:520]


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
                "lock_s": float(match.group(2)),
                "rows_sent": int(match.group(3)),
                "rows_examined": int(match.group(4)),
            }
            continue
        if current is None or line.startswith("#") or line.startswith("SET timestamp="):
            continue
        sql_lines.append(line)
    finish()
    return entries


def run_sql(cwd: Path, page_set: str, timeout: int) -> tuple[Path, Path]:
    stamp = dt.datetime.now().strftime("%Y-%m-%d-%H%M%S")
    csv_path = cwd / f"csm-219-sql-profile-results-{stamp}.csv"
    md_path = cwd / f"csm-219-sql-profile-summary-{stamp}.md"
    rows: list[dict[str, str]] = []
    page_summaries: list[dict[str, str]] = []

    ddev(cwd, "xhprof", "off", check=False)
    db_exec(cwd, "SET GLOBAL slow_query_log=OFF;")
    db_exec(
        cwd,
        f"SET GLOBAL log_output='FILE'; "
        f"SET GLOBAL slow_query_log_file='{SLOW_LOG_FILE}'; "
        "SET GLOBAL long_query_time=0.000000;",
    )
    try:
        for label, path in pages_for_set(page_set):
            row_summary, grouped_rows = profile_sql_page(cwd, label, path, timeout)
            page_summaries.append(row_summary)
            rows.extend(grouped_rows)
    finally:
        db_exec(
            cwd,
            "SET GLOBAL slow_query_log=OFF; "
            "SET GLOBAL long_query_time=10.000000; "
            "SET GLOBAL slow_query_log=ON;",
            check=False,
        )
        ddev(cwd, "xhprof", "off", check=False)

    write_sql_outputs(csv_path, md_path, page_summaries, rows)
    return csv_path, md_path


def profile_sql_page(
    cwd: Path,
    label: str,
    path: str,
    timeout: int,
) -> tuple[dict[str, str], list[dict[str, str]]]:
    db_exec(cwd, "SET GLOBAL slow_query_log=OFF;")
    db_shell(cwd, f": > {SLOW_LOG_FILE}")
    db_exec(cwd, "SET GLOBAL long_query_time=0.000000; SET GLOBAL slow_query_log=ON;")
    url = profile_url(path, "sql", label)
    metrics, _ = curl_page(cwd, url, timeout)
    db_exec(cwd, "SET GLOBAL slow_query_log=OFF;")
    log_text = db_shell(cwd, f"cat {SLOW_LOG_FILE}", check=False)
    entries = [
        item
        for item in parse_slow_log(log_text)
        if item.get("sql") and "mysql.slow_log" not in item.get("sql", "")
    ]

    groups: dict[str, dict] = {}
    examples: dict[str, str] = {}
    for entry in entries:
        key = fingerprint(entry["sql"])
        groups.setdefault(
            key,
            {"count": 0, "total_s": 0.0, "max_s": 0.0, "rows_examined": 0, "rows_sent": 0},
        )
        examples.setdefault(key, entry["sql"])
        groups[key]["count"] += 1
        groups[key]["total_s"] += entry["query_s"]
        groups[key]["max_s"] = max(groups[key]["max_s"], entry["query_s"])
        groups[key]["rows_examined"] += entry["rows_examined"]
        groups[key]["rows_sent"] += entry["rows_sent"]

    total_q = sum(item["query_s"] for item in entries)
    max_q = max([item["query_s"] for item in entries] or [0])
    total_examined = sum(item["rows_examined"] for item in entries)
    total_sent = sum(item["rows_sent"] for item in entries)
    summary = {
        "label": label,
        "path": path,
        "http_code": metrics.get("http_code", ""),
        "request_total_s": metrics.get("time_total", ""),
        "request_ttfb_s": metrics.get("time_starttransfer", ""),
        "query_count": str(len(entries)),
        "query_total_s": f"{total_q:.6f}",
        "max_query_s": f"{max_q:.6f}",
        "request_rows_examined": str(total_examined),
        "request_rows_sent": str(total_sent),
        "url": url,
    }
    print(
        f"{metrics.get('http_code', '?')} total={metrics.get('time_total', '?')}s "
        f"queries={len(entries)} qtime={total_q:.6f}s rows={total_examined} {label}",
        flush=True,
    )

    grouped_rows = []
    top_groups = sorted(
        groups.items(), key=lambda item: (item[1]["total_s"], item[1]["count"]), reverse=True
    )[:10]
    for index, (key, group) in enumerate(top_groups, 1):
        row = dict(summary)
        row.update(
            {
                "rank": str(index),
                "group_count": str(group["count"]),
                "group_total_s": f"{group['total_s']:.6f}",
                "group_max_s": f"{group['max_s']:.6f}",
                "group_rows_examined": str(group["rows_examined"]),
                "group_rows_sent": str(group["rows_sent"]),
                "normalized_sql": key,
                "example_sql": examples[key][:2000],
            }
        )
        grouped_rows.append(row)
    return summary, grouped_rows


def safe_cell(value: str, limit: int = 260) -> str:
    value = value.replace("`", "").replace("\n", " ").replace("\r", " ").replace("|", "\\|")
    value = SPACE_RE.sub(" ", value).strip()
    return value if len(value) <= limit else value[: limit - 3] + "..."


def write_sql_outputs(
    csv_path: Path,
    md_path: Path,
    page_summaries: list[dict[str, str]],
    rows: list[dict[str, str]],
) -> None:
    fields = [
        "label",
        "path",
        "http_code",
        "request_total_s",
        "request_ttfb_s",
        "query_count",
        "query_total_s",
        "max_query_s",
        "request_rows_examined",
        "request_rows_sent",
        "rank",
        "group_count",
        "group_total_s",
        "group_max_s",
        "group_rows_examined",
        "group_rows_sent",
        "normalized_sql",
        "example_sql",
        "url",
    ]
    with csv_path.open("w", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)

    summary_rows = sorted(
        page_summaries, key=lambda row: number(row["query_total_s"]), reverse=True
    )
    grouped_by_page: dict[tuple[str, str], list[dict[str, str]]] = {}
    for row in rows:
        grouped_by_page.setdefault((row["label"], row["path"]), []).append(row)

    lines = [
        "# CSM-219 SQL profiling summary",
        "",
        "MariaDB file slow log was temporarily set to `long_query_time=0`.",
        "XHProf was disabled during SQL capture.",
        "",
        "## Request Query Totals",
        "",
        "| SQL total s | Queries | Rows examined | HTTP total s | HTTP | Label | Path |",
        "|---:|---:|---:|---:|---:|---|---|",
    ]
    for row in summary_rows:
        lines.append(
            f"| {row['query_total_s']} | {row['query_count']} | "
            f"{row['request_rows_examined']} | {row['request_total_s']} | "
            f"{row['http_code']} | {row['label']} | `{row['path']}` |"
        )
    lines += ["", "## Top Query Families Per Page"]
    for (label, path), values in grouped_by_page.items():
        first = values[0]
        lines += [
            "",
            f"### {label} (`{path}`)",
            (
                f"Request total `{first['request_total_s']}s`; "
                f"SQL total `{first['query_total_s']}s`; "
                f"queries `{first['query_count']}`; "
                f"rows examined `{first['request_rows_examined']}`."
            ),
            "",
            "| SQL total s | Count | Rows examined | Query family |",
            "|---:|---:|---:|---|",
        ]
        for row in values[:6]:
            lines.append(
                f"| {row['group_total_s']} | {row['group_count']} | "
                f"{row['group_rows_examined']} | `{safe_cell(row['normalized_sql'])}` |"
            )
    lines.append("")
    lines.append(f"Raw CSV: `{csv_path.name}`")
    md_path.write_text("\n".join(lines) + "\n")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--mode",
        choices=["xhprof", "sql", "both"],
        default="both",
        help="Profiling pass to run.",
    )
    parser.add_argument(
        "--page-set",
        choices=["priority", "full"],
        default="full",
        help="Use the priority subset or the full representative page set.",
    )
    parser.add_argument("--timeout", type=int, default=60, help="Curl timeout per request.")
    parser.add_argument(
        "--skip-start",
        action="store_true",
        help="Do not run `ddev start` before profiling.",
    )
    args = parser.parse_args()

    cwd = Path.cwd()
    if not (cwd / "config" / "sync").is_dir():
        print("Run this script from docroot/sites/carlsonschool.umn.edu.", file=sys.stderr)
        return 1

    if not args.skip_start:
        ddev(cwd, "start")

    drush(cwd, "status", "--fields=bootstrap,db-status,uri", "--format=list")
    outputs: list[Path] = []
    if args.mode in ("xhprof", "both"):
        outputs.extend(run_xhprof(cwd, args.page_set, args.timeout))
    if args.mode in ("sql", "both"):
        outputs.extend(run_sql(cwd, args.page_set, args.timeout))

    print("\nGenerated:")
    for output in outputs:
        print(f"- {output}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
