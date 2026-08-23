# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_PASSPORT_ACCESS_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

All external API endpoints require a Passport **client-credentials** access token sent as a Bearer token.

<style>
#token-fetcher {
    background: #f8f9fc;
    border: 1px solid #e2e5ed;
    border-left: 4px solid #6ac174;
    border-radius: 6px;
    padding: 18px 20px;
    margin: 16px 0 24px;
    font-size: 13px;
}
#token-fetcher .tf-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 700;
    color: #333;
    margin: 0 0 14px;
    letter-spacing: 0.01em;
}
#token-fetcher .tf-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 10px;
}
#token-fetcher .tf-field label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 5px;
}
#token-fetcher .tf-field input {
    width: 100%;
    background: #fff;
    border: 1px solid #d0d5dd;
    border-radius: 4px;
    color: #1a1a2e;
    padding: 8px 10px;
    font-size: 13px;
    font-family: inherit;
    box-sizing: border-box;
    transition: border-color 0.15s;
    outline: none;
}
#token-fetcher .tf-field input:focus {
    border-color: #6ac174;
    box-shadow: 0 0 0 3px rgba(106,193,116,0.12);
}
#token-fetcher .tf-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 4px;
}
#tf-submit {
    background: #6ac174;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 9px 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    letter-spacing: 0.01em;
    transition: background 0.15s, opacity 0.15s;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
#tf-submit:hover { background: #56b060; }
#tf-submit:disabled { opacity: 0.6; cursor: not-allowed; }
#tf-status { font-size: 12px; color: #666; line-height: 1.4; }
#tf-status.success { color: #2e7d32; font-weight: 600; }
#tf-status.error   { color: #c0392b; font-weight: 600; }
#tf-token-row {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e2e5ed;
    display: none;
}
#tf-token-row label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}
#tf-token-row label span {
    color: #2e7d32;
    text-transform: none;
    font-weight: 500;
    letter-spacing: 0;
}
#tf-token-output {
    width: 100%;
    background: #1a1e2e;
    border: 1px solid #2d3147;
    border-radius: 4px;
    color: #7ecfb3;
    padding: 10px 12px;
    font-size: 11px;
    font-family: 'Fira Mono', 'Courier New', monospace;
    line-height: 1.5;
    resize: none;
    box-sizing: border-box;
    white-space: nowrap;
    overflow-x: auto;
    height: 48px;
    display: block;
}
</style>

<div id="token-fetcher">
    <p class="tf-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6ac174" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
        Get an access token
    </p>
    <div class="tf-row">
        <div class="tf-field">
            <label>Client ID</label>
            <input id="tf-client-id" type="text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" autocomplete="off" spellcheck="false"/>
        </div>
        <div class="tf-field">
            <label>Client Secret</label>
            <input id="tf-client-secret" type="password" placeholder="Your client secret" autocomplete="off"/>
        </div>
    </div>
    <div class="tf-actions">
        <button id="tf-submit" onclick="puptas_fetchToken()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            Fetch Token
        </button>
        <span id="tf-status"></span>
    </div>
    <div id="tf-token-row">
        <label>Access token <span>— auto-filled into all endpoints below</span></label>
        <textarea id="tf-token-output" rows="1" readonly></textarea>
    </div>
</div>

<script>
function puptas_fetchToken() {
    var clientId     = document.getElementById('tf-client-id').value.trim();
    var clientSecret = document.getElementById('tf-client-secret').value.trim();
    var statusEl     = document.getElementById('tf-status');
    var btn          = document.getElementById('tf-submit');
    statusEl.className = '';
    if (!clientId || !clientSecret) {
        statusEl.className = 'error';
        statusEl.textContent = '⚠ Enter both Client ID and Client Secret.';
        return;
    }
    btn.disabled = true;
    btn.innerHTML = '⏱ Fetching…';
    statusEl.textContent = '';
    fetch(tryItOutBaseUrl + '/oauth/token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
            grant_type: 'client_credentials',
            client_id: clientId,
            client_secret: clientSecret,
            scope: 'medical-read student-read program-read medical-write',
        }),
    })
    .then(function(res) { return res.json().then(function(d) { return { ok: res.ok, data: d }; }); })
    .then(function(result) {
        btn.disabled = false;
        btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Fetch Token';
        if (!result.ok || !result.data.access_token) {
            var msg = (result.data && (result.data.message || result.data.error)) || 'Request failed';
            statusEl.className = 'error';
            statusEl.textContent = '✗ ' + msg;
            return;
        }
        var token = result.data.access_token;
        document.getElementById('tf-token-row').style.display = 'block';
        document.getElementById('tf-token-output').value = token;
        document.querySelectorAll('input.auth-value').forEach(function(input) {
            input.value = 'Bearer ' + token;
        });
        var expires = result.data.expires_in ? Math.round(result.data.expires_in / 3600) + 'h' : 'unknown';
        statusEl.className = 'success';
        statusEl.textContent = '✓ Token applied to all endpoints — expires in ' + expires + '.';
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Fetch Token';
        statusEl.className = 'error';
        statusEl.textContent = '✗ Network error: ' + err.message;
    });
}
document.addEventListener('DOMContentLoaded', function() {
    var s = document.getElementById('tf-client-secret');
    if (s) s.addEventListener('keydown', function(e) { if (e.key === 'Enter') puptas_fetchToken(); });
});
</script>

## Obtaining a token

Use the OAuth2 client credentials grant. Your system administrator will provide you with a `client_id` and `client_secret`.

```bash
curl --request POST \
  --url "https://<APP_URL>/oauth/token" \
  --header "Content-Type: application/json" \
  --data '{
    "grant_type": "client_credentials",
    "client_id": "<YOUR_CLIENT_ID>",
    "client_secret": "<YOUR_CLIENT_SECRET>",
    "scope": "student-read program-read medical-read"
  }'
```

Available scopes: `student-read`, `program-read`, `medical-read`, `medical-write`

The response will include an `access_token` you can use as the Bearer token. Tokens expire after the configured TTL (default 1 year for client-credentials tokens).

## Medical webhook security

The medical webhook (`POST /api/v1/webhooks/medical-result`) additionally requires an HMAC `X-Medical-Signature` header:

- Compute `hash_hmac('sha256', <raw JSON body>, <shared secret>)` using the secret at `services.medical_webhook.secret`
- Send the resulting hex digest as the `X-Medical-Signature` header
- Include a `timestamp` (ISO8601 or UNIX epoch, within 5 minutes) and a unique `nonce` in the payload to prevent replay attacks

<style>
#webhook-tester {
    background: #f8f9fc;
    border: 1px solid #e2e5ed;
    border-left: 4px solid #e67e22;
    border-radius: 6px;
    padding: 18px 20px;
    margin: 16px 0 24px;
    font-size: 13px;
}
#webhook-tester .wt-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 700; color: #333;
    margin: 0 0 14px; letter-spacing: 0.01em;
}
#webhook-tester .wt-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
#webhook-tester .wt-row-full { margin-bottom: 10px; }
#webhook-tester label { display: block; font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px; }
#webhook-tester input, #webhook-tester select { width: 100%; background: #fff; border: 1px solid #d0d5dd; border-radius: 4px; color: #1a1a2e; padding: 8px 10px; font-size: 13px; font-family: inherit; box-sizing: border-box; transition: border-color 0.15s; outline: none; }
#webhook-tester input:focus { border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.12); }
#wt-submit { background: #e67e22; color: #fff; border: none; border-radius: 4px; padding: 9px 20px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background 0.15s; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
#wt-submit:hover { background: #ca6f1e; }
#wt-submit:disabled { opacity: 0.6; cursor: not-allowed; }
#wt-status { font-size: 12px; color: #666; line-height: 1.4; }
#wt-status.success { color: #2e7d32; font-weight: 600; }
#wt-status.error   { color: #c0392b; font-weight: 600; }
#wt-response-row { margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e5ed; display: none; }
#wt-response-row label { margin-bottom: 6px; }
#wt-response-output { width: 100%; background: #1a1e2e; border: 1px solid #2d3147; border-radius: 4px; color: #7ecfb3; padding: 10px 12px; font-size: 11px; font-family: 'Fira Mono', 'Courier New', monospace; line-height: 1.5; resize: vertical; box-sizing: border-box; min-height: 60px; display: block; }
</style>

<div id="webhook-tester">
    <p class="wt-title">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#e67e22" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
        Interactive Webhook Tester — computes HMAC automatically
    </p>
    <div class="wt-row">
        <div>
            <label>Reference Number</label>
            <input id="wt-ref" type="text" placeholder="e.g. QA-2026-MED-001" value="QA-2026-MED-001"/>
        </div>
        <div>
            <label>Is Health Profile Completed</label>
            <select id="wt-cleared">
                <option value="1">1 — Cleared</option>
                <option value="0">0 — Not Cleared</option>
            </select>
        </div>
    </div>
    <div class="wt-row-full">
        <label>HMAC Secret (services.medical_webhook.secret)</label>
        <input id="wt-secret" type="password" placeholder="Paste the shared webhook secret"/>
    </div>
    <div class="wt-row-full">
        <label>Bearer Token (from the token fetcher above)</label>
        <input id="wt-token" type="text" placeholder="eyJ0eXAiOiJKV1Qi..." spellcheck="false"/>
    </div>
    <div style="display:flex; align-items:center; gap:12px; margin-top:4px;">
        <button id="wt-submit" onclick="puptas_sendWebhook()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
            Send Webhook
        </button>
        <span id="wt-status"></span>
    </div>
    <div id="wt-response-row">
        <label>Response</label>
        <textarea id="wt-response-output" rows="3" readonly></textarea>
    </div>
</div>

<script>
async function puptas_sendWebhook() {
    var refNum   = document.getElementById('wt-ref').value.trim();
    var cleared  = parseInt(document.getElementById('wt-cleared').value);
    var secret   = document.getElementById('wt-secret').value.trim();
    var token    = document.getElementById('wt-token').value.trim();
    var statusEl = document.getElementById('wt-status');
    var btn      = document.getElementById('wt-submit');
    statusEl.className = '';
    if (!refNum || !secret || !token) {
        statusEl.className = 'error';
        statusEl.textContent = '⚠ Fill in all fields.';
        return;
    }
    var timestamp = Math.floor(Date.now() / 1000);
    var nonce = ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, function(c) {
        return (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16);
    });
    var bodyObj = { reference_number: refNum, is_health_profile_completed: cleared, timestamp: timestamp, nonce: nonce };
    var bodyStr = JSON.stringify(bodyObj);
    var enc = new TextEncoder();
    var cryptoKey = await crypto.subtle.importKey('raw', enc.encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
    var sigBuf = await crypto.subtle.sign('HMAC', cryptoKey, enc.encode(bodyStr));
    var sigHex = Array.from(new Uint8Array(sigBuf)).map(function(b) { return b.toString(16).padStart(2,'0'); }).join('');
    btn.disabled = true;
    btn.textContent = '⏱ Sending…';
    statusEl.textContent = '';
    try {
        var res = await fetch(tryItOutBaseUrl + '/api/v1/webhooks/medical-result', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token, 'X-Medical-Signature': sigHex, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: bodyStr,
        });
        var data = await res.json();
        document.getElementById('wt-response-row').style.display = 'block';
        document.getElementById('wt-response-output').value = 'HTTP ' + res.status + '\n' + JSON.stringify(data, null, 2);
        if (res.ok) { statusEl.className = 'success'; statusEl.textContent = '✓ ' + (data.message || 'Success'); }
        else { statusEl.className = 'error'; statusEl.textContent = '✗ ' + (data.message || 'Request failed'); }
    } catch(e) {
        statusEl.className = 'error';
        statusEl.textContent = '✗ Network error: ' + e.message;
    }
    btn.disabled = false;
    btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg> Send Webhook';
}
document.addEventListener('DOMContentLoaded', function() {
    var tfOutput = document.getElementById('tf-token-output');
    if (tfOutput) {
        var observer = new MutationObserver(function() {
            var v = tfOutput.value; if (v) document.getElementById('wt-token').value = v;
        });
        observer.observe(tfOutput, { attributes: true, childList: true, subtree: true, characterData: true });
    }
});
</script>
