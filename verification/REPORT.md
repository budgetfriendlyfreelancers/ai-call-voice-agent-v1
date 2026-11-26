# VoiceShop Run 1 verification

Verified 25 November 2026. **Apache deployment and both real browser flows pass. One minor tap-target issue remains; no product code was changed.**

Apache: http://localhost/voiceshop/public/index.php  
Built-in server, preserved: http://127.0.0.1:8087/index.php

Evidence paths below are relative to `D:/My-Projects/Ai-Call-Voice-Agent/verification/` unless written in full.

## 1. Environment

- Windows 10 Pro Education, build 10.0.19045: `logs/windows.txt`.
- Wampserver 3.4.2; Apache and MySQL services Running: `logs/services.txt`, `logs/wamp-version.txt`. The tray icon colour was not visually inspected.
- Apache 2.4.62 on port 80; web PHP **8.1.31**, SAPI **apache2handler**, `pdo_mysql=true`, `mbstring=true`: temporary HTTP probe returned **200**, recorded in `logs/apache-runtime.json` and `logs/apache-runtime-headers.txt`. The deployed probe was removed (`logs/probe-removed.txt`).
- CLI used: PHP **8.2.30** (`logs/cli-php.txt`). MySQL **9.1.0**, database `voiceshop`, host 127.0.0.1:3306 (`logs/database-after-seed.txt`).
- Playwright **1.65.1**, installed only in `verification/`; existing Chrome **157.0.8103.79**, headless, Android mobile user agent, **390×844**, device scale 2. Desktop health capture: 1440×1000. Versions and metrics: `logs/playwright-version.txt`, `logs/apache-mobile.json`, `logs/builtin-mobile.json`.

## 2. Checklist

| Item | Result | Evidence |
|---|---|---|
| Apache deploy | PASS | `logs/deploy.txt`; `curl-orders` HTTP 200; 45 source/copy product/config hashes match in `logs/product-copy-integrity.txt`. |
| Setup/seed | PASS | `curl-setup-seed` HTTP 200, “DEMO-0001: duplicate - already imported”; SQL: 2 shops, 4 orders, 5 items, 5 history rows before browser fixtures. |
| HTTP checks on Apache URL | PASS | `logs/apache-http-suite.txt`: **19 PASS, 0 FAIL**; URL/status/body excerpts in `logs/curl-results.json`. |
| .htaccess/rewrite | PASS | No project .htaccess or router script; plain PHP URLs work. mod_rewrite=true; localhost AllowOverride All (`logs/apache-config.txt`, web probe). |
| Mobile login | PASS | Both servers: screenshot 01, PIN 2468 enables Owner mode; login POST 302 then dashboard 200. |
| Order list | PASS | Both: screenshots 02/06, HTTP 200; mobile `scrollWidth=390`, no horizontal scroll. |
| Order detail | PASS | Both: screenshot 03; customer, items, status and history visible. |
| Manual import | PASS | Both: screenshots 04–06; real sample-button clicks, textarea fills and submits; POST 201; new cards show Awaiting Merchant Approval. |
| NEEDS_DETAILS highlighting | PASS | Both: screenshots 07/08; orders #17/#19; DB status NEEDS_DETAILS and five missing fields. |
| Approve with PIN 2468 | PASS | Both: screenshot 11; approval POST 302, final detail 200, CONFIRMED. |
| Wrong PIN rejected | PASS | Both: screenshots 09/10; POST 422, “Owner PIN is incorrect or not configured.”; order remains awaiting approval, Approve disabled. |
| Status history updates | PASS | Both: screenshot 12 and `logs/status-history-db.json`; transition and owner actor confirmed in SQL. |
| Config Health placeholders | PASS | Both: screenshots 13/14; six warning messages, three explicitly unusable resolved-voice panels; curl HTTP 200, zero PHP error markers. |
| Config Health validator: shop 1 | PASS | Anna Masala Puri Stall: “Configuration valid”, zero errors, two warnings; `logs/config-audit.json`. |
| Config Health validator: shop 2 | PASS | Lakshmi Provision Store: “Configuration valid”, zero errors, four warnings; `logs/config-audit.json`. |
| 44px navigation tap-target goal | FAIL | Both: nav links about 41.59px high; Setup about 43.45px wide. Measured in mobile JSON logs. |

Commands used (from the original project, except the package install):

```text
robocopy D:/My-Projects/Ai-Call-Voice-Agent C:/wamp64/www/voiceshop /E /XD .git verification node_modules /R:1 /W:1
C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off C:/wamp64/www/voiceshop/tests/http_tests.php http://localhost/voiceshop/public
powershell -NoProfile -File verification/curl-check.ps1
node verification/mobile-check.js both
C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off verification/config-audit.php
C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off verification/db-evidence.php
```

`curl-check.ps1` uses curl.exe with a cookie jar. Setup, list, detail, import form, health, CSS and JS returned **200**; import POST **201**; login/approval **302**. Redirects were relative (`index.php`, `index.php?page=detail&id=13&saved=1`) and resolved inside `/voiceshop/public/`. Cookie: `path=/; HttpOnly; SameSite=Lax`. Complete headers/body excerpts are retained under `logs/curl-*`.

**Missing fields:** `phone`, `address.house_no`, `address.street`, `address.area`, `address.pincode`. Detail shows the label “Missing”, an error panel and `input.missing`: border `rgb(183, 80, 45)` (#b7502d), background `rgb(255, 245, 235)` (#fff5eb). List shows Needs Details and “5 missing details”. Tamil glyphs render in the inspected screenshots; no cut-off text or horizontal overflow was observed.

**Database history:** order #16 (Apache) changed AWAITING_MERCHANT_APPROVAL → CONFIRMED at **2026-11-25 23:27:21**, actor `owner`; #18 (built-in) at **23:27:54**, actor `owner`. This schema uses CONFIRMED, not APPROVED. Exact prepared SELECT queries, parameters and rows are in `logs/status-history-db.json`.

**Browser errors:** each final run recorded exactly one HTTP 422 and its browser console resource-error message, both from the intentional wrong-PIN test. Zero failed network requests, uncaught page errors or unexpected 4xx/5xx responses. See both mobile JSON logs.

**Config checks:** the UI displays aggregate validity and individual warning/error messages, not a separate success line for every check. The independent config/DB audit recorded:

| Check | Anna Masala Puri Stall | Lakshmi Provision Store |
|---|---|---|
| Required top-level keys | PASS | PASS |
| Enabled languages have blocks | PASS | PASS |
| Default language enabled | PASS | PASS |
| Complete default block/dashboard texts | PASS | PASS |
| Language product keys match SKUs | PASS | PASS |
| Selected gender exists | PASS | PASS |
| Default product display names | PASS | PASS |
| Allowed price visibility | PASS | PASS |
| Exactly six statuses | PASS | PASS |
| Status-flow codes valid | PASS | PASS |
| Address must_have subset | PASS | PASS |
| DB registry matches stripped config | PASS | PASS |
| Usable voice IDs | WARN: 2 placeholders | WARN: 4 placeholders |

Exact warning messages, all visibly shown and verified against JSON and the DB registry:

```text
Anna Masala Puri Stall:
ta-IN/female: unfilled voice_id (REPLACE_WITH_VOICE_ID_TA_F).
ta-IN/male: unfilled voice_id (REPLACE_WITH_VOICE_ID_TA_M).

Lakshmi Provision Store:
ta-IN/female: unfilled voice_id (REPLACE_WITH_VOICE_ID_TA_F_2).
ta-IN/male: unfilled voice_id (REPLACE_WITH_VOICE_ID_TA_M_2).
en-IN/female: unfilled voice_id (REPLACE_WITH_VOICE_ID_EN_F_2).
en-IN/male: unfilled voice_id (REPLACE_WITH_VOICE_ID_EN_M_2).
```

Each resolved voice panel says **“No usable voice configured yet; fallback is also a placeholder.”** Grocery English resolves to the Tamil female placeholder. “Configuration valid” denotes structural validity; the voices are not marked usable. No real SKU or real voice was falsely flagged. Health HTTP 200 and absence of PHP warnings/notices: `logs/health-html-audit.txt`, `logs/curl-health.html`.

## 3. Bugs found

1. **Low severity — navigation tap targets below 44px.** Open any page at 390px and measure `nav a`: height 41.59px; Setup width 43.45px. All links were clickable and text remained visible, but they fall short of the chosen thumb-friendly target. Evidence: `tapTargets` in both final mobile logs. No functional blocker was found in the tested flows.

## 4. Fixes applied

**None to product code.** Verification-only corrections: curl's payload-file argument was assembled separately after a PowerShell expression error; the browser test expected six voice warnings after initially expecting eight. Both corrected harnesses passed. The first-attempt browser logs and failure screenshots remain for transparency. No UI/CSS, application PHP, config schema or database schema changes were made.

## 5. Screenshot list

28 final screenshots plus 2 first-attempt health screenshots. The `13-*-failure.png` files record the incorrect test expectation, not an application failure.

- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/01-apache-owner-login.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/01-builtin-owner-login.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/02-apache-order-list.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/02-builtin-order-list.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/03-apache-existing-detail.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/03-builtin-existing-detail.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/04-apache-complete-import-form.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/04-builtin-complete-import-form.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/05-apache-complete-import-result.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/05-builtin-complete-import-result.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/06-apache-new-order-listed.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/06-builtin-new-order-listed.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/07-apache-needs-details-list.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/07-builtin-needs-details-list.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/08-apache-needs-details-highlight.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/08-builtin-needs-details-highlight.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/09-apache-wrong-pin-rejected.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/09-builtin-wrong-pin-rejected.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/10-apache-still-unapproved.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/10-builtin-still-unapproved.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/11-apache-approved.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/11-builtin-approved.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/12-apache-approved-history.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/12-builtin-approved-history.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/13-apache-failure.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/13-apache-health-mobile.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/13-builtin-failure.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/13-builtin-health-mobile.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/14-apache-health-desktop.png
- D:/My-Projects/Ai-Call-Voice-Agent/verification/screenshots/14-builtin-health-desktop.png

## 6. Apache versus built-in server

- No deployment path, relative asset or rewrite issue found. CSS/JS HTTP 200. `/voiceshop/public` redirects and sessions worked on Apache without a base-path setting. No hardcoded 8087 URL was found in product source.
- Apache uses PHP 8.1.31; the existing built-in server uses PHP 8.2.30. Both browser flows passed. The original D: project and port 8087 remain intact, with final HTTP 200 responses (`logs/final-availability.txt`).
- The Apache copy uses the existing environment settings for `voiceshop` at 127.0.0.1:3306 and the requested demo PIN; no credentials were printed. No .htaccess was added.
- Requested Apache/PHP error-log tails were captured before/after. They were unchanged; their latest file timestamps predate this run (1/2 November). Old unrelated errors are not attributed to VoiceShop. Actual access log is `C:/wamp64/logs/access.log`, not `apache_access.log`; VoiceShop entries were captured from the real path. See `logs/error-log-comparison.txt`, `logs/log-metadata.txt`, `logs/apache-access-after.txt`.

## 7. Impossible or skipped steps and alternatives

- No requested application flow remained skipped. Headless Chrome ran on both servers, using real clicks/fills after the initial navigation.
- Sandbox restrictions initially blocked system inspection, npm downloading and Chrome process launch. Approved elevated executions completed them; no curl-only substitute was needed for the browser flows.
- There is no production API/webhook endpoint or APPROVED status in Run 1. The implemented manual import form/service and CONFIRMED transition were tested instead. No voice-provider call was attempted.
- Wamp tray colour was not observed; Running service states plus successful Apache/MySQL requests verified availability.

## 8. Remaining risks and recommended next steps

- Increase navigation target dimensions to at least 44×44px in a separate UI change; no urgent functional repair is indicated by this run.
- Retain the local-demo scope: owner PIN/session, setup access, placeholder voices and unconverted alternate-unit pricing are not production readiness.
- Browser fixtures were retained for inspection: final verified orders #16–#19, curl order #13 and four first-attempt browser orders. Total now: **2 shops, 13 orders, 14 items, 19 status-log rows**. Original four demo orders remain intact. All verification fixtures have distinguishable VERIFY call IDs and fictional customer data.
- Headless mobile emulation is not physical-device or concurrency/load testing. Keep `verification/mobile-check.js`, the lockfile and logs for repeatable regression checks.
