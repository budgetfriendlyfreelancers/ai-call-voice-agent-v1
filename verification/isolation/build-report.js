const fs = require('fs');
const path = require('path');
const root = __dirname;
function read(name) {
  const bytes = fs.readFileSync(path.join(root, 'logs', name));
  return bytes.toString(bytes[0] === 255 && bytes[1] === 254 ? 'utf16le' : 'utf8').replace(/^\uFEFF/, '');
}
const groups = [
  ['HTTP: PHP 8087 + isolation 8088', 'http-builtin.txt', 63],
  ['HTTP: Apache + isolation 8088', 'http-apache.txt', 63],
  ['Security: PHP 8087', 'security-builtin.txt', 21],
  ['Security: Apache', 'security-apache.txt', 21],
  ['Security: isolated 8088', 'security-isolated.txt', 21],
  ['Regression including database integration', 'regression.txt', 34],
  ['Simulation: PHP 8087', 'simulation-builtin.txt', 3],
  ['Simulation: Apache', 'simulation-apache.txt', 3],
  ['Simulation: isolated 8088', 'simulation-isolated.txt', 3],
];
let report = `# Dedicated webhook isolation verification

Verified 2026-11-26 (Asia/Calcutta). The original public app is unchanged. The new router delegates only POST /webhook/elevenlabs.php to the existing webhook entry. It rejects other paths before loading application code and never serves static files. No signature, extraction, transaction or intake logic was duplicated.

## Results

| Suite | Result |
|---|---|
`;
for (const [name, file, expected] of groups) {
  const lines = read(file).split(/\r?\n/).filter(x => /^(PASS|FAIL) /.test(x));
  if (lines.length !== expected || lines.some(x => x.startsWith('FAIL'))) throw Error('Incomplete/failing evidence: ' + file);
  report += `| ${name} | ${expected}/${expected} PASS |\n`;
}
for (const target of ['apache', 'builtin']) {
  const data = JSON.parse(read(target + '-mobile.json'));
  if (data.result !== 'PASS') throw Error('Browser failed: ' + target);
  report += `| Playwright ${target} | PASS; ${data.screenshots.length} screenshots; ${data.failedRequests.length} failed requests |\n`;
}
report += `
The HTTP suite preserves its original 23 expectations and adds 40 checks. The 21 security and 34 regression expectations are unchanged. Playwright assertions are unchanged; an optional output directory argument preserves earlier evidence. PHP 8.1 lint passed for both changed PHP files.

## Server and tunnel commands

Working directory: D:/My-Projects/Ai-Call-Voice-Agent. Apache and PHP 8087 were already running and were retained. The exact PHP 8088 command executed was:

\`\`\`powershell
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off -S 127.0.0.1:8088 -t webhook-public webhook-public/index.php
\`\`\`

Existing server startup commands (not re-executed during this fix):

\`\`\`powershell
Start-Service wampapache64
& C:/wamp64/bin/php/php8.2.30/php.exe -d xdebug.mode=off -S 127.0.0.1:8087 -t public
\`\`\`

Apache serves http://localhost/voiceshop/public. Its service was confirmed Running. The dedicated router argument is required. Loopback binding keeps 8088 off the LAN.

> NEVER tunnel Apache or 8087: those servers expose the dashboard, Config Health and owner login. Only 8088 may be a tunnel target.

Tunnel commands for the user (neither executed):

\`\`\`powershell
cloudflared tunnel --url http://127.0.0.1:8088
# Alternative:
ngrok http http://127.0.0.1:8088
\`\`\`

Configure https://<tunnel-host>/webhook/elevenlabs.php in ElevenLabs. Neither cloudflared nor ngrok was found on PATH. The prior real-account instructions reserve tunnel launch and real calls for the user. Public-provider delivery was not tested; local isolation and signed processing were tested.

## Exact suite commands

Each command was captured under logs/ using PowerShell Tee-Object. The Apache HTTP runner uses PHP 8.1 and the deployed copy because its existing Health check temporarily creates a config beside that runner. Other PHP commands use PHP 8.2.30 with Xdebug disabled.

\`\`\`powershell
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
\`\`\`

## HTTP response evidence

Actual curl output (logs/curl-proof.txt):

\`\`\`text
${read('curl-proof.txt').trim()}
\`\`\`

Correctly signed requests use the existing CLI simulator, reading the secret from private config without printing it. Actual output on 8088:

\`\`\`text
${read('simulation-isolated.txt').trim()}
\`\`\`

## Web root contents

\`\`\`text
webhook-public/
└── index.php
\`\`\`

There are no assets, config files or other entry scripts in this directory. The original public/ directory remains in place.

## Deviations and notes

- The router reuses the original public/webhook/elevenlabs.php entry directly, which already invokes the shared controller/intake. No refactor was necessary.
- The server command adds the explicit router argument so all unknown paths receive the guarded JSON 404 rather than static-server fallback behavior.
- No public tunnel or real call was launched for the reasons above. Existing full-app servers were retained rather than restarted.
- An initial successful 63-check run emitted a local Xdebug log-permission warning. The recorded final run disables Xdebug and passes cleanly.
- Historical Run 2 tunnel instructions and their report generator now direct readers to 8088 only; historical test evidence remains intact.
- Simulation fixtures remain visible as designed. HTTP/security suites clean up their own fixtures.

## Full named PHP check results
`;
for (const [name,file] of groups) {
  report += `\n### ${name}\n\nSource: [${file}](logs/${file})\n\n| Result | Check |\n|---|---|\n`;
  for (const line of read(file).split(/\r?\n/).filter(x => /^(PASS|FAIL) /.test(x))) {
    const split=line.indexOf(' ');
    report += `| ${line.slice(0,split)} | ${line.slice(split+1).replace(/\|/g,'\\|')} |\n`;
  }
}
report += '\n## Playwright check results\n';
for (const target of ['apache','builtin']) {
  const data=JSON.parse(read(target+'-mobile.json'));
  report += `\n### ${target}: ${data.result}\n\nEvidence: [browser log](logs/${target}-mobile.json). All existing UI assertions passed, including import, owner PIN/approval, history, Config Health counts, mobile navigation target size and desktop sizing. The expected wrong-PIN HTTP 422 is an intentional check, not a failed test.\n\n| Result | Screen / navigation check | Width | Document width |\n|---|---|---|---|\n`;
  for (const check of data.checks) report += `| PASS | ${check.name} | ${check.width} | ${check.scrollWidth} |\n`;
}
fs.writeFileSync(path.join(root,'REPORT.md'),report);
console.log('Report generated from complete passing evidence.');
