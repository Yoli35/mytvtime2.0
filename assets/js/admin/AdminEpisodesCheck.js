/**
 * Outil d'administration : vérification des épisodes des séries des utilisateurs (UserEpisodeCheckService).
 * Une requête par série d'utilisateur, pour rester sous les limites d'exécution de l'hébergement.
 */
export class AdminEpisodesCheck {
    constructor() {
        this.form = document.getElementById('episodes-check');
        if (!this.form) {
            return;
        }
        this.results = this.form.querySelector('.episodes-check-results');
        this.progress = this.form.querySelector('.episodes-check-progress');
        this.progressFill = this.form.querySelector('.episodes-check-progress-bar-fill');
        this.checkButton = this.form.querySelector('button[value="check"]');
        this.stopButton = this.form.querySelector('button[value="stop"]');
        this.nextButton = this.form.querySelector('button[value="next"]');
        this.applyInput = this.form.querySelector('input[name="apply"]');
        this.periodSelect = this.form.elements['period'];
        this.dateInput = this.form.elements['date'];
        this.offsetInput = this.form.elements['offset'];
        this.limitInput = this.form.elements['limit'];
        this.watchedMissingInput = this.form.elements['watchedMissingOnly'];
        this.stopped = false;
        this.hasNextBatch = false;

        this.icons = {info: 'ℹ️', warning: '⚠️', fix: '🔧', error: '🚫'};

        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.start();
        });
        this.stopButton.addEventListener('click', () => {
            this.stopped = true;
            this.stopButton.disabled = true;
        });
        this.applyInput.addEventListener('change', () => {
            this.form.classList.toggle('apply', this.applyInput.checked);
        });
        // Filtre d'affichage (instantané, y compris sur des résultats déjà affichés)
        this.watchedMissingInput.addEventListener('change', () => {
            this.results.classList.toggle('watched-missing-only', this.watchedMissingInput.checked);
        });
        // La date de référence n'a de sens qu'avec une période
        this.periodSelect.addEventListener('change', () => {
            this.dateInput.disabled = !this.periodSelect.value;
        });
        // Lot suivant : on avance l'offset de la taille du lot et on relance
        this.nextButton.addEventListener('click', () => {
            const limit = parseInt(this.limitInput.value) || 0;
            this.offsetInput.value = ((parseInt(this.offsetInput.value) || 0) + limit).toString();
            this.start();
        });
    }

    async start() {
        const params = {
            seriesId: parseInt(this.form.elements['seriesId'].value) || 0,
            userId: parseInt(this.form.elements['userId'].value) || 0,
            userSeriesId: parseInt(this.form.elements['userSeriesId'].value) || 0,
            period: this.periodSelect.value,
            date: this.periodSelect.value ? this.dateInput.value : '',
            offset: parseInt(this.offsetInput.value) || 0,
            limit: parseInt(this.limitInput.value) || 0,
        };
        const options = {
            deep: this.form.elements['deep'].checked,
            apply: this.applyInput.checked,
            issuesOnly: this.form.elements['issuesOnly'].checked,
        };

        if (!params.seriesId && !params.userId && !params.userSeriesId && !params.period && !params.limit
            && !confirm('No filter: every series of every user will be checked. Continue?')) {
            return;
        }

        this.results.innerHTML = '';
        this.progressFill.style.width = '0';
        this.stopped = false;
        this.setRunning(true);

        let list = [];
        let total = 0;
        try {
            const data = await this.post(this.form.dataset.listUrl, params);
            list = data.list;
            total = data.total;
        } catch (error) {
            this.addLine('error', 'Error fetching the series list: ' + error.message);
            this.setRunning(false);
            return;
        }

        const totals = {ok: 0, issues: 0, fixed: 0, error: 0};
        const counts = {updated: 0, created: 0, deleted: 0, skipped: 0, watchedNotOnTmdb: 0};
        let watchedMissingSeries = 0;
        let tmdbCalls = 0;
        let done = 0;
        // Position dans l'ensemble des séries correspondant aux filtres, quand on traite un lot
        const batch = (params.offset || params.limit) ? ` (${params.offset + 1}–${params.offset + list.length} / ${total})` : '';

        for (let i = 0; i < list.length && !this.stopped; i++) {
            const row = list[i];
            this.progress.textContent = `${i + 1} / ${list.length}${batch} — ${row.name}`;
            this.progressFill.style.width = `${(i + 1) / list.length * 100}%`;
            try {
                const data = await this.post(this.form.dataset.runUrl, {id: row.id, deep: options.deep, apply: options.apply});
                const result = data.result;
                totals[result.status]++;
                tmdbCalls += result.tmdbCalls;
                Object.keys(counts).forEach(key => counts[key] += result.counts[key] ?? 0);
                watchedMissingSeries += result.counts.watchedNotOnTmdb > 0 ? 1 : 0;
                if (!options.issuesOnly || result.status !== 'ok') {
                    this.addResult(row, result);
                }
            } catch (error) {
                totals.error++;
                this.addResult(row, {status: 'error', messages: [{level: 'error', text: error.message}]});
            }
            done++;
        }

        const verb = options.apply ? '' : 'to ';
        this.progress.textContent = (this.stopped ? `Stopped — ${done} / ` : '') + `${list.length} user series${batch}`;
        this.addSummary([
            `User series: ${done}${done < list.length ? ' / ' + list.length : ''} — ok: ${totals.ok} — with issues: ${totals.issues} — fixed: ${totals.fixed} — errors: ${totals.error}`,
            `Episodes ${verb}update: ${counts.updated} — ${verb}create: ${counts.created} — ${verb}delete: ${counts.deleted} — skipped: ${counts.skipped} — TMDB calls: ${tmdbCalls}`,
            counts.watchedNotOnTmdb ? `👁️ Watched episodes no longer on TMDB: ${counts.watchedNotOnTmdb} in ${watchedMissingSeries} series (left as is)` : '',
            !options.apply && totals.issues ? 'Dry run: nothing was changed. Check "Apply changes" to fix.' : '',
        ].filter(Boolean));
        // Lot suivant possible si le lot est allé jusqu'au bout et qu'il reste des séries
        this.hasNextBatch = !this.stopped && params.limit > 0 && params.offset + list.length < total;
        this.setRunning(false);
    }

    async post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(body),
        });
        if (!response.ok) {
            const data = await response.json().catch(() => null);
            throw new Error(data?.message ?? `HTTP ${response.status}`);
        }
        return response.json();
    }

    setRunning(running) {
        this.checkButton.disabled = running;
        this.stopButton.disabled = !running;
        this.nextButton.disabled = running || !this.hasNextBatch;
        if (running) {
            this.hasNextBatch = false;
        }
    }

    addResult(row, result) {
        const item = document.createElement('div');
        item.classList.add('item', 'status-' + result.status);
        const watchedMissing = result.counts?.watchedNotOnTmdb ?? 0;
        if (watchedMissing) {
            item.classList.add('has-watched-missing');
        }

        const header = document.createElement('div');
        header.classList.add('item-header');
        const name = document.createElement('span');
        name.classList.add('name');
        name.textContent = row.name;
        const ids = document.createElement('span');
        ids.classList.add('ids');
        ids.textContent = `series ${row.series_id} · user series ${row.id} · user ${row.user_id}`;
        const status = document.createElement('span');
        status.classList.add('status');
        status.textContent = result.status;
        header.append(name, ids);
        if (watchedMissing) {
            const badge = document.createElement('span');
            badge.classList.add('watched-missing-badge');
            badge.textContent = `👁️ ${watchedMissing} watched, not on TMDB`;
            header.appendChild(badge);
        }
        header.appendChild(status);
        item.appendChild(header);

        if (result.messages.length) {
            const ul = document.createElement('ul');
            result.messages.forEach(message => {
                const li = document.createElement('li');
                li.classList.add('level-' + message.level);
                const watchedMissingMessage = message.code === 'watched_not_on_tmdb';
                if (watchedMissingMessage) {
                    li.classList.add('watched-missing');
                }
                li.textContent = `${watchedMissingMessage ? '👁️' : (this.icons[message.level] ?? '')} ${message.text}`;
                ul.appendChild(li);
            });
            item.appendChild(ul);
        }
        this.results.appendChild(item);
        item.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    addLine(level, text) {
        this.addResult({name: '', series_id: '-', id: '-', user_id: '-'}, {status: level, messages: [{level, text}]});
    }

    addSummary(lines) {
        const item = document.createElement('div');
        item.classList.add('item', 'summary');
        lines.forEach(line => {
            const div = document.createElement('div');
            div.textContent = line;
            item.appendChild(div);
        });
        this.results.appendChild(item);
        item.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }
}
