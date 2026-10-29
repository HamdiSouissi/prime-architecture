import assert from 'node:assert/strict';
import axe from 'axe-core';
import { chromium } from 'playwright-core';

// Synthetic CI data only. Automated checks complement, but do not replace,
// keyboard, assistive-technology and human contrast reviews.
const base = 'http://localhost:8080';
const browser = await chromium.launch({ channel: 'chrome', args: ['--no-sandbox'] });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const findings = new Map();
function add(key, value, identity = value) {
    const entry = findings.get(key) ?? { count: 0, examples: [], identities: new Set() };
    entry.count++;
    if (entry.examples.length < 25 && !entry.identities.has(identity)) { entry.examples.push(value); entry.identities.add(identity); }
    findings.set(key, entry);
}
async function check(path, locale, width) {
    const response = await page.goto(`${base}${path}`, { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200, `${path} returned ${response.status()}`);
    await page.waitForFunction(() => !!window.Alpine, null, { timeout: 15000 });
    await page.evaluate(() => document.fonts.ready);
    const overflow = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, viewport: innerWidth }));
    if (overflow.scroll > overflow.viewport + 1) add('horizontal-overflow', `${locale}/${width} ${path}: ${overflow.scroll} > ${overflow.viewport}`);
    const duplicateIds = await page.evaluate(() => {
        const seen = new Set();
        const duplicates = new Set();
        for (const element of document.querySelectorAll('[id]')) {
            if (seen.has(element.id)) duplicates.add(element.id);
            seen.add(element.id);
        }
        return [...duplicates];
    });
    for (const id of duplicateIds) add('duplicate-id', `${locale}/${width} ${path}: #${id}`, `${path}#${id}`);
    await page.addScriptTag({ content: axe.source });
    const result = await page.evaluate(async () => window.axe.run(document, {
        runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
    }));
    for (const violation of result.violations) {
        for (const node of violation.nodes) add(violation.id, `${locale}/${width} ${path}: ${node.target.join(' ')} (${violation.impact})`, node.target.join(' '));
    }
}
try {
    await check('/login', 'fr', 1440);
    await page.locator('#email').fill(process.env.ADMIN_EMAIL ?? 'admin@example.com');
    await page.locator('#password').fill(process.env.ADMIN_PASSWORD ?? 'CI-admin-password-123');
    await Promise.all([page.waitForURL('**/dashboard'), page.locator('form[action$="/login"] button[type="submit"]').click()]);
    await page.goto(`${base}/projects`);
    const projectHref = await page.locator('tr').filter({ hasText: 'Projet UI Delegation' }).locator('a').first().getAttribute('href');
    const project = new URL(projectHref, base).pathname;
    await page.goto(`${base}/billing`);
    const quoteHref = await page.locator('a[href*="/billing/quotes/"]').first().getAttribute('href');
    const invoiceHref = await page.locator('a[href*="/billing/invoices/"]').first().getAttribute('href');
    const paths = ['/', '/dashboard', '/clients', '/clients/create', '/projects', '/projects/create', project,
        `${project}/documents`, `${project}/documents/create`, `${project}/planning`, `${project}/billing/fees`,
        `${project}/billing/quotes/create`, '/billing', '/billing/settings', new URL(quoteHref, base).pathname,
        new URL(invoiceHref, base).pathname, '/profile', '/users', '/users/create', '/activity',
        '/planning/templates', '/planning/calendar', '/planning/notifications', '/my-tasks'];
    for (const path of paths) await check(path, 'fr', 1440);
    await page.locator('#locale').selectOption('ar');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[action$="/locale"] button[type="submit"]').click()]);
    assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
    await page.setViewportSize({ width: 375, height: 812 });
    for (const path of paths) await check(path, 'ar', 375);
    if (findings.size) {
        for (const [id, entry] of findings) {
            const message = `${entry.count} occurrences; ${entry.examples.join(' | ')}`.slice(0, 3700).replaceAll('%', '%25').replaceAll('\r', '%0D').replaceAll('\n', '%0A');
            console.error(`::warning title=A11Y ${id}::${message}`);
        }
        throw new Error(`Accessibility checks found ${findings.size} types of issue`);
    }
    console.log(`Automated WCAG A/AA checks passed on ${paths.length} staff routes in French desktop and Arabic mobile.`);
} catch (error) {
    console.error(`::error title=Accessibility browser::${String(error.stack ?? error).replaceAll('%', '%25').replaceAll('\r', '%0D').replaceAll('\n', '%0A')}`);
    process.exitCode = 1;
} finally {
    await browser.close();
}
