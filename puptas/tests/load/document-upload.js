import http from 'k6/http';
import { check, sleep } from 'k6';
import {
    BASE_URL,
    TEST_USER,
    standardStages,
    defaultThresholds,
    authenticateUser,
    getMockPngBytes,
} from './config.js';

/**
 * Endpoint 3: Applicant Document Upload
 *
 * Route: POST /upload-files
 * Importance:
 *  - High I/O and CPU intensive operation (file validation, compression, cloud/disk storage).
 *  - Verifies system throughput when multiple applicants upload credentials simultaneously.
 */

// Allow 200, 302, and 422 as expected application responses
http.setResponseCallback(http.expectedStatuses(200, 302, 422));

export const options = {
    stages: standardStages,
    thresholds: {
        ...defaultThresholds,
        'http_req_duration{name:UploadFiles}': ['p(95)<3500'], // Higher allowance for multipart file processing
    },
};

// Cached mock file bytes (1x1 valid PNG)
const mockImageBytes = getMockPngBytes();

export default function () {
    const jar = http.cookieJar();

    // Authenticate the VU session
    authenticateUser(TEST_USER.email, TEST_USER.password);

    // Retrieve active CSRF token from cookie jar
    const cookies = jar.cookiesForURL(BASE_URL);
    const xsrfCookie = cookies['XSRF-TOKEN'] ? decodeURIComponent(cookies['XSRF-TOKEN'][0]) : '';

    // Prepare multipart form data payload
    const payload = {
        filePhoto2x2: http.file(mockImageBytes, 'test_2x2.png', 'image/png'),
        file10Front: http.file(mockImageBytes, 'grade10_card.png', 'image/png'),
    };

    const params = {
        headers: {
            'X-XSRF-TOKEN': xsrfCookie,
            'Accept': 'application/json, text/plain, */*',
        },
        tags: { name: 'UploadFiles' },
    };

    const res = http.post(`${BASE_URL}/upload-files`, payload, params);

    // Checks: 200 (uploaded), 302 (redirected), or 422 (validation requirements)
    // Critical check: Should NEVER return 500 (Unhandled exception / DB timeout / Out of memory)
    check(res, {
        'upload responded without 500 server crash': (r) => r.status !== 500,
        'upload accepted or handled (200, 302, or 422)': (r) =>
            r.status === 200 || r.status === 302 || r.status === 422,
        'upload latency under 4000ms': (r) => r.timings.duration < 4000,
    });

    // Pacing: real users take time between uploads
    sleep(2 + Math.random() * 2);
}
