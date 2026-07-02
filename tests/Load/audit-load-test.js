/**
 * k6 Load & Redaction-Invariant Test
 *
 * Run locally:
 *   vendor/bin/testbench migrate
 *   vendor/bin/testbench serve &
 *   k6 run tests/Load/audit-load-test.js
 *
 * With a custom base URL:
 *   k6 run tests/Load/audit-load-test.js --env BASE_URL=http://localhost:8000
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000';

// Custom metrics — `data_leak_errors` must stay at 0 for the suite to pass.
const dataLeakErrors = new Counter('data_leak_errors');
const redactionFailRate = new Rate('redaction_fail_rate');

export const options = {
    scenarios: {
        // Ramp up to 20 VUs, hold, then ramp down.
        load: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 20 },
                { duration: '1m', target: 20 },
                { duration: '10s', target: 0 },
            ],
            tags: { scenario: 'load' },
        },
        // Spike: 50 concurrent VUs for 30 s to stress singleton state isolation.
        spike: {
            executor: 'constant-vus',
            vus: 50,
            duration: '30s',
            startTime: '1m40s',
            tags: { scenario: 'spike' },
        },
    },
    thresholds: {
        // Less than 1 % of requests may fail.
        http_req_failed: ['rate<0.01'],
        // 95th-percentile response time under 500 ms.
        http_req_duration: ['p(95)<500'],
        // Zero plaintext leakage allowed.
        data_leak_errors: ['count==0'],
    },
};

function randomCode(prefix) {
    return `${prefix}-${Math.random().toString(36).slice(2, 10).toUpperCase()}`;
}

function containsPlaintext(responseBody, value) {
    return responseBody !== null && responseBody.includes(value);
}

export default function () {
    const discountCode = randomCode('PROMO');
    const nationalId = randomCode('NID');

    // ── Create ────────────────────────────────────────────────────────────
    const createRes = http.post(
        `${BASE_URL}/api/records`,
        JSON.stringify({
            status: 'pending',
            total: parseFloat((Math.random() * 500).toFixed(2)),
            discount_code: discountCode,
            national_id: nationalId,
        }),
        { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } },
    );

    const createOk = check(createRes, {
        'create → 201': (r) => r.status === 201,
        'create → has id': (r) => {
            try {
                return JSON.parse(r.body).id > 0;
            } catch {
                return false;
            }
        },
    });

    // Check response body does not echo back plaintext sensitive values.
    if (containsPlaintext(createRes.body, discountCode)) {
        dataLeakErrors.add(1);
        redactionFailRate.add(1);
        console.error(`DATA LEAK [create]: discount_code '${discountCode}' in response`);
    } else {
        redactionFailRate.add(0);
    }

    if (containsPlaintext(createRes.body, nationalId)) {
        dataLeakErrors.add(1);
        console.error(`DATA LEAK [create]: national_id '${nationalId}' in response`);
    }

    if (!createOk) {
        sleep(0.5);
        return;
    }

    let orderId;
    try {
        orderId = JSON.parse(createRes.body).id;
    } catch {
        sleep(0.5);
        return;
    }

    // ── Update ────────────────────────────────────────────────────────────
    const newDiscountCode = randomCode('UPDATED');
    const newNationalId = randomCode('UPDNID');

    const updateRes = http.patch(
        `${BASE_URL}/api/records/${orderId}`,
        JSON.stringify({
            status: 'shipped',
            discount_code: newDiscountCode,
            national_id: newNationalId,
        }),
        { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } },
    );

    check(updateRes, {
        'update → 200': (r) => r.status === 200,
    });

    if (containsPlaintext(updateRes.body, newDiscountCode)) {
        dataLeakErrors.add(1);
        console.error(`DATA LEAK [update]: discount_code '${newDiscountCode}' in response`);
    }

    if (containsPlaintext(updateRes.body, newNationalId)) {
        dataLeakErrors.add(1);
        console.error(`DATA LEAK [update]: national_id '${newNationalId}' in response`);
    }

    sleep(0.5);
}
