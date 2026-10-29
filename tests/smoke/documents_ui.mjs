import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';

const base = 'http://localhost:8080';
const browser = await chromium.launch({ channel: 'chrome', args: ['--no-sandbox'] });
function pdfBytes(text) {
    const content = `BT /F1 12 Tf 30 50 Td (${text}) Tj ET`;
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 100] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        `<< /Length ${content.length} >>\nstream\n${content}\nendstream`,
    ];
    let data = '%PDF-1.4\n';
    const offsets = [];
    for (const [index, object] of objects.entries()) {
        offsets.push(Buffer.byteLength(data));
        data += `${index + 1} 0 obj\n${object}\nendobj\n`;
    }
    const xref = Buffer.byteLength(data);
    data += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
    for (const offset of offsets) data += `${String(offset).padStart(10, '0')} 00000 n \n`;
    data += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`;
    return Buffer.from(data, 'ascii');
}

try {
    const context = await browser.newContext();
    const page = await context.newPage();
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
    await page.locator('#file').setInputFiles({ name: 'plan.pdf', mimeType: 'application/pdf', buffer: pdfBytes('Version 1') });
    await Promise.all([page.waitForURL(/\/documents\/\d+$/), page.locator('form[enctype="multipart/form-data"] button[type="submit"]').click()]);
    const documentUrl = page.url();
    for (const version of [2, 3]) {
        await page.locator('input[name="file"]').setInputFiles({ name: 'plan.pdf', mimeType: 'application/pdf', buffer: pdfBytes(`Version ${version}`) });
        await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[enctype="multipart/form-data"] button[type="submit"]').click()]);
    }
    assert.equal(await page.locator('section table tbody tr').count(), 3);
    const previewLink = await page.getByRole('link', { name: 'Aperçu' }).first().getAttribute('href');
    const preview = await page.request.get(previewLink);
    assert.equal(preview.status(), 200);
    assert.match(preview.headers()['content-disposition'], /^inline/);
    assert.equal(preview.headers()['x-content-type-options'], 'nosniff');
    const pdfPage = await context.newPage();
    const pdfResponse = await pdfPage.goto(previewLink);
    assert.equal(pdfResponse.status(), 200);
    await pdfPage.waitForFunction(() => document.contentType === 'application/pdf' || !!document.querySelector('embed, iframe'));
    await pdfPage.close();
    await page.locator('input[name="file"]').setInputFiles({
        name: 'image.png', mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', 'base64'),
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
    await multi.getByRole('progressbar', { name: 'Progression du téléversement – multiple.pdf' }).waitFor();
    await multi.locator('button[type="submit"]').click();
    await multi.getByText('Modifications enregistrées.').waitFor();
    await multi.getByText('Fichier invalide, trop volumineux ou de format non autorisé.').waitFor();
    await page.locator('#locale').selectOption('ar');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.locator('form[action$="/locale"] button[type="submit"]').click()]);
    await page.goto(documentUrl);
    assert.equal(await page.locator('html').getAttribute('dir'), 'rtl');
    assert.ok(await page.getByRole('link', { name: 'تنزيل' }).count() >= 3);
    console.log('Document browser smoke: upload, versions, preview and Arabic passed.');
} catch (error) {
    const message = String(error.stack ?? error).replaceAll('%', '%25').replaceAll('\r', '%0D').replaceAll('\n', '%0A');
    console.error(`::error title=Document browser::${message}`);
    process.exitCode = 1;
} finally {
    await browser.close();
}
