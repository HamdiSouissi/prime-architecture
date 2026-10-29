export default () => ({
    files: [],
    busy: false,
    choose(event) {
        this.files = Array.from(event.target.files, file => ({ file, progress: 0, message: '', done: false }));
    },
    async submit(event) {
        if (this.busy) return;
        const form = event.target;
        this.busy = true;
        for (const item of this.files) {
            if (item.done) continue;
            const data = new FormData(form);
            data.set('title', item.file.name.replace(/\.[^.]+$/, ''));
            data.set('file', item.file);
            item.message = '';
            await new Promise(resolve => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', form.action);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.upload.onprogress = e => { if (e.lengthComputable) item.progress = Math.round(e.loaded * 100 / e.total); };
                xhr.onload = () => {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        item.done = xhr.status === 201;
                        item.message = item.done ? response.message : Object.values(response.errors || {}).flat().join(' ') || form.dataset.error;
                    } catch { item.message = form.dataset.error; }
                    resolve();
                };
                xhr.onerror = () => { item.message = form.dataset.error; resolve(); };
                xhr.send(data);
            });
        }
        this.busy = false;
    },
});
