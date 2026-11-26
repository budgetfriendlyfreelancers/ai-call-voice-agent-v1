# VoiceShop — Runs 1 and 2

Core PHP 8.1+ + MySQL/PDO prototype for manual and signed ElevenLabs post-call order intake and owner review. No Composer, frameworks, Node runtime, or CDN dependencies. Voice runs in the hosted ElevenLabs agent; this app receives its webhook and makes no outbound provider API calls. The original shop schema and Tamil strings are preserved.

**Run 2 setup, exact extraction fields, user TODOs and webhook behavior:** [RUN2.md](RUN2.md). Existing installations must run Setup again (or `php database/migrate.php`) to add `calls` and widen order call IDs. Mobile navigation now has 44×44px minimum tap targets; desktop styling is unchanged.

## Wamp setup

1. Start Wamp and wait for the green icon. Select PHP 8.x. Enable `pdo_mysql` and `mbstring`. MySQL must be running.
2. Copy this project to `C:/wamp64/www/voiceshop/` (so `public/index.php` is inside that folder).
3. Open `http://localhost/phpmyadmin`. Create database `voiceshop` with collation `utf8mb4_unicode_ci`. Alternatively import `database/schema.sql`, which creates the database and tables idempotently.
4. Copy `config/env.example.php` to `config/env.php`. Set DB host, port, username, password, database and owner PIN. Typical local Wamp settings: username `root`, empty password, port `3306`. For this fictional local demo you may choose PIN `2468`; the shipped example leaves it blank, disabling owner mode until configured. Keep `env.php` private: it is gitignored. Never use the demo PIN for a deployed application.
5. Open `http://localhost/voiceshop/public/setup.php` and click **Create tables & seed demo data**. It creates missing tables, registers configs and inserts four demo orders once. PHP must be able to write to its configured session directory and to `storage/logs` (the latter is created automatically).
6. Open `http://localhost/voiceshop/public/index.php`.

No rewrite rules or `.htaccess` are needed. The navigation uses plain PHP URLs. Use only a local demo installation; production deployment is outside Run 1.

## How to test

- [ ] Dashboard opens with PROTOTYPE DEMO label; Tamil text renders correctly.
- [ ] Today's Orders / Needs Details / Awaiting Approval show seeded orders.
- [ ] Import page: select the complete sample JSON, import → Awaiting Approval.
- [ ] Import the incomplete sample → Needs Details, missing fields highlighted on its detail page.
- [ ] Re-import the same `call_id` → “duplicate - already imported”, no new order.
- [ ] Edit a Needs Details order, fill missing fields → offered transition to Awaiting Approval without changing status automatically.
- [ ] Without Owner mode Approve is disabled; with Owner mode Approve → CONFIRMED, history updated.
- [ ] Manually alter a status POST to a disallowed transition → rejected.
- [ ] Config Health shows grocery `en-IN` fallback badges and voice placeholder warnings.
- [ ] Temporarily rename a product key in a language block → validator errors in Config Health, no crash. Restore the file afterward.

Also verify at 360px viewport width, check keyboard focus and click both sample-prefill buttons. Customer messages are previews; nothing is sent.

## Automated tests

Open `http://localhost/voiceshop/tests/run_tests.php` for 22 plain-PHP checks (read-only except temporary config fixtures and the language-miss log). For database tests, create **voiceshop_test** in phpMyAdmin using utf8mb4_unicode_ci, then open `http://localhost/voiceshop/tests/integration.php` and click the button. This uses credentials in `config/env.php` but always selects `voiceshop_test`, creates tables there and removes only its uniquely named test orders. Test shop registry entries remain in that isolated DB.

Optional command-line equivalents, using PHP 8 rather than a system PHP 7 binary:

```text
php tests/run_tests.php
php tests/run_tests.php --integration
php -S 127.0.0.1:8086 -t public
php tests/http_tests.php http://127.0.0.1:8086
```

The HTTP suite needs PHP cURL and a running local server. It creates/seeds the configured demo database via the Setup page, tests real form/session requests, and removes only its generated HTTP orders. It never resets existing demo orders. Run it on a fresh demo if you have already edited the four seed orders. The normal application needs neither CLI nor cURL. All browser POST forms include CSRF tokens, including the integration-test form.

Run 1 verification: **34/34 service and MySQL checks; 19/19 HTTP checks.** Run 2 extends the HTTP runner to 23 checks and adds webhook security/replay tests plus a signed simulation. See [verification/run2/REPORT.md](verification/run2/REPORT.md) for current results; the earlier reports remain historical evidence.

## Configuration and extension points

Adding a shop means adding `config/shops/<shop_id>.json`, with a matching `shop_id` and the same schema. It is discovered on each request, validated, shown in the switcher/health page and synchronized into the registry on database-backed pages. No PHP changes or manual registry insert are needed. A test creates a third shop file in an isolated config directory, discovers and loads it, then removes the fixture. Invalid files appear on Config Health and are excluded from selectable shops; existing orders remain stored.

Use `ShopConfigRepositoryInterface` for a future MySQL config repository. The file repository strips `_comment*` recursively and validates every load. `LanguageResolver` handles dotted keys with per-key default-language fallback and logs second misses as JSON lines under `storage/logs/language.log`.

Voice providers implement `VoiceProviderInterface`; add a provider class and one factory map entry. `ElevenLabsProvider` reads its key from the supplied environment array and deliberately throws on synthesis. All supplied voice IDs are placeholders, including default Tamil voices. Health reports fallback *and* that the fallback is still unusable. Changing selected gender needs only JSON edits.

The ElevenLabs adapter and browser form both call `OrderIntakeService::ingest(array $payload)`. `IntakeResult` contains an HTTP-like code, message and order. JSON data is retained semantically (not byte-for-byte). `call_id` is globally unique and case-sensitive. The webhook owns a transaction containing its audit row, order, items and initial history. Edits and transitions lock the order row and are transactional.

## Assumptions and limits

- PHP 8.0 is supported with a small `array_is_list` compatibility function. No DTO-only `Order`, `OrderItem`, or `Shop` classes were added; associative records keep the prototype small. Services own business rules; repositories own SQL; the presenter prepares display data.
- Database JSON values use LONGTEXT for MySQL compatibility. PDO uses native prepared statements, exception mode and utf8mb4. PHP and MySQL sessions use India time (Asia/Kolkata / +05:30), defining “today”.
- Config prices are INR prototype estimates. The schema supplies one price per SKU and no alternate-unit conversion rates, so each displayed line is config price × selected quantity. For example, `gram` and `packet` share the same numeric SKU price in this prototype; define conversion/per-unit pricing before any real monetary use. Unknown items display “Not priced” and are excluded from the known-item subtotal. `never_show` still permits owner display, as requested.
- Quantities must be positive, at most 999999999, with up to three decimals. Unknown item units retain the imported value during edits because no `allowed_units` exist for them. Imported options are retained and shown; the owner can edit only quantity and unit.
- Required address fields apply to pickup as well as delivery because the schema makes them unconditional. Payment preference may be blank; delivery defaults from config on import. Requested time is free text, not a reservation.
- Payload missing-fields notes merge with computed requirements. On save, known requirements are recomputed; extra extraction notes remain until explicitly marked resolved. Filling details never moves status automatically.
- Global call-ID constraints handle duplicate-key races. Run 2 adds a six-request concurrent replay check; sustained load testing remains out of scope.
- The owner PIN is a demo session guard, not multi-owner authentication. Setup and manual import are local-demo facilities. Signed webhooks are implemented; notifications, payments, stock promises and real-account voice-call verification are not included.
- Seed data uses clearly fictional names, `0000000000` phones and invalid demo pincodes. Repeating setup preserves edits and timestamps. If you revisit on a later day, today's section may be empty; import a fresh sample. Original seed rows are not modified just to refresh dates.
- A shop's registry is updated on normal database-backed requests and setup. Config Health intentionally works without any DB configuration.

## After local Run 2 verification

Follow [RUN2.md](RUN2.md) to configure the hosted agent, real voice IDs, extraction fields and real webhook signing secret. Before deployment add proper owner authentication, rate limits, HTTPS, permissions, retention rules, a private document root and controlled setup access. Clarify unit pricing/conversions before using totals for payment. WhatsApp/status delivery remains a separate explicitly authorized integration.
