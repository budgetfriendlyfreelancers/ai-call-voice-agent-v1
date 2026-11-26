// Verification only. Run: node verification/mobile-check.js [apache|builtin|both]
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const root = path.join(__dirname, process.argv[3] || 'run2');
fs.mkdirSync(path.join(root, 'logs'), {recursive:true});
fs.mkdirSync(path.join(root, 'screenshots'), {recursive:true});
const ua = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36';
const targets = { apache: 'http://localhost/voiceshop/public', builtin: 'http://127.0.0.1:8087' };
async function run(browser, name, base) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: ua });
  const page = await context.newPage();
  const log = { name, base, browser: browser.version(), viewport: '390x844 @2x', checks: [], screenshots: [], consoleErrors: [], failedRequests: [], errorResponses: [], responses: [], tapTargets: [], orders: {} };
  const stamp = Date.now().toString();
  let step = 0;
  page.on('console', m => { if (m.type() === 'error') log.consoleErrors.push(m.text()); });
  page.on('pageerror', e => log.consoleErrors.push('PAGE ERROR: ' + e.message));
  page.on('requestfailed', r => log.failedRequests.push({ url: r.url(), failure: r.failure() }));
  page.on('response', r => { log.responses.push({ status: r.status(), url: r.url() }); if (r.status() >= 400) log.errorResponses.push({ status: r.status(), url: r.url() }); });
  async function shot(label) {
    const file = path.join(root, 'screenshots', `${String(++step).padStart(2,'0')}-${name}-${label}.png`);
    await page.screenshot({ path: file, fullPage: true }); log.screenshots.push(file);
    const metrics = await page.evaluate(() => ({ width: innerWidth, scrollWidth: document.documentElement.scrollWidth, offenders: [...document.querySelectorAll('body *')].filter(e => e.getBoundingClientRect().right > innerWidth + 1).slice(0,10).map(e => ({tag:e.tagName, cls:e.className, text:e.textContent.slice(0,100)})) }));
    log.checks.push({ name: label, url: page.url(), ...metrics });
    if (label !== 'health-desktop') assert.ok(metrics.scrollWidth <= 390, `${label}: horizontal scroll ${metrics.scrollWidth}`);
    if (label !== 'health-desktop') {
      const navTargets = await page.locator('nav a').evaluateAll(els=>els.map(e=>({text:e.textContent,width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height})));
      log.checks[log.checks.length-1].navTargets = navTargets;
      assert.ok(navTargets.every(t=>t.width>=44&&t.height>=44), 'Navigation tap targets must be at least 44x44');
    }
    return file;
  }
  async function nav(label) { await page.locator('nav').getByRole('link', { name: label, exact: true }).click(); await page.waitForLoadState('load'); }
  async function openOwner() { const details=page.locator('details.owner'); if (!(await details.getAttribute('open') !== null)) await details.locator('summary').click(); }
  async function login(pin) { await openOwner(); await page.locator('input[name="pin"]').fill(pin); await page.getByRole('button', { name: 'Enable owner mode', exact:true }).click(); await page.waitForLoadState('load'); }
  async function openCard(customer) { await page.locator('a.card').filter({ hasText: customer }).first().click(); await page.waitForLoadState('load'); }
  try {
    const first = await page.goto(base + '/index.php'); assert.equal(first.status(),200);
    await login('2468'); assert.match(await page.locator('details.owner summary').innerText(), /ON/); await shot('owner-login');
    await nav('Orders'); assert.ok(await page.locator('a.card').count()); await shot('order-list');
    await page.locator('a.card').first().click();
    for (const text of ['Customer & delivery','Items · Owner prices','Status history']) assert.ok(await page.getByRole('heading',{name:text,exact:true}).isVisible());
    await shot('existing-detail');
    await nav('Import'); await page.getByRole('button',{name:'Sample JSON: complete',exact:true}).click();
    const good=JSON.parse(await page.locator('#payload').inputValue());
    good.call_id=`VERIFY-${name}-${stamp}-GOOD`; good.customer.name=`Verification ${name} ${stamp}`; good.customer.phone='000'+stamp.slice(-7);
    log.orders.good={call_id:good.call_id,customer:good.customer.name,phone:good.customer.phone};
    await page.locator('#payload').fill(JSON.stringify(good,null,2)); await shot('complete-import-form');
    await page.getByRole('button',{name:'Import order',exact:true}).click(); assert.match(await page.locator('main').innerText(), /Order imported/); await shot('complete-import-result');
    await nav('Orders'); const goodCard=page.locator('a.card').filter({hasText:good.customer.name}).first(); assert.match(await goodCard.innerText(),/Awaiting Merchant Approval/); await shot('new-order-listed');
    await openCard(good.customer.name); log.orders.good.id=Number(new URL(page.url()).searchParams.get('id'));
    await nav('Import'); await page.getByRole('button',{name:'Sample JSON: missing fields',exact:true}).click();
    const bad=JSON.parse(await page.locator('#payload').inputValue()); bad.call_id=`VERIFY-${name}-${stamp}-MISSING`; bad.customer.name=`Missing ${name} ${stamp}`;
    log.orders.missing={call_id:bad.call_id,customer:bad.customer.name};
    await page.locator('#payload').fill(JSON.stringify(bad,null,2)); await page.getByRole('button',{name:'Import order',exact:true}).click();
    await nav('Orders'); const badCard=page.locator('a.card').filter({hasText:bad.customer.name}).first(); assert.match(await badCard.innerText(),/Needs Details/); assert.match(await badCard.innerText(),/missing details/); await shot('needs-details-list');
    await openCard(bad.customer.name); log.orders.missing.id=Number(new URL(page.url()).searchParams.get('id'));
    log.missingFields=await page.locator('main .error').innerText();
    log.missingStyles=await page.locator('input.missing').evaluateAll(els=>els.map(e=>({name:e.name,className:e.className,border:getComputedStyle(e).borderColor,background:getComputedStyle(e).backgroundColor})));
    assert.equal(log.missingStyles.length,5); await shot('needs-details-highlight');
    await openOwner(); await page.getByRole('button',{name:'Leave owner mode',exact:true}).click();
    await nav('Orders'); await openCard(good.customer.name); assert.ok(await page.getByRole('button',{name:'Approve → Confirmed',exact:true}).isDisabled());
    await login('0000'); assert.match(await page.locator('main .error').innerText(),/Owner PIN is incorrect or not configured/); await shot('wrong-pin-rejected');
    await nav('Orders'); await openCard(good.customer.name); assert.match(await page.locator('main .heading .badge').innerText(),/Awaiting Merchant Approval/); assert.ok(await page.getByRole('button',{name:'Approve → Confirmed',exact:true}).isDisabled()); await shot('still-unapproved');
    await login('2468'); await openCard(good.customer.name); await page.getByRole('button',{name:'Approve → Confirmed',exact:true}).click();
    assert.equal(await page.locator('main .heading .badge').innerText(),'Confirmed'); await shot('approved');
    await nav('Orders'); await openCard(good.customer.name);
    const history=page.locator('section.panel').filter({has:page.getByRole('heading',{name:'Status history',exact:true})});
    log.history=await history.innerText(); assert.match(log.history,/Awaiting Merchant Approval → Confirmed/); assert.match(log.history,/owner/); await shot('approved-history');
    await nav('Config Health'); assert.equal(await page.locator('main .success').count(),2); assert.equal(await page.locator('main .warning').count(),6);
    log.health=await page.locator('main').innerText(); assert.match(log.health,/No usable voice configured yet; fallback is also a placeholder/); await shot('health-mobile');
    log.tapTargets=await page.locator('a,button,summary,input,select').evaluateAll(els=>els.map(e=>({tag:e.tagName,text:(e.innerText||e.getAttribute('aria-label')||e.name||'').slice(0,70),width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height})).filter(e=>e.width>0&&e.height>0&&(e.width<44||e.height<44)));
    await page.setViewportSize({width:1440,height:1000}); await shot('health-desktop');
    log.desktopNavTargets=await page.locator('nav a').evaluateAll(els=>els.map(e=>({text:e.textContent,width:e.getBoundingClientRect().width,height:e.getBoundingClientRect().height,minHeight:getComputedStyle(e).minHeight,minWidth:getComputedStyle(e).minWidth})));
    assert.ok(log.desktopNavTargets.every(t=>t.minHeight!=='44px'&&t.minWidth!=='44px'), 'Mobile minimum sizes must not apply to desktop');
    log.result='PASS';
  } catch(e) { log.result='FAIL'; log.error=e.stack; try { await shot('failure'); } catch {} }
  finally { fs.writeFileSync(path.join(root,'logs',`${name}-mobile.json`),JSON.stringify(log,null,2)); await context.close(); }
  console.log(`${name}: ${log.result}, ${log.screenshots.length} screenshots, ${log.errorResponses.length} HTTP errors, ${log.failedRequests.length} failed requests`);
  if(log.error) console.error(log.error);
  return log.result==='PASS';
}
(async()=>{
  const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  let ok=true;
  try { for(const [name,base] of Object.entries(targets)) if(!process.argv[2]||process.argv[2]==='both'||process.argv[2]===name) ok=(await run(browser,name,base))&&ok; }
  finally { await browser.close(); }
  process.exitCode=ok?0:1;
})().catch(e=>{console.error(e);process.exitCode=1;});
