# Run 2 — Signed ElevenLabs post-call intake

The existing dashboard and manual import remain intact. Voice conversations run in your ElevenLabs hosted agent. This PHP app receives the completed conversation, verifies its signature, records an audit row and uses the same `OrderIntakeService` as manual import. It does not start calls, synthesize standalone audio or send customer messages.

## Install or upgrade

1. Use PHP **8.1+**, MySQL, `pdo_mysql`, `mbstring`; the simulation/test client additionally uses PHP `curl`.
2. Keep database/owner settings in the gitignored `config/env.php`. New keys are documented in `config/env.example.php` and `.env.example`. The app loads the PHP config file; `.env.example` is a naming reference, not a promise of a dotenv parser.
3. Run the existing browser Setup action, or `php database/migrate.php`. Migration `database/migrations/002_calls.sql` creates `calls` and expands `orders.call_id` from 191 to 255 characters, retaining its UNIQUE index. It is repeatable. `calls.shop_id` references the existing VARCHAR(100) `shops.shop_id`, not a nonexistent integer `shops.id`.
4. For **local simulations only**, run `php tests/configure_webhook_demo.php`. It generates a random signing secret directly into the ignored file if absent and adds `local_sim_grocery`/`local_sim_panipuri` mappings only if no mapping exists. It does not overwrite real configured values or print secrets. Copy the private settings along with the app to your Apache deployment so both instances use the same local signing secret.
5. Run `php tests/simulate_webhook.php http://127.0.0.1:8087` and `php tests/simulate_webhook.php http://localhost/voiceshop/public`.

The simulator is intentionally CLI-only and localhost-only; it cannot become a public signing endpoint. Complete, missing-address and unchanged replay payloads must each return **200 {"ok":true}**, with exactly one call row and one order per conversation. Fixture orders remain visible for inspection; the HTTP/security suites clean up only their own fixtures.

## User TODOs — all real values in one place

| Setting | Where | User action |
|---|---|---|
| Signing secret | `config/env.php`: `ELEVENLABS_WEBHOOK_SECRET` | Replace the locally generated secret with the secret for your real ElevenLabs webhook. Do not use an API key here. |
| Agent mapping | `config/env.php`: `SHOP_AGENT_MAP` | Replace `local_sim_*` or `REPLACE_WITH_*_AGENT_ID` keys with your actual agent IDs; values are `panipuri_stall_01` / `grocery_store_02`. Remove local-only entries before public use. |
| Puri Tamil voices | `config/shops/panipuri_stall_01.json` | Replace `REPLACE_WITH_VOICE_ID_TA_F` and `REPLACE_WITH_VOICE_ID_TA_M`. |
| Grocery Tamil voices | `config/shops/grocery_store_02.json` | Replace `REPLACE_WITH_VOICE_ID_TA_F_2` and `REPLACE_WITH_VOICE_ID_TA_M_2`. |
| Grocery English voices | Same grocery JSON | Replace `REPLACE_WITH_VOICE_ID_EN_F_2` and `REPLACE_WITH_VOICE_ID_EN_M_2`. |
| Hosted agent settings | ElevenLabs account | Set a Tamil-capable voice, persona, first message and the data collection fields below. Local JSON edits do not automatically update a hosted agent. |
| Webhook destination | ElevenLabs account | Use `https://<tunnel-host>/webhook/elevenlabs.php`, with the tunnel targeting only port 8088. |

No API key is needed to verify a webhook or run the local simulator. `elevenlabs_api_key` remains unused by this path. Config Health reports secret presence only (never its value), mappings, validation errors and unusable voice placeholders.

## Exact data collection contract

Enable the **post_call_transcription** event. The receiver reads `data.agent_id`, uses `data.conversation_id` as `call_id`, and reads `data.analysis.data_collection_results`.

Each collection key below has a provider result object with a `value`, e.g. `"customer_name": {"value": "குமார்", ...}`. Configure string fields for all entries. `items` and `address` contain valid JSON strings, with no Markdown fences. Structured arrays/objects are also accepted if the provider supplies them directly.

| Collection key | Value/example |
|---|---|
| `customer_name` | Customer name, or null if not collected |
| `phone` | Phone as a string, preserving leading zeroes |
| `language` | `ta-IN` or `en-IN`; unsupported/missing uses shop default |
| `items` | JSON string: `[{"name":"rice","quantity":5,"unit":"kg"},{"name":"dal","quantity":1,"unit":"kg"},{"name":"oil","quantity":2,"unit":"litre"}]` |
| `address` | JSON string: `{"house_no":"12","street":"மாதிரி தெரு","area":"மாதிரி நகர்","landmark":"கடை அருகில்","pincode":"000000"}`; null if missing |
| `requested_time` | Free text; never a delivery promise |
| `delivery_choice` | `pickup` or `home_delivery` |
| `payment_choice` | A configured payment option, such as `upi` or `cash_on_delivery` |

`tests/Support/ElevenLabsFixture.php` is the complete reference payload used by `tests/simulate_webhook.php`. Item `sku`/`qty` are also supported, but the name/quantity example matches the requested hosted-agent fields. Names are matched case-insensitively against exact SKUs, translated display names and aliases. Ambiguous/unmatched names stay free text. Dal is deliberately unknown in the supplied grocery config: it is stored and flagged `unknown_item`, not priced. Add its product and translations through config if the shop sells it.

The current shared intake rejects empty item lists, invalid/missing quantities and invalid units with **422**. It does not invent quantities. Missing required customer/address fields create **NEEDS_DETAILS**; complete orders create **AWAITING_MERCHANT_APPROVAL**, with initial history from DRAFT. Both are unapproved drafts in the product sense; neither is auto-confirmed.

## Signing, responses and retry behavior

Implementation follows the [ElevenLabs post-call docs](https://elevenlabs.io/docs/eleven-agents/workflows/post-call-webhooks) and [official SDK verifier](https://github.com/elevenlabs/elevenlabs-python/blob/main/src/elevenlabs/webhooks_custom.py): `ElevenLabs-Signature: t=<seconds>,v0=<hex>`; HMAC-SHA256 of `<timestamp>.<unchanged raw body>`. The verifier uses `hash_equals`, accepts whitespace/reordered fields and multiple `v0` candidates, and treats HTTP header names case-insensitively through PHP's server header mapping. It never accepts unsigned/bare-digest or undocumented signature-version fallbacks.

Timestamps must be within 30 minutes of server time (past or future). `event_timestamp` is the event's time and is not used as the signing timestamp. Repeat delivery is deduplicated by the globally unique, case-sensitive conversation ID. The client signs each delivery at the current time; unchanged replay bodies are preserved exactly.

| Situation | Response |
|---|---|
| Blank/unconfigured secret, missing/invalid/stale signature | `401 {"error":"unauthorized"}` |
| Valid signed event, accepted or duplicate | `200 {"ok":true}` |
| Valid signed unknown agent or non-transcription event | `200 {"ok":true}`, logged skip, no audit/order row |
| Signed malformed JSON/extraction | `422 {"error":"invalid_payload"}`, no partial audit/order |
| Signed wrong HTTP method | `405 {"error":"method_not_allowed"}` |
| Signed body above 2 MiB | `413 {"error":"payload_too_large"}` |
| Database/config processing failure | `503 {"error":"temporarily_unavailable"}` |

No response includes exception text, paths, SQL or signatures. Valid call payloads/extraction are stored in native MySQL JSON, so object formatting/key order can change but data is preserved. Transcript text is UTF-8 and must fit TEXT (65,535 bytes); oversized transcript extraction is rejected rather than silently truncated. The entire event is stored in `calls.payload`; the original provider collection-result map is in `calls.extraction`.

Call insert, order, items and status history share one PDO transaction. Invalid extraction rolls the audit insert back so a corrected replay can succeed. A concurrent unique-key collision is acknowledged only after the winning call row exists; manual-import collisions attach an audit to the existing order without duplicating it. Initial webhook history actor is `system/elevenlabs`; manual import retains `system/webhook-sim`.

`storage/logs/webhook.log` records event codes and hashed call IDs, without payloads, customer data, signatures or secrets. Unknown-agent behavior is deliberately always a logged 200 skip; check this log and mappings if no order appears.

## Real-account steps — user only

1. Create/configure your hosted ElevenLabs agent and test its Tamil voice. Apply persona/greeting and the extraction contract above.
2. Fill the real IDs and secret in the TODO table. Keep the two local deployment copies consistent.
3. Start the dedicated webhook server below and expose **only port 8088** using one of the tunnel commands. Configure `https://<tunnel-host>/webhook/elevenlabs.php` as the ElevenLabs post-call webhook, enable HMAC authentication, and use its signing secret locally. Enable provider retries if desired.
4. Make a real test conversation, then review/approve the unapproved order in the local dashboard. Real account setup, public tunnel launch and a real call remain user-only steps.

## Local servers and public webhook tunnel

> **WARNING:** The Wamp Apache app (`http://localhost/voiceshop/public`) and PHP server on port **8087** are LOCAL-ONLY and must **NEVER** be tunneled. Tunneling those full-app ports exposes the dashboard and Config Health page. **8088 is the only permitted tunnel target; never target any other port.**

Run from `D:/My-Projects/Ai-Call-Voice-Agent` in PowerShell. Wamp manages the existing Apache service; if stopped, start it from Wamp or an elevated PowerShell:

```powershell
Start-Service wampapache64
# Existing full app, local only; leave it running if already started:
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off -S 127.0.0.1:8087 -t public
# Separate terminal: dedicated webhook-only server (router argument is REQUIRED):
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off -S 127.0.0.1:8088 -t webhook-public webhook-public/index.php
```

Both PHP servers bind IPv4 loopback so they are not reachable from the LAN. The tunnel runs on this same machine and targets that same address (equivalent to localhost:8088 without IPv6 ambiguity). Choose one tunnel command in another terminal:

```powershell
cloudflared tunnel --url http://127.0.0.1:8088
# Or:
ngrok http http://127.0.0.1:8088
```

Only `POST /webhook/elevenlabs.php` is accepted on 8088. All other paths return generic 404 JSON, including dashboard, Health, owner login, setup, import, assets, and the router filename. Other methods on the webhook path return 405. The router never falls through to static files and delegates the accepted request to the original webhook entry; signature verification and intake are shared. Do not omit the final router argument from the server command.

```text
webhook-public/
`-- index.php
```

Local simulation verifies the receiver, not real voice quality or provider delivery. Run `php tests/simulate_webhook.php http://127.0.0.1:8088` for complete, missing-address and replay cases. The original Apache and 8087 webhook endpoints remain available for local regression checks only.

## Tests

```text
php tests/run_tests.php --integration
php tests/http_tests.php http://127.0.0.1:8087 http://127.0.0.1:8088
php tests/http_tests.php http://localhost/voiceshop/public http://127.0.0.1:8088
php tests/webhook_tests.php http://127.0.0.1:8087
php tests/webhook_tests.php http://localhost/voiceshop/public
php tests/simulate_webhook.php http://127.0.0.1:8087
php tests/simulate_webhook.php http://localhost/voiceshop/public
php tests/webhook_tests.php http://127.0.0.1:8088
php tests/simulate_webhook.php http://127.0.0.1:8088
node verification/mobile-check.js both isolation
```

Run the Apache HTTP test script from the deployed copy (`C:/wamp64/www/voiceshop/tests/http_tests.php`) because one existing test creates a temporary broken config beside the runner. Other scripts can run from either copy when private environment settings agree. The Node dependency is verification-only; the PHP application has no Node requirement. Run 2 evidence is under `verification/run2/`, leaving Run 1 screenshots/report untouched.

The HTTP suite retains the original 23 checks and adds 40 isolation checks (63 total per original app server). Start all three servers before running it. Isolation evidence and the full named check report are in `verification/isolation/REPORT.md`.
