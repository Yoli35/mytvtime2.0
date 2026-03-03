let self;

export class WatchButton {
    constructor() {
        self = this;

        this.watchButton = document.querySelector('.watch-button');
        this.userMovieId = this.watchButton.getAttribute('data-um-id');

        this.modifyWatchDate = this.modifyWatchDate.bind(this);

        this.init();
    }

    init() {
        const markAsViewedDiv = this.watchButton.querySelector('.mark-as-viewed');
        markAsViewedDiv.addEventListener('click', function () {
            fetch('/api/movie/watch/button/add/' + self.userMovieId,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                }
            ).then(response => response.json())
                /** @param {{viewed: boolean, dateString: string, lastViewedAt: string}} data */
                .then(data => {
                    console.log(data);
                    markAsViewedDiv.classList.add('viewed');
                    const viewDateDiv = document.createElement('div');
                    viewDateDiv.classList.add('watched-at');
                    const textNode = document.createTextNode(' ' + data.lastViewedAt);
                    viewDateDiv.appendChild(textNode);
                    viewDateDiv.setAttribute('data-watched-at', data.dateString);
                    self.watchButton.appendChild(viewDateDiv);
                });
        });

        const watchedAtDivs = this.watchButton.querySelectorAll('div.watched-at');
        watchedAtDivs.forEach(watchedAtDiv => {
            watchedAtDiv.addEventListener('click', this.modifyWatchDate);
        });
    }

    modifyWatchDate(e) {
        const watchedAtDiv = e.currentTarget;
        const watchedAt = watchedAtDiv.getAttribute('data-watched-at');

        const watchedAtModifyDiv = document.createElement('div');
        watchedAtModifyDiv.classList.add('watched-at-modify');
        const datetimeInput = document.createElement('input');
        datetimeInput.setAttribute('type', 'datetime-local');
        datetimeInput.setAttribute('value', watchedAt);

        const buttonsDiv = document.createElement('div');
        buttonsDiv.classList.add('buttons');

        const datetimeSaveButton = document.createElement('button');
        const svgSave = this.getSvg('save');
        datetimeSaveButton.appendChild(svgSave);
        datetimeSaveButton.setAttribute('data-um-id', this.userMovieId);

        const datetimeDeleteButton = document.createElement('button');
        const svgDelete = this.getSvg('trash');
        datetimeDeleteButton.appendChild(svgDelete);
        datetimeDeleteButton.setAttribute('data-um-id', this.userMovieId);

        const datetimeCancelButton = document.createElement('button');
        const svgCancel = this.getSvg('cancel');
        datetimeCancelButton.appendChild(svgCancel);

        buttonsDiv.appendChild(datetimeSaveButton);
        buttonsDiv.appendChild(datetimeDeleteButton);
        buttonsDiv.appendChild(datetimeCancelButton);
        watchedAtModifyDiv.appendChild(datetimeInput);
        watchedAtModifyDiv.appendChild(buttonsDiv);
        watchedAtDiv.appendChild(watchedAtModifyDiv);

        datetimeInput.focus();
        datetimeInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                watchedAtModifyDiv.remove();
                watchedAtDiv.style.display = 'flex';
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                this.touchMovie({currentTarget: datetimeSaveButton});
            }
        });
        datetimeSaveButton.addEventListener('click', this.touchMovie);
        datetimeDeleteButton.addEventListener('click', this.removeMovieView);
        datetimeCancelButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            watchedAtModifyDiv.remove();
        });
    }

    touchMovie(e) { // Ajuste la date de visionnage à la valeur de l'input datetime-local
        e.preventDefault();
        e.stopPropagation();
        const datetimeSaveButton = e.currentTarget;
        const id = datetimeSaveButton.getAttribute('data-um-id');
        const watchedAtModifyDiv = datetimeSaveButton.closest(".watched-at-modify");
        const watchedAtDiv = watchedAtModifyDiv.closest('.watched-at');
        const lastDatetime = watchedAtDiv.getAttribute('data-watched-at');
        const datetimeInput = watchedAtModifyDiv.querySelector('input');
        const newDatetime = datetimeInput.value;
        console.log(newDatetime);

        fetch(`/api/movie/watch/button/touch/` + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                newDatetime: newDatetime,
                lastDatetime: lastDatetime
            })
        }).then((response) => response.json())
            .then(data => {
                // TODO: Vérifier "data"
                console.log(data);
                watchedAtModifyDiv.remove();
                watchedAtDiv.innerText = data.date;
                watchedAtDiv.setAttribute('data-watched-at', newDatetime);
            });
        watchedAtModifyDiv.remove();
    }

    removeMovieView(e) {
        e.preventDefault();
        e.stopPropagation();
        const datetimeDeleteButton = e.currentTarget;
        const id = datetimeDeleteButton.getAttribute('data-um-id');
        const watchedAtModifyDiv = datetimeDeleteButton.closest(".watched-at-modify");
        const watchedAtDiv = watchedAtModifyDiv.closest('.watched-at');
        const datetime = watchedAtDiv.getAttribute('data-watched-at');
        console.log(datetime);

        fetch(`/api/movie/watch/button/remove/` + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                date: datetime
            })
        }).then((response) => response.json())
            .then(data => {
                // TODO: Vérifier "data"
                console.log(data);
                watchedAtModifyDiv.remove();
                watchedAtDiv.remove();
                if (data.viewed === 0) {
                    watchedAtDiv.classList.remove('viewed');
                }
            });
        watchedAtModifyDiv.remove();
    }

    getSvg(id) {
        const clone = document.querySelector('div#svgs').querySelector('div#' + id).querySelector('svg').cloneNode(true);
        clone.removeAttribute('id');
        return clone;
    }
}