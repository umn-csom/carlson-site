#!/usr/bin/env python3
"""Smoke-test deferred Webform coverage from the CSM-266 inventory CSV.

This script checks the published exact URLs in ``csm-266-webform-page-inventory.csv``
for the two supported deferred patterns:

* ``node_webform_field``
* ``paragraph_webform``

For each page it performs two real anonymous GET requests and verifies:

* the initial HTML contains a deferred placeholder
* the initial HTML does not contain ``form_build_id``
* the expected deferred route marker is present
* the second GET reaches Drupal page cache (``x-drupal-cache: HIT``)

This is a smoke test, not a formal proof. It is useful for quickly flagging
inventory drift and obvious regressions across the current page set.
"""

from __future__ import annotations

import argparse
import csv
import json
import ssl
import sys
import urllib.error
import urllib.request
from collections import Counter
from pathlib import Path
from typing import Any
from urllib.parse import urljoin

NODE_PATTERN = "node_webform_field"
PARAGRAPH_PATTERN = "paragraph_webform"


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Smoke-test deferred Webform page coverage from inventory.",
    )
    parser.add_argument(
        "--root",
        type=Path,
        default=Path.cwd(),
        help="Repo root containing csm-266-webform-page-inventory.csv.",
    )
    parser.add_argument(
        "--base-url",
        default="https://carlsonschool.ddev.site",
        help="Base site URL to test against.",
    )
    parser.add_argument(
        "--csv",
        type=Path,
        default=None,
        help=(
            "Optional path to the inventory CSV. Defaults to "
            "<root>/csm-266-webform-page-inventory.csv."
        ),
    )
    parser.add_argument(
        "--timeout",
        type=int,
        default=20,
        help="Request timeout in seconds.",
    )
    parser.add_argument(
        "--fail-limit",
        type=int,
        default=40,
        help="Maximum number of failing rows to print.",
    )
    parser.add_argument(
        "--json",
        action="store_true",
        help="Emit full machine-readable JSON instead of a text summary.",
    )
    return parser.parse_args()


def load_rows(csv_path: Path) -> list[dict[str, str]]:
    with csv_path.open(newline="", encoding="utf-8") as handle:
        return list(csv.DictReader(handle))


def expected_route_marker(pattern: str) -> str:
    if pattern == NODE_PATTERN:
        return "/carlson-general/deferred-webform/"
    if pattern == PARAGRAPH_PATTERN:
        return "/carlson-general/deferred-paragraph-webform/"
    raise ValueError(f"Unsupported pattern: {pattern}")


def fetch(
    url: str,
    timeout: int,
    context: ssl.SSLContext,
) -> tuple[int | str, str, str, dict[str, str], str]:
    request = urllib.request.Request(
        url,
        headers={"User-Agent": "CSM-266 smoke test"},
    )
    try:
        response = urllib.request.urlopen(
            request,
            context=context,
            timeout=timeout,
        )
        body = response.read().decode("utf-8", "ignore")
        return (
            getattr(response, "status", response.getcode()),
            response.geturl(),
            body,
            {k.lower(): v for k, v in response.headers.items()},
            "",
        )
    except urllib.error.HTTPError as exc:
        return (
            exc.code,
            exc.geturl(),
            exc.read().decode("utf-8", "ignore"),
            {k.lower(): v for k, v in exc.headers.items()},
            "",
        )
    except Exception as exc:  # pragma: no cover - defensive shell utility.
        return ("EXC", "", "", {}, str(exc))


def check_row(
    row: dict[str, str],
    base_url: str,
    timeout: int,
    context: ssl.SSLContext,
) -> dict[str, Any]:
    path = row["relative_url_or_path_pattern"]
    url = urljoin(base_url, path)
    route_marker = expected_route_marker(row["pattern"])

    result: dict[str, Any] = {
        "pattern": row["pattern"],
        "path": path,
        "bundle": row["bundle"],
        "nid": row["nid"],
        "webform_id": row["webform_id"],
    }

    status1, final1, body1, _headers1, error1 = fetch(url, timeout, context)
    result["status1"] = status1
    if error1:
        result["error"] = error1
        result["pass"] = False
        return result

    status2, final2, _body2, headers2, error2 = fetch(url, timeout, context)
    result["status2"] = status2
    if error2:
        result["error2"] = error2

    result.update(
        {
            "redirected": final1 != url or final2 != url,
            "final1": final1,
            "placeholder": "data-deferred-webform-url" in body1,
            "inline_form_build_id": "form_build_id" in body1,
            "route_marker": route_marker in body1,
            "x_drupal_cache_2": headers2.get("x-drupal-cache", ""),
            "x_drupal_dynamic_cache_2": headers2.get(
                "x-drupal-dynamic-cache",
                "",
            ),
            "cache_control_2": headers2.get("cache-control", ""),
        }
    )

    result["pass"] = (
        status1 == 200
        and status2 == 200
        and not result["redirected"]
        and result["placeholder"]
        and not result["inline_form_build_id"]
        and result["route_marker"]
        and result["x_drupal_cache_2"] == "HIT"
    )
    return result


def build_summary(results: list[dict[str, Any]]) -> dict[str, Any]:
    return {
        "total": len(results),
        "pass": sum(1 for result in results if result.get("pass")),
        "fail": sum(1 for result in results if not result.get("pass")),
        "by_pattern": dict(Counter(result["pattern"] for result in results)),
        "pass_by_pattern": dict(
            Counter(
                result["pattern"]
                for result in results
                if result.get("pass")
            )
        ),
        "fail_by_pattern": dict(
            Counter(
                result["pattern"]
                for result in results
                if not result.get("pass")
            )
        ),
    }


def print_text_report(
    results: list[dict[str, Any]],
    fail_limit: int,
) -> None:
    summary = build_summary(results)
    print("TOTAL", summary["total"])
    print("PASS", summary["pass"])
    print("FAIL", summary["fail"])
    print("BY_PATTERN", json.dumps(summary["by_pattern"], sort_keys=True))
    print(
        "PASS_BY_PATTERN",
        json.dumps(summary["pass_by_pattern"], sort_keys=True),
    )
    print(
        "FAIL_BY_PATTERN",
        json.dumps(summary["fail_by_pattern"], sort_keys=True),
    )
    print("SAMPLE_FAILS")
    failures = [result for result in results if not result.get("pass")]
    for result in failures[:fail_limit]:
        print(
            json.dumps(
                {
                    "pattern": result["pattern"],
                    "path": result["path"],
                    "status1": result.get("status1"),
                    "status2": result.get("status2"),
                    "redirected": result.get("redirected"),
                    "placeholder": result.get("placeholder"),
                    "inline_form_build_id": result.get(
                        "inline_form_build_id",
                    ),
                    "route_marker": result.get("route_marker"),
                    "x_drupal_cache_2": result.get("x_drupal_cache_2"),
                    "x_drupal_dynamic_cache_2": result.get(
                        "x_drupal_dynamic_cache_2",
                    ),
                    "final1": result.get("final1"),
                    "webform_id": result.get("webform_id"),
                    "bundle": result.get("bundle"),
                    "nid": result.get("nid"),
                    "error": result.get("error", ""),
                },
                sort_keys=True,
            )
        )


def main() -> int:
    args = parse_args()
    csv_path = args.csv or (args.root / "csm-266-webform-page-inventory.csv")
    rows = load_rows(csv_path)
    published = [
        row
        for row in rows
        if row["status"] == "published"
        and row["is_exact_url"] == "yes"
        and row["pattern"] in (NODE_PATTERN, PARAGRAPH_PATTERN)
    ]

    context = ssl._create_unverified_context()
    results = []
    for index, row in enumerate(published, start=1):
        results.append(check_row(row, args.base_url, args.timeout, context))
        if index % 25 == 0:
            print(
                f"checked {index}/{len(published)}",
                file=sys.stderr,
            )

    if args.json:
        print(
            json.dumps(
                {
                    "summary": build_summary(results),
                    "results": results,
                },
                indent=2,
                sort_keys=True,
            )
        )
    else:
        print_text_report(results, args.fail_limit)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
