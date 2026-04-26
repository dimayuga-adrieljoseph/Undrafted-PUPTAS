#!/usr/bin/env bash
# =============================================================================
# tests/load/concurrent_student_numbers.sh
#
# Load test: fires 50 simultaneous POST requests to the assign-student-number
# debug route and verifies that all returned student numbers are unique.
#
# Prerequisites:
#   - curl  (available on all OS)
#   - jq    (install: brew install jq  /  apt install jq)
#   - The app must be running and accessible at BASE_URL
#   - The debug route must still be present in routes/web.php
#
# Usage:
#   chmod +x tests/load/concurrent_student_numbers.sh
#   BASE_URL=https://puptas.undraftedbsit2027.com \
#   IDP_USER_ID=47f31f54-690a-4740-babb-18c4eaaffe85 \
#   SECRET=debug2026 \
#   bash tests/load/concurrent_student_numbers.sh
# =============================================================================

BASE_URL="${BASE_URL:-http://localhost:8000}"
SECRET="${SECRET:-debug2026}"
CONCURRENCY=50
TMPDIR_RESULTS=$(mktemp -d)

echo "🚀  Firing ${CONCURRENCY} concurrent requests to ${BASE_URL}/debug-medical/assign-student-number/..."
echo ""

# We need 50 *different* users to properly stress-test concurrent generation.
# If you only have one test user the route returns 'already_has_number' after
# the first call.  Pass a list of IDP user IDs via the IDP_USER_IDS environment
# variable (space-separated), or supply a single IDP_USER_ID to generate 50
# requests for independent test users created by ApiTestStudentsSeeder.

if [ -n "${IDP_USER_IDS}" ]; then
    IDP_IDS=($IDP_USER_IDS)
elif [ -n "${IDP_USER_ID}" ]; then
    # Single-user mode: just hit the same user repeatedly to test idempotency.
    IDP_IDS=()
    for i in $(seq 1 $CONCURRENCY); do
        IDP_IDS+=("${IDP_USER_ID}")
    done
else
    echo "❌  Set IDP_USER_IDS (space-separated list of 50 IDP user IDs) or IDP_USER_ID."
    exit 1
fi

# Launch all requests in the background
PIDS=()
for i in $(seq 0 $((CONCURRENCY - 1))); do
    uid="${IDP_IDS[$((i % ${#IDP_IDS[@]}))]}"
    outfile="${TMPDIR_RESULTS}/result_${i}.json"
    curl -s -X POST \
        "${BASE_URL}/debug-medical/assign-student-number/${uid}/${SECRET}" \
        -o "${outfile}" \
        -w "%{http_code}" > "${TMPDIR_RESULTS}/status_${i}.txt" 2>&1 &
    PIDS+=($!)
done

# Wait for all background jobs to finish
for pid in "${PIDS[@]}"; do
    wait "$pid"
done

echo "✅  All requests completed."
echo ""

# ── Analysis ─────────────────────────────────────────────────────────────────
NUMBERS=()
ERRORS=0

for i in $(seq 0 $((CONCURRENCY - 1))); do
    outfile="${TMPDIR_RESULTS}/result_${i}.json"
    if [ -f "$outfile" ]; then
        status=$(jq -r '.status // "error"' "$outfile" 2>/dev/null)
        num=$(jq -r '.student_number // empty' "$outfile" 2>/dev/null)

        if [ "$status" = "success" ] || [ "$status" = "already_has_number" ]; then
            NUMBERS+=("$num")
        else
            echo "⚠️   Request ${i}: unexpected response — $(cat "$outfile")"
            ((ERRORS++))
        fi
    fi
done

UNIQUE_COUNT=$(printf '%s\n' "${NUMBERS[@]}" | sort -u | wc -l | tr -d ' ')
TOTAL_COUNT=${#NUMBERS[@]}

echo "📊  Results:"
echo "    Total successful responses : ${TOTAL_COUNT}"
echo "    Unique student numbers      : ${UNIQUE_COUNT}"
echo "    Errors / unexpected         : ${ERRORS}"
echo ""

if [ "$UNIQUE_COUNT" -eq "$TOTAL_COUNT" ]; then
    echo "✅  PASS — All student numbers are unique. No race condition detected."
    exit 0
else
    echo "❌  FAIL — Duplicates detected! Race condition is NOT fixed."
    printf '%s\n' "${NUMBERS[@]}" | sort | uniq -d | while read dup; do
        echo "    DUPLICATE: $dup"
    done
    exit 1
fi
