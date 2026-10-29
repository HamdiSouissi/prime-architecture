/** Server is the sole authority for monetary calculations; no floating point arithmetic in the browser. */
export default (initialLines, defaultRate, previewUrl) => ({
    lines: initialLines.map((line, key) => ({ ...line, key })),
    sequence: initialLines.length,
    totals: null,
    requestId: 0,
    add() {
        if (this.lines.length < 500) this.lines.push({ key: this.sequence++, description: '', unit: '', quantity: '1', unit_price: '0.000', discount_percent: '0', tax_rate: defaultRate });
    },
    remove(index) {
        if (this.lines.length > 1) this.lines.splice(index, 1);
    },
    async preview(form) {
        if (!form) return;
        const requestId = ++this.requestId;
        const data = new FormData(form);
        data.delete('_method');
        try {
            const response = await fetch(previewUrl, { method: 'POST', body: data, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const result = response.ok ? await response.json() : null;
            if (requestId === this.requestId) this.totals = result;
        } catch {
            if (requestId === this.requestId) this.totals = null;
        }
    },
});
