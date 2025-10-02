import {ToolTips} from "ToolTips";

let gThis;

export class TranslationsForms {
    constructor(id, type, translations) {
        gThis = this;
        this.toolTips = new ToolTips();
        this.translations = translations;
        this.init(id, type);
    }

    init(id, mediaType) {
        const lang = document.documentElement.lang;
        const localizationToolsButton = document.querySelector('.localization-tools-button');
        const localizationToolsMenu = document.querySelector('.localization-tools-menu');
        localizationToolsButton.addEventListener('click', function () {
            localizationToolsMenu.classList.toggle('active');
        });

        const localizedName = document.querySelector('#localized-name');
        const localizedOverview = document.querySelector('#localized-overview');
        const additionalOverview = document.querySelector('#additional-overview');
        const overviews = document.querySelectorAll('.overview');
        const localizedNameForm = document.querySelector('.localized-name-form');
        const overviewForm = document.querySelector('.overview-form');
        const deleteOverviewForm = document.querySelector('.delete-overview-form');
        const lnForm = document.querySelector('#localized-name-form');
        const lnCancel = lnForm.querySelector('button[type="button"]');
        const lnDelete = lnForm.querySelector('button[value="delete"]');
        const lnAdd = lnForm.querySelector('button[value="add"]');
        const ovForm = document.querySelector('#overview-form');
        const ovCancel = ovForm.querySelector('button[type="button"]');
        const ovAdd = ovForm.querySelector('button[value="add"]');
        const deleteOvForm = document.querySelector('#delete-overview-form');
        const deleteOvCancel = deleteOvForm.querySelector('button[type="button"]');
        const deleteOvDelete = deleteOvForm.querySelector('button[value="delete"]');

        localizedName.addEventListener('click', function () {
            localizationToolsMenu.classList.toggle('active');
            gThis.displayForm(localizedNameForm);
        });
        lnCancel.addEventListener('click', function () {
            gThis.hideForm(localizedNameForm);
        });
        lnDelete?.addEventListener('click', function (event) {
            event.preventDefault();

            fetch('/' + lang + '/' + mediaType + '/localized/name/delete/' + id,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({locale: lang})
                }
            ).then(function (response) {
                if (response.ok) {
                    gThis.hideForm(localizedNameForm);
                    const localizedNameSpan = document.querySelector('.localization-span');
                    const brJustAfterSpan = document.querySelector('.localization-span + br');
                    localizedNameSpan.remove();
                    brJustAfterSpan.remove();
                }
            });
        });
        lnAdd.addEventListener('click', function (event) {
            event.preventDefault();

            const name = lnForm.querySelector('#name');
            const errors = lnForm.querySelectorAll('.error');
            errors.forEach(function (error) {
                error.textContent = '';
            });
            if (!name.value) {
                name.nextElementSibling.textContent = gThis.translations['This field is required'];
            } else {
                fetch('/' + lang + '/' + mediaType + '/localized/name/add/' + id,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({name: name.value})
                    }
                ).then(function (response) {
                    if (response.ok) {
                        gThis.hideForm(localizedNameForm);
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
        });

        localizedOverview.addEventListener('click', function () {
            const firstRow = ovForm.querySelector('.form-row:first-child');
            const hiddenInputTool = ovForm.querySelector('#tool');
            hiddenInputTool.setAttribute('data-type', 'localized');
            hiddenInputTool.setAttribute('data-crud', 'add');
            hiddenInputTool.setAttribute('data-overview-id', "-1");
            firstRow.classList.add('hide');
            const submitButton = ovForm.querySelector('button[type="submit"]');
            submitButton.textContent = gThis.translations['Add'];
            const overviewField = ovForm.querySelector('#overview-field');
            overviewField.value = '';
            localizationToolsMenu.classList.toggle('active');
            gThis.displayForm(overviewForm);
        });
        additionalOverview.addEventListener('click', function () {
            const firstRow = ovForm.querySelector('.form-row:first-child');
            const hiddenInputTool = ovForm.querySelector('#tool');
            hiddenInputTool.setAttribute('data-type', 'additional');
            hiddenInputTool.setAttribute('data-crud', 'add');
            hiddenInputTool.setAttribute('data-overview-id', "-1");
            firstRow.classList.remove('hide');
            const submitButton = ovForm.querySelector('button[type="submit"]');
            submitButton.textContent = gThis.translations['Add'];
            const overviewField = ovForm.querySelector('#overview-field');
            overviewField.value = '';
            localizationToolsMenu.classList.toggle('active');
            gThis.displayForm(overviewForm);
        });
        ovCancel.addEventListener('click', function () {
            gThis.hideForm(overviewForm);
        });

        /* Tools for every added overview */
        if (overviews) {
            overviews.forEach(function (overview) {
                const tools = overview.querySelector('.tools');
                const edit = tools.querySelector('.edit');
                const del = tools.querySelector('.delete');
                edit.addEventListener('click', gThis.editOverview);
                del.addEventListener('click', gThis.deleteOverview);
            });
        }

        deleteOvCancel.addEventListener('click', function () {
            gThis.hideForm(deleteOverviewForm);
        });
        ovAdd.addEventListener('click', function (event) {
            event.preventDefault();

            const source = ovForm.querySelector('#overview-source');
            const sourceError = source.closest('label').querySelector('.error');
            const overviewField = ovForm.querySelector('#overview-field');
            const overviewError = overviewField.closest('label').querySelector('.error');
            const hiddenInputTool = ovForm.querySelector('#tool');
            const errors = ovForm.querySelectorAll('.error');
            errors.forEach(function (error) {
                error.textContent = '';
            });
            const type = hiddenInputTool.getAttribute('data-type');
            const overviewId = parseInt(hiddenInputTool.getAttribute('data-overview-id'));
            const crud = hiddenInputTool.getAttribute('data-crud');
            const additional = type === 'additional';
            if (additional && !source.value) {
                sourceError.textContent = gThis.translations['This field is required'];
            }
            if (!overviewField.value) {
                overviewError.textContent = gThis.translations['This field is required'];
            }
            let data = {
                overviewId: overviewId,
                source: source.value,
                overview: overviewField.value,
                type: type,
                crud: crud,
                locale: lang
            };

            fetch('/' + lang + '/' + mediaType + '/overview/add/edit/' + id, {
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
                        gThis.hideForm(overviewForm);

                        if (crud === 'edit') {
                            const overviewDiv = document.querySelector('.' + type + '.overview[data-id="' + overviewId + '"]');
                            const contentDiv = overviewDiv.querySelector('.content');
                            const newContentText = overviewField.value;
                            contentDiv.setAttribute('data-overview', newContentText);
                            // replace \n by <br>
                            contentDiv.innerHTML = newContentText.replace(/\n/g, '<br>');
                            //contentDiv.textContent = newContentText;

                            const toolsDiv = overviewDiv.querySelector('.tools');
                            gThis.setSource(toolsDiv, data.source);

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
                        gThis.setSource(toolsDiv, sourceRecord);

                        const localeDiv = document.createElement('div');
                        localeDiv.classList.add('locale');
                        localeDiv.textContent = lang.toUpperCase();
                        toolsDiv.appendChild(localeDiv);

                        const editDiv = document.createElement('div');
                        editDiv.classList.add('edit');
                        editDiv.setAttribute('data-id', newId);
                        editDiv.setAttribute('data-title', gThis.translations['Edit']);
                        const penSvg = document.querySelector('#svgs').querySelector('#pen').querySelector('svg').cloneNode(true);
                        editDiv.appendChild(penSvg);
                        editDiv.addEventListener('click', gThis.editOverview);
                        toolsDiv.appendChild(editDiv);

                        const deleteDiv = document.createElement('div');
                        deleteDiv.classList.add('delete');
                        deleteDiv.setAttribute('data-id', newId);
                        deleteDiv.setAttribute('data-title', gThis.translations['Delete']);
                        const trashSvg = document.querySelector('#svgs').querySelector('#trash').querySelector('svg').cloneNode(true);
                        deleteDiv.appendChild(trashSvg);
                        deleteDiv.addEventListener('click', gThis.deleteOverview);
                        toolsDiv.appendChild(deleteDiv);

                        overviewDiv.appendChild(toolsDiv);

                        overviewsDiv.appendChild(overviewDiv);
                        gThis.toolTips.init(overviewDiv);

                        overviewField.value = '';
                    }
                });
        });
        deleteOvDelete.addEventListener('click', function (event) {
            event.preventDefault();

            const overviewType = deleteOverviewForm.querySelector('#overview-type').value;
            const overviewId = deleteOverviewForm.querySelector('#overview-id').value;
            fetch('/' + lang + '/' + mediaType + '/overview/delete/' + overviewId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({overviewType: overviewType})
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        gThis.hideForm(deleteOverviewForm);
                        const overviewDiv = document.querySelector('.' + overviewType + '.overview[data-id="' + overviewId + '"]');
                        overviewDiv.remove();
                    }
                });
        });
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
                gThis.toolTips.init(sourceDiv);
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
        submitButton.textContent = gThis.translations['Edit'];
        e.preventDefault();
        gThis.displayForm(overviewForm);
    }

    deleteOverview(e) {
        const deleteOverviewForm = document.querySelector('.delete-overview-form');
        const deleteToolDiv = e.currentTarget;
        const overviewDiv = deleteToolDiv.closest('.overview');
        const type = overviewDiv.classList.contains('localized') ? 'localized' : 'additional';
        const id = deleteToolDiv.getAttribute('data-id');
        const overviewType = deleteOverviewForm.querySelector('#overview-type');
        const overviewId = deleteOverviewForm.querySelector('#overview-id');
        overviewType.value = type;
        overviewId.value = id;
        e.preventDefault();
        gThis.displayForm(deleteOverviewForm);

    }

    displayForm(form) {
        if (form.getAttribute('popover') === "") {
            form.showPopover();
            form.querySelector('textarea').focus();
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