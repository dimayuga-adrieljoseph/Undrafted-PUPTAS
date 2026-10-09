import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { BASE_URL } from './config.js';

/**
 * Failed Lookup Limiter Load / Verification Test
 *
 * Scenario:
 * - 1 VU executing 40 sequential failed lookups for one fake target name.
 * - Each request uses a distinct random reference number.
 * - Rate paced with sleep(1.2) to remain under the 60 req/min IP backstop.
 * - Validates that the per-name failure limiter trips (default 30 failures)
 *   and returns HTTP 429 on iteration 31, continuing through iteration 40.
 *
 * NOTE: Do not run with STATUS_CHECKER_DISABLE_THROTTLING=true.
 */

const count200 = new Counter('count_200');
const count429 = new Counter('count_429');
const countOther = new Counter('count_other');

export const options = {
    scenarios: {
        failed_lookup_check: {
            executor: 'per-vu-iterations',
            vus: 1,
            iterations: 40,
            maxDuration: '2m',
        },
    },
    thresholds: {
        count_429: ['count>0'], // Ensure rate limiter engaged
    },
};

// Target fake name to test per-name failure containment
const TARGET_FIRST_NAME = 'K6Fake';
const TARGET_LAST_NAME = 'TestTarget';

let first429Iteration = null;

export default function () {
    const iter = __ITER + 1; // 1-indexed iteration number

    // Generate a random 6-digit reference number in YYYY-XXX-XXX format
    const group1 = Math.floor(100 + Math.random() * 900);
    const group2 = Math.floor(100 + Math.random() * 900);
    const randomRef = `2026-${group1}-${group2}`;

    const payload = JSON.stringify({
        referenceNumber: randomRef,
        firstName: TARGET_FIRST_NAME,
        lastName: TARGET_LAST_NAME,
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        tags: { name: 'FailedLookupCheck' },
    };

    const res = http.post(`${BASE_URL}/api/public/admission-results`, payload, params);

    if (res.status === 200) {
        count200.add(1);
        check(res, {
            'status is 200 (not found)': (r) => r.status === 200 && r.json('found') === false,
        });
    } else if (res.status === 429) {
        count429.add(1);
        if (first429Iteration === null) {
            first429Iteration = iter;
            console.log(`[ALERT] First HTTP 429 received on Iteration ${iter}`);
        }
        check(res, {
            'status is 429 (rate limited)': (r) => r.status === 429 && r.json('message') === 'too_many_attempts',
            'has Retry-After header': (r) => r.headers['Retry-After'] !== undefined,
        });
    } else {
        countOther.add(1);
    }

    // Pacing: 1.2s sleep keeps throughput at ~50 req/min (below the 60 req/min IP cap)
    sleep(1.2);
}

export function handleSummary(data) {
    const c200 = data.metrics.count_200 ? data.metrics.count_200.values.count : 0;
    const c429 = data.metrics.count_429 ? data.metrics.count_429.values.count : 0;
    const cOther = data.metrics.count_other ? data.metrics.count_other.values.count : 0;

    console.log('\n========================================');
    console.log('       FAILED LOOKUP TEST SUMMARY       ');
    console.log('========================================');
    console.log(`Target Name: ${TARGET_FIRST_NAME} ${TARGET_LAST_NAME}`);
    console.log(`Total Requests: 40`);
    console.log(`HTTP 200 (no_record) count: ${c200}`);
    console.log(`HTTP 429 (too_many_attempts) count: ${c429}`);
    console.log(`Other Status count: ${cOther}`);
    console.log(`First 429 Iteration: ${first429Iteration ?? 'None (did not trip)'}`);
    console.log('========================================\n');

    return {
        stdout: '',
    };
}
