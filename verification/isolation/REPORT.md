# Dedicated webhook isolation verification

Verified 2026-11-26 (Asia/Calcutta). The original public app is unchanged. The new router delegates only POST /webhook/elevenlabs.php to the existing webhook entry. It rejects other paths before loading application code and never serves static files. No signature, extraction, transaction or intake logic was duplicated.

## Results

| Suite | Result |
|---|---|
| HTTP: PHP 8087 + isolation 8088 | 63/63 PASS |
| HTTP: Apache + isolation 8088 | 63/63 PASS |
| Security: PHP 8087 | 21/21 PASS |
| Security: Apache | 21/21 PASS |
| Security: isolated 8088 | 21/21 PASS |
| Regression including database integration | 34/34 PASS |
| Simulation: PHP 8087 | 3/3 PASS |
| Simulation: Apache | 3/3 PASS |
| Simulation: isolated 8088 | 3/3 PASS |
| Playwright apache | PASS; 14 screenshots; 0 failed requests |
| Playwright builtin | PASS; 14 screenshots; 0 failed requests |

The HTTP suite preserves its original 23 expectations and adds 40 checks. The 21 security and 34 regression expectations are unchanged. Playwright assertions are unchanged; an optional output directory argument preserves earlier evidence. PHP 8.1 lint passed for both changed PHP files.

## Server and tunnel commands

Working directory: D:/My-Projects/Ai-Call-Voice-Agent. Apache and PHP 8087 were already running and were retained. The exact PHP 8088 command executed was:

```powershell
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off -S 127.0.0.1:8088 -t webhook-public webhook-public/index.php
```

Existing server startup commands (not re-executed during this fix):

```powershell
Start-Service wampapache64
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off -S 127.0.0.1:8087 -t public
```

Apache serves http://localhost/voiceshop/public. Its service was confirmed Running. The dedicated router argument is required. Loopback binding keeps 8088 off the LAN.

> NEVER tunnel Apache or 8087: those servers expose the dashboard, Config Health and owner login. Only 8088 may be a tunnel target.

Tunnel commands for the user (neither executed):

```powershell
cloudflared tunnel --url http://127.0.0.1:8088
# Alternative:
ngrok http http://127.0.0.1:8088
```

Configure https://<tunnel-host>/webhook/elevenlabs.php in ElevenLabs. Neither cloudflared nor ngrok was found on PATH. The prior real-account instructions reserve tunnel launch and real calls for the user. Public-provider delivery was not tested; local isolation and signed processing were tested.

## Exact suite commands

Each command was captured under logs/ using PowerShell Tee-Object. The Apache HTTP runner uses PHP 8.1 and the deployed copy because its existing Health check temporarily creates a config beside that runner. Other PHP commands use PHP 8.2.30 with Xdebug disabled.

```powershell
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/http_tests.php http://127.0.0.1:8087
& C:/wamp64/bin/php/php8.1.31/php.exe -d xdebug.mode=off C:/wamp64/www/voiceshop/tests/http_tests.php http://localhost/voiceshop/public http://127.0.0.1:8088
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/webhook_tests.php http://127.0.0.1:8087
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/webhook_tests.php http://localhost/voiceshop/public
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/webhook_tests.php http://127.0.0.1:8088
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/run_tests.php --integration
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/simulate_webhook.php http://127.0.0.1:8087
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/simulate_webhook.php http://localhost/voiceshop/public
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/simulate_webhook.php http://127.0.0.1:8088
node verification/mobile-check.js both isolation
```

## HTTP response evidence

Actual curl output (logs/curl-proof.txt):

```text
GET http://127.0.0.1:8088/index.php
{"error":"not_found"}
HTTP 404
GET http://127.0.0.1:8088/index.php?page=health
{"error":"not_found"}
HTTP 404
POST /webhook/elevenlabs.php missing signature
{"error":"unauthorized"}
HTTP 401
POST /webhook/elevenlabs.php bad signature
{"error":"unauthorized"}
HTTP 401
```

Correctly signed requests use the existing CLI simulator, reading the secret from private config without printing it. Actual output on 8088:

```text
Complete order: HTTP 200 {"ok":true}
PASS call_id=SIM-6e17c5877314dfa8-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL
Missing address: HTTP 200 {"ok":true}
PASS call_id=SIM-6e17c5877314dfa8-missing calls=1 orders=1 status=NEEDS_DETAILS
Replay complete unchanged: HTTP 200 {"ok":true}
PASS call_id=SIM-6e17c5877314dfa8-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL

3 PASS, 0 FAIL. Simulation orders retained for dashboard inspection.
```

## Web root contents

```text
webhook-public/
└── index.php
```

There are no assets, config files or other entry scripts in this directory. The original public/ directory remains in place.

## Deviations and notes

- The router reuses the original public/webhook/elevenlabs.php entry directly, which already invokes the shared controller/intake. No refactor was necessary.
- The server command adds the explicit router argument so all unknown paths receive the guarded JSON 404 rather than static-server fallback behavior.
- No public tunnel or real call was launched for the reasons above. Existing full-app servers were retained rather than restarted.
- An initial successful 63-check run emitted a local Xdebug log-permission warning. The recorded final run disables Xdebug and passes cleanly.
- Historical Run 2 tunnel instructions and their report generator now direct readers to 8088 only; historical test evidence remains intact.
- Simulation fixtures remain visible as designed. HTTP/security suites clean up their own fixtures.

## Full named PHP check results

### HTTP: PHP 8087 + isolation 8088

Source: [http-builtin.txt](logs/http-builtin.txt)

| Result | Check |
|---|---|
| PASS | Setup creates tables and four seeds |
| PASS | Repeated setup dedupes seed orders |
| PASS | Dashboard sections, demo badge and Tamil UTF-8 |
| PASS | Import page banner and two JSON samples |
| PASS | Complete HTTP import awaits approval |
| PASS | HTTP duplicate message returns existing order |
| PASS | Approve disabled without owner mode |
| PASS | Forged approval blocked server-side |
| PASS | Owner PIN enables session |
| PASS | Owner HTTP approval succeeds |
| PASS | Disallowed HTTP transition rejected |
| PASS | Incomplete HTTP import needs details |
| PASS | Missing inputs highlighted and preview fallback shown |
| PASS | Save offers explicit approval queue transition |
| PASS | CSRF mismatch rejected |
| PASS | Malformed JSON produces clear error |
| PASS | Health has English fallback and unfilled voice warnings |
| PASS | Broken config shows error list without crash |
| PASS | Shop switcher filters orders |
| PASS | Webhook wrong and missing signatures return generic 401 |
| PASS | Webhook correct signature creates audit and draft order |
| PASS | Webhook replay returns 200 without duplicate call or order |
| PASS | Webhook missing address creates NEEDS_DETAILS |
| PASS | Isolated GET / returns generic 404 |
| PASS | Isolated POST / returns generic 404 |
| PASS | Isolated GET /index.php returns generic 404 |
| PASS | Isolated POST /index.php returns generic 404 |
| PASS | Isolated GET /index.php?page=health returns generic 404 |
| PASS | Isolated POST /index.php?page=health returns generic 404 |
| PASS | Isolated GET /index.php?page=owner returns generic 404 |
| PASS | Isolated POST /index.php?page=owner returns generic 404 |
| PASS | Isolated GET /index.php?page=detail&id=1 returns generic 404 |
| PASS | Isolated POST /index.php?page=detail&id=1 returns generic 404 |
| PASS | Isolated GET /index.php?shop=grocery_store_02 returns generic 404 |
| PASS | Isolated POST /index.php?shop=grocery_store_02 returns generic 404 |
| PASS | Isolated GET /import.php returns generic 404 |
| PASS | Isolated POST /import.php returns generic 404 |
| PASS | Isolated GET /setup.php returns generic 404 |
| PASS | Isolated POST /setup.php returns generic 404 |
| PASS | Isolated GET /assets/css/app.css returns generic 404 |
| PASS | Isolated POST /assets/css/app.css returns generic 404 |
| PASS | Isolated GET /assets/js/import.js returns generic 404 |
| PASS | Isolated POST /assets/js/import.js returns generic 404 |
| PASS | Isolated GET /public/index.php returns generic 404 |
| PASS | Isolated POST /public/index.php returns generic 404 |
| PASS | Isolated GET /config/env.php returns generic 404 |
| PASS | Isolated POST /config/env.php returns generic 404 |
| PASS | Isolated GET /../public/index.php returns generic 404 |
| PASS | Isolated POST /../public/index.php returns generic 404 |
| PASS | Isolated GET /%2e%2e/public/index.php returns generic 404 |
| PASS | Isolated POST /%2e%2e/public/index.php returns generic 404 |
| PASS | Isolated GET /webhook/elevenlabs.php/extra returns generic 404 |
| PASS | Isolated POST /webhook/elevenlabs.php/extra returns generic 404 |
| PASS | Isolated GET /webhook/%65levenlabs.php returns generic 404 |
| PASS | Isolated POST /webhook/%65levenlabs.php returns generic 404 |
| PASS | Isolated GET //webhook/elevenlabs.php returns generic 404 |
| PASS | Isolated POST //webhook/elevenlabs.php returns generic 404 |
| PASS | Isolated GET /unknown returns generic 404 |
| PASS | Isolated POST /unknown returns generic 404 |
| PASS | Isolated webhook GET returns 405 |
| PASS | Isolated missing signature returns 401 |
| PASS | Isolated bad signature returns 401 |
| PASS | Isolated signed webhook returns 200 with audit and draft order |

### HTTP: Apache + isolation 8088

Source: [http-apache.txt](logs/http-apache.txt)

| Result | Check |
|---|---|
| PASS | Setup creates tables and four seeds |
| PASS | Repeated setup dedupes seed orders |
| PASS | Dashboard sections, demo badge and Tamil UTF-8 |
| PASS | Import page banner and two JSON samples |
| PASS | Complete HTTP import awaits approval |
| PASS | HTTP duplicate message returns existing order |
| PASS | Approve disabled without owner mode |
| PASS | Forged approval blocked server-side |
| PASS | Owner PIN enables session |
| PASS | Owner HTTP approval succeeds |
| PASS | Disallowed HTTP transition rejected |
| PASS | Incomplete HTTP import needs details |
| PASS | Missing inputs highlighted and preview fallback shown |
| PASS | Save offers explicit approval queue transition |
| PASS | CSRF mismatch rejected |
| PASS | Malformed JSON produces clear error |
| PASS | Health has English fallback and unfilled voice warnings |
| PASS | Broken config shows error list without crash |
| PASS | Shop switcher filters orders |
| PASS | Webhook wrong and missing signatures return generic 401 |
| PASS | Webhook correct signature creates audit and draft order |
| PASS | Webhook replay returns 200 without duplicate call or order |
| PASS | Webhook missing address creates NEEDS_DETAILS |
| PASS | Isolated GET / returns generic 404 |
| PASS | Isolated POST / returns generic 404 |
| PASS | Isolated GET /index.php returns generic 404 |
| PASS | Isolated POST /index.php returns generic 404 |
| PASS | Isolated GET /index.php?page=health returns generic 404 |
| PASS | Isolated POST /index.php?page=health returns generic 404 |
| PASS | Isolated GET /index.php?page=owner returns generic 404 |
| PASS | Isolated POST /index.php?page=owner returns generic 404 |
| PASS | Isolated GET /index.php?page=detail&id=1 returns generic 404 |
| PASS | Isolated POST /index.php?page=detail&id=1 returns generic 404 |
| PASS | Isolated GET /index.php?shop=grocery_store_02 returns generic 404 |
| PASS | Isolated POST /index.php?shop=grocery_store_02 returns generic 404 |
| PASS | Isolated GET /import.php returns generic 404 |
| PASS | Isolated POST /import.php returns generic 404 |
| PASS | Isolated GET /setup.php returns generic 404 |
| PASS | Isolated POST /setup.php returns generic 404 |
| PASS | Isolated GET /assets/css/app.css returns generic 404 |
| PASS | Isolated POST /assets/css/app.css returns generic 404 |
| PASS | Isolated GET /assets/js/import.js returns generic 404 |
| PASS | Isolated POST /assets/js/import.js returns generic 404 |
| PASS | Isolated GET /public/index.php returns generic 404 |
| PASS | Isolated POST /public/index.php returns generic 404 |
| PASS | Isolated GET /config/env.php returns generic 404 |
| PASS | Isolated POST /config/env.php returns generic 404 |
| PASS | Isolated GET /../public/index.php returns generic 404 |
| PASS | Isolated POST /../public/index.php returns generic 404 |
| PASS | Isolated GET /%2e%2e/public/index.php returns generic 404 |
| PASS | Isolated POST /%2e%2e/public/index.php returns generic 404 |
| PASS | Isolated GET /webhook/elevenlabs.php/extra returns generic 404 |
| PASS | Isolated POST /webhook/elevenlabs.php/extra returns generic 404 |
| PASS | Isolated GET /webhook/%65levenlabs.php returns generic 404 |
| PASS | Isolated POST /webhook/%65levenlabs.php returns generic 404 |
| PASS | Isolated GET //webhook/elevenlabs.php returns generic 404 |
| PASS | Isolated POST //webhook/elevenlabs.php returns generic 404 |
| PASS | Isolated GET /unknown returns generic 404 |
| PASS | Isolated POST /unknown returns generic 404 |
| PASS | Isolated webhook GET returns 405 |
| PASS | Isolated missing signature returns 401 |
| PASS | Isolated bad signature returns 401 |
| PASS | Isolated signed webhook returns 200 with audit and draft order |

### Security: PHP 8087

Source: [security-builtin.txt](logs/security-builtin.txt)

| Result | Check |
|---|---|
| PASS | HMAC verifies exact UTF-8 raw bytes |
| PASS | Whitespace, component order and multiple v0 signatures accepted |
| PASS | Empty secret and missing header rejected |
| PASS | Tampered body and wrong secret rejected |
| PASS | Stale and future timestamps rejected |
| PASS | Unsupported version and repeated timestamp rejected |
| PASS | Missing secret controller returns generic 401 without DB |
| PASS | Settings reject malformed map and unknown shop |
| PASS | Placeholder agent mapping warns and cannot route |
| PASS | Malformed nested data produces generic rejection |
| PASS | Unknown agent acknowledged without database rows |
| PASS | Audio event skipped without reserving call ID |
| PASS | Signed malformed JSON rejected generically |
| PASS | Signed invalid quantity rolls back audit and order |
| PASS | Retry after corrected extraction can create the rolled-back call |
| PASS | Audit preserves full payload, extraction and Tamil transcript |
| PASS | Names map rice/oil; dal stays flagged free text; supplied money ignored |
| PASS | 255-character conversation ID accepted without truncation |
| PASS | 256-character conversation ID rejected |
| PASS | Existing manual order gets one audit without another order |
| PASS | Concurrent replay creates exactly one call, order and history row |

### Security: Apache

Source: [security-apache.txt](logs/security-apache.txt)

| Result | Check |
|---|---|
| PASS | HMAC verifies exact UTF-8 raw bytes |
| PASS | Whitespace, component order and multiple v0 signatures accepted |
| PASS | Empty secret and missing header rejected |
| PASS | Tampered body and wrong secret rejected |
| PASS | Stale and future timestamps rejected |
| PASS | Unsupported version and repeated timestamp rejected |
| PASS | Missing secret controller returns generic 401 without DB |
| PASS | Settings reject malformed map and unknown shop |
| PASS | Placeholder agent mapping warns and cannot route |
| PASS | Malformed nested data produces generic rejection |
| PASS | Unknown agent acknowledged without database rows |
| PASS | Audio event skipped without reserving call ID |
| PASS | Signed malformed JSON rejected generically |
| PASS | Signed invalid quantity rolls back audit and order |
| PASS | Retry after corrected extraction can create the rolled-back call |
| PASS | Audit preserves full payload, extraction and Tamil transcript |
| PASS | Names map rice/oil; dal stays flagged free text; supplied money ignored |
| PASS | 255-character conversation ID accepted without truncation |
| PASS | 256-character conversation ID rejected |
| PASS | Existing manual order gets one audit without another order |
| PASS | Concurrent replay creates exactly one call, order and history row |

### Security: isolated 8088

Source: [security-isolated.txt](logs/security-isolated.txt)

| Result | Check |
|---|---|
| PASS | HMAC verifies exact UTF-8 raw bytes |
| PASS | Whitespace, component order and multiple v0 signatures accepted |
| PASS | Empty secret and missing header rejected |
| PASS | Tampered body and wrong secret rejected |
| PASS | Stale and future timestamps rejected |
| PASS | Unsupported version and repeated timestamp rejected |
| PASS | Missing secret controller returns generic 401 without DB |
| PASS | Settings reject malformed map and unknown shop |
| PASS | Placeholder agent mapping warns and cannot route |
| PASS | Malformed nested data produces generic rejection |
| PASS | Unknown agent acknowledged without database rows |
| PASS | Audio event skipped without reserving call ID |
| PASS | Signed malformed JSON rejected generically |
| PASS | Signed invalid quantity rolls back audit and order |
| PASS | Retry after corrected extraction can create the rolled-back call |
| PASS | Audit preserves full payload, extraction and Tamil transcript |
| PASS | Names map rice/oil; dal stays flagged free text; supplied money ignored |
| PASS | 255-character conversation ID accepted without truncation |
| PASS | 256-character conversation ID rejected |
| PASS | Existing manual order gets one audit without another order |
| PASS | Concurrent replay creates exactly one call, order and history row |

### Regression including database integration

Source: [regression.txt](logs/regression.txt)

| Result | Check |
|---|---|
| PASS | Both exact seed configs validate |
| PASS | Recursive comment stripping |
| PASS | Broken default block rejected |
| PASS | Mismatched product key rejected |
| PASS | Bad voice.selected rejected |
| PASS | Malformed voice type returns errors |
| PASS | Invalid status / address subset / price visibility rejected |
| PASS | Placeholder voices are warnings only |
| PASS | Partial English closing and delivery fall back to Tamil |
| PASS | Dotted product fallback and English override |
| PASS | Unknown language uses default |
| PASS | Double miss returns null and writes log |
| PASS | Voice placeholder fallback gender and source |
| PASS | Voice config-only gender switch |
| PASS | Provider stub makes no call |
| PASS | Unknown provider rejected |
| PASS | Allowed and disallowed StatusFlow |
| PASS | Puri required fields, phone optional, merge deduped |
| PASS | Grocery required phone and pincode |
| PASS | Complete payload missing fields empty |
| PASS | Invalid quantity, unit and max items rejected |
| PASS | Third shop discovered with no engine edits |
| PASS | DB complete intake and initial history |
| PASS | DB call_id dedupe through intake |
| PASS | DB UNIQUE constraint rejects duplicate directly |
| PASS | DB non-owner approval rejected |
| PASS | DB disallowed transition rejected |
| PASS | DB owner approval and history |
| PASS | DB missing fields and unknown free text flag |
| PASS | DB incomplete approval queue rejected |
| PASS | DB edit fills missing without forcing status |
| PASS | DB explicit queue transition after edit |
| PASS | DB invalid edit is atomic |
| PASS | DB raw payload prices ignored by owner display |

### Simulation: PHP 8087

Source: [simulation-builtin.txt](logs/simulation-builtin.txt)

| Result | Check |
|---|---|
| PASS | call_id=SIM-78ebc6e89aa04033-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL |
| PASS | call_id=SIM-78ebc6e89aa04033-missing calls=1 orders=1 status=NEEDS_DETAILS |
| PASS | call_id=SIM-78ebc6e89aa04033-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL |

### Simulation: Apache

Source: [simulation-apache.txt](logs/simulation-apache.txt)

| Result | Check |
|---|---|
| PASS | call_id=SIM-18971241ced890f6-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL |
| PASS | call_id=SIM-18971241ced890f6-missing calls=1 orders=1 status=NEEDS_DETAILS |
| PASS | call_id=SIM-18971241ced890f6-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL |

### Simulation: isolated 8088

Source: [simulation-isolated.txt](logs/simulation-isolated.txt)

| Result | Check |
|---|---|
| PASS | call_id=SIM-6e17c5877314dfa8-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL |
| PASS | call_id=SIM-6e17c5877314dfa8-missing calls=1 orders=1 status=NEEDS_DETAILS |
| PASS | call_id=SIM-6e17c5877314dfa8-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL |

## Playwright check results

### apache: PASS

Evidence: [browser log](logs/apache-mobile.json). All existing UI assertions passed, including import, owner PIN/approval, history, Config Health counts, mobile navigation target size and desktop sizing. The expected wrong-PIN HTTP 422 is an intentional check, not a failed test.

| Result | Screen / navigation check | Width | Document width |
|---|---|---|---|
| PASS | owner-login | 390 | 390 |
| PASS | order-list | 390 | 390 |
| PASS | existing-detail | 390 | 390 |
| PASS | complete-import-form | 390 | 390 |
| PASS | complete-import-result | 390 | 390 |
| PASS | new-order-listed | 390 | 390 |
| PASS | needs-details-list | 390 | 390 |
| PASS | needs-details-highlight | 390 | 390 |
| PASS | wrong-pin-rejected | 390 | 390 |
| PASS | still-unapproved | 390 | 390 |
| PASS | approved | 390 | 390 |
| PASS | approved-history | 390 | 390 |
| PASS | health-mobile | 390 | 390 |
| PASS | health-desktop | 1440 | 1440 |

### builtin: PASS

Evidence: [browser log](logs/builtin-mobile.json). All existing UI assertions passed, including import, owner PIN/approval, history, Config Health counts, mobile navigation target size and desktop sizing. The expected wrong-PIN HTTP 422 is an intentional check, not a failed test.

| Result | Screen / navigation check | Width | Document width |
|---|---|---|---|
| PASS | owner-login | 390 | 390 |
| PASS | order-list | 390 | 390 |
| PASS | existing-detail | 390 | 390 |
| PASS | complete-import-form | 390 | 390 |
| PASS | complete-import-result | 390 | 390 |
| PASS | new-order-listed | 390 | 390 |
| PASS | needs-details-list | 390 | 390 |
| PASS | needs-details-highlight | 390 | 390 |
| PASS | wrong-pin-rejected | 390 | 390 |
| PASS | still-unapproved | 390 | 390 |
| PASS | approved | 390 | 390 |
| PASS | approved-history | 390 | 390 |
| PASS | health-mobile | 390 | 390 |
| PASS | health-desktop | 1440 | 1440 |
