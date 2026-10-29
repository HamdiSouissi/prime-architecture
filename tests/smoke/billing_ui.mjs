import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';

const browser = await chromium.launch({ channel: 'chrome', args: ['--no-sandbox'] });
const base = 'http://localhost:8080';
try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    const errors = [];
    const remote = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (/^https?:/.test(request.url()) && !request.url().startsWith(base)) remote.push(request.url()); });
    await page.goto(`${base}/login`);
    await page.locator('#email').fill(process.env.ADMIN_EMAIL ?? 'admin@example.com');
    await page.locator('#password').fill(process.env.ADMIN_PASSWORD ?? 'CI-admin-password-123');
    await Promise.all([page.waitForURL('**/dashboard'), page.locator('form[action$="/login"] button[type="submit"]').click()]);
    await page.screenshot({ path: 'ui-workspace-fr.jpg', type: 'jpeg', quality: 50 });
    await page.goto(`${base}/projects`);
    await page.locator('tr').filter({ hasText: 'Projet UI Delegation' }).locator('a').click();
    await page.route('**/billing/quotes/preview', async route => {
        await new Promise(resolve => setTimeout(resolve, 500));
        await route.continue();
    });
    await page.getByRole('link', { name: 'Nouveau devis', exact: true }).click();
    await page.locator('[aria-busy="true"]').waitFor({ state: 'visible', timeout: 5000 });
    await page.locator('input[name="lines[0][description]"]').fill('Étude architecturale');
    await page.locator('input[name="lines[0][unit_price]"]').fill('100.000');
    await page.waitForFunction(() => document.querySelector('#preview-total_ttc')?.textContent === '119.000', null, { timeout: 15000 });
    await Promise.all([page.waitForURL(/\/billing\/quotes\/\d+$/), page.locator('form button[type="submit"]').last().click()]);
    page.once('dialog', dialog => dialog.accept());
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[action$="/issue"] button').click()]);
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[action$="/decision"]').first().locator('button').click()]);
    const quoteUrl = page.url();
    const due = new Date(); due.setUTCDate(due.getUTCDate() + 30);
    await page.locator('form[action$="/convert"] input[name="due_date"]').fill(due.toISOString().slice(0, 10));
    await Promise.all([page.waitForURL(/\/billing\/invoices\/\d+$/), page.locator('form[action$="/convert"] button').click()]);
    page.once('dialog', dialog => dialog.accept());
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[action$="/issue"] button').click()]);
    await page.locator('#payment-amount').fill('119.000');
    await page.locator('#method').selectOption('cash');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[action$="/payments"] button').click()]);
    assert.equal(await page.getByText('Payée', { exact: true }).count(), 1);
    await page.locator('#locale').selectOption('ar');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[action$="/locale"] button[type="submit"]').click()]);
    assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
    await page.goto(quoteUrl);
    await page.screenshot({ path: 'ui-billing-ar.jpg', type: 'jpeg', quality: 50 });
    await page.setViewportSize({ width: 375, height: 812 });
    assert.equal(await page.locator('#site-navigation').isVisible(), false);
    await page.locator('button[aria-controls="site-navigation"]').click();
    assert.equal(await page.locator('#site-navigation').isVisible(), true);
    assert.deepEqual(errors, []);
    assert.deepEqual(remote, []);
    console.log('Billing browser smoke: server preview, quote, invoice, payment and RTL passed.');
} catch (error) {
    console.error(`::error title=Billing browser::${String(error.stack ?? error).replaceAll('%', '%25').replaceAll('\r', '%0D').replaceAll('\n', '%0A')}`);
    process.exitCode = 1;
} finally {
    await browser.close();
}
