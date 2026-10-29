import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';

const browser = await chromium.launch({ channel: 'chrome', args: ['--no-sandbox'] });
const context = await browser.newContext();
const page = await context.newPage();
const base = 'http://localhost:8080';
try {
    await page.goto(`${base}/login`);
    await page.locator('#email').fill(process.env.ADMIN_EMAIL ?? 'admin@example.com');
    await page.locator('#password').fill(process.env.ADMIN_PASSWORD ?? 'CI-admin-password-123');
    await Promise.all([page.waitForURL('**/dashboard'), page.locator('form[action$="/login"] button[type="submit"]').click()]);
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.reload();
    const cached = await page.evaluate(async () => {
        const names = await caches.keys();
        const entries = [];
        for (const name of names) {
            for (const request of await (await caches.open(name)).keys()) entries.push(new URL(request.url).pathname);
        }
        return entries;
    });
    assert.deepEqual(cached, ['/pwa/offline.html']);
    await context.setOffline(true);
    await page.goto(`${base}/dashboard`);
    await page.getByRole('heading', { name: 'Connexion indisponible' }).waitFor();
    assert.equal(await page.locator('text=لا يوجد اتصال').count(), 1);
    console.log('PWA offline shell available without caching authenticated pages.');
} finally {
    await browser.close();
}
