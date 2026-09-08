#!/usr/bin/env bash
# Smoke test key Local Roots routes and API endpoints.
# Usage: BASE_URL=http://localhost:8080 ./scripts/smoke-test.sh

set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8080}"
BASE_URL="${BASE_URL%/}"
PASS=0
FAIL=0

check() {
  local method="$1"
  local path="$2"
  local expected="$3"
  local url="${BASE_URL}${path}"
  local code

  if [ "$method" = "GET" ]; then
    code=$(curl -s -o /dev/null -w "%{http_code}" "$url")
  else
    code=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$url")
  fi

  if [ "$code" = "$expected" ]; then
    printf "OK   %s %s -> %s\n" "$method" "$path" "$code"
    PASS=$((PASS + 1))
  else
    printf "FAIL %s %s -> %s (expected %s)\n" "$method" "$path" "$code" "$expected"
    FAIL=$((FAIL + 1))
  fi
}

echo "Smoke testing ${BASE_URL}"
echo "--- Pages ---"
check GET "/" "200"
check GET "/shop" "200"
check GET "/cart" "200"
check GET "/checkout" "200"
check GET "/checkout/failed" "200"
check GET "/checkout/canceled" "200"
check GET "/account" "200"
check GET "/wishlist" "200"
check GET "/order-status" "200"
check GET "/contact" "200"
check GET "/brands" "200"
check GET "/llms.txt" "200"

echo "--- Checkout API routes (POST without CSRF expects 400, not 404) ---"
check POST "/localroots/checkout/apply-coupon" "400"
check POST "/localroots/checkout/save-cart-meta" "400"
check POST "/localroots/checkout/calculate-shipping" "400"

echo "--- Newsletter ---"
check POST "/localroots/newsletter/subscribe" "400"
check POST "/newsletter/subscribe" "400"

echo "--- Product sample ---"
check GET "/products/bag-in-black-leather" "200"

echo "--- Summary ---"
echo "Passed: ${PASS}"
echo "Failed: ${FAIL}"

if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
