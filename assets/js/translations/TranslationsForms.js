import {ToolTips} from "ToolTips";

let self;

export class TranslationsForms {
    constructor(id, type, translations, seasonNumber = null) {
        self = this;
        this.toolTips = new ToolTips();
        this.translations = translations;
        this.id = id;
        this.mediaType = type;
        this.seasonNumber = seasonNumber;
        this.lang = document.documentElement.lang;

        this.localizationToolsMenu = document.querySelector('.localization-tools-menu');
        this.nameFormContainer = document.querySelector('.localized-name-form');
        this.nameForm = document.querySelector('#localized-name-form');
        this.overviewFormContainer = document.querySelector('.overview-form');
        this.overviewForm = document.querySelector('#overview-form');
        this.deleteOverviewFormContainer = document.querySelector('.delete-overview-form');
        this.deleteOverviewForm = document.querySelector('#delete-overview-form');

        this.biographyFormContainer = document.querySelector("#biography-form-container");
        this.biographyForm = document.querySelector("#biography-form");

        this.openNameForm = this.openNameForm.bind(this);
        this.openLocalizedOverviewForm = this.openLocalizedOverviewForm.bind(this);
        this.openAdditionalOverviewForm = this.openAdditionalOverviewForm.bind(this);
        this.openDeleteOverviewForm = this.openDeleteOverviewForm.bind(this);
        this.openBiographyForm = this.openBiographyForm.bind(this);
        this.displayForm = this.displayForm.bind(this);

        this.saveName = this.saveName.bind(this);
        this.deleteName = this.deleteName.bind(this);
        this.cancelName = this.cancelName.bind(this);

        this.saveOverview = this.saveOverview.bind(this);
        this.cancelOverview = this.cancelOverview.bind(this);
        this.deleteOverview = this.deleteOverview.bind(this);
        this.cancelDeletion = this.cancelDeletion.bind(this);

        this.saveBiography = this.saveBiography.bind(this);
        this.cancelBiography = this.cancelBiography.bind(this);

        this.init();
    }

    init() {
        const localizationToolsButton = document.querySelector('.localization-tools-button');
        const localizationToolsMenu = document.querySelector('.localization-tools-menu');
        localizationToolsButton?.addEventListener('click', function () {
            localizationToolsMenu.classList.toggle('active');
        });

        const localizedName = document.querySelector('#localized-name');
        const localizedOverview = document.querySelector('#localized-overview');
        const additionalOverview = document.querySelector('#additional-overview');
        const overviews = document.querySelectorAll('.overview');




        if (localizedName && this.seasonNumber == null) {
            const cancelButton = this.nameForm?.querySelector('button[type="button"]');
            const deleteButton = this.nameForm?.querySelector('button[value="delete"]');
            const saveButton = this.nameForm?.querySelector('button[value="add"]');
            localizedName.addEventListener('click', this.openNameForm);
            cancelButton.addEventListener('click', this.cancelName);
            deleteButton?.addEventListener('click', this.deleteName);
            saveButton.addEventListener('click', this.saveName);
        }

        localizedOverview?.addEventListener('click', this.openLocalizedOverviewForm);

        if (additionalOverview && this.seasonNumber == null) {
            additionalOverview.addEventListener('click', this.openAdditionalOverviewForm);
        }

        if (this.overviewForm) {
            const cancelButton = this.overviewForm?.querySelector('button[type="button"]');
            const saveButton = this.overviewForm?.querySelector('button[value="add"]');
            cancelButton.addEventListener('click', this.cancelOverview);
            saveButton.addEventListener('click', this.saveOverview);
        }

        if (this.deleteOverviewForm) {
            const cancelButton = this.deleteOverviewForm?.querySelector('button[type="button"]');
            const deleteButton = this.deleteOverviewForm?.querySelector('button[value="delete"]');
            cancelButton.addEventListener('click', this.cancelDeletion);
            deleteButton.addEventListener('click', this.deleteOverview);
        }

        /* Tools for every added overview */
        if (overviews) {
            overviews.forEach(function (overview) {
                const tools = overview.querySelector('.tools');
                if (tools) {
                    const edit = tools.querySelector('.edit');
                    const del = tools.querySelector('.delete');
                    edit.addEventListener('click', self.editOverview);
                    del.addEventListener('click', self.openDeleteOverviewForm);
                }
            });
        }

        if (this.biographyFormContainer) {
            const localizedBiographyMenu = document.querySelector("#localized-biography");
            const cancelButton = this.biographyForm.querySelector('button[type="button"]');
            const saveButton = this.biographyForm.querySelector('button[value="add"]');

            localizedBiographyMenu.addEventListener('click', this.openBiographyForm);
            cancelButton.addEventListener('click', this.cancelBiography);
            saveButton.addEventListener("click", this.saveBiography);
        }
    }

    async openNameForm() {
        await this.updateClipboardCacheFromUserGesture();
        this.localizationToolsMenu.classList.toggle('active');
        this.displayForm(this.nameFormContainer);
    }

    saveName(e) {
        e.preventDefault();

        const name = this.nameForm.querySelector('#name');
        const errors = this.nameForm.querySelectorAll('.error');
        errors.forEach(function (error) {
            error.textContent = '';
        });
        if (!name.value) {
            name.nextElementSibling.textContent = self.translations['This field is required'];
        } else {
            fetch('/api/' + self.mediaType + '/name/add/' + self.id, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({name: name.value})
                }
            ).then(function (response) {
                if (response.ok) {
                    self.hideForm(self.nameFormContainer);
                    const localizedNameSpan = document.querySelector('.localization-span');
                    if (localizedNameSpan) {
                        localizedNameSpan.textContent = name.value;
                    } else {
                        const h1 = document.querySelector('h1');
                        const nameSpan = document.querySelector('.name-span');
                        const localizedNameSpan = document.createElement('span');
                        const brJustAfterSpan = document.createElement('br');
                        localizedNameSpan.classList.add('localization-span');
                        localizedNameSpan.textContent = name.value;
                        h1.insertBefore(localizedNameSpan, nameSpan);
                        h1.insertBefore(brJustAfterSpan, nameSpan);
                    }
                }
            });
        }
    }

    deleteName(e) {
        e.preventDefault();

        fetch('/api/' + this.mediaType + '/name/remove/' + this.id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({locale: this.lang})
            }
        ).then(function (response) {
            if (response.ok) {
                self.hideForm(self.nameFormContainer);
                const localizedNameSpan = document.querySelector('.localization-span');
                const brJustAfterSpan = document.querySelector('.localization-span + br');
                localizedNameSpan.remove();
                brJustAfterSpan.remove();
            }
        });
    }

    cancelName() {
        this.hideForm(this.nameFormContainer);
    }

    async openLocalizedOverviewForm() {
        await this.updateClipboardCacheFromUserGesture();
        const firstRow = this.overviewForm.querySelector('.form-row:first-child');
        const hiddenInputTool = this.overviewForm.querySelector('#tool');
        hiddenInputTool.setAttribute('data-type', 'localized');
        hiddenInputTool.setAttribute('data-crud', 'add');
        hiddenInputTool.setAttribute('data-overview-id', "-1");
        firstRow.classList.add('hide');
        const submitButton = this.overviewForm.querySelector('button[type="submit"]');
        submitButton.textContent = this.translations['Add'];
        const overviewField = this.overviewForm.querySelector('#overview-field');
        overviewField.value = '';
        this.localizationToolsMenu.classList.toggle('active');
        this.displayForm(this.overviewFormContainer);
    }

    async openAdditionalOverviewForm() {
        await this.updateClipboardCacheFromUserGesture();
        const overviewFormContainer = document.querySelector('.overview-form');
        const firstRow = this.overviewForm.querySelector('.form-row:first-child');
        const hiddenInputTool = this.overviewForm.querySelector('#tool');
        hiddenInputTool.setAttribute('data-type', 'additional');
        hiddenInputTool.setAttribute('data-crud', 'add');
        hiddenInputTool.setAttribute('data-overview-id', "-1");
        firstRow.classList.remove('hide');
        const submitButton = this.overviewForm.querySelector('button[type="submit"]');
        submitButton.textContent = this.translations['Add'];
        const overviewField = this.overviewForm.querySelector('#overview-field');
        overviewField.value = '';
        this.localizationToolsMenu.classList.toggle('active');
        this.displayForm(overviewFormContainer);
    }

    saveOverview(e) {
        e.preventDefault();

        const source = this.overviewForm.querySelector('#overview-source');
        const sourceError = source.closest('label').querySelector('.error');
        const overviewField = this.overviewForm.querySelector('#overview-field');
        const overviewError = overviewField.closest('label').querySelector('.error');
        const hiddenInputTool = this.overviewForm.querySelector('#tool');
        const errors = this.overviewForm.querySelectorAll('.error');
        errors.forEach(function (error) {
            error.textContent = '';
        });
        const type = hiddenInputTool.getAttribute('data-type');
        const overviewId = parseInt(hiddenInputTool.getAttribute('data-overview-id'));
        const crud = hiddenInputTool.getAttribute('data-crud');
        const additional = type === 'additional';
        if (additional && !source.value) {
            sourceError.textContent = self.translations['This field is required'];
        }
        if (!overviewField.value) {
            overviewError.textContent = self.translations['This field is required'];
        }
        let data = {
            overviewId: overviewId,
            source: source.value,
            overview: overviewField.value,
            type: type,
            crud: crud,
            locale: this.lang
        };
        if (this.seasonNumber) {
            data = {seasonNumber: this.seasonNumber, ...data};
        }

        fetch('/api/' + this.mediaType + '/overview/add/' + this.id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                if (data.success) {
                    self.hideForm(self.overviewFormContainer);

                    if (crud === 'edit') {
                        const overviewDiv = document.querySelector('.' + type + '.overview[data-id="' + overviewId + '"]');
                        const contentDiv = overviewDiv.querySelector('.content');
                        const newContentText = overviewField.value;
                        contentDiv.setAttribute('data-overview', newContentText);
                        contentDiv.innerHTML = newContentText.replace(/\n/g, '<br>');
                        const toolsDiv = overviewDiv.querySelector('.tools');
                        self.setSource(toolsDiv, data.source);
                        return;
                    }

                    // crud: add
                    const infosDiv = document.querySelector('.infos');
                    const infosContentDiv = infosDiv.querySelector('.infos-content');
                    let overviewsDiv = infosContentDiv.querySelector('.' + type + '.overviews');

                    const newId = data.id;
                    /** @type {Source} */
                    const sourceRecord = data.source;

                    const overviewDiv = document.createElement('div');
                    overviewDiv.classList.add(type, 'overview');
                    overviewDiv.setAttribute('data-id', newId);
                    const contentDiv = document.createElement('div');
                    contentDiv.classList.add('content');
                    contentDiv.setAttribute('data-overview', overviewField.value);
                    contentDiv.innerHTML = overviewField.value.replace(/\n/g, '<br>');
                    overviewDiv.appendChild(contentDiv);

                    const toolsDiv = document.createElement('div');
                    toolsDiv.classList.add('tools');
                    self.setSource(toolsDiv, sourceRecord);

                    const localeDiv = document.createElement('div');
                    localeDiv.classList.add('locale');
                    localeDiv.textContent = this.lang.toUpperCase();
                    toolsDiv.appendChild(localeDiv);

                    const editDiv = document.createElement('div');
                    editDiv.classList.add('edit');
                    editDiv.setAttribute('data-id', newId);
                    editDiv.setAttribute('data-title', self.translations['Edit']);
                    const penSvg = document.querySelector('#svgs').querySelector('#pen').querySelector('svg').cloneNode(true);
                    editDiv.appendChild(penSvg);
                    editDiv.addEventListener('click', self.editOverview);
                    toolsDiv.appendChild(editDiv);

                    const deleteDiv = document.createElement('div');
                    deleteDiv.classList.add('delete');
                    deleteDiv.setAttribute('data-id', newId);
                    deleteDiv.setAttribute('data-title', self.translations['Delete']);
                    const trashSvg = document.querySelector('#svgs').querySelector('#trash').querySelector('svg').cloneNode(true);
                    deleteDiv.appendChild(trashSvg);
                    deleteDiv.addEventListener('click', self.openDeleteOverviewForm);
                    toolsDiv.appendChild(deleteDiv);

                    overviewDiv.appendChild(toolsDiv);

                    overviewsDiv.appendChild(overviewDiv);
                    self.toolTips.init(overviewDiv);

                    if (this.seasonNumber) {
                        const globalToolsDiv = document.querySelector('.localization-tools');
                        globalToolsDiv.classList.add('d-none');
                    }

                    overviewField.value = '';
                }
            });
    }

    cancelOverview() {
        this.hideForm(this.overviewFormContainer);
    }

    async openBiographyForm() {
        await this.updateClipboardCacheFromUserGesture();
        const biographyFormContainer = document.querySelector("#biography-form-container");
        const localizationToolsMenu = document.querySelector('.localization-tools-menu');
        const localizedBiographyText = document.querySelector(".localized-bio").innerText;
        const biographyForm = document.querySelector("#biography-form");
        const hiddenInputTool = biographyForm.querySelector('#tool');
        hiddenInputTool.setAttribute('data-crud', localizedBiographyText.length ? 'edit' : 'add');
        hiddenInputTool.setAttribute('data-people-id', "id");
        const submitButton = biographyForm.querySelector('button[type="submit"]');
        submitButton.textContent = this.translations[localizedBiographyText.length ? 'Edit' : 'Add'];
        const biographyField = biographyForm.querySelector('#biography-field');
        biographyField.value = localizedBiographyText;
        localizationToolsMenu.classList.toggle('active');
        this.displayForm(biographyFormContainer);
    }

    saveBiography(e) {
        e.preventDefault();
        const biographyField = this.biographyForm.querySelector('#biography-field');
        if (biographyField.value.length < 20) {
            const errorSpan = this.biographyForm.querySelector(".error");
            errorSpan.innerText = self.translations['biography error'];
            return;
        }
        fetch('/api/people/biography/' + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({'bio': biographyField.value})
        })
            .then(res => res.json())
            .then(data => {
                console.log(data);
                if (data.success) {
                    self.hideForm(self.biographyFormContainer);
                    const localizedBioDiv = document.querySelector('.localized-bio');
                    localizedBioDiv.innerText = biographyField.value;
                }
            })
            .catch(e => {
                console.log(e)
            });
    }

    cancelBiography() {
        this.hideForm(self.biographyFormContainer);
    }

    setSource(toolsDiv, sourceRecord) {
        if (sourceRecord) {
            let sourceDiv = toolsDiv.querySelector('.source'), sourceExists = false;
            if (!sourceDiv) {
                sourceDiv = document.createElement('div');
                sourceDiv.classList.add('source');
            } else {
                sourceDiv.innerHTML = "";
                sourceExists = true;
            }
            if (sourceRecord.path) {
                const sourceA = document.createElement('a');
                sourceA.href = sourceRecord.path;
                sourceA.setAttribute('data-title', sourceRecord.name);
                sourceA.target = '_blank';
                sourceA.rel = 'noopener noreferrer';
                sourceDiv.appendChild(sourceA);
                if (sourceRecord.logoPath) {
                    const sourceImg = document.createElement('img');
                    sourceImg.src = sourceRecord.logoPath;
                    sourceImg.alt = sourceRecord.name;
                    sourceA.appendChild(sourceImg);
                } else {
                    sourceA.textContent = sourceRecord.name;
                }
                self.toolTips.init(sourceDiv);
            } else {
                sourceDiv.textContent = sourceRecord.name;
            }
            if (!sourceExists) {
                toolsDiv.appendChild(sourceDiv);
            }
        }
    }

    editOverview(e) {
        const overviewForm = document.querySelector('.overview-form');
        const editTool = e.currentTarget;
        const overviewDiv = editTool.closest('.overview');
        const type = overviewDiv.classList.contains('localized') ? 'localized' : 'additional';
        const id = editTool.getAttribute('data-id');
        const content = overviewDiv.querySelector('.content').getAttribute('data-overview');
        const form = document.querySelector('.overview-form');
        const hiddenInputTool = form.querySelector('#tool');
        const overviewField = form.querySelector('#overview-field');
        hiddenInputTool.value = id;
        hiddenInputTool.setAttribute('data-type', type);
        hiddenInputTool.setAttribute('data-crud', 'edit');
        hiddenInputTool.setAttribute('data-overview-id', id);
        overviewField.value = content.trim();
        const firstRow = form.querySelector('.form-row:first-child');
        if (type === 'localized') {
            firstRow.classList.add('hide');
        } else {
            firstRow.classList.remove('hide');
            const select = form.querySelector('#overview-source');
            const sourceId = overviewDiv.getAttribute('data-source-id');
            if (sourceId) {
                select.value = sourceId;
            }
        }
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.textContent = self.translations['Edit'];
        e.preventDefault();
        self.displayForm(overviewForm);
    }

    openDeleteOverviewForm(e) {
        const deleteToolDiv = e.currentTarget;
        const overviewDiv = deleteToolDiv.closest('.overview');
        const type = overviewDiv.classList.contains('localized') ? 'localized' : 'additional';
        const id = deleteToolDiv.getAttribute('data-id');
        const overviewType = this.deleteOverviewForm.querySelector('#overview-type');
        const overviewId = this.deleteOverviewForm.querySelector('#overview-id');
        overviewType.value = type;
        overviewId.value = id;
        e.preventDefault();
        self.displayForm(this.deleteOverviewFormContainer);
    }

    deleteOverview(e) {
        e.preventDefault();

        const overviewType = this.deleteOverviewForm.querySelector('#overview-type').value;
        const overviewId = this.deleteOverviewForm.querySelector('#overview-id').value;
        fetch('/api/' + this.mediaType + '/overview/remove/' + overviewId, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({overviewType: overviewType})
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    self.hideForm(this.deleteOverviewForm);
                    const overviewDiv = document.querySelector('.' + overviewType + '.overview[data-id="' + overviewId + '"]');
                    overviewDiv.remove();
                    if (this.seasonNumber) {
                        const globalToolsDiv = document.querySelector('.localization-tools');
                        globalToolsDiv.classList.remove('d-none');
                    }
                }
            });
    }

    cancelDeletion() {
        this.hideForm(this.deleteOverviewFormContainer);
    }

    displayForm(formContainer) {
        this.pasteButton(formContainer);

        if (formContainer.getAttribute('popover') === "") {
            formContainer.showPopover();
            formContainer.querySelector('textarea')?.focus();
        } else {
            formContainer.classList.add('display');
            setTimeout(function () {
                form.classList.add('active');
            }, 0);
            formContainer.querySelector('textarea')?.focus();
        }
    }

    pasteButton(formContainer) {
        if (!this.clipboardCache) return;

        const textarea = formContainer.querySelector('textarea');
        if (!textarea) return;

        // Crée le bouton si nécessaire
        const text = this.clipboardCache
        const submitButton = formContainer.querySelector('button[type="submit"]');
        const parentElement = submitButton.parentElement;
        const pasteBtn = document.createElement('button');
        pasteBtn.type = 'button';
        pasteBtn.classList.add('paste-from-clipboard');
        pasteBtn.textContent = (self.translations && self.translations['Paste']) || 'Coller';
        pasteBtn.setAttribute("data-title", "“ " + text + " ”");
        self.toolTips.initElement(pasteBtn);

        pasteBtn.addEventListener('click', function () {
            const start = textarea.selectionStart || 0;
            const end = textarea.selectionEnd || 0;
            const before = textarea.value.slice(0, start);
            const after = textarea.value.slice(end);
            textarea.value = before + text + after;
            // replacer le curseur après le texte collé
            const newPos = start + text.length;
            textarea.setSelectionRange(newPos, newPos);
            textarea.focus();
            pasteBtn.remove();
        });
        parentElement.insertBefore(pasteBtn, submitButton);
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

        // retirer le bouton "Coller" s'il existe
        const pasteBtn = form.querySelector('.paste-from-clipboard');
        if (pasteBtn) {
            pasteBtn.remove();
        }
    }

    async updateClipboardCacheFromUserGesture() {
        try {
            if (!navigator.clipboard?.readText) return;
            const text = await navigator.clipboard.readText();
            this.clipboardCache = (text && text.trim()) ? text : "";
        } catch {
            this.clipboardCache = "";
        }
    }
}