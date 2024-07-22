import {ToolTips} from 'ToolTips';

let gThis;

export class Keyword {
    maxLength;

    constructor(type) {
        gThis = this;
        this.toolTips = new ToolTips();
        this.maxLength = 0;
        this.init(type); // type: 'series'|'movies'
    }

    init(type) {
        const lang = document.querySelector("html").getAttribute("lang");
        const keywordForm = document.querySelector('.keyword-translation-form');
        const keywordFormsCancel = keywordForm.querySelector('button[type="button"]');
        const keywordFormsSubmit = keywordForm.querySelector('button[type="submit"]');
        const keywordsDiv = document.querySelector('.keywords');
        const missingKeywords = document.querySelectorAll('.keyword.missing');
        missingKeywords.forEach(keyword => {
            keyword.addEventListener('click', function () {
                gThis.keywordInitFields(keywordForm, missingKeywords);
                gThis.displayForm(keywordForm);
            });
        });
        keywordFormsCancel.addEventListener('click', function () {
            gThis.hideForm(keywordForm);
        });
        keywordFormsSubmit.addEventListener('click', function (event) {
            event.preventDefault();

            const language = keywordForm.querySelector('#language');
            const fields = keywordForm.querySelectorAll('.field');
            const tvId = keywordsDiv.getAttribute('data-id');
            const translations = [];
            fields.forEach(field => {
                const original = field.querySelector('input').getAttribute('data-original');
                const translated = field.querySelector('input').value;
                translations.push({original: original, translated: translated});
            });
            fetch('/' + lang + '/' + type + '/keywords/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({id: tvId, language: language.value, keywords: translations})
            })
                .then((response) => response.json())
                .then(data => {
                    console.log({data});
                    gThis.hideForm(keywordForm);
                    const factDiv = document.querySelector(type==='series'?'.fact.keyword-block':'.content.keyword-block');
                    factDiv.innerHTML = data.keywords;
                    gThis.toolTips.init(factDiv);
                });
        });
    }

    keywordInitFields(form, missingKeywords) {
        const content = form.querySelector(".form-body");
        let values = [];

        gThis.keywordTranslationSelect(content);

        missingKeywords.forEach((keyword) => {
            values.push(keyword.textContent);
            gThis.maxLength = Math.max(gThis.maxLength, keyword.textContent.length);
        });
        console.log({values});
        gThis.keywordTranslationFields(content, values);
    }

    keywordTranslationFields(content, keywords) {
        let index = 1;
        let fields = '';
        keywords.forEach(keyword => {
            console.log(keyword);
            const field = ' \
            <div class="field">\n \
                <div class="translation">\n \
                    <label for="translated-' + index + '">' + keyword + '\n \
                        <input id="translated-' + index + '" type="text" data-original="' + keyword + '" value="' + keyword + '" style="width: ' + gThis.maxLength + 'ch">\n \
                    </label>\n \
                </div>\n \
            </div>\n';
            index++;
            fields += field;
        });
        let div = document.createElement("div");
        div.classList.add("fields");
        div.innerHTML = fields;
        content.appendChild(div);
    }

    keywordTranslationSelect(content) {
        const locale = document.querySelector("html").getAttribute("lang");
        const languages = {
            "en": [["fr", "French"], ["en", "English"]],
            "fr": [["fr", "Français"], ["en", "Anglais"]]
        };
        const label = {
            "en": "Language:",
            "fr": "Langue :"
        };
        let select = ' \
            <label for="language">' + label[locale] + '&nbsp;\n \
                <select id="language">\n';
        languages[locale].forEach(language => {
            select += ' \
                    <option value="' + language[0] + '"' + (language[0] === locale ? ' selected' : '') + '>' + language[1] + '</option>\n';
        });
        select += '\
                </select>\n \
            </label>\n';
        let div = document.createElement("div");
        div.classList.add("language");
        div.innerHTML = select;
        content.appendChild(div);
    }

    displayForm(form) {
        form.classList.add('display');
        setTimeout(function () {
            form.classList.add('active');
        }, 0);
    }

    hideForm(form) {
        form.classList.remove('active');
        setTimeout(function () {
            form.classList.remove('display');
        }, 300);
    }
}