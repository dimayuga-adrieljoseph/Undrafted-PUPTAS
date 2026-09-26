import http from 'k6/http';

/**
 * PUPTAS k6 Load Testing Configuration & Shared Helpers
 *
 * Staging Target: Railway Staging Environment
 * DO NOT point this at production to prevent real data corruption or rate-limit exhaustion.
 */

// Base URL targeting Railway Staging by default
export const BASE_URL = (__ENV.BASE_URL || 'https://pup-admission-system-staging.up.railway.app').replace(/\/+$/, '');

// Test account credentials (test-only accounts on staging)
export const TEST_USER = {
    email: __ENV.TEST_USER_EMAIL || 'testapplicant@gmail.com',
    password: __ENV.TEST_USER_PASSWORD || 'password',
};

// Standard ramp up, hold, and ramp down stages for normal load testing
export const standardStages = [
    { duration: '30s', target: 20 }, // Ramp up to 20 virtual users
    { duration: '1m', target: 20 },  // Hold at 20 virtual users
    { duration: '20s', target: 0 },  // Ramp down to 0 virtual users
];

// High concurrency / stress stages (for peak registration release simulations)
export const stressStages = [
    { duration: '30s', target: 50 },  // Ramp up to 50 virtual users
    { duration: '1m30s', target: 50 },// Hold at 50 virtual users
    { duration: '30s', target: 100 }, // Spike to 100 virtual users
    { duration: '1m', target: 100 },  // Hold peak
    { duration: '30s', target: 0 },   // Cool down
];

// Standard SLA thresholds for panel claims
export const defaultThresholds = {
    http_req_failed: ['rate<0.02'],     // Error rate should stay under 2%
    http_req_duration: ['p(95)<2500'],  // 95% of requests must complete under 2.5s
};

/**
 * Extracts Laravel CSRF token from HTML body if present.
 * Looks for <meta name="csrf-token" content="..."> or <input type="hidden" name="_token" value="...">
 */
export function extractCsrfToken(html) {
    if (!html) return '';
    const metaMatch = html.match(/<meta\s+name=["']csrf-token["']\s+content=["']([^"']+)["']/i);
    if (metaMatch && metaMatch[1]) return metaMatch[1];

    const inputMatch = html.match(/<input[^>]+name=["']_token["'][^>]+value=["']([^"']+)["']/i);
    if (inputMatch && inputMatch[1]) return inputMatch[1];

    return '';
}

/**
 * Returns a minimal valid 1x1 PNG byte array for mock file uploads
 */
export function getMockPngBytes() {
    return new Uint8Array([
        0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A,
        0x00, 0x00, 0x00, 0x0D, 0x49, 0x48, 0x44, 0x52,
        0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x01,
        0x08, 0x06, 0x00, 0x00, 0x00, 0x1F, 0x15, 0xC4,
        0x89, 0x00, 0x00, 0x00, 0x0A, 0x49, 0x44, 0x41,
        0x54, 0x78, 0x9C, 0x63, 0x00, 0x01, 0x00, 0x00,
        0x05, 0x00, 0x01, 0x0D, 0x0A, 0x2D, 0xB4, 0x00,
        0x00, 0x00, 0x00, 0x49, 0x45, 0x4E, 0x44, 0xAE,
        0x42, 0x60, 0x82
    ]);
}

/**
 * Helper to authenticate the current VU against staging
 * Tries dev-login shortcut first (available if APP_DEBUG=true),
 * then falls back to standard CSRF + /login post.
 */
export function authenticateUser(email = TEST_USER.email, password = TEST_USER.password) {
    const jar = http.cookieJar();

    // 1. Try staging dev-login bypass first
    const devRes = http.get(`${BASE_URL}/dev-login?email=${encodeURIComponent(email)}`, {
        redirects: 0,
        tags: { name: 'AuthDevBypass' }
    });

    if (devRes.status === 302 || devRes.status === 200) {
        return { success: true, method: 'dev-login' };
    }

    // 2. Fall back to standard Laravel session login
    const loginPageRes = http.get(`${BASE_URL}/login?local=1`, {
        headers: {
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        },
        tags: { name: 'AuthLoginPage' },
    });

    const csrfToken = extractCsrfToken(loginPageRes.body);
    const cookies = jar.cookiesForURL(BASE_URL);
    const xsrfCookie = cookies['XSRF-TOKEN'] ? decodeURIComponent(cookies['XSRF-TOKEN'][0]) : '';
    const activeToken = csrfToken || xsrfCookie;

    const loginRes = http.post(`${BASE_URL}/login`, {
        email: email,
        password: password,
        _token: activeToken,
    }, {
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-XSRF-TOKEN': xsrfCookie,
        },
        redirects: 0,
        tags: { name: 'AuthStandardLogin' },
    });

    return {
        success: loginRes.status === 302 || loginRes.status === 200,
        method: 'standard-login',
        status: loginRes.status,
    };
}
