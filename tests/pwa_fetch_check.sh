#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${AQUARIUM_PWA_BASE_URL:-}"

if [[ -z "$BASE_URL" ]]; then
    echo "Usage: AQUARIUM_PWA_BASE_URL=https://your-site.example tests/pwa_fetch_check.sh" >&2
    exit 1
fi

BASE_URL="${BASE_URL%/}"

declare -a CHECKS=(
    "manifest.json|application/manifest+json"
    "sw.js|application/javascript"
    "icons/icon-192.png|image/png"
    "icons/icon-512.png|image/png"
    "icons/icon-512-maskable.png|image/png"
)

pass_count=0
fail_count=0

check_asset() {
    local path="$1"
    local expected_type="$2"
    local url="${3:-}"
    local headers content_type http_code

    if [[ -z "$url" ]]; then
        url="${BASE_URL}/${path}"
    fi

    headers="$(curl -sS -D - -o /tmp/pwa_fetch_body --max-time 20 "$url" || true)"
    http_code="$(printf '%s\n' "$headers" | awk 'toupper($1) ~ /^HTTP\// { code=$2 } END { print code+0 }')"
    content_type="$(printf '%s\n' "$headers" | awk 'tolower($1) == "content-type:" { print tolower($2); exit }' | tr -d '\r')"

    if [[ "$http_code" != "200" ]]; then
        echo "FAIL $path -> HTTP $http_code"
        fail_count=$((fail_count + 1))
        return 1
    fi

    if [[ "$content_type" != *"$expected_type"* ]]; then
        echo "FAIL $path -> Content-Type: ${content_type:-unknown} (expected $expected_type)"
        fail_count=$((fail_count + 1))
        return 1
    fi

    if [[ "$expected_type" == "application/manifest+json" ]]; then
        if ! head -c 1 /tmp/pwa_fetch_body | grep -q '{'; then
            echo "FAIL $path -> body is not JSON (expected application/manifest+json)"
            fail_count=$((fail_count + 1))
            return 1
        fi
    fi

    if [[ "$expected_type" == "application/javascript" ]]; then
        if ! head -c 20 /tmp/pwa_fetch_body | grep -qE 'self|const|import'; then
            echo "FAIL $path -> body is not JavaScript (expected service worker script)"
            fail_count=$((fail_count + 1))
            return 1
        fi
    fi

    if [[ "$expected_type" == "image/png" ]]; then
        if ! head -c 4 /tmp/pwa_fetch_body | cmp -s - <(printf '\x89PNG'); then
            echo "FAIL $path -> body is not PNG"
            fail_count=$((fail_count + 1))
            return 1
        fi
    fi

    echo "PASS $path -> $content_type"
    pass_count=$((pass_count + 1))
    return 0
}

check_manifest_urls() {
    if ! python3 - "$BASE_URL" <<'PY'
import json
import subprocess
import sys

base_url = sys.argv[1].rstrip("/")
manifest_path = "/tmp/pwa_fetch_body"

with open(manifest_path, encoding="utf-8") as handle:
    manifest = json.load(handle)

start_url = manifest.get("start_url", "")
scope = manifest.get("scope", "")
expected_start = f"{base_url}/index.php"
expected_scope = f"{base_url}/"

errors = []

if start_url != expected_start:
    errors.append(f"start_url is {start_url!r}, expected {expected_start!r}")

if scope not in (expected_scope, base_url):
    errors.append(f"scope is {scope!r}, expected {expected_scope!r}")

icons = manifest.get("icons") or []
sizes = {tuple(icon.get("sizes", "").split("x")) for icon in icons if icon.get("sizes")}
required = {("192", "192"), ("512", "512")}
if not required.issubset(sizes):
    errors.append(f"missing required icon sizes (have {sorted(sizes)}, need 192x192 and 512x512)")

any_purpose = [icon for icon in icons if "any" in str(icon.get("purpose", "any"))]
if not any_purpose:
    errors.append('missing at least one icon with purpose "any"')

for index, icon in enumerate(icons):
    src = icon.get("src", "")
    if not src:
        errors.append(f"icons[{index}] has empty src")
        continue

    if src.startswith("/"):
        icon_url = f"{base_url}{src}"
    elif src.startswith("http://") or src.startswith("https://"):
        icon_url = src
    else:
        icon_url = f"{base_url}/{src}"

    result = subprocess.run(
        ["curl", "-sS", "-o", "/tmp/pwa_icon_body", "-w", "%{http_code}", icon_url],
        capture_output=True,
        text=True,
        timeout=20,
        check=False,
    )
    http_code = result.stdout.strip()
    if http_code != "200":
        errors.append(f"icons[{index}] {src!r} -> HTTP {http_code or 'error'}")
        continue

    with open("/tmp/pwa_icon_body", "rb") as handle:
        if handle.read(4) != b"\x89PNG":
            errors.append(f"icons[{index}] {src!r} -> body is not PNG")

if errors:
    for error in errors:
        print(f"FAIL manifest.json -> {error}")
    sys.exit(1)

print(f"PASS manifest.json -> start_url, scope, and {len(icons)} icon URL(s) OK")
PY
    then
        fail_count=$((fail_count + 1))
        return
    fi

    pass_count=$((pass_count + 1))
}

echo "Checking PWA assets on ${BASE_URL}"
echo

manifest_checked=false
for check in "${CHECKS[@]}"; do
    IFS='|' read -r path expected_type <<< "$check"
    if check_asset "$path" "$expected_type"; then
        if [[ "$path" == "manifest.json" && "$manifest_checked" == "false" ]]; then
            check_manifest_urls
            manifest_checked=true
        fi
    fi
done

echo
echo "Summary: ${pass_count} passed, ${fail_count} failed"

if [[ "$fail_count" -gt 0 ]]; then
    echo
    echo "PWA assets must return HTTP 200 with the correct Content-Type."
    echo "Check that manifest.json, sw.js, and icons/ are deployed and reachable."
    echo "Manifest start_url, scope, and every icons[].src URL must match the base URL."
    exit 1
fi

echo "All PWA fetch checks passed."
