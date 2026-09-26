import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, TEST_USER, standardStages, defaultThresholds, extractCsrfToken } from './config.js';

/**
 * Endpoint 2: Authentication & Login Workflow
 *
 * Routes:
 *  1. GET  /login?local=1  (Fetch session cookie and CSRF token)
 *  2. POST /login          (Authenticate user credentials)
 *
 * Importance:
 *  - Establishes concurrent authentication throughput without session deadlocks.
 *  - Verifies session store write concurrency (Redis / Database / File session driver).
 */

// Allow 200 (OK/dev login), 302 (standard Laravel redirect on successful login), and 422 (validation)
http.setResponseCallback(http.expectedStatuses(200, 302, 422));

export const options = {
    stages: standardStages,
    thresholds: {
        ...defaultThresholds,
        'http_req_duration{name:LoginSubmit}': ['p(95)<1800'],
    },
};

export default function () {
    const jar = http.cookieJar();

    // Step 1: Request the login page to initialize session and extract CSRF token
    const loginPageRes = http.get(`${BASE_URL}/login?local=1`, {
        headers: {
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        },
        tags: { name: 'LoginPage' },
    });

    check(loginPageRes, {
        'login page loaded (200)': (r) => r.status === 200,
    });

    const csrfToken = extractCsrfToken(loginPageRes.body);

    // Also check for XSRF-TOKEN cookie if meta tag wasn't embedded
    const cookies = jar.cookiesForURL(BASE_URL);
    const xsrfCookie = cookies['XSRF-TOKEN'] ? decodeURIComponent(cookies['XSRF-TOKEN'][0]) : '';
    const activeToken = csrfToken || xsrfCookie;

    // Step 2: Attempt credentials login
    const loginPayload = {
        email: TEST_USER.email,
        password: TEST_USER.password,
        _token: activeToken,
    };

    const loginRes = http.post(`${BASE_URL}/login`, loginPayload, {
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-XSRF-TOKEN': xsrfCookie,
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        },
        redirects: 0, // Stop on redirect so we can inspect 302 vs 200
        tags: { name: 'LoginSubmit' },
    });

    check(loginRes, {
        'login redirected (302) or OK (200)': (r) => r.status === 302 || r.status === 200,
        'response completed in < 2000ms': (r) => r.timings.duration < 2000,
    });

    // Pacing
    sleep(1.5 + Math.random());
}
