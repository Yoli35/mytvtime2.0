let self = null

/**
 * Envoi des vidéos personnelles par morceaux (PersonalVideoController : upload/start → upload/chunk × n → upload/finish).
 * Chaque requête ne contient qu'un morceau (8 Mo) : la taille des vidéos ne dépend plus de upload_max_filesize / post_max_size.
 */
export class PVideoUpload {
    constructor() {
        self = this;
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        this.urls = {
            start: globs.app_video_personal_upload_start,
            chunk: globs.app_video_personal_upload_chunk,
            finish: globs.app_video_personal_upload_finish,
            cancel: globs.app_video_personal_upload_cancel,
        };
        this.maxFileSize = globs.maxFileSize;
        this.translations = globs.texts;
        this.maxAttempts = 3;
        this.uploading = false;

        this.init();
    }

    init() {
        self.form = document.getElementById('upload-form');
        self.input = self.form.querySelector('input[type="file"]');
        self.button = self.form.querySelector('button[type="submit"]');
        self.progressList = document.querySelector('.upload-progress');
        self.token = self.form.querySelector('input[name="_token"]').value;

        self.form.addEventListener('submit', self.submit);

        // Fermer ou quitter la page interrompt l'envoi
        window.addEventListener('beforeunload', (e) => {
            if (self.uploading) e.preventDefault();
        });

        // Des vidéos sont en cours de conversion (commande app:videos:process) : on rafraîchit la page, sauf pendant un envoi
        if (document.querySelector('.personal-videos-page[data-has-pending]')) {
            setInterval(() => {
                if (!self.uploading) window.location.reload();
            }, 30000);
        }
    }

    async submit(e) {
        e.preventDefault();
        const files = Array.from(self.input.files);
        if (!files.length) return;

        self.uploading = true;
        self.input.disabled = true;
        self.button.disabled = true;
        self.progressList.replaceChildren();
        const rows = files.map(file => self.addRow(file));

        let finished = 0;
        for (let i = 0; i < files.length; i++) {
            if (await self.uploadFile(files[i], rows[i])) finished++;
        }

        self.uploading = false;
        self.input.disabled = false;
        self.button.disabled = false;
        self.input.value = '';
        // Les messages de upload/finish (flash) s'affichent au rechargement ; en cas d'échec total, les erreurs restent visibles
        if (finished) window.location.reload();
    }

    /**
     * @return {Promise<boolean>} true si la vidéo a été créée
     */
    async uploadFile(file, row) {
        if (!file.type.startsWith('video/') && !file.name.toLowerCase().endsWith('.mov')) {
            self.setRowError(row, self.translations['Not a video file']);
            return false;
        }
        if (file.size > self.maxFileSize) {
            self.setRowError(row, self.translations['File too large']);
            return false;
        }

        let uploadId = null;
        try {
            const start = await self.post(self.urls.start, {name: file.name, size: file.size});
            uploadId = start.uploadId;
            const chunkSize = start.chunkSize;

            let offset = 0;
            let stalls = 0;
            const t0 = performance.now();
            while (offset < file.size) {
                const chunk = file.slice(offset, offset + chunkSize);
                const result = await self.post(self.urls.chunk, {uploadId: uploadId, offset: offset, chunk: chunk});
                // Le serveur indique où reprendre (morceau déjà reçu après une coupure, par exemple)
                if (result.received <= offset && ++stalls > self.maxAttempts) {
                    throw new Error(self.translations['Upload failed']);
                }
                if (result.received > offset) stalls = 0;
                offset = result.received;
                self.setRowProgress(row, offset, file.size, t0);
            }

            self.setRowStatus(row, self.translations['Processing']);
            await self.post(self.urls.finish, {uploadId: uploadId, name: file.name});
            self.setRowStatus(row, self.translations['Done']);
            row.classList.add('done');
            return true;
        } catch (error) {
            self.setRowError(row, error.message);
            if (uploadId) {
                self.post(self.urls.cancel, {uploadId: uploadId}, 1).catch(() => null);
            }
            return false;
        }
    }

    /**
     * POST multipart ; une erreur réseau ou serveur (5xx) est retentée, avec une attente croissante.
     */
    async post(url, data, maxAttempts = self.maxAttempts) {
        const formData = new FormData();
        formData.append('_token', self.token);
        Object.entries(data).forEach(([key, value]) => {
            if (value instanceof Blob) {
                formData.append(key, value, key);
            } else {
                formData.append(key, value);
            }
        });

        let lastError = null;
        for (let attempt = 1; attempt <= maxAttempts; attempt++) {
            try {
                const response = await fetch(url, {method: 'POST', body: formData});
                const json = await response.json().catch(() => ({ok: false, error: response.statusText}));
                if (response.ok && json.ok) return json;
                lastError = new Error(json.error || self.translations['Upload failed']);
                if (response.status < 500) throw lastError; // erreur définitive (fichier refusé, session expirée…)
            } catch (error) {
                if (error === lastError) throw error;
                lastError = error;
            }
            if (attempt < maxAttempts) {
                await new Promise(resolve => setTimeout(resolve, 1000 * 3 ** (attempt - 1)));
            }
        }
        throw lastError;
    }

    addRow(file) {
        const row = document.createElement('li');
        const name = document.createElement('span');
        name.classList.add('name');
        name.textContent = file.name + ' (' + self.formatSize(file.size) + ')';
        const progress = document.createElement('progress');
        progress.max = 100;
        progress.value = 0;
        const status = document.createElement('span');
        status.classList.add('status');
        status.textContent = self.translations['Waiting'];
        row.append(name, progress, status);
        self.progressList.appendChild(row);
        return row;
    }

    setRowProgress(row, received, total, t0) {
        row.querySelector('progress').value = Math.round(received / total * 100);
        const seconds = (performance.now() - t0) / 1000;
        const speed = seconds > 0 ? received / seconds : 0;
        const remaining = speed > 0 ? Math.round((total - received) / speed) : 0;
        self.setRowStatus(row, Math.round(received / total * 100) + ' % · ' + self.formatSize(speed) + '/s · '
            + Math.floor(remaining / 60) + ':' + String(remaining % 60).padStart(2, '0'));
    }

    setRowStatus(row, text) {
        row.querySelector('.status').textContent = text;
    }

    setRowError(row, text) {
        row.classList.add('error');
        self.setRowStatus(row, text);
    }

    formatSize(number) {
        if (number < 1e3) {
            return `${Math.round(number)} o`;
        } else if (number < 1e6) {
            return `${(number / 1e3).toFixed(1)} Ko`;
        } else if (number < 1e9) {
            return `${(number / 1e6).toFixed(1)} Mo`;
        }
        return `${(number / 1e9).toFixed(2)} Go`;
    }
}
