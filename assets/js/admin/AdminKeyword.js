let self = null;

export class AdminKeyword {
    constructor(flashMessage) {
        self = this;
        this.flashMessage = flashMessage;
        this.saving = false;
        this.firstArray = [];
        const globs = document.querySelector('#globs').textContent;
        this.json = JSON.parse(globs);
        this.activeLetter = this.json['first'];
        this.translations = this.json['translations'];
        this.language = this.json['language'];

        this.init();
        console.log('AdminKeyword initialized');
    }

    init() {
        const firstInput = document.querySelector('.keyword__input');
        if (firstInput) {
            firstInput.focus();
        }

        const inputs = document.querySelectorAll('.keyword__input');
        inputs.forEach(input => {
            input.addEventListener('keydown', this.save)
        });

        this.check();
    }

    save(e) {
        const input = e.target;
        if (self.saving || e.key !== 'Enter' || input.value.length === 0) {
            return;
        }
        self.saving = true;
        const translations = [{original: input.dataset.keyword, translated: input.value}];
        fetch('/api/keywords/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({type: '', id: 0, noUpdate: 1, language: self.language, keywords: translations})
        })
            .then((response) => response.json())
            .then(data => {
                if (data['success'] === false) {
                    self.flashMessage.add('error', data['message']);
                    return;
                }
                self.flashMessage.add('success', self.translations[data['message']], data['keywords']);
                const keywordTranslationDiv = input.closest('.keyword__translation');
                const label = keywordTranslationDiv.querySelector('label');
                label.remove();
                const svgCheck = document.querySelector('#check').querySelector('svg').cloneNode(true);
                keywordTranslationDiv.appendChild(svgCheck);
                keywordTranslationDiv.appendChild(document.createTextNode(input.value));
                const missingCountSpan = document.querySelector('.missing-count');
                const missingTranslationSpan = document.querySelector('.missing-translations');
                const missingCount = missingCountSpan.textContent - 1;
                missingCountSpan.dataset.count = missingCount.toString();
                missingCountSpan.textContent = missingCount.toString();
                if (missingCount === 1) {
                    missingTranslationSpan.textContent = self.translations['Only one translation left!'];
                }
                const link = document.querySelector(`[data-letter="${self.activeLetter}"]`);
                link.title = missingCount.toString() + ' / ' + self.firstArray[self.activeLetter];
                const firstInput = document.querySelector('.keyword__input');
                if (firstInput) {
                    firstInput.focus();
                }
                self.saving = false;
            })
            .catch((error) => {
                self.flashMessage.add('error', self.translations[''] + ".", error.message);
                console.error(error);
                self.saving = false;
            });
    }

    check() {
        fetch('/api/keywords/check', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
            .then((response) => response.json())
            .then(data => {
                /*console.log(data);*/
                const links = document.querySelectorAll('.admin__keywords_firsts > a');
                self.firstArray = data['firstArray'];
                links.forEach(link => {
                    const letter = link.dataset.letter;
                    if (self.firstArray[letter] === 0) {
                        link.classList.add('done');
                    } else {
                        link.title = self.firstArray[letter];
                    }
                })
            });
    }
}