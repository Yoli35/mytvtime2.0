let gThis;

export class Profile {
    /**
     * @typedef Globs
     * @type {Object}
     * "translations"
     * @property {Array} translations
     * "targetedLanguages"
     * @property {Array} targetedLanguages
     * "preferredLanguages"
     * @property {Array} preferredLanguages
     * "languages"
     * @property {Array} languages
     */

    constructor() {
        gThis = this;
        const jsonGlobsObject = JSON.parse(document.querySelector('div#globs').textContent);
        this.translations = jsonGlobsObject.translations;
        this.targetedLanguages = jsonGlobsObject.targetedLanguages;
        this.preferredLanguages = jsonGlobsObject.preferredLanguages;
        this.languages = jsonGlobsObject.languages;
        this.init();
    }

    init() {
        console.log("User profile init");
        const avatarFile = document.querySelector('#user_avatarFile');
        avatarFile.addEventListener("change", updateImageDisplay);

        const bannerFile = document.querySelector('#user_bannerFile');
        bannerFile.addEventListener("change", updateImageDisplay);

        const previewAvatar = document.querySelector('.preview-avatar-file');
        const previewBanner = document.querySelector('.preview-banner-file');
        previewAvatar.addEventListener("click", () => {
            avatarFile.click();
        });
        previewBanner.addEventListener("click", () => {
            bannerFile.click();
        });

        const targetLanguageSelect = document.querySelector('#targetedLanguageSelect');
        const targetedLanguageList = document.querySelector('#targeted-language-list');
        const preferredLanguageSelect = document.querySelector('#preferredLanguageSelect');
        const preferredLanguageList = document.querySelector('#preferred-language-list');

        targetLanguageSelect.addEventListener('change', (e) => {
            // add the selected language to the list of targeted languages
            const selectedLanguage = e.target.value;
            if (selectedLanguage && !this.targetedLanguages.includes(selectedLanguage)) {
                this.targetedLanguages.push(selectedLanguage);
                this.updateLanguageList(targetedLanguageList, selectedLanguage);
                this.fetchLanguageSettings('targeted', 'add', selectedLanguage);
            }
            // Clear the select after adding
            e.target.value = '';
        });

        preferredLanguageSelect.addEventListener('change', (e) => {
            // add the selected language to the list of preferred languages
            const selectedLanguage = e.target.value;
            if (selectedLanguage && !this.preferredLanguages.includes(selectedLanguage)) {
                this.preferredLanguages.push(selectedLanguage);
                this.updateLanguageList(preferredLanguageList, selectedLanguage);
                this.fetchLanguageSettings('preferred', 'add', selectedLanguage);
            }
            // Clear the select after adding
            e.target.value = '';
        });

        // Initialize the targeted languages list
        this.targetedLanguages.forEach(language => {
            this.updateLanguageList(targetedLanguageList, language);
        });
        // Initialize the preferred languages list
        this.preferredLanguages.forEach(language => {
            this.updateLanguageList(preferredLanguageList, language);
        });

        function updateImageDisplay(e) {
            const input = e.target;
            /*const inputName = input.name;*/
            const preview = input.closest('.form-field').querySelector("div[class^='preview']");
            while (preview.firstChild) {
                preview.removeChild(preview.firstChild);
            }
            const curFiles = input.files;
            if (curFiles.length === 0) {
                const div = document.createElement("div");
                div.textContent = "No files currently selected for upload";
                preview.appendChild(div);
                return;
            }

            const file = curFiles[0];

            if (validFileType(file)) {
                preview.textContent = `${file.name}`;
                const image = document.createElement("img");
                image.src = URL.createObjectURL(file);
                image.alt = image.title = file.name;
                preview.appendChild(image);
            } else {
                preview.innerHTML = `${file.name}<span class="error">${gThis.translations['Not a valid file type. Update your selection']}.</span>`;
            }

        }

        function validFileType(file) {
            const fileTypes = [
                "image/jpeg",
                "image/png",
                "image/webp",
            ];
            return fileTypes.includes(file.type);
        }
    }

    updateLanguageList(listElement, language) {
        // Add new item
        const li = document.createElement('li');
        const svgsDiv = document.querySelector("#svgs");
        const svgTrash = svgsDiv.querySelector("svg#trash").cloneNode(true);
        const svgBurger = svgsDiv.querySelector("svg#burger").cloneNode(true);
        li.classList.add("language-item");
        li.setAttribute("data-id", language);
        li.textContent = this.languages[language] || language; // Use translation if available
        svgTrash.setAttribute("id", "delete-" + language);
        svgTrash.setAttribute("data-id", language);
        svgTrash.classList.add("delete-language");
        svgTrash.addEventListener("click", gThis.deleteLanguage);
        li.appendChild(svgTrash);
        svgBurger.setAttribute("id", "drag-" + language);
        svgBurger.setAttribute("data-id", language);
        svgBurger.classList.add("drag-language");
        svgBurger.addEventListener("click", gThis.dragLanguage);
        li.appendChild(svgBurger);
        listElement.appendChild(li);
    }

    deleteLanguage(e) {
        e.preventDefault();
        const trashIcon = e.currentTarget;
        const list = trashIcon.closest('ul');
        const listId = list.getAttribute("id");
        const languageId = trashIcon.getAttribute("data-id");
        console.log("Delete language: " + languageId);
        // Remove the language from the list and update the UI
        const listItem = document.querySelector(`li[data-id="${languageId}"]`);
        if (listItem) {
            listItem.remove();
            // Also remove from targetedLanguages or preferredLanguages arrays
            if (listId === 'targeted-language-list') {
                gThis.targetedLanguages = gThis.targetedLanguages.filter(lang => lang !== languageId);
                console.log("Updated targeted languages: " + gThis.targetedLanguages);
                gThis.fetchLanguageSettings('targeted', 'delete', languageId);
            } else if (listId === 'preferred-language-list') {
                gThis.preferredLanguages = gThis.preferredLanguages.filter(lang => lang !== languageId);
                console.log("Updated preferred languages: " + gThis.preferredLanguages);
                gThis.fetchLanguageSettings('preferred', 'delete', languageId);
            }
        }
    }

    fetchLanguageSettings(languageType, action, languageId) {
        const lang = document.documentElement.lang;
        // Fetch the language settings from the server
        fetch('/' + lang + '/user/language-settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                languageType: languageType,
                languageId: languageId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log("Fetched language settings:", data.languages);
                    // Update the UI with the fetched languages
                    const listElement = document.querySelector(`#${languageType}-language-list`);
                    listElement.innerHTML = ''; // Clear existing items
                    data.languages.forEach(language => {
                        this.updateLanguageList(listElement, language);
                    });
                } else {
                    console.error("Error fetching language settings:", data.message);
                }
            })
            .catch(error => console.error("Fetch error:", error));

    }

    dragLanguage(e) {
        e.preventDefault();
        const languageId = e.target.getAttribute("data-id");
        console.log("Drag language: " + languageId);
    }
}