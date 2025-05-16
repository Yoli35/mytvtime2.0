import {ToolTips} from "ToolTips";

let gThis;
export class AdminMovieEdit {
    constructor() {
        gThis = this;
        this.toolTip = new ToolTips();
        this.atrForm = document.querySelector('.admin__append_to_response__form');
        const globs = document.querySelector("#globs");
        this.globs = JSON.parse(globs.textContent);
        this.appendToResponseArray = this.globs['appendToResponse'];
        this.atrUrl = this.globs.atrUrl;
        this.movieId = this.globs.movieId;
        this.atrSelect = document.querySelector('#append_to_response');
        this.atrResultsDiv = document.querySelector('.admin__append_to_response__results');

        this.init();
    }

    init() {
        this.atrSelect.addEventListener('change', async function (e) {
            const newValue = e.target.value;
            let language = null, page = null, include_image_language = null;
            switch (newValue) {
                case 'changes':
                    page = gThis.appendToResponseArray['Changes']['extra_fields']['page'];
                    break;
                case 'credits':
                    language = gThis.appendToResponseArray['Credits']['extra_fields']['language'];
                    break;
                case 'images':
                    language = gThis.appendToResponseArray['Images']['extra_fields']['language'];
                    include_image_language = gThis.appendToResponseArray['Images']['extra_fields']['include_image_language'];
                    break;
                case 'lists':
                    language = gThis.appendToResponseArray['Lists']['extra_fields']['language'];
                    page = gThis.appendToResponseArray['Lists']['extra_fields']['page'];
                    break;
                case 'reviews':
                    language = gThis.appendToResponseArray['Reviews']['extra_fields']['language'];
                    page = gThis.appendToResponseArray['Reviews']['extra_fields']['page'];
                    break;
                case 'videos':
                    language = gThis.appendToResponseArray['Videos']['extra_fields']['language'];
                    break;
            }

            const languageSelect = document.querySelector('#language');
            const languageLabel = document.querySelector('label[for="language"]');
            const pageSelect = document.querySelector('#page');
            const pageLabel = document.querySelector('label[for="page"]');
            const includeImageLanguageSelect = document.querySelector('#include_image_language');
            const includeImageLanguageLabel = document.querySelector('label[for="include_image_language"]');
            if (language) {
                languageSelect.value = language;
                languageLabel.style.display = 'flex';
            } else {
                languageSelect.value = 'null';
                languageLabel.style.display = 'none';
            }
            if (page) {
                pageSelect.value = page;
                pageLabel.style.display = 'flex';
            } else {
                pageSelect.value = 'null';
                pageLabel.style.display = 'none';
            }
            if (include_image_language) {
                includeImageLanguageSelect.value = include_image_language;
                includeImageLanguageLabel.style.display = 'flex';
            } else {
                includeImageLanguageSelect.value = 'null';
                includeImageLanguageLabel.style.display = 'none';
            }
            gThis.atrResultsDiv.innerHTML = '';
        });

        this.atrForm.addEventListener('submit', (event) => {
            const atrSelect = document.querySelector('#append_to_response');
            const atrValue = atrSelect.value;
            const atrUrl = gThis.atrUrl;
            const atrData = new FormData(gThis.atrForm);
            atrData.append('append_to_response', atrValue);
            atrData.append('id', gThis.movieId);
            event.preventDefault();
            fetch(atrUrl, {
                method: 'POST',
                body: atrData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    gThis.atrResultsDiv.innerHTML = data;
                    gThis.toolTip.init(gThis.atrResultsDiv);
                })
                .catch(error => {
                    console.error('There was a problem with the fetch operation:', error);
                });
        });
    }
}