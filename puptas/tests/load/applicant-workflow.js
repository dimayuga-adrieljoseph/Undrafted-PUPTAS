import http from 'k6/http';
import { check, group, sleep } from 'k6';
import {
    BASE_URL,
    TEST_USER,
    standardStages,
    defaultThresholds,
    authenticateUser,
    getMockPngBytes,
} from './config.js';

/**
 * Composite Workflow: End-to-End Applicant Journey
 *
 * Sequence:
 *  1. Status Check (PUPCET Result verification)
 *  2. Authentication & Session Establishment
 *  3. Dashboard / Eligible Programs view
 *  4. Document Upload (2x2 Photo)
 *  5. Application Program Choices Submission
 */

// Allow standard HTTP response statuses
http.setResponseCallback(http.expectedStatuses(200, 302, 409, 422));

export const options = {
    stages: standardStages,
    thresholds: defaultThresholds,
};

const mockImageBytes = getMockPngBytes();

export default function () {
    const jar = http.cookieJar();

    // Stage 1: Public Status Check
    group('01_CheckAdmissionStatus', () => {
        const queryPayload = JSON.stringify({
            referenceNumber: '2026-00001-TG-0',
            firstName: 'Juan',
            lastName: 'Dela Cruz',
        });

        const res = http.post(`${BASE_URL}/api/public/admission-results`, queryPayload, {
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            tags: { name: 'Workflow_StatusCheck' },
        });

        check(res, {
            'status check responded': (r) => r.status === 200 || r.status === 422,
        });
    });

    sleep(1);

    // Stage 2: Authentication
    group('02_Authentication', () => {
        const authResult = authenticateUser(TEST_USER.email, TEST_USER.password);
        check(authResult, {
            'auth succeeded': (r) => r.success === true,
        });
    });

    sleep(1);

    const cookies = jar.cookiesForURL(BASE_URL);
    const xsrfCookie = cookies['XSRF-TOKEN'] ? decodeURIComponent(cookies['XSRF-TOKEN'][0]) : '';

    // Stage 3: View Eligible Programs
    group('03_ViewEligiblePrograms', () => {
        const res = http.get(`${BASE_URL}/user/eligible-programs`, {
            headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrfCookie },
            tags: { name: 'Workflow_EligiblePrograms' },
        });

        check(res, {
            'programs loaded (200)': (r) => r.status === 200,
        });
    });

    sleep(1);

    // Stage 4: Upload Document
    group('04_UploadDocument', () => {
        const uploadPayload = {
            filePhoto2x2: http.file(mockImageBytes, 'user_photo.png', 'image/png'),
        };

        const res = http.post(`${BASE_URL}/upload-files`, uploadPayload, {
            headers: { 'X-XSRF-TOKEN': xsrfCookie, 'Accept': 'application/json' },
            tags: { name: 'Workflow_UploadDoc' },
        });

        check(res, {
            'upload handled cleanly': (r) => r.status !== 500,
        });
    });

    sleep(1);

    // Stage 5: Submit Application
    group('05_SubmitApplication', () => {
        const submitPayload = JSON.stringify({
            program_id: 1,
            second_choice_id: 2,
            third_choice_id: 3,
        });

        const res = http.post(`${BASE_URL}/user/application/submit`, submitPayload, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': xsrfCookie,
            },
            tags: { name: 'Workflow_SubmitApp' },
        });

        check(res, {
            'submission processed without 500': (r) => r.status !== 500,
        });
    });

    sleep(2);
}
