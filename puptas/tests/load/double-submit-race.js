import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import {
    BASE_URL,
    extractCsrfToken,
    authenticateUser,
} from './config.js';

/**
 * Race Condition Load Test: Double/Concurrent Application Submission
 *
 * Route: POST /user/application/submit
 * Scenario:
 *  - 20 seeded test applicant accounts (testapplicant1@gmail.com .. testapplicant20@gmail.com)
 *  - Each VU authenticates as their assigned test applicant
 *  - Each VU fires 3 identical POST /user/application/submit requests simultaneously via http.batch
 *  - Checks that row locking serializes submission: exactly one 200, remainder 409, zero 500s.
 */

// Custom counters for summary export
export const vus_with_zero_200s = new Counter('vus_with_zero_200s');
export const vus_with_exactly_one_200 = new Counter('vus_with_exactly_one_200');
export const vus_with_multiple_200s = new Counter('vus_with_multiple_200s');
export const server_500_errors = new Counter('server_500_errors');

// Allow expected HTTP response statuses
http.setResponseCallback(http.expectedStatuses(200, 409, 422));

export const options = {
    scenarios: {
        double_submit_race: {
            executor: 'per-vu-iterations',
            vus: 20,
            iterations: 1,
            maxDuration: '2m',
        },
    },
    thresholds: {
        'vus_with_multiple_200s': ['count==0'],
        'server_500_errors': ['count==0'],
    },
};

export default function () {
    const vuId = __VU;
    const email = `testapplicant${vuId}@gmail.com`;
    const password = __ENV.TEST_USER_PASSWORD || 'password';

    // 1. Authenticate the VU using assigned seeded account
    const authResult = authenticateUser(email, password);
    check(authResult, {
        'authenticated successfully': (r) => r.success === true,
    });

    // 2. Load /user/application for page state and active CSRF token
    const appPageRes = http.get(`${BASE_URL}/user/application`, {
        headers: {
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        },
        tags: { name: 'LoadApplicationPage' },
    });

    const jar = http.cookieJar();
    const cookies = jar.cookiesForURL(BASE_URL);
    const xsrfCookie = cookies['XSRF-TOKEN'] ? decodeURIComponent(cookies['XSRF-TOKEN'][0]) : '';
    const pageCsrf = extractCsrfToken(appPageRes.body);
    const activeToken = pageCsrf || xsrfCookie;

    // 3. Prepare 3 identical concurrent submission payloads
    const payload = JSON.stringify({
        program_id: 1,
        second_choice_id: 2,
        third_choice_id: 3,
    });

    const postParams = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': xsrfCookie,
            'X-CSRF-TOKEN': activeToken,
        },
        tags: { name: 'SubmitApplicationBatch' },
    };

    const req = {
        method: 'POST',
        url: `${BASE_URL}/user/application/submit`,
        body: payload,
        params: postParams,
    };

    // 4. Fire 3 concurrent POSTs simultaneously using http.batch
    const responses = http.batch([req, req, req]);

    let count200 = 0;
    let count409 = 0;
    let count500 = 0;

    for (const res of responses) {
        if (res.status === 200) {
            count200++;
        } else if (res.status === 409) {
            count409++;
        } else if (res.status === 500) {
            count500++;
        }
    }

    if (count500 > 0) {
        server_500_errors.add(count500);
    }

    if (count200 === 0) {
        vus_with_zero_200s.add(1);
    } else if (count200 === 1) {
        vus_with_exactly_one_200.add(1);
    } else {
        vus_with_multiple_200s.add(1);
    }

    check(responses, {
        'exactly one 200 success': () => count200 === 1,
        'remaining requests return 409 conflict': () => count409 === (responses.length - count200),
        'no server 500 errors': () => count500 === 0,
    });
}
