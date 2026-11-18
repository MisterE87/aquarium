#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

PORT="${AQUARIUM_TEST_PORT:-8765}"
BASE_URL="http://127.0.0.1:${PORT}"
LOGIN_PASSWORD="${AQUARIUM_LOGIN_PASSWORD:-test-password}"
AUTH_HMAC_SECRET="${AQUARIUM_AUTH_HMAC_SECRET:-test-hmac-secret}"
DB_HOST="${AQUARIUM_DB_HOST:-127.0.0.1}"
DB_NAME="${AQUARIUM_DB_NAME:-aquarium_test}"
DB_USER="${AQUARIUM_DB_USER:-root}"
DB_PASS="${AQUARIUM_DB_PASS:-}"

PHP_PID=""
ENV_BACKUP=""
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

cleanup() {
    if [[ -n "$PHP_PID" ]] && kill -0 "$PHP_PID" 2>/dev/null; then
        kill "$PHP_PID" 2>/dev/null || true
        wait "$PHP_PID" 2>/dev/null || true
    fi
    if [[ -n "$ENV_BACKUP" && -f "$ENV_BACKUP" ]]; then
        mv -f "$ENV_BACKUP" "$ROOT_DIR/env.php"
    elif [[ -f "$ROOT_DIR/env.php" && -f "$ROOT_DIR/env.test.php" ]]; then
        if cmp -s "$ROOT_DIR/env.php" "$ROOT_DIR/env.test.php" 2>/dev/null; then
            rm -f "$ROOT_DIR/env.php"
        fi
    fi
}
trap cleanup EXIT

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "ERROR: Required command not found: $1" >&2
        exit 1
    fi
}

get_auth_token() {
    php -r "echo hash_hmac('sha256', getenv('LOGIN_PASSWORD') ?: 'test-password', getenv('AUTH_HMAC_SECRET') ?: 'test-hmac-secret');" \
        LOGIN_PASSWORD="$LOGIN_PASSWORD" \
        AUTH_HMAC_SECRET="$AUTH_HMAC_SECRET"
}

api_get() {
    local endpoint="$1"
    local token="${2:-}"
    local extra_args=()
    if [[ -n "$token" ]]; then
        extra_args+=(-H "Authorization: Bearer ${token}")
        extra_args+=(-H "X-APP-TOKEN: ${token}")
    fi
    curl -sS -w "\n%{http_code}" "${extra_args[@]}" "${BASE_URL}/api.php?endpoint=${endpoint}"
}

api_post() {
    local endpoint="$1"
    local body="$2"
    local token="${3:-}"
    local extra_args=()
    if [[ -n "$token" ]]; then
        extra_args+=(-H "Authorization: Bearer ${token}")
        extra_args+=(-H "X-APP-TOKEN: ${token}")
    fi
    curl -sS -w "\n%{http_code}" -X POST \
        -H "Content-Type: application/json" \
        "${extra_args[@]}" \
        -d "$body" \
        "${BASE_URL}/api.php?endpoint=${endpoint}"
}

split_response() {
    local response="$1"
    HTTP_BODY="$(echo "$response" | sed '$d')"
    HTTP_CODE="$(echo "$response" | tail -n 1)"
}

pass() {
    TESTS_RUN=$((TESTS_RUN + 1))
    TESTS_PASSED=$((TESTS_PASSED + 1))
    echo "  PASS: $1"
}

fail() {
    TESTS_RUN=$((TESTS_RUN + 1))
    TESTS_FAILED=$((TESTS_FAILED + 1))
    echo "  FAIL: $1" >&2
    if [[ -n "${HTTP_CODE:-}" ]]; then
        echo "        HTTP status: ${HTTP_CODE}" >&2
    fi
    if [[ -n "${HTTP_BODY:-}" ]]; then
        echo "        Body: ${HTTP_BODY}" >&2
    fi
}

assert_status() {
    local name="$1"
    local response="$2"
    local expected="$3"
    split_response "$response"
    if [[ "$HTTP_CODE" == "$expected" ]]; then
        pass "$name (HTTP $expected)"
    else
        fail "$name — expected HTTP $expected, got $HTTP_CODE"
    fi
}

assert_json_success() {
    local name="$1"
    local response="$2"
    split_response "$response"
    local success
    success="$(echo "$HTTP_BODY" | jq -r '.success // empty')"
    if [[ "$success" == "true" ]]; then
        pass "$name (.success == true)"
    else
        fail "$name — expected .success == true"
    fi
}

assert_json_field() {
    local name="$1"
    local response="$2"
    local jq_filter="$3"
    local expected="$4"
    split_response "$response"
    local actual
    actual="$(echo "$HTTP_BODY" | jq -r "$jq_filter")"
    if [[ "$actual" == "$expected" ]]; then
        pass "$name ($jq_filter == $expected)"
    else
        fail "$name — expected $jq_filter == '$expected', got '$actual'"
    fi
}

assert_json_array_nonempty() {
    local name="$1"
    local response="$2"
    local jq_filter="$3"
    split_response "$response"
    local length
    length="$(echo "$HTTP_BODY" | jq -r "$jq_filter | length")"
    if [[ "$length" -gt 0 ]]; then
        pass "$name ($length items)"
    else
        fail "$name — expected non-empty array at $jq_filter"
    fi
}

assert_body_contains() {
    local name="$1"
    local response="$2"
    local substring="$3"
    split_response "$response"
    if echo "$HTTP_BODY" | grep -qF "$substring"; then
        pass "$name (contains '$substring')"
    else
        fail "$name — body does not contain '$substring'"
    fi
}

wait_for_server() {
    local attempts=0
    while [[ $attempts -lt 30 ]]; do
        local code
        code="$(curl -sS -o /dev/null -w "%{http_code}" "${BASE_URL}/api.php?endpoint=activities" 2>/dev/null || echo "000")"
        if [[ "$code" == "401" || "$code" == "200" ]]; then
            return 0
        fi
        sleep 0.2
        attempts=$((attempts + 1))
    done
    echo "ERROR: PHP server did not start on ${BASE_URL} (last status: ${code:-unknown})" >&2
    exit 1
}

setup_database() {
    echo "Setting up test database '${DB_NAME}'..."
    local mysql_cmd=(mysql -h "$DB_HOST" -u "$DB_USER")
    if [[ -n "$DB_PASS" ]]; then
        mysql_cmd+=(-p"$DB_PASS")
    fi
    "${mysql_cmd[@]}" -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    sed -e '/^CREATE DATABASE IF NOT EXISTS aquarium/d' \
        -e "s/^USE aquarium;/USE \`${DB_NAME}\`;/" \
        "$ROOT_DIR/aquarium_schema.sql" | "${mysql_cmd[@]}" "$DB_NAME"
}

setup_env() {
    if [[ -f "$ROOT_DIR/env.php" ]]; then
        ENV_BACKUP="$(mktemp)"
        cp "$ROOT_DIR/env.php" "$ENV_BACKUP"
    fi
    cp "$ROOT_DIR/env.test.php" "$ROOT_DIR/env.php"
}

start_server() {
    echo "Starting PHP server on port ${PORT}..."
    if curl -sS -o /dev/null "${BASE_URL}/api.php" 2>/dev/null; then
        echo "ERROR: Port ${PORT} is already in use" >&2
        exit 1
    fi
    (cd "$ROOT_DIR" && php -S "127.0.0.1:${PORT}") >/dev/null 2>&1 &
    PHP_PID=$!
    wait_for_server
}

run_auth_tests() {
    echo ""
    echo "Auth tests"
    echo "----------"

    assert_status "No token returns 401" \
        "$(api_get 'activities')" "401"

    assert_json_field "No token error message" \
        "$(api_get 'activities')" ".error" "Unauthorized"

    assert_status "Invalid token returns 401" \
        "$(api_get 'activities' 'invalid-token')" "401"

    assert_status "Valid Bearer token returns 200" \
        "$(api_get 'activities' "$AUTH_TOKEN")" "200"
}

run_get_tests() {
    echo ""
    echo "GET endpoint tests"
    echo "------------------"

    local response

    response="$(api_get 'activities' "$AUTH_TOKEN")"
    assert_json_success "GET activities" "$response"
    assert_json_array_nonempty "GET activities data" "$response" ".data"
    assert_json_field "GET activities has code field" "$response" ".data[0].code | type" "string"

    response="$(api_get 'latest_maintenance' "$AUTH_TOKEN")"
    assert_json_success "GET latest_maintenance" "$response"
    assert_json_array_nonempty "GET latest_maintenance data" "$response" ".data"

    response="$(api_get 'inventory' "$AUTH_TOKEN")"
    assert_json_success "GET inventory" "$response"
    assert_json_array_nonempty "GET inventory data" "$response" ".data"
    assert_json_field "GET inventory stock_status" "$response" ".data[0].stock_status | type" "string"

    response="$(api_get 'maintenance_history' "$AUTH_TOKEN")"
    assert_json_success "GET maintenance_history" "$response"

    response="$(api_get 'maintenance_history&activity_id=1' "$AUTH_TOKEN")"
    assert_json_success "GET maintenance_history filtered" "$response"

    response="$(api_get 'upcoming_maintenance&days=365' "$AUTH_TOKEN")"
    assert_json_success "GET upcoming_maintenance" "$response"

    assert_status "Unknown endpoint returns 404" \
        "$(api_get 'nonexistent' "$AUTH_TOKEN")" "404"
}

run_post_validation_tests() {
    echo ""
    echo "POST validation tests"
    echo "---------------------"

    assert_status "record_maintenance missing fields returns 400" \
        "$(api_post 'record_maintenance' '{}' "$AUTH_TOKEN")" "400"

    assert_status "update_inventory missing fields returns 400" \
        "$(api_post 'update_inventory' '{}' "$AUTH_TOKEN")" "400"
}

run_post_write_tests() {
    echo ""
    echo "POST write tests"
    echo "----------------"

    local response
    local stock_before stock_after

    response="$(api_post 'record_maintenance' '{"code":"J","date":"2026-01-01","notes":"API test"}' "$AUTH_TOKEN")"
    assert_json_success "record_maintenance for code J" "$response"

    response="$(api_get 'maintenance_history&activity_id=9&limit=5' "$AUTH_TOKEN")"
    assert_json_success "maintenance_history after record" "$response"
    assert_body_contains "maintenance_history contains test note" "$response" "API test"

    stock_before="$(api_get 'inventory' "$AUTH_TOKEN" | sed '$d' | jq -r '.data[] | select(.activity_codes | contains("A")) | .current_stock' | head -n 1)"

    response="$(api_post 'update_inventory' '{"code":"A","quantity":2,"type":"purchase","notes":"API test purchase"}' "$AUTH_TOKEN")"
    assert_json_success "update_inventory add stock" "$response"

    stock_after="$(api_get 'inventory' "$AUTH_TOKEN" | sed '$d' | jq -r '.data[] | select(.activity_codes | contains("A")) | .current_stock' | head -n 1)"

    if [[ "$((stock_before + 2))" -eq "$stock_after" ]]; then
        pass "inventory stock increased by 2 ($stock_before -> $stock_after)"
    else
        fail "inventory stock — expected $((stock_before + 2)), got $stock_after"
    fi
}

main() {
    require_command curl
    require_command jq
    require_command php
    require_command mysql

    echo "Aquarium API integration tests"
    echo "=============================="

    setup_env
    setup_database
    start_server

    AUTH_TOKEN="$(get_auth_token)"

    run_auth_tests
    run_get_tests
    run_post_validation_tests
    run_post_write_tests

    echo ""
    echo "=============================="
    echo "Results: ${TESTS_PASSED}/${TESTS_RUN} passed"

    if [[ "$TESTS_FAILED" -gt 0 ]]; then
        echo "${TESTS_FAILED} test(s) failed." >&2
        exit 1
    fi

    echo "All tests passed."
}

main "$@"
