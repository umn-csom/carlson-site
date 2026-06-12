#!/usr/bin/env python3
"""CSM-382: Crawl anonymous pages and classify real edge cacheability.

Primary signal (the ticket's actual question): the **Cache-Control** and
**CDN-Cache-Control** response headers, which decide whether Cloudflare/Varnish
edge-cache the page. The x-drupal-* debug headers are captured too, but they are
secondary/misleading (a webform page reports max-age:0 yet is fully edge-cached
because the platform overrides it). Each URL is bucketed into a `cache_tier`:
full / short / redirect / error / bypass (see classify_tier).

Headers are emitted at the origin by two RESPONSE subscribers, so DDEV is
representative of DEV/PROD policy:
  - http_cache_control (contrib): s-maxage/stale-* floor, only when Symfony
    considers the response cacheable.
  - carlson_general CloudflareCacheControlResponseEventSubscriber: CDN-Cache-Control
    (no-store / max-age=900 / 3600 / 300 by status & cacheability).

Stdlib only (urllib) so it runs on the host with no pip install. Crawls
anonymously (no cookies). No cachebust by default: an uncacheable
response is never stored in Internal Page Cache, so `max-age:0` /
`UNCACHEABLE` always shows through, and the debug max-age/contexts
headers are stored in the page-cache entry so they stay accurate on a
HIT.

Examples:
  # Validate on the 8 representative pages:
  ./scripts/cache_audit.py --paths-file scripts/csm-382-sample-paths.txt \
      --out scripts/output/sample

  # Full site crawl from the sitemap:
  ./scripts/cache_audit.py --out scripts/output/sitemap
"""

import argparse
import csv
import json
import os
import re
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from xml.etree import ElementTree

DEFAULT_BASE = "https://carlsonschool.ddev.site"
USER_AGENT = "CSM-382-cache-audit/1.0"

BODY_RE = re.compile(rb"<body[^>]*\sclass=\"([^\"]*)\"")
NODE_ID_RE = re.compile(r"\bpage-node-(\d+)\b")
NODE_TYPE_RE = re.compile(r"\bpage-node-type-([\w-]+)\b")
# Inline webform render markers (a webform actually printed into the page).
WEBFORM_RE = re.compile(
    rb"webform-submission-[a-z0-9_]+-form|"
    rb'data-drupal-selector="edit-[a-z0-9-]*"[^>]*class="[^"]*webform|'
    rb'<form[^>]*class="[^"]*webform-submission'
)


class _PassThroughErrors(urllib.request.HTTPErrorProcessor):
    """Return 3xx/4xx/5xx responses as-is instead of following/raising.

    We classify each URL's OWN response: a redirect is its own (cheap, cacheable)
    response, and following it would mis-attribute the target page's tier and
    hide no-store login redirects. Overriding the error processor also means 4xx
    /5xx come back as normal responses (status readable), no HTTPError raised.
    """

    def http_response(self, request, response):
        return response

    https_response = http_response


def build_opener(insecure, follow_redirects=False):
    ctx = ssl.create_default_context()
    if insecure:
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE
    handlers = [urllib.request.HTTPSHandler(context=ctx)]
    if not follow_redirects:
        handlers.append(_PassThroughErrors())
    opener = urllib.request.build_opener(*handlers)
    opener.addheaders = [("User-Agent", USER_AGENT)]
    return opener


def to_base_host(url, base):
    """Rewrite scheme+host of an absolute URL to the crawl base (keeps path).

    Sitemaps emit the canonical PROD host; we must crawl the local DDEV host.
    """
    if not url.startswith("http"):
        return base + url
    b = urllib.parse.urlsplit(base)
    u = urllib.parse.urlsplit(url)
    return urllib.parse.urlunsplit((b.scheme, b.netloc, u.path, u.query, ""))


def parse_max_age(raw):
    """'0 (Uncacheable)' -> 0, '-1 (permanent)' -> -1, '300' -> 300."""
    if raw is None:
        return None
    m = re.search(r"-?\d+", raw)
    return int(m.group(0)) if m else None


def directive_seconds(cache_control, name):
    """Return int seconds for a Cache-Control directive, or None if absent.

    e.g. directive_seconds('max-age=300, public, s-maxage=2628000', 's-maxage')
    -> 2628000.
    """
    if not cache_control:
        return None
    m = re.search(rf"(?:^|[,\s]){re.escape(name)}=(\d+)", cache_control, re.I)
    return int(m.group(1)) if m else None


def classify_tier(status, cache_control, cdn):
    """Classify the real edge-cache behavior from Cache-Control / CDN-Cache-Control.

    This is the ground truth the ticket cares about (NOT the x-drupal-* debug
    headers). The carlson CloudflareCacheControlResponseEventSubscriber writes
    CDN-Cache-Control; http_cache_control writes s-maxage only when the response
    is Symfony-cacheable. Tiers:

      bypass   - CDN-Cache-Control: no-store  => NEVER edge-cached (origin every hit)
      short    - 200 but s-maxage dropped (poor cacheability) => ~CDN max-age only,
                 re-renders at origin every few minutes instead of being held long
      redirect - 3xx (cached 300/3600s)
      error    - 4xx/5xx that are still briefly cached (403/404/405/429)
      full     - normal long-lived edge cache (s-maxage floor + CDN max-age>=900)
    """
    cc_l = (cache_control or "").lower()
    cdn_l = (cdn or "").lower()
    s_maxage = directive_seconds(cache_control, "s-maxage")

    # Real bypass: explicit no-store at the edge, or (no CDN header) a
    # private/no-cache/no-store Cache-Control.
    if "no-store" in cdn_l:
        return "bypass"
    if not cdn_l and ("no-store" in cc_l or "private" in cc_l or "no-cache" in cc_l):
        return "bypass"

    if 300 <= status < 400:
        return "redirect"
    if status >= 400:
        return "error"

    # 200-range: distinguish full vs short by the surviving s-maxage floor.
    if s_maxage and s_maxage > 3600:
        return "full"
    return "short"


def get_sitemap_urls(opener, sitemap_url, timeout, seen=None):
    """Return all <loc> URLs, recursing into nested sitemap indexes."""
    if seen is None:
        seen = set()
    if sitemap_url in seen:
        return []
    seen.add(sitemap_url)
    urls = []
    try:
        with opener.open(sitemap_url, timeout=timeout) as resp:
            data = resp.read()
    except Exception as exc:  # noqa: BLE001
        sys.stderr.write(f"sitemap fetch failed {sitemap_url}: {exc}\n")
        return urls
    try:
        root = ElementTree.fromstring(data)
    except ElementTree.ParseError as exc:
        sys.stderr.write(f"sitemap parse failed {sitemap_url}: {exc}\n")
        return urls
    ns = "{http://www.sitemaps.org/schemas/sitemap/0.9}"
    is_index = root.tag.endswith("sitemapindex")
    for loc in root.iter(f"{ns}loc"):
        value = (loc.text or "").strip()
        if not value:
            continue
        if is_index:
            urls.extend(get_sitemap_urls(opener, value, timeout, seen))
        else:
            urls.append(value)
    return urls


def probe(opener, url, timeout, cachebust, retries=1):
    target = url
    if cachebust:
        sep = "&" if "?" in url else "?"
        target = f"{url}{sep}cb={os.urandom(4).hex()}"
    row = {
        "url": url,
        "http_status": None,
        "drupal_cache": None,
        "dynamic_cache": None,
        "max_age_raw": None,
        "max_age": None,
        "uncacheable": None,
        "cache_control": None,
        "cdn_cache_control": None,
        "cdn_no_store": None,
        "s_maxage": None,
        "cache_tier": None,
        "bare_user_context": None,
        "contexts": None,
        "node_id": None,
        "node_type": None,
        "tag_count": None,
        "has_webform": None,
        "error": None,
    }
    attempt = 0
    while True:
        try:
            req = urllib.request.Request(target)
            with opener.open(req, timeout=timeout) as resp:
                status = resp.status
                headers = resp.headers
                body = resp.read()
            break
        except urllib.error.HTTPError as exc:
            status = exc.code
            headers = exc.headers
            body = b""
            break
        except Exception as exc:  # noqa: BLE001
            # Cold renders on heavy pages can exceed the timeout; retry once.
            if attempt < retries:
                attempt += 1
                continue
            row["error"] = str(exc)
            return row

    row["http_status"] = status
    row["drupal_cache"] = headers.get("X-Drupal-Cache")
    row["dynamic_cache"] = headers.get("X-Drupal-Dynamic-Cache")
    raw_age = headers.get("X-Drupal-Cache-Max-Age")
    row["max_age_raw"] = raw_age
    row["max_age"] = parse_max_age(raw_age)
    contexts = headers.get("X-Drupal-Cache-Contexts")
    row["contexts"] = contexts
    if contexts is not None:
        row["bare_user_context"] = "user" in contexts.split()
    tags = headers.get("X-Drupal-Cache-Tags")
    row["tag_count"] = len(tags.split()) if tags else 0

    dynamic = (row["dynamic_cache"] or "").upper()
    row["uncacheable"] = ("UNCACHEABLE" in dynamic) or (row["max_age"] == 0)

    # CDN ground truth: what the CloudflareCacheControlResponseEventSubscriber
    # actually wrote for the edge. The internal X-Drupal-Cache-Max-Age debug
    # header is overridden by http_cache_control (s-maxage) and does NOT reflect
    # CDN behavior. The edge bypasses cache only when CDN-Cache-Control is
    # no-store (or, absent that header, when Cache-Control is private/no-store/
    # no-cache or the page cache reports UNCACHEABLE).
    cc = headers.get("Cache-Control") or ""
    cdn = (headers.get("Cloudflare-CDN-Cache-Control")
           or headers.get("CDN-Cache-Control") or "")
    row["cache_control"] = cc
    row["cdn_cache_control"] = cdn
    row["s_maxage"] = directive_seconds(cc, "s-maxage")
    cc_l = cc.lower()
    if cdn:
        row["cdn_no_store"] = "no-store" in cdn.lower()
    else:
        row["cdn_no_store"] = (
            (row["drupal_cache"] or "").upper() == "UNCACHEABLE"
            or "no-store" in cc_l
            or "private" in cc_l
            or "no-cache" in cc_l
        )
    row["cache_tier"] = classify_tier(status, cc, cdn)

    m = BODY_RE.search(body)
    if m:
        classes = m.group(1).decode("utf-8", "replace")
        nid = NODE_ID_RE.search(classes)
        ntype = NODE_TYPE_RE.search(classes)
        row["node_id"] = nid.group(1) if nid else None
        row["node_type"] = ntype.group(1) if ntype else None
    row["has_webform"] = bool(WEBFORM_RE.search(body))
    return row


def load_paths(args, base):
    if args.paths:
        raw = args.paths
    elif args.paths_file:
        with open(args.paths_file, encoding="utf-8") as fh:
            raw = [line.strip() for line in fh]
    else:
        return None
    urls = []
    for item in raw:
        if not item or item.startswith("#"):
            continue
        urls.append(to_base_host(item, base))
    return urls


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--base-url", default=DEFAULT_BASE)
    ap.add_argument("--sitemap", help="Sitemap URL (default {base}/sitemap.xml)")
    ap.add_argument("--paths", nargs="*", help="Explicit paths/URLs to crawl")
    ap.add_argument("--paths-file", help="File with one path/URL per line")
    ap.add_argument("--out", default="scripts/output/cache-audit",
                    help="Output prefix; writes <out>.json and <out>.csv")
    ap.add_argument("--workers", type=int, default=6)
    ap.add_argument("--timeout", type=int, default=150)
    ap.add_argument("--retries", type=int, default=1)
    ap.add_argument("--limit", type=int, default=0, help="Cap number of URLs")
    ap.add_argument("--cachebust", action="store_true",
                    help="Force a fresh render per request (slow; usually unneeded)")
    ap.add_argument("--secure", action="store_true",
                    help="Verify TLS (off by default for the DDEV self-signed cert)")
    ap.add_argument("--follow-redirects", action="store_true",
                    help="Follow 3xx (default: classify each URL's own response)")
    args = ap.parse_args()

    base = args.base_url.rstrip("/")
    opener = build_opener(insecure=not args.secure,
                          follow_redirects=args.follow_redirects)

    urls = load_paths(args, base)
    if urls is None:
        sitemap = args.sitemap or f"{base}/sitemap.xml"
        sys.stderr.write(f"Fetching sitemap: {sitemap}\n")
        urls = [to_base_host(u, base)
                for u in get_sitemap_urls(opener, sitemap, args.timeout)]
    # De-dupe, keep order.
    seen, ordered = set(), []
    for u in urls:
        if u not in seen:
            seen.add(u)
            ordered.append(u)
    urls = ordered
    if args.limit:
        urls = urls[: args.limit]
    total = len(urls)
    sys.stderr.write(f"Crawling {total} URLs with {args.workers} workers...\n")

    rows = []
    done = 0
    with ThreadPoolExecutor(max_workers=args.workers) as pool:
        futures = {
            pool.submit(probe, opener, u, args.timeout, args.cachebust,
                        args.retries): u
            for u in urls
        }
        for fut in as_completed(futures):
            rows.append(fut.result())
            done += 1
            if done % 10 == 0 or done == total:
                sys.stderr.write(f"  {done}/{total}\n")

    rows.sort(key=lambda r: r["url"])
    os.makedirs(os.path.dirname(args.out) or ".", exist_ok=True)
    with open(f"{args.out}.json", "w", encoding="utf-8") as fh:
        json.dump(rows, fh, indent=2)
    fields = list(rows[0].keys()) if rows else []
    with open(f"{args.out}.csv", "w", encoding="utf-8", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=fields)
        writer.writeheader()
        writer.writerows(rows)

    def first_segment(url):
        path = urllib.parse.urlsplit(url).path or "/"
        seg = path.strip("/").split("/", 1)[0]
        return "/" + seg if seg else "/"

    def group(rows_subset, keyfn, label):
        buckets = {}
        for r in rows_subset:
            k = keyfn(r)
            buckets[k] = buckets.get(k, 0) + 1
        if not buckets:
            return
        sys.stderr.write(f"\n  by {label}:\n")
        for k, c in sorted(buckets.items(), key=lambda x: -x[1])[:25]:
            sys.stderr.write(f"    {c:5d}  {k}\n")

    errors = [r for r in rows if r["error"]]
    # Real edge-cache behavior (the ticket's actual question), keyed on
    # Cache-Control / CDN-Cache-Control — NOT the x-drupal-* debug headers.
    tiers = {}
    for r in rows:
        tiers.setdefault(r["cache_tier"], []).append(r)

    sys.stderr.write("\n=== Edge cacheability tiers (from Cache-Control) ===\n")
    sys.stderr.write(f"Total crawled: {total}   Errors: {len(errors)}\n\n")
    order = ["full", "short", "redirect", "error", "bypass", None]
    blurb = {
        "full": "long-lived edge cache (s-maxage floor + CDN max-age>=900)",
        "short": "POOR cacheability: s-maxage dropped, re-renders at origin often",
        "redirect": "3xx redirects (cached 300/3600s)",
        "error": "4xx/5xx briefly cached",
        "bypass": "NOT edge-cached: CDN no-store (origin render every hit)",
        None: "unclassified",
    }
    for tier in order:
        subset = tiers.get(tier)
        if not subset:
            continue
        sys.stderr.write(f"  {len(subset):5d}  {tier or '(none)'}  - {blurb.get(tier)}\n")

    # Detail the two tiers that cost origin renders: 'bypass' and 'short'.
    for tier in ("bypass", "short"):
        subset = tiers.get(tier) or []
        if not subset:
            continue
        sys.stderr.write(f"\n--- tier '{tier}' ({len(subset)} URLs) ---")
        group(subset, lambda r: r["node_type"] or "(non-node/other)", "node_type")
        group(subset, lambda r: first_segment(r["url"]), "path prefix")
        group(subset, lambda r: str(r["http_status"]), "http status")
        sys.stderr.write("\n  sample URLs:\n")
        for r in subset[:15]:
            sys.stderr.write(
                f"    [{r['http_status']}] cdn={r['cdn_cache_control'] or '-'}"
                f"  s-maxage={r['s_maxage']}  {r['url']}\n")

    sys.stderr.write(f"\nWrote {args.out}.json and {args.out}.csv\n")


if __name__ == "__main__":
    main()
