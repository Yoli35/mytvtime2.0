let gThis;

export class Profile {
    /**
     * @typedef Globs
     * @type {Object}
     * @property {Array} translations
     */

    constructor() {
        gThis = this;
        const jsonGlobsObject = JSON.parse(document.querySelector('div#globs').textContent);
        this.translations = jsonGlobsObject.translations;
        this.init();
    }

    init() {
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

        function updateImageDisplay(e) {
            const input = e.target;
            const inputName = input.name;
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

        const fileTypes = [
            "image/jpeg",
            "image/png",
            "image/webp",
        ];

        function validFileType(file) {
            return fileTypes.includes(file.type);
        }
    }
}