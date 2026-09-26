import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { BASE_URL, standardStages, defaultThresholds } from './config.js';

/**
 * Endpoint 1: Public Applicant Admission Status Check
 *
 * Route: POST /api/public/admission-results
 * Importance:
 *  - Highest concurrent traffic endpoint during PUPCET results release.
 *  - Tests database lookups, query indexing, SHA-256 name hashing, audit logging,
 *    and throttle middleware resilience (10/min per ref, 30/min per IP).
 */

// Custom counter to monitor when the security rate-limiter engages
const rateLimitedCounter = new Counter('rate_limited_429');

// Allow 200 (found/matched), 422 (validation error/invalid format), and 429 (security rate-limit engaged)
http.setResponseCallback(http.expectedStatuses(200, 422, 429));

export const options = {
    stages: standardStages,
    thresholds: {
        ...defaultThresholds,
        http_req_duration: ['p(95)<1500'],
    },
};

// Test dataset cycling through known and edge-case reference queries
const testQueries = [
    { referenceNumber: '2026-00001-TG-0', firstName: 'Juan', lastName: 'Dela Cruz' },
    { referenceNumber: '2026-00002-TG-0', firstName: 'Maria', lastName: 'Santos' },
    { referenceNumber: '2026-00003-TG-0', firstName: 'Jose', lastName: 'Rizal' },
    { referenceNumber: 'DEBUG-TEST-001', firstName: 'John', lastName: 'Doe' },
    { referenceNumber: '2026-99999-TG-0', firstName: 'Nonexistent', lastName: 'Applicant' },
];

export default function () {
    // Pick a test query based on VU and iteration
    const query = testQueries[(__VU + __ITER) % testQueries.length];

    const payload = JSON.stringify({
        referenceNumber: query.referenceNumber,
        firstName: query.firstName,
        lastName: query.lastName,
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        tags: { name: 'StatusCheck' },
    };

    const res = http.post(`${BASE_URL}/api/public/admission-results`, payload, params);

    if (res.status === 429) {
        rateLimitedCounter.add(1);
    }

    // Checks: Ensure response returns 200, 422, or 429 without unhandled 500 server crashes
    check(res, {
        'status is 200, 422, or 429 (rate-limited)': (r) =>
            r.status === 200 || r.status === 422 || r.status === 429,
        'has JSON response': (r) => {
            try {
                return JSON.parse(r.body) !== null;
            } catch (e) {
                return false;
            }
        },
        'response time < 1500ms': (r) => r.timings.duration < 1500,
    });

    // Pacing: 1 to 2 seconds simulated user think time between queries
    sleep(1 + Math.random());
}
