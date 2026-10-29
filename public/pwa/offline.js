/* All IndexedDB content other than cryptographic metadata is AES-GCM ciphertext.
 * The passphrase and derived key live only in this page's memory. */
const enc = new TextEncoder();
const dec = new TextDecoder();
const $ = id => document.getElementById(id);
let key = null, salt = null, vault = null, version = null;
const random = size => crypto.getRandomValues(new Uint8Array(size));

function database() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('archi-offline-vault', 1);
        req.onupgradeneeded = () => req.result.createObjectStore('vault');
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}
async function read() {
    const db = await database();
    return new Promise((resolve, reject) => {
        const tx = db.transaction('vault', 'readonly');
        const req = tx.objectStore('vault').get('data');
        req.onsuccess = () => resolve(req.result ?? null);
        tx.oncomplete = () => db.close();
        tx.onerror = () => reject(tx.error);
    });
}
async function write(record, expected) {
    const db = await database();
    return new Promise((resolve, reject) => {
        const tx = db.transaction('vault', 'readwrite');
        const store = tx.objectStore('vault');
        const req = store.get('data');
        let stale = false;
        req.onsuccess = () => {
            if ((req.result?.version ?? null) !== expected) { stale = true; tx.abort(); return; }
            store.put(record, 'data');
        };
        tx.oncomplete = () => { db.close(); resolve(); };
        tx.onabort = () => { db.close(); reject(new Error(stale ? 'stale' : 'write')); };
        tx.onerror = () => {};
    });
}
async function derive(secret, value) {
    const material = await crypto.subtle.importKey('raw', enc.encode(secret), 'PBKDF2', false, ['deriveKey']);
    return crypto.subtle.deriveKey({ name: 'PBKDF2', hash: 'SHA-256', salt: value, iterations: 310000 }, material, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
}
async function save() {
    const iv = random(12);
    const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv, additionalData: enc.encode('archi-vault-v1') }, key, enc.encode(JSON.stringify(vault)));
    const next = crypto.randomUUID();
    await write({ version: next, salt, iv, cipher }, version);
    version = next;
}
function status(message, error = false) { $('status').textContent = message; $('status').className = error ? 'error' : ''; }
function draw() {
    const select = $('project');
    select.replaceChildren();
    for (const project of vault.projects) {
        const opt = document.createElement('option'); opt.value = String(project.id); opt.textContent = `${project.reference} — ${project.name}`; select.append(opt);
    }
    const list = $('drafts'); list.replaceChildren();
    for (const draft of vault.drafts) {
        const li = document.createElement('li');
        li.textContent = `${draft.project_name} — ${draft.recorded_on} — ${draft.percent}% — ${draft.note || ''}${draft.conflict ? ' — Conflit : version serveur conservée / تعارض: تم اعتماد نسخة الخادم' : ''}`;
        const button = document.createElement('button'); button.className = 'secondary'; button.type = 'button'; button.textContent = 'Supprimer / حذف';
        button.onclick = async () => {
            if (!confirm('Supprimer ce relevé local ? / حذف التقرير المحلي؟')) return;
            vault.drafts = vault.drafts.filter(item => item.operation_id !== draft.operation_id);
            try { await save(); draw(); } catch { status('Modification concurrente : rechargez la page. / تغيّر المحتوى، أعد تحميل الصفحة.', true); }
        };
        li.append(' ', button); list.append(li);
    }
}
async function session() {
    const response = await fetch('/offline/projects', { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
    if (!response.ok || !(response.headers.get('content-type') || '').includes('application/json')) throw new Error('session');
    return response.json();
}
$('unlock').onclick = async () => {
    try {
        if (!window.isSecureContext || !crypto.subtle || $('secret').value.length < 12) throw new Error('secret');
        const record = await read(); salt = record ? new Uint8Array(record.salt) : random(16);
        key = await derive($('secret').value, salt); $('secret').value = '';
        vault = record ? JSON.parse(dec.decode(await crypto.subtle.decrypt({ name: 'AES-GCM', iv: record.iv, additionalData: enc.encode('archi-vault-v1') }, key, record.cipher))) : { owner_id: null, projects: [], drafts: [] };
        version = record?.version ?? null;
        if (!record) await save();
        $('workspace').hidden = false; draw(); status('Coffre ouvert. / تم فتح الخزنة.');
    } catch { key = null; vault = null; $('secret').value = ''; $('workspace').hidden = true; status('Phrase invalide, coffre inaccessible ou navigateur non compatible. / العبارة غير صحيحة أو الخزنة غير متاحة.', true); }
};
$('prepare').onclick = async () => {
    try {
        const data = await session();
        if (vault.owner_id !== null && vault.owner_id !== data.owner_id) throw new Error('owner');
        vault.owner_id = data.owner_id; vault.projects = data.projects;
        await save(); draw(); status('Projets préparés. / تم تحضير المشاريع.');
    } catch { status('Connexion, compte ou préparation impossible. / تعذر الاتصال أو تحضير المشاريع.', true); }
};
$('draft-form').onsubmit = async event => {
    event.preventDefault();
    const project = vault.projects.find(item => String(item.id) === $('project').value);
    const value = $('percent').value;
    if (!project || vault.drafts.some(item => item.project_id === project.id) || !/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/.test(value) || Number(value) > 100) { status('Projet invalide ou relevé déjà en attente. / مشروع غير صالح أو تقرير قيد الانتظار.', true); return; }
    vault.drafts.push({ operation_id: crypto.randomUUID(), owner_id: vault.owner_id, project_id: project.id, project_name: project.name, base_revision: project.base_revision, recorded_on: $('recorded').value, percent: value, note: $('note').value, conflict: false });
    try { await save(); $('note').value = ''; draw(); status('Relevé chiffré enregistré sur cet appareil. / حُفظ التقرير مشفّراً على الجهاز.'); }
    catch { status('Enregistrement impossible : rechargez la page. / تعذر الحفظ، أعد تحميل الصفحة.', true); }
};
$('sync').onclick = async () => {
    try {
        const current = await session();
        if (current.owner_id !== vault.owner_id) throw new Error('owner');
        for (const draft of [...vault.drafts]) {
            if (draft.conflict) continue;
            const response = await fetch(`/offline/projects/${draft.project_id}/progress`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': current.csrf_token }, body: JSON.stringify(draft) });
            if (response.status === 409) { draft.conflict = true; await save(); continue; }
            if (!response.ok) throw new Error('sync');
            const result = await response.json();
            vault.drafts = vault.drafts.filter(item => item.operation_id !== draft.operation_id);
            const project = vault.projects.find(item => item.id === draft.project_id);
            if (project) project.base_revision = result.id;
            await save();
        }
        draw(); status('Synchronisation terminée. Vérifiez les éventuels conflits. / انتهت المزامنة. تحقق من التعارضات.');
    } catch { draw(); status('Synchronisation impossible ; les relevés restent sur cet appareil. / تعذرت المزامنة وتبقى التقارير على الجهاز.', true); }
};
$('purge').onclick = async () => {
    if (!confirm('Effacer définitivement tous les relevés locaux ? / حذف كل التقارير المحلية نهائياً؟')) return;
    try {
        const current = await read();
        if (current) { const db = await database(); await new Promise((resolve, reject) => { const tx = db.transaction('vault', 'readwrite'); tx.objectStore('vault').delete('data'); tx.oncomplete = () => { db.close(); resolve(); }; tx.onerror = () => reject(tx.error); }); }
        vault = null; key = null; version = null; $('workspace').hidden = true; status('Coffre effacé. / تم حذف الخزنة.');
    } catch { status('Suppression impossible. / تعذر الحذف.', true); }
};
