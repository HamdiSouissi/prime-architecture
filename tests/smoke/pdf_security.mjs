import { writeFile } from 'node:fs/promises';

// Runs inside the private renderer; only synthetic data is used.
const html = `<!doctype html><html><body><p id="marker">SAFE_RENDER</p><script>document.getElementById('marker').textContent='SCRIPT_EXECUTED'</script></body></html>`;
const response = await fetch('http://127.0.0.1:3000/render', {
    method: 'POST',
    headers: { Authorization: `Bearer ${process.env.BILLING_PDF_TOKEN}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ html }),
});
if (!response.ok) throw new Error(`PDF security probe failed: ${response.status}`);
const pdf = Buffer.from(await response.arrayBuffer());
if (!pdf.subarray(0, 5).equals(Buffer.from('%PDF-'))) throw new Error('Invalid security probe PDF');
await writeFile('/tmp/security-probe.pdf', pdf);
