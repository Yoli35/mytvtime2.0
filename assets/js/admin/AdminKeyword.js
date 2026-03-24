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
            input.addEventListener('keydown', this.save);
            input.addEventListener('paste', this.edit);
        });

        this.check();
    }

    save(e) {
        const input = e.target;
        if (e.type !== 'submit' && (self.saving || e.key !== 'Enter' || input.value.length === 0)) {
            return;
        }
        self.saving = true;
        let originalText, translatedText;
        if (e.type === 'submit') {
            const textarea = e.target.closest('form').querySelector('textarea');
            originalText = input.dataset.keyword;
            translatedText = textarea.value
                .replace(/\r?\n/g, '<br/> ')
                .replace(/\[\d+]/g, '')
                .replace(/\s{2,}/g, ' ')
                .trim();
        } else {
            originalText = input.dataset.keyword;
            translatedText = input.value;
        }
        const translations = [{original: originalText, translated: translatedText}];
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
                if (e.type === 'submit') {
                    const dialog = document.querySelector('.edit-keyword-dialog');
                    dialog.close();
                    dialog.remove();
                }
                const keywordTranslationDiv = input.closest('.keyword__translation');
                const label = keywordTranslationDiv.querySelector('label');
                label.remove();
                const svgCheck = document.querySelector('#check').querySelector('svg').cloneNode(true);
                keywordTranslationDiv.appendChild(svgCheck);
                keywordTranslationDiv.appendChild(document.createTextNode(translatedText));
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

    edit(e) {
        const text = e.clipboardData.getData('text');
        if (text.length < 32) {
            return;
        }
        const input = e.target;
        const dialog = document.createElement('dialog');
        const form = document.createElement('form');
        const formRow1 = document.createElement('div');
        const formRow2 = document.createElement('div');
        const formField1 = document.createElement('div');
        const formField2 = document.createElement('div');
        const textarea = document.createElement('textarea');
        const cancelButton = document.createElement('button');
        const saveButton = document.createElement('button');
        cancelButton.setAttribute('type', 'button');
        cancelButton.addEventListener('click', () => {
            dialog.close();
            dialog.remove();
        });
        saveButton.setAttribute('type', 'submit');
        formRow1.classList.add('form-row');
        formField1.classList.add('form-field');
        formRow2.classList.add('form-row');
        formField2.classList.add('form-field');
        formField1.appendChild(textarea);
        formField2.appendChild(cancelButton);
        formField2.appendChild(saveButton);
        formRow1.appendChild(formField1);
        formRow2.appendChild(formField2);
        form.classList.add('form');
        form.append(formRow1);
        form.append(formRow2);
        form.setAttribute('data-keyword', input.dataset.keyword);
        form.addEventListener('submit', self.save);
        form.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.tagName === 'TEXTAREA') {
                e.preventDefault();
                self.save(e);
            }
            if (e.key === 'Escape') {
                dialog.close();
                dialog.querySelector('form').reset();
                dialog.querySelector('form').remove();
                dialog.remove();
            }
        });
        dialog.append(form);
        dialog.classList.add('dialog');
        dialog.classList.add('edit-keyword-dialog');
        dialog.classList.add('active');
        document.querySelector('.admin').append(dialog);
        dialog.showModal();
        textarea.addEventListener('input', (e) => {
            const field = e.currentTarget;
            if (field.scrollHeight > field.clientHeight) {
                field.style.height = `${field.scrollHeight}px`;
            }
        });
        textarea.value = text;
        textarea.focus();
        textarea.select();
        cancelButton.textContent = self.translations['Cancel'];
        saveButton.textContent = self.translations['Save'];
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