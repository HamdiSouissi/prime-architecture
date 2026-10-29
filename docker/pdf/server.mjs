import http from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { chromium } from 'playwright-core';

const token = process.env.BILLING_PDF_TOKEN ?? '';
if (Buffer.byteLength(token) < 32) throw new Error('BILLING_PDF_TOKEN must contain at least 32 bytes');
const expected = Buffer.from(`Bearer ${token}`);
const browser = await chromium.launch({ executablePath: '/usr/bin/chromium', args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-background-networking'] });
let active = 0;
const maxBody = 2 * 1024 * 1024;

const server = http.createServer(async (req, res) => {
    const auth = Buffer.from(req.headers.authorization ?? '');
    if (auth.length !== expected.length || !timingSafeEqual(auth, expected)) { res.writeHead(403).end(); return; }
    if (req.method === 'GET' && req.url === '/health') { res.writeHead(200).end(); return; }
    if (req.method !== 'POST' || req.url !== '/render') { res.writeHead(404).end(); return; }
    if (active >= 2) { res.writeHead(503).end(); return; }
    active++;
    let context;
    try {
        const chunks = [];
        let size = 0;
        for await (const chunk of req) {
            size += chunk.length;
            if (size > maxBody) { res.writeHead(413).end(); return; }
            chunks.push(chunk);
        }
        const input = JSON.parse(Buffer.concat(chunks).toString('utf8'));
        if (typeof input.html !== 'string' || Buffer.byteLength(input.html) > maxBody) { res.writeHead(400).end(); return; }
        context = await browser.newContext({ javaScriptEnabled: false, serviceWorkers: 'block' });
        await context.route('**/*', (route) => route.abort());
        const page = await context.newPage();
        page.setDefaultTimeout(15000);
        await page.setContent(input.html, { waitUntil: 'load', timeout: 15000 });
        let timer;
        const pdf = await Promise.race([
            page.pdf({ format: 'A4', printBackground: true, margin: { top: '15mm', right: '14mm', bottom: '15mm', left: '14mm' } }),
            new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('PDF timeout')), 15000); }),
        ]).finally(() => clearTimeout(timer));
        if (pdf.length > 10 * 1024 * 1024) { res.writeHead(413).end(); return; }
        res.writeHead(200, { 'Content-Type': 'application/pdf', 'Content-Length': pdf.length, 'Cache-Control': 'no-store' }).end(pdf);
    } catch (error) {
        console.error('PDF rendering failed:', error instanceof Error ? `${error.name}: ${error.message}`.slice(0, 600) : 'unknown error');
        if (!res.headersSent) res.writeHead(500).end();
    } finally {
        if (context) await context.close().catch(() => {});
        active--;
    }
});
server.requestTimeout = 20000;
server.headersTimeout = 10000;
server.listen(3000, '0.0.0.0');
process.on('SIGTERM', async () => { server.close(); await browser.close(); process.exit(0); });
