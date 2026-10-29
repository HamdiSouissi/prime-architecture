import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';

const base = 'http://localhost:8080';
const browser = await chromium.launch({ channel: 'chrome', args: ['--no-sandbox'] });
try {
    const page = await browser.newPage();
    await page.goto(`${base}/login`);
    await page.locator('#email').fill(process.env.ADMIN_EMAIL ?? 'admin@example.com');
    await page.locator('#password').fill(process.env.ADMIN_PASSWORD ?? 'CI-admin-password-123');
    await Promise.all([page.waitForURL('**/dashboard'), page.locator('form[action$="/login"] button[type="submit"]').click()]);
    await page.goto(`${base}/projects`);
    const row = page.locator('tr').filter({ hasText: 'Projet UI Delegation' });
    await row.locator('a').click();
    await page.getByRole('link', { name: 'Documents', exact: true }).click();
    await page.getByRole('link', { name: 'Nouveau document' }).click();
    await page.locator('#title').fill('Document UI');
    await page.locator('#file').setInputFiles({ name: 'plan.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n% Version 1\n%%EOF\n') });
    await Promise.all([page.waitForURL(/\/documents\/\d+$/), page.locator('form[enctype="multipart/form-data"] button[type="submit"]').click()]);
    const documentUrl = page.url();
    for (const version of [2, 3]) {
        await page.locator('input[name="file"]').setInputFiles({ name: 'plan.pdf', mimeType: 'application/pdf', buffer: Buffer.from(`%PDF-1.4\n% Version ${version}\n%%EOF\n`) });
        await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[enctype="multipart/form-data"] button[type="submit"]').click()]);
    }
    assert.equal(await page.locator('section table tbody tr').count(), 3);
    const previewLink = await page.getByRole('link', { name: 'Aperçu' }).first().getAttribute('href');
    const preview = await page.request.get(previewLink);
    assert.equal(preview.status(), 200);
    assert.match(preview.headers()['content-disposition'], /^inline/);
    assert.equal(preview.headers()['x-content-type-options'], 'nosniff');
    await page.locator('input[name="file"]').setInputFiles({
        name: 'image.png', mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+n42UAAAAASUVORK5CYII=', 'base64'),
    });
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[enctype="multipart/form-data"] button[type="submit"]').click()]);
    assert.equal(await page.locator('section table tbody tr').count(), 4);
    const imageLink = await page.getByRole('link', { name: 'Aperçu' }).first().getAttribute('href');
    const imagePage = await page.context().newPage();
    await imagePage.goto(imageLink);
    await imagePage.locator('img').waitFor();
    assert.equal(await imagePage.locator('img').evaluate(image => image.naturalWidth), 1);
    await imagePage.close();
    await page.goto(documentUrl.replace(/\/\d+$/, '/create'));
    const multi = page.locator('section[x-data="documentMultiUpload"]');
    await multi.locator('input[type="file"]').setInputFiles([
        { name: 'multiple.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4\n% Multi\n%%EOF\n') },
        { name: 'fake.pdf', mimeType: 'application/pdf', buffer: Buffer.from('<?php echo 1;') },
    ]);
    await multi.locator('button[type="submit"]').click();
    await multi.getByText('Modifications enregistrées.').waitFor();
    await multi.getByText('Fichier invalide, trop volumineux ou de format non autorisé.').waitFor();
    await page.locator('#locale').selectOption('ar');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[action$="/locale"] button[type="submit"]').click()]);
    await page.goto(documentUrl);
    assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
    assert.ok(await page.getByRole('link', { name: 'تنزيل' }).count() >= 3);
    console.log('Document browser smoke: upload, versions, preview and Arabic passed.');
} finally {
    await browser.close();
}
