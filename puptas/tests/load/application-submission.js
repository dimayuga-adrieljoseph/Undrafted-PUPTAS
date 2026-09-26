import http from 'k6/http';
import { check, sleep } from 'k6';
import {
    BASE_URL,
    TEST_USER,
    standardStages,
    defaultThresholds,
    authenticateUser,
} from './config.js';

/**
 * Endpoint 4: Application Submission
 *
 * Route: POST /user/application/submit
 * Importance:
 *  - Core transaction endpoint where applicant submits final program choices.
 *  - Exercises database write transactions, row locking, quota checks, and audit logging.
 */

// Allow 200 (submitted), 302 (redirected), 409 (already submitted), and 422 (criteria not met)
http.setResponseCallback(http.expectedStatuses(200, 302, 409, 422));

export const options = {
    stages: standardStages,
    thresholds: {
        ...defaultThresholds,
        'http_req_duration{name:SubmitApplication}': ['p(95)<2500'],
    },
};

export default function () {
    const jar = http.cookieJar();

    // Authenticate the VU
    authenticateUser(TEST_USER.email, TEST_USER.password);

    // Retrieve active CSRF token
    const cookies = jar.cookiesForURL(BASE_URL);
    const xsrfCookie = cookies['XSRF-TOKEN'] ? decodeURIComponent(cookies['XSRF-TOKEN'][0]) : '';

    // Step 1: Pre-fetch eligible programs (typical user behavior before submitting)
    http.get(`${BASE_URL}/user/eligible-programs`, {
        headers: {
            'Accept': 'application/json',
            'X-XSRF-TOKEN': xsrfCookie,
        },
        tags: { name: 'GetEligiblePrograms' },
    });

    // Step 2: Submit final program selections
    // Note: Program IDs 1, 2, 3 correspond to seeded programs (e.g. BSIT, BSCS, BSEE)
    const payload = JSON.stringify({
        program_id: 1,
        second_choice_id: 2,
        third_choice_id: 3,
    });

    const params = {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': xsrfCookie,
        },
        tags: { name: 'SubmitApplication' },
    };

    const res = http.post(`${BASE_URL}/user/application/submit`, payload, params);

    // Checks:
    // 200 = Successfully submitted
    // 409 = Application already submitted (expected on repeated iterations by the same user)
    // 422 = Validation requirement (e.g., prerequisite documents or grades needed)
    // 500 = Server failure / DB lock timeout (FAILURE CONDITION)
    check(res, {
        'submission handled cleanly (200, 409, or 422)': (r) =>
            r.status === 200 || r.status === 409 || r.status === 422,
        'no internal server error (not 500)': (r) => r.status !== 500,
        'duration under 2500ms': (r) => r.timings.duration < 2500,
    });

    sleep(2 + Math.random());
}
