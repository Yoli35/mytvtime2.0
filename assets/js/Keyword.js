import {ToolTips} from 'ToolTips';

let gThis;

export class Keyword {
    maxLength;

    constructor(type) {
        gThis = this;
        this.toolTips = new ToolTips();
        this.maxLength = 0;
        this.init(type);
    }

    init(type) {
        const lang = document.querySelector("html").getAttribute("lang");
        const keywordFormContainer = document.querySelector('.keyword-translation-form');
        const keywordForm = document.querySelector('#keyword-translation-form');
        if (!keywordForm) {
            return;
        }
        const keywordFormsCancel = keywordForm.querySelector('button[type="button"]');
        const keywordFormsSubmit = keywordForm.querySelector('button[type="submit"]');
        const keywordsDiv = document.querySelector('.keywords');
        const keywords = keywordsDiv.querySelectorAll('.keyword');
        const missingKeywords = document.querySelectorAll('.keyword.missing');
        missingKeywords.forEach(keyword => {
            keyword.addEventListener('click', function () {
                if (keywordForm.classList.contains('active')) {
                    return;
                }
                gThis.displayForm(keywordFormContainer);
                gThis.keywordInitFields(keywordForm, missingKeywords);
            });
        });
        // Copier dans le presse-papiers le contenu du "data-title"
        keywords.forEach(keyword => {
            if (keyword.classList.contains('missing')) {
                return;
            }
            keyword.addEventListener('click', function () {
                const copyText = keyword.getAttribute('data-title');
                navigator.clipboard.writeText(copyText).then(() => {
                    console.log('Copied to clipboard: ' + copyText);
                }).catch(err => {
                    console.error('Error copying text: ', err);
                });
            });
        });
        keywordFormsCancel.addEventListener('click', function () {
            gThis.hideForm(keywordFormContainer);
        });
        keywordFormsSubmit.addEventListener('click', function (event) {
            event.preventDefault();

            const language = keywordForm.querySelector('#language');
            const inputs = keywordForm.querySelectorAll('input[id^="translated"]');
            const tvId = keywordsDiv.getAttribute('data-id');
            const translations = [];
            inputs.forEach(input => {
                const original = input.getAttribute('data-original');
                const translated = input.value;
                translations.push({original: original, translated: translated});
            });
            fetch('/api/keywords/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({type: type, id: tvId, language: language.value, keywords: translations})
            })
                .then((response) => response.json())
                .then(data => {
                    gThis.hideForm(keywordFormContainer);
                    const keywordsDiv = document.querySelector('.keywords');
                    keywordsDiv.innerHTML = data.keywords;
                    gThis.toolTips.init(keywordsDiv);
                });
        });
    }

    keywordInitFields(form, missingKeywords) {
        const buttons = form.querySelector("#keyword-translation-buttons");
        const rows = form.querySelectorAll('.form-row:not([id="keyword-translation-buttons"])')
        let values = [];
        rows.forEach(row => {
            row.remove();
        });

        gThis.keywordTranslationSelect(buttons);

        missingKeywords.forEach((keyword) => {
            values.push(keyword.textContent);
            gThis.maxLength = Math.max(gThis.maxLength, keyword.textContent.length);
        });
        gThis.keywordTranslationFields(buttons, values);
    }

    keywordTranslationFields(buttons, keywords) {
        let index = 1;
        keywords.forEach(keyword => {
            keyword = keyword.trim();
            const row = document.createElement("div");
            row.classList.add('form-row');
            const field = ' \
                <div class="form-field">\n \
                    <label for="translated-' + index + '">\n \
                        <div class="keyword-translation-label">' + keyword + '</div>\n \
                        <input id="translated-' + index + '" type="text" data-original="' + keyword + '" value="' + keyword + '">\n \
                    </label>\n \
            </div>\n';/* style="width: ' + gThis.maxLength + 'ch"*/
            index++;
            row.innerHTML = field;
            buttons.parentNode.insertBefore(row, buttons);
        });
    }

    keywordTranslationSelect(buttons) {
        const locale = document.querySelector("html").getAttribute("lang");
        const languages = {
            "en": [["fr", "🇫🇷 French"], ["en", "🇬🇧 English"], ["kr", "🇰🇷 Korean"]],
            "fr": [["fr", "🇫🇷 Français"], ["en", "🇬🇧 Anglais"], ["kr", "🇰🇷 Coréen"]],
            "kr": [["ko", "🇫🇷 한국어"], ["en", "🇬🇧 영어"], ["fr", "🇰🇷 프랑스어"]]
        };
        const label = {
            "en": "Language:",
            "fr": "Langue :",
            "ko": "언어 :"
        };
        const row = document.createElement("div");
        row.classList.add('form-row');
        let select = ' \
                <div class="form-field">\n \
                    <label for="language">' + label[locale] + '&nbsp;\n \
                        <select id="language">\n';
        languages[locale].forEach(language => {
            select += ' \
                            <option value="' + language[0] + '"' + (language[0] === locale ? ' selected' : '') + '>' + language[1] + '</option>\n';
        });
        select += '\
                        </select>\n \
                    </label>\n \
            </div>\n';
        row.innerHTML = select;
        buttons.parentNode.insertBefore(row, buttons)
    }

    displayForm(form) {
        if (form.getAttribute('popover') === "") {
            form.showPopover();
        } else {
            form.classList.add('display');
            setTimeout(function () {
                form.classList.add('active');
            }, 0);
        }
    }

    hideForm(form) {
        if (form.getAttribute('popover') === "") {
            form.hidePopover();
        } else {
            form.classList.remove('active');
            setTimeout(function () {
                form.classList.remove('display');
            }, 300);
        }
    }
}