# Run 2 verification report

Completed 26 November 2026. Evidence is in verification/run2/logs/ and verification/run2/screenshots/. Both run modes remain available:

- Apache: http://localhost/voiceshop/public/index.php
- Built-in: http://127.0.0.1:8087/index.php
- Webhook: append /webhook/elevenlabs.php to the corresponding public base URL.

## Implemented changes

- Mobile-only navigation minimums of 44×44px; desktop navigation sizing is unchanged.
- Applied database/migrations/002_calls.sql: calls audit table, JSON payload/extraction, TEXT transcript, unique call_id and shop foreign key. orders.call_id widened to 255 characters without removing its unique index.
- New raw-body ElevenLabs endpoint with timestamped HMAC-SHA256 verification and hash_equals; generic JSON responses only.
- Configured agent mapping and secret-presence checks on Config Health, with secret values hidden and placeholder comments retained.
- Existing OrderIntakeService reused inside the webhook transaction; one atomic commit for audit, order, items and history. Manual import retains its behavior.
- Signed Tamil grocery simulator, four new HTTP checks, security/rollback/concurrency tests, updated Playwright checks and RUN2.md handoff.

## Exact commands run

From D:/My-Projects/Ai-Call-Voice-Agent in PowerShell (log redirections omitted here; the raw outputs are retained):

```powershell
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/configure_webhook_demo.php
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off database/migrate.php
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/run_tests.php --integration
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/http_tests.php http://127.0.0.1:8087
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/webhook_tests.php http://127.0.0.1:8087
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off tests/simulate_webhook.php http://127.0.0.1:8087
robocopy D:/My-Projects/Ai-Call-Voice-Agent C:/wamp64/www/voiceshop /E /XD .git verification node_modules storage /R:1 /W:1
& C:/wamp64/bin/php/php8.1.31/php.exe -d xdebug.mode=off C:/wamp64/www/voiceshop/tests/http_tests.php http://localhost/voiceshop/public
& C:/wamp64/bin/php/php8.1.31/php.exe -d xdebug.mode=off C:/wamp64/www/voiceshop/tests/webhook_tests.php http://localhost/voiceshop/public
& C:/wamp64/bin/php/php8.1.31/php.exe -d xdebug.mode=off C:/wamp64/www/voiceshop/tests/simulate_webhook.php http://localhost/voiceshop/public
node verification/mobile-check.js both
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off verification/run2/database-evidence.php
```

The configured local simulation secret was generated randomly into gitignored config/env.php and copied privately to Apache, never printed. Real account settings were not created or modified.

## Checks and results

### Existing 34 config/service/MySQL checks

| Check | Result |
|---|---|
| Both exact seed configs validate | PASS |
| Recursive comment stripping | PASS |
| Broken default block rejected | PASS |
| Mismatched product key rejected | PASS |
| Bad voice.selected rejected | PASS |
| Malformed voice type returns errors | PASS |
| Invalid status / address subset / price visibility rejected | PASS |
| Placeholder voices are warnings only | PASS |
| Partial English closing and delivery fall back to Tamil | PASS |
| Dotted product fallback and English override | PASS |
| Unknown language uses default | PASS |
| Double miss returns null and writes log | PASS |
| Voice placeholder fallback gender and source | PASS |
| Voice config-only gender switch | PASS |
| Provider stub makes no call | PASS |
| Unknown provider rejected | PASS |
| Allowed and disallowed StatusFlow | PASS |
| Puri required fields, phone optional, merge deduped | PASS |
| Grocery required phone and pincode | PASS |
| Complete payload missing fields empty | PASS |
| Invalid quantity, unit and max items rejected | PASS |
| Third shop discovered with no engine edits | PASS |
| DB complete intake and initial history | PASS |
| DB call_id dedupe through intake | PASS |
| DB UNIQUE constraint rejects duplicate directly | PASS |
| DB non-owner approval rejected | PASS |
| DB disallowed transition rejected | PASS |
| DB owner approval and history | PASS |
| DB missing fields and unknown free text flag | PASS |
| DB incomplete approval queue rejected | PASS |
| DB edit fills missing without forcing status | PASS |
| DB explicit queue transition after edit | PASS |
| DB invalid edit is atomic | PASS |
| DB raw payload prices ignored by owner display | PASS |

### Built-in HTTP suite: original 19 plus 4 webhook checks

| Check | Result |
|---|---|
| Setup creates tables and four seeds | PASS |
| Repeated setup dedupes seed orders | PASS |
| Dashboard sections, demo badge and Tamil UTF-8 | PASS |
| Import page banner and two JSON samples | PASS |
| Complete HTTP import awaits approval | PASS |
| HTTP duplicate message returns existing order | PASS |
| Approve disabled without owner mode | PASS |
| Forged approval blocked server-side | PASS |
| Owner PIN enables session | PASS |
| Owner HTTP approval succeeds | PASS |
| Disallowed HTTP transition rejected | PASS |
| Incomplete HTTP import needs details | PASS |
| Missing inputs highlighted and preview fallback shown | PASS |
| Save offers explicit approval queue transition | PASS |
| CSRF mismatch rejected | PASS |
| Malformed JSON produces clear error | PASS |
| Health has English fallback and unfilled voice warnings | PASS |
| Broken config shows error list without crash | PASS |
| Shop switcher filters orders | PASS |
| Webhook wrong and missing signatures return generic 401 | PASS |
| Webhook correct signature creates audit and draft order | PASS |
| Webhook replay returns 200 without duplicate call or order | PASS |
| Webhook missing address creates NEEDS_DETAILS | PASS |

### Apache HTTP suite: original 19 plus 4 webhook checks

| Check | Result |
|---|---|
| Setup creates tables and four seeds | PASS |
| Repeated setup dedupes seed orders | PASS |
| Dashboard sections, demo badge and Tamil UTF-8 | PASS |
| Import page banner and two JSON samples | PASS |
| Complete HTTP import awaits approval | PASS |
| HTTP duplicate message returns existing order | PASS |
| Approve disabled without owner mode | PASS |
| Forged approval blocked server-side | PASS |
| Owner PIN enables session | PASS |
| Owner HTTP approval succeeds | PASS |
| Disallowed HTTP transition rejected | PASS |
| Incomplete HTTP import needs details | PASS |
| Missing inputs highlighted and preview fallback shown | PASS |
| Save offers explicit approval queue transition | PASS |
| CSRF mismatch rejected | PASS |
| Malformed JSON produces clear error | PASS |
| Health has English fallback and unfilled voice warnings | PASS |
| Broken config shows error list without crash | PASS |
| Shop switcher filters orders | PASS |
| Webhook wrong and missing signatures return generic 401 | PASS |
| Webhook correct signature creates audit and draft order | PASS |
| Webhook replay returns 200 without duplicate call or order | PASS |
| Webhook missing address creates NEEDS_DETAILS | PASS |

### Built-in webhook edge/security checks

| Check | Result |
|---|---|
| HMAC verifies exact UTF-8 raw bytes | PASS |
| Whitespace, component order and multiple v0 signatures accepted | PASS |
| Empty secret and missing header rejected | PASS |
| Tampered body and wrong secret rejected | PASS |
| Stale and future timestamps rejected | PASS |
| Unsupported version and repeated timestamp rejected | PASS |
| Missing secret controller returns generic 401 without DB | PASS |
| Settings reject malformed map and unknown shop | PASS |
| Placeholder agent mapping warns and cannot route | PASS |
| Malformed nested data produces generic rejection | PASS |
| Unknown agent acknowledged without database rows | PASS |
| Audio event skipped without reserving call ID | PASS |
| Signed malformed JSON rejected generically | PASS |
| Signed invalid quantity rolls back audit and order | PASS |
| Retry after corrected extraction can create the rolled-back call | PASS |
| Audit preserves full payload, extraction and Tamil transcript | PASS |
| Names map rice/oil; dal stays flagged free text; supplied money ignored | PASS |
| 255-character conversation ID accepted without truncation | PASS |
| 256-character conversation ID rejected | PASS |
| Existing manual order gets one audit without another order | PASS |
| Concurrent replay creates exactly one call, order and history row | PASS |

### Apache webhook edge/security checks

| Check | Result |
|---|---|
| HMAC verifies exact UTF-8 raw bytes | PASS |
| Whitespace, component order and multiple v0 signatures accepted | PASS |
| Empty secret and missing header rejected | PASS |
| Tampered body and wrong secret rejected | PASS |
| Stale and future timestamps rejected | PASS |
| Unsupported version and repeated timestamp rejected | PASS |
| Missing secret controller returns generic 401 without DB | PASS |
| Settings reject malformed map and unknown shop | PASS |
| Placeholder agent mapping warns and cannot route | PASS |
| Malformed nested data produces generic rejection | PASS |
| Unknown agent acknowledged without database rows | PASS |
| Audio event skipped without reserving call ID | PASS |
| Signed malformed JSON rejected generically | PASS |
| Signed invalid quantity rolls back audit and order | PASS |
| Retry after corrected extraction can create the rolled-back call | PASS |
| Audit preserves full payload, extraction and Tamil transcript | PASS |
| Names map rice/oil; dal stays flagged free text; supplied money ignored | PASS |
| 255-character conversation ID accepted without truncation | PASS |
| 256-character conversation ID rejected | PASS |
| Existing manual order gets one audit without another order | PASS |
| Concurrent replay creates exactly one call, order and history row | PASS |

### builtin-simulation.txt

```text
Complete order: HTTP 200 {"ok":true}
PASS call_id=SIM-9082fe4f26491bec-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL
Missing address: HTTP 200 {"ok":true}
PASS call_id=SIM-9082fe4f26491bec-missing calls=1 orders=1 status=NEEDS_DETAILS
Replay complete unchanged: HTTP 200 {"ok":true}
PASS call_id=SIM-9082fe4f26491bec-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL

3 PASS, 0 FAIL. Simulation orders retained for dashboard inspection.
```

### apache-simulation.txt

```text
Complete order: HTTP 200 {"ok":true}
PASS call_id=SIM-4bceb44f2903f537-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL
Missing address: HTTP 200 {"ok":true}
PASS call_id=SIM-4bceb44f2903f537-missing calls=1 orders=1 status=NEEDS_DETAILS
Replay complete unchanged: HTTP 200 {"ok":true}
PASS call_id=SIM-4bceb44f2903f537-complete calls=1 orders=1 status=AWAITING_MERCHANT_APPROVAL

3 PASS, 0 FAIL. Simulation orders retained for dashboard inspection.
```

### Browser checks

| Server | Result | Evidence summary |
|---|---|---|
| apache | PASS | 14 full-page screenshots; 13 mobile screens checked; 1 expected wrong-PIN 422, 0 failed requests |
| builtin | PASS | 14 full-page screenshots; 13 mobile screens checked; 1 expected wrong-PIN 422, 0 failed requests |

Both flows exercised owner login, list/detail, sample-prefill buttons, complete/missing imports, wrong PIN, approval/history and Config Health. Navigation measurements are asserted at every captured mobile step. Document scrollWidth remained at most 390px. No unexpected console/network failures were recorded.

Apache measured navigation at 390px:

| Link | Width px | Height px |
|---|---:|---:|
| Orders | 50.5 | 44 |
| Import | 52.484375 | 44 |
| Config Health | 104.375 | 44 |
| Setup | 44 | 44 |

Desktop at 1440px: min-height/min-width of 44px do not apply; original approximately 41.59px link heights remain. Raw mobile and desktop dimensions are in apache-mobile.json and builtin-mobile.json. Screenshots include both mobile and desktop Config Health.

### Migration and syntax checks

PHP 8.1 lint: 58 files, 0 failures

Migration output: Schema and migrations applied. Native JSON columns, FK and unique indexes were read back from MySQL in logs/database.json. calls.shop_id matches the existing VARCHAR(100) shops.shop_id. Simulation calls have valid JSON audit payloads and one associated order/history row each.

## Deviations and assumptions

1. The prompt refers to shops.id; the existing primary key is shops.shop_id. The migration matches that type/key and utf8mb4 collation.
2. Unapproved drafts preserve Run 1 statuses: complete orders are AWAITING_MERCHANT_APPROVAL; missing required details yield NEEDS_DETAILS. Initial history begins at DRAFT; no order is auto-confirmed.
3. The existing PHP config format remains the source for secrets/mappings. .env.example documents names, while config/env.example.php is the executable template; no dotenv package/parser was introduced.
4. Structured items/address are JSON strings in provider collection values, with direct array/object values also accepted. Invalid/missing quantities or empty item lists are rejected, not fabricated. Missing address is accepted into NEEDS_DETAILS.
5. Dal is absent from the original grocery config, so the requested sample stores it as unknown free text and flags it. Rice/oil resolve through existing aliases. No invented prices/products.
6. Only the documented v0 timestamp/HMAC signature scheme is accepted. Whitespace/order/multiple candidates are tolerated; unsigned/bare signatures and unknown schemes are not. Thirty-minute age tolerance also rejects far-future timestamps.
7. Signed unknown-agent and non-transcription events are logged and acknowledged with 200, without audit/orders. Failed validation rolls back both rows so a corrected replay can succeed.
8. Transcript must fit TEXT (65,535 bytes); signed body limit is 2 MiB. Payload/extraction JSON preserves values, not original formatting/key order.
9. Voice synthesis remains in the hosted ElevenLabs agent. Standalone server TTS is unused. Real account creation, voice selection, public tunnel and real conversation were explicitly user-only steps and were not performed.
10. Superseded by the webhook isolation fix: tunnel ONLY to 127.0.0.1:8088, using /webhook/elevenlabs.php. NEVER tunnel Apache or port 8087. See RUN2.md for the required dedicated router command.

The first audit assertion compared associative JSON key order; MySQL canonicalizes native JSON objects. The test was corrected to compare values semantically, then passed on both servers. No payload data was lost.

## TODO(user): fill real settings

- config/env.php: replace ELEVENLABS_WEBHOOK_SECRET with the actual ElevenLabs webhook signing secret (not an API key). The present generated value is for local simulation only.
- config/env.php: replace/remove local_sim_grocery and local_sim_panipuri mapping keys with real agent IDs mapped to grocery_store_02 and panipuri_stall_01. The example uses REPLACE_WITH_*_AGENT_ID keys, which cannot route events.
- Puri JSON: replace REPLACE_WITH_VOICE_ID_TA_F and REPLACE_WITH_VOICE_ID_TA_M.
- Grocery JSON: replace REPLACE_WITH_VOICE_ID_TA_F_2, REPLACE_WITH_VOICE_ID_TA_M_2, REPLACE_WITH_VOICE_ID_EN_F_2 and REPLACE_WITH_VOICE_ID_EN_M_2.
- In ElevenLabs: configure the hosted voice/persona, the exact data-collection fields in RUN2.md, HMAC webhook URL/secret and optional retries; run a real conversation yourself.

## Sources and limitations

Signature/event implementation checked against [ElevenLabs post-call webhooks](https://elevenlabs.io/docs/eleven-agents/workflows/post-call-webhooks) and the [official Python SDK verifier](https://github.com/elevenlabs/elevenlabs-python/blob/main/src/elevenlabs/webhooks_custom.py). No external API call or real voice conversation was made. Local simulation verifies receiving/authentication/persistence, not hosted-agent extraction quality.

Six parallel HTTP deliveries were tested; this is an idempotency check, not sustained load testing. Owner PIN/Setup access and alternate-unit price assumptions remain local-demo limitations. Simulator fixtures are retained; automated HTTP/security fixtures are removed by their runners.
