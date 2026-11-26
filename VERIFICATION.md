# Run 1 — Final verification report

Verified on 25 November 2026 with PHP 8.2.30 and local Wamp MySQL 9.1.0. The 34 service/MySQL checks also passed on PHP 8.0.30. All 44 PHP files passed syntax checks on both versions. The source workspace was empty apart from Task-101.txt.

## 1. What was built (file/class list with one line each)

- `bootstrap.php`: handwritten namespace autoloader, India timezone and PHP 8.0 compatibility helper.
- `config/env.example.php`: environment-only DB, owner PIN and future API-key settings; `config/env.php` is gitignored.
- `config/shops/panipuri_stall_01.json`: original complete Tamil street-stall config.
- `config/shops/grocery_store_02.json`: original grocery config with intentionally partial English.
- `src/Config/ShopConfigRepositoryInterface.php`: storage-independent config contract.
- `src/Config/FileShopConfigRepository.php`: discovery, recursive comment removal, validation and safe loading.
- `src/Config/ConfigValidator.php`: blocking structural/schema checks and nonblocking voice warnings.
- `src/Config/ValidationResult.php`: validation error/warning result.
- `src/Config/ConfigException.php`: readable config failure list.
- `src/Language/LanguageResolver.php`: dotted per-key fallback with source metadata and missing-key log.
- `src/Voice/VoiceProviderInterface.php`: provider contract.
- `src/Voice/ElevenLabsProvider.php`: environment-configured, network-free synthesis stub.
- `src/Voice/VoiceProviderFactory.php`: provider map and unknown-provider error.
- `src/Voice/VoiceResolver.php`: selected gender, placeholder fallback and unusable-default metadata.
- `src/Db/Database.php`: native prepared-statement PDO connection using utf8mb4.
- `src/Db/Migrator.php`: idempotent table creation in the configured DB.
- `database/schema.sql`: database, four InnoDB tables, foreign keys, indexes and unique call ID.
- `src/Domain/StatusFlow.php`: allowed transitions and shared owner-facing status labels.
- `src/Repository/ShopRepository.php`: file-config registry synchronization.
- `src/Repository/OrderRepository.php`: prepared order lookup, listing, insertion and updates.
- `src/Repository/OrderItemRepository.php`: prepared item insert/read/update.
- `src/Repository/StatusLogRepository.php`: append/read status history.
- `src/Service/PayloadValidator.php`: payload types, lengths, limits, quantity and unit validation.
- `src/Service/MissingFieldsDetector.php`: config-derived missing fields merged with extraction notes.
- `src/Service/IntakeResult.php`: HTTP-like import result.
- `src/Service/OrderIntakeService.php`: transactional intake, unique-key dedupe, flags and initial history.
- `src/Service/OrderEditService.php`: atomic field/item edits and missing-detail recomputation.
- `src/Service/OrderStatusService.php`: locked transitions, owner approval guard and history.
- `src/Service/OrderPresenter.php`: card sections, localized product names, config prices and preview data.
- `src/Service/DemoSeeder.php`: sample payloads and four idempotent fictional orders.
- `src/Http/App.php`: request routing, service wiring, thin form handling and readable errors.
- `src/Http/Csrf.php`: session tokens and POST verification.
- `src/Http/View.php`: centralized UTF-8 escaping and rendering.
- `public/index.php`: session-aware HTML front controller.
- `public/import.php`: manual webhook-simulator entry point.
- `public/setup.php`: browser setup entry point.
- `public/assets/css/app.css`: responsive 360px-capable styling, status colours and form states.
- `public/assets/js/import.js`: two sample JSON prefill buttons.
- `views/layout.php`: shared navigation, shop selector, demo badge and owner toggle.
- `views/orders.php`: today's, missing-details and awaiting-approval card sections/counts.
- `views/detail.php`: editable details/items, prices, missing fields, transitions, flags, previews and history.
- `views/import.php`: import textarea, shop selector, sample buttons and result link.
- `views/health.php`: errors, warnings, enabled languages, voice resolution and live text fallback.
- `views/setup.php`: CSRF-protected idempotent setup action.
- `views/error.php`: readable failure screen with recovery links.
- `tests/run_tests.php`: plain-PHP unit/service and isolated MySQL integration checks.
- `tests/integration.php`: CSRF-protected browser integration-test launcher.
- `tests/http_tests.php`: localhost HTTP/session/form regression checks.
- `README.md`: exact Wamp setup, checklist, extensions, assumptions and Run 2 notes.
- `.gitignore`: excludes real environment settings and generated logs.

## 2. What was tested

**Final result: 34 service/MySQL checks PASS, 19 HTTP checks PASS, zero final failures.** Every PHP file passed lint on PHP 8.0 and 8.2. MySQL integration used `voiceshop_test`; HTTP smoke tests used local `voiceshop` and left only the four demo orders. Test orders and temporary broken configs were removed.

### Requested README checklist

| Check | Result |
|---|---|
| Dashboard/demo label and Tamil | PASS for HTTP HTML content and intact UTF-8 Tamil; visual glyph rendering NOT RUN (no graphical browser available). |
| Three order sections contain seeds | PASS via HTTP. |
| Complete sample → awaiting approval | PASS via service and HTTP. |
| Incomplete sample → missing details/highlight | PASS via service and HTTP HTML classes. |
| Same call_id dedupes | PASS via intake, database constraint and HTTP. |
| Edit fills missing; offers transition | PASS via transactional service and HTTP. |
| Non-owner disabled; owner confirms/history | PASS via HTML, forged POST rejection, session PIN and service history checks. |
| Disallowed transition rejected | PASS via service and forged HTTP POST. |
| Health English fallback and warnings | PASS via resolver tests and HTTP. |
| Broken language product key shows error | PASS via validator and temporary invalid config HTTP test. |

360px visual layout, native browser interactions and actual sample-button clicks: **NOT RUN**; CSS/markup/JavaScript reviewed, samples exercised through HTTP instead. Apache under the final copied Wamp URL: **NOT RUN**; HTTP verification used the PHP 8.2 development server. No claim of a visual browser or production load test.

### Automated service/config tests — each PASS

1. Both exact seed configs validate.
2. Recursive comment stripping.
3. Broken default block rejected.
4. Mismatched product key rejected.
5. Bad voice.selected rejected.
6. Malformed voice type returns errors.
7. Invalid statuses, address subset and price visibility rejected.
8. Placeholder voices produce warnings only.
9. Partial English closing and delivery notice fall back to Tamil.
10. Dotted product fallback and English override.
11. Unknown language resolves to default.
12. Double miss returns null and writes log.
13. Voice placeholder fallback returns default gender and source.
14. Config-only voice gender switch.
15. Provider stub throws without a network call.
16. Unknown provider rejected.
17. Allowed/disallowed StatusFlow transitions.
18. Puri required fields, optional phone and deduplicated missing notes.
19. Grocery requires phone and pincode.
20. Complete payload has no missing fields.
21. Invalid quantity, unit and item-count limit rejected.
22. Third config discovered/loaded without engine edits.
23. DB complete intake and initial history.
24. DB call-ID deduplication through intake.
25. DB UNIQUE constraint rejects direct duplication.
26. DB non-owner approval rejected.
27. DB disallowed transition rejected.
28. DB owner approval and matching history.
29. DB missing fields and unknown free-text item flag.
30. DB missing details block approval-queue transition.
31. DB edit resolves missing fields without forcing status.
32. DB explicit queue transition succeeds after edit.
33. DB invalid edit is atomic.
34. DB imported price ignored by owner display (0.01 submitted, config 40 and line total 80 displayed).

### Automated HTTP tests — each PASS

1. Setup creates tables and four seeds.
2. Repeating setup dedupes seeds.
3. Dashboard sections, demo badge and Tamil UTF-8.
4. Import banner and two samples.
5. Complete import awaits approval.
6. Duplicate response returns existing order.
7. Approve disabled without owner mode.
8. Forged approval rejected server-side.
9. Owner PIN enables session.
10. Owner HTTP approval succeeds.
11. Disallowed HTTP transition rejected.
12. Incomplete HTTP import needs details.
13. Missing inputs highlighted and fallback preview shown.
14. Save offers explicit queue transition.
15. Invalid CSRF token rejected.
16. Malformed JSON produces readable error.
17. Config Health has English fallback and placeholder warnings.
18. Broken config produces an error list without a crash.
19. Shop switcher filters orders.

The first HTTP attempt failed because sandbox permissions blocked Wamp's session files. The server was rerun with approved session-directory access. An initial dashboard assertion also compared an apostrophe before HTML decoding; it was corrected. The final suites above both pass.

## 3. Setup steps as finally implemented

1. Start Wamp (green icon), select PHP 8.x, enable pdo_mysql and mbstring, and start MySQL.
2. Copy the project to `C:/wamp64/www/voiceshop/`.
3. Open `http://localhost/phpmyadmin` and create `voiceshop` with `utf8mb4_unicode_ci`, or import `database/schema.sql`.
4. Copy `config/env.example.php` to `config/env.php`, set DB credentials (typical local Wamp: root/empty password) and choose an owner PIN; `2468` is only a documented local-demo choice.
5. Open `http://localhost/voiceshop/public/setup.php` and click **Create tables & seed demo data**.
6. Open `http://localhost/voiceshop/public/index.php`.

## 4. Deviations from this prompt or the schema, and assumptions made

Schema keys and the two source configs are preserved. The suggested structure was simplified by using associative records instead of empty DTO classes and a DemoSeeder service behind the setup page instead of database/seed.php. There is no rewrite dependency.

Owner PIN has no hardcoded default; the example leaves it blank. The local ignored env used the documented demo PIN for testing. Times use India time. LONGTEXT stores JSON. Imported JSON is preserved semantically, including untrusted price fields, while display uses only config prices.

One config price applies numerically to any allowed unit because the schema has no conversions; totals are explicitly prototype estimates. Unknown items are unpriced, and their units cannot be edited into an invented allowed-unit list. Quantity bounds match DECIMAL(12,3). Pickup still requires address fields because requirements are unconditional. Extra extraction missing-field notes require explicit acknowledgement on edit.

All default voices are also placeholders, so fallback reports an unusable default instead of pretending there is a real voice. Sample call IDs use a timestamp; reusing the same prefilled payload intentionally exercises dedupe. Seeds remain idempotent without refreshing timestamps or resetting owner edits.

The third-shop test proves automatic file discovery and loading; switcher/registry code iterates the same discovered configs. Adding another valid file needs no PHP edits.

## 5. Known limitations and what Run 2 will need

No real ElevenLabs calls, telephony, LLM extraction, webhook auth, messaging or payments. No graphical browser, Apache-deployment or concurrent-load test was performed. A unique constraint plus duplicate-key exception handling covers intake races, but stress testing remains outstanding.

Run 2 needs usable voice IDs, provider networking, call routing, extraction validation, authenticated webhook handling, retry/observability work and clear per-unit pricing. Deployment requires proper owner authentication, HTTPS, rate limiting, private configuration/log storage and restricted setup access. The present owner PIN/session and manual-import interface are a local prototype.
