// Assemble the report from recorded evidence; never label a missing test log as passed.
const fs = require('fs');
const path = require('path');
const root = __dirname;
const read = name => {
  const bytes=fs.readFileSync(path.join(root,'logs',name));
  return bytes.toString(bytes[0]===0xff&&bytes[1]===0xfe?'utf16le':'utf8').replace(/^\uFEFF/,'');
};
const json = name => JSON.parse(read(name));
const a=json('apache-mobile.json'), b=json('builtin-mobile.json');
const suite = (title, name) => `### ${title}\n\n| Check | Result |\n|---|---|\n` + read(name).split(/\r?\n/).filter(l=>/^(PASS|FAIL) /.test(l)).map(l=>`| ${l.slice(l.indexOf(' ')+1).replaceAll('|','\\|')} | ${l.startsWith('PASS')?'PASS':'FAIL'} |`).join('\n') + '\n';
const simulations = ['builtin-simulation.txt','apache-simulation.txt'].map(n=>`### ${n}\n\n\`\`\`text\n${read(n).trim()}\n\`\`\`\n`).join('\n');
const mobile = [a,b].map(l=>`| ${l.name} | ${l.result} | ${l.screenshots.length} full-page screenshots; ${l.checks.filter(c=>c.navTargets).length} mobile screens checked; ${l.errorResponses.length} expected wrong-PIN 422, ${l.failedRequests.length} failed requests |`).join('\n');
const nav = a.checks.find(c=>c.navTargets).navTargets.map(t=>`| ${t.text} | ${t.width} | ${t.height} |`).join('\n');
let text=`# Run 2 verification report

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

\`\`\`powershell
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
\`\`\`

The configured local simulation secret was generated randomly into gitignored config/env.php and copied privately to Apache, never printed. Real account settings were not created or modified.

## Checks and results

${suite('Existing 34 config/service/MySQL checks','regression-tests.txt')}
${suite('Built-in HTTP suite: original 19 plus 4 webhook checks','builtin-http.txt')}
${suite('Apache HTTP suite: original 19 plus 4 webhook checks','apache-http.txt')}
${suite('Built-in webhook edge/security checks','builtin-webhook-tests.txt')}
${suite('Apache webhook edge/security checks','apache-webhook-tests.txt')}
${simulations}
### Browser checks

| Server | Result | Evidence summary |
|---|---|---|
${mobile}

Both flows exercised owner login, list/detail, sample-prefill buttons, complete/missing imports, wrong PIN, approval/history and Config Health. Navigation measurements are asserted at every captured mobile step. Document scrollWidth remained at most 390px. No unexpected console/network failures were recorded.

Apache measured navigation at 390px:

| Link | Width px | Height px |
|---|---:|---:|
${nav}

Desktop at 1440px: min-height/min-width of 44px do not apply; original approximately 41.59px link heights remain. Raw mobile and desktop dimensions are in apache-mobile.json and builtin-mobile.json. Screenshots include both mobile and desktop Config Health.

### Migration and syntax checks

${read('lint.txt').trim()}

Migration output: ${read('migration.txt').trim()} Native JSON columns, FK and unique indexes were read back from MySQL in logs/database.json. calls.shop_id matches the existing VARCHAR(100) shops.shop_id. Simulation calls have valid JSON audit payloads and one associated order/history row each.

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
10. Tunnel instructions were corrected for the actual /public path. Tunnel to 127.0.0.1:8087 and use /webhook/elevenlabs.php, or tunnel to Apache's origin and use /voiceshop/public/webhook/elevenlabs.php.

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
`;
fs.writeFileSync(path.join(root,'REPORT.md'),text);
console.log('Saved verification/run2/REPORT.md from evidence logs.');
