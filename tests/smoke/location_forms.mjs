import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';

const base = process.env.SITE_URL ?? 'http://localhost:8080';
if (!['localhost', '127.0.0.1'].includes(new URL(base).hostname)) {
    throw new Error('The browser smoke test must target a local test instance.');
}
const browser = await chromium.launch({
    channel: 'chrome',
    args: ['--no-sandbox'],
});

async function chooseGovernorate(page, label) {
    await page.locator('#governorate_id').selectOption({ label });
}

async function waitForDelegation(page, label, enabled) {
    await page.waitForFunction(({ label, enabled }) => {
        const option = [...document.querySelectorAll('#delegation_id option')].find(item => item.textContent.trim() === label);
        return option && option.disabled !== enabled && option.hidden !== enabled;
    }, { label, enabled });
}

try {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${base}/login`);
    await page.locator('#email').fill(process.env.ADMIN_EMAIL ?? 'admin@example.com');
    await page.locator('#password').fill(process.env.ADMIN_PASSWORD ?? 'CI-admin-password-123');
    await Promise.all([
        page.waitForURL('**/dashboard'),
        page.locator('form[action$="/login"] button[type="submit"]').click(),
    ]);

    await page.goto(`${base}/clients/create`);
    await waitForDelegation(page, 'Carthage', false);
    await chooseGovernorate(page, 'Tunis');
    await waitForDelegation(page, 'Carthage', true);
    await waitForDelegation(page, 'Ariana Ville', false);
    await page.locator('#delegation_id').selectOption({ label: 'Carthage' });
    await chooseGovernorate(page, 'Ariana');
    await waitForDelegation(page, 'Ariana Ville', true);
    await waitForDelegation(page, 'Carthage', false);
    assert.equal(await page.locator('#delegation_id').inputValue(), '');
    await page.locator('#delegation_id').selectOption({ label: 'Ariana Ville' });
    await page.locator('#name').fill('Client UI Delegation');
    await Promise.all([
        page.waitForURL(/\/clients\/\d+$/),
        page.locator('form[action$="/clients"] button[type="submit"]').click(),
    ]);
    const clientUrl = page.url();
    await page.goto(`${clientUrl}/edit`);
    assert.equal(await page.locator('#delegation_id option:checked').textContent(), 'Ariana Ville');

    // Invalid CIN forces a server redirect; both location selections must survive.
    await chooseGovernorate(page, 'Tunis');
    await waitForDelegation(page, 'Carthage', true);
    await page.locator('#delegation_id').selectOption({ label: 'Carthage' });
    await page.locator('#national_id').fill('123');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.locator('form[action$="/clients/' + clientUrl.split('/').pop() + '"] button[type="submit"]').click(),
    ]);
    await page.waitForSelector('[role="alert"]');
    assert.equal(await page.locator('#governorate_id option:checked').textContent(), 'Tunis');
    assert.equal(await page.locator('#delegation_id option:checked').textContent(), 'Carthage');
    await page.locator('#national_id').fill('12345678');
    await Promise.all([
        page.waitForURL(/\/clients\/\d+$/),
        page.locator('form[action$="/clients/' + clientUrl.split('/').pop() + '"] button[type="submit"]').click(),
    ]);

    await page.goto(`${base}/projects/create`);
    await page.locator('#name').fill('Projet UI Delegation');
    await page.locator('#client_id').selectOption({ label: 'Client UI Delegation' });
    const architectId = await page.locator('#lead_architect_id option:not([value=""])').first().getAttribute('value');
    await page.locator('#lead_architect_id').selectOption(architectId);
    await chooseGovernorate(page, 'Ariana');
    await waitForDelegation(page, 'Ariana Ville', true);
    await page.locator('#delegation_id').selectOption({ label: 'Ariana Ville' });
    await Promise.all([
        page.waitForURL(/\/projects\/\d+$/),
        page.locator('form[action$="/projects"] button[type="submit"]').click(),
    ]);
    const projectUrl = page.url();
    await page.goto(`${projectUrl}/edit`);
    assert.equal(await page.locator('#delegation_id option:checked').textContent(), 'Ariana Ville');
    await chooseGovernorate(page, 'Tunis');
    await waitForDelegation(page, 'Carthage', true);
    assert.equal(await page.locator('#delegation_id').inputValue(), '');
    await page.locator('#delegation_id').selectOption({ label: 'Carthage' });
    await Promise.all([
        page.waitForURL(/\/projects\/\d+$/),
        page.locator('form[action$="/projects/' + projectUrl.split('/').pop() + '"] button[type="submit"]').click(),
    ]);
    await page.goto(`${projectUrl}/edit`);
    assert.equal(await page.locator('#delegation_id option:checked').textContent(), 'Carthage');

    // Arabic labels and RTL use the same persisted data.
    await page.locator('#locale').selectOption('ar');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.locator('form[action$="/locale"] button[type="submit"]').click(),
    ]);
    await page.goto(`${projectUrl}/edit`);
    assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
    assert.equal((await page.locator('#delegation_id option:checked').textContent()).trim(), 'قرطاج');
    await chooseGovernorate(page, 'أريانة');
    await waitForDelegation(page, 'أريانة المدينة', true);
    assert.equal(await page.locator('#delegation_id').inputValue(), '');

    // No-JavaScript fallback renders the saved selection and still validates mismatches.
    const noJs = await browser.newContext({ javaScriptEnabled: false, storageState: await context.storageState() });
    const fallback = await noJs.newPage();
    await fallback.goto(`${projectUrl}/edit`);
    assert.equal((await fallback.locator('#delegation_id option:checked').textContent()).trim(), 'قرطاج');
    await fallback.goto(`${base}/clients/create`);
    await fallback.locator('#name').fill('Invalid location without JS');
    await fallback.locator('#governorate_id').selectOption({ label: 'تونس' });
    await fallback.locator('#delegation_id').selectOption({ label: 'أريانة المدينة' });
    await Promise.all([
        fallback.waitForNavigation({ waitUntil: 'load' }),
        fallback.locator('form[action$="/clients"] button[type="submit"]').click(),
    ]);
    await fallback.waitForSelector('#delegation-error');
    await noJs.close();
    await context.close();
    console.log('Location forms: filtering, persistence, validation, FR/AR and no-JS fallback succeeded.');
} finally {
    await browser.close();
}
