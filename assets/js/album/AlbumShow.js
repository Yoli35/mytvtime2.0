import {Diaporama} from 'Diaporama';
import {FlashMessage} from "FlashMessage";
import {Map} from "Map";
import {ToolTips} from 'ToolTips';

let gThis = null;

export class AlbumShow {
    /**
     *  @typedef Photo
     * @type {Object}
     * @property {number} id
     * @property {number} user_id
     * @property {number} album_id
     * @property {string} caption
     * @property {string} image_path
     * @property {string} created_at_tring
     * @property {string} updated_at_string
     * @property {string} date_string
     * @property {number} latitude
     * @property {number} longitude
     */

    /** @typedef Album
     * @type {Object}
     * @property {number} id
     * @property {number} user_id
     * @property {string} name
     * @property {string} created_at_tring
     * @property {string} updated_at_string
     * @property {Array.<Photo>} photos
     */

    /**
     * @typedef Globs
     * @type {Object}
     * @property {Album} album
     * @property {Array.<string>} texts
     * @property {Array.<string>} srcsetPaths
     */

    constructor() {
        gThis = this;
        this.lang = document.querySelector('html').getAttribute('lang');
        this.toolTips = new ToolTips();
        this.flashMessage = new FlashMessage();
        this.diaporama = new Diaporama();
        /** @var {Globs} */
        const globs = JSON.parse(document.querySelector('div#globs').textContent);
        this.album = globs.album;
        this.texts = globs.texts;
        this.srcsetPaths = globs.srcsetPaths;
        this.fileTypes = [
            "image/jpeg",
            "image/png",
            "image/webp",
        ];

        this.init();
    }

    init() {

        /******************************************************************************
         * Diaporama for photos                                 *
         ******************************************************************************/
        const photoWrapper = document.querySelector('.album-photos');
        const photos = photoWrapper.querySelectorAll('img');
        this.diaporama.start(photos);

        /******************************************************************************
         * mapbox gl                                                                  *
         ******************************************************************************/
        const mapDiv = document.querySelector('.map-controller');
        if (mapDiv) {
            this.map = new Map();
        }

        /******************************************************************************
         * Album form                                                                 *
         ******************************************************************************/
        const addPhotosButton = document.querySelector('.add-photos-button');
        const modifyAlbumDialog = document.querySelector('.side-panel.modify-album-dialog');
        const modifyAlbumForm = modifyAlbumDialog.querySelector('form');
        const nameInput = document.querySelector('input[name="name"]');
        /*const inputGoogleMapsUrl = modifyAlbumForm.querySelector('input[name="google-map-url"]');
        const inputLatitude = modifyAlbumForm.querySelector('input[name="latitude"]');
        const inputLongitude = modifyAlbumForm.querySelector('input[name="longitude"]');*/
        const modifyAlbumCancel = modifyAlbumDialog.querySelector('button[type="button"]');
        const modifyAlbumSubmit = modifyAlbumDialog.querySelector('button[type="submit"]');
        const submitRow = modifyAlbumForm.querySelector('.form-row.submit-row');
        const scrollDownToSubmitDiv = modifyAlbumDialog.querySelector('.scroll-down-to-submit');
        const scrollDownToSubmitButton = scrollDownToSubmitDiv.querySelector('button');

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                console.log(entry)
                if (entry.isIntersecting) {
                    scrollDownToSubmitDiv.style.display = 'none';
                } else {
                    scrollDownToSubmitDiv.style.display = 'flex';
                }
            });
        });
        observer.observe(submitRow);
        scrollDownToSubmitButton.addEventListener('click', function () {
            // addLocationDialog > frame > form > submit-row
            // frame overflow-y: auto;
            // faire apparaitre la div "submit-row" dans le cadre
            modifyAlbumDialog.querySelector('.frame').scrollTo(0, submitRow.offsetTop);
        });

        addPhotosButton.addEventListener('click', function () {
            gThis.openAlbumPanel(modifyAlbumDialog);
        });
        /*inputGoogleMapsUrl.addEventListener('paste', function (e) {
            const url = e.clipboardData.getData('text');
            const isGoogleMapsUrl = url.match(/https:\/\/www.google.com\/maps\//);
            let urlParts;
            if (isGoogleMapsUrl) {
                urlParts = url.split('@')[1].split(',');
            } else { // 48.8566,2.3522
                urlParts = url.split(',');
            }
            inputLatitude.value = parseFloat(urlParts[0].trim());
            inputLongitude.value = parseFloat(urlParts[1].trim());
        });*/

        // https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file
        const imageFiles = modifyAlbumForm.querySelector('input[name="image-files"]');
        imageFiles.addEventListener("change", gThis.updateImageDisplay);

        modifyAlbumCancel.addEventListener('click', function () {
            gThis.closeAlbumPanel();
        });
        modifyAlbumSubmit.addEventListener('click', function (event) {
            event.preventDefault();

            const errorSpan = nameInput.parentElement.querySelector('span');
            if (nameInput.value === '') {
                errorSpan.innerText = 'This field is required';
                return;
            } else {
                errorSpan.innerText = '';
            }

            modifyAlbumCancel.setAttribute('disabled', '');
            modifyAlbumSubmit.setAttribute('disabled', '');

            const summaryDiv = document.createElement('div');
            summaryDiv.classList.add('album-summary');
            modifyAlbumForm.appendChild(summaryDiv);

            const formData = gThis.getFormData(modifyAlbumForm);
            const formFiles = gThis.getFormFiles(modifyAlbumForm);
            gThis.fileCount = formFiles.length;

            summaryDiv.innerText = 'Saving album infos…'
            fetch('/' + gThis.lang + '/album/modify/' + gThis.album.id,
                {
                    method: 'POST',
                    body: formData
                }
            ).then(async function (response) {
                const data = await response.json();
                console.log({data});
                if (response.ok) {
                    data['messages'].forEach(msg => {
                        gThis.flashMessage.add('success', msg);
                        gThis.fetchFile(formFiles, summaryDiv);
                    })
                } else {
                    gThis.flashMessage.add('error', data.message);
                }
            });
        });
    }

    fetchFile(formFiles, summaryDiv) {
        const count = formFiles.length;
        if (count) {
            const formFile = formFiles.shift();
            const filename = formFile.name;
            summaryDiv.innerText = 'Saving ' + filename + ' file' + ' - ' + (gThis.fileCount - count) + ' / ' + gThis.fileCount;
            fetch('/' + gThis.lang + '/album/add/' + gThis.album.id,
                {
                    method: 'POST',
                    body: formFile
                }
            ).then(async function (response) {
                const data = await response.json();
                console.log({data});
                if (response.ok) {
                    data['messages'].forEach(msg => {
                        gThis.flashMessage.add('success', msg);
                    });
                    const imagePaths = data['image_paths'];
                    if (imagePaths.length) {
                        const noPhotoDiv = document.querySelector('.no-photo');
                        noPhotoDiv?.remove();
                        const albumPhotosDiv = document.querySelector('.album-photos');
                        imagePaths.forEach(imagePath => {
                            const albumPhotoDiv = document.createElement('div');
                            albumPhotoDiv.classList.add('album-photo');
                            const img = document.createElement('img');
                            img.src = gThis.srcsetPaths['highRes'] + imagePath;
                            img.alt = imagePath;
                            img.loading = "lazy";
                            img.srcset = gThis.srcsetPaths['lowRes'] + imagePath + ' 576w,'
                                        + gThis.srcsetPaths['mediumRes'] + imagePath + ' 720w,'
                                        + gThis.srcsetPaths['highRes'] + imagePath + ' 1080w,'
                                        + gThis.srcsetPaths['original'] + imagePath + ' 1600w';
                            albumPhotoDiv.appendChild(img);
                            gThis.diaporama.start(albumPhotoDiv.querySelectorAll('img'));
                            albumPhotosDiv.appendChild(albumPhotoDiv);
                        });
                    }
                } else {
                    gThis.flashMessage.add('error', data.message);
                }
                gThis.fetchFile(formFiles, summaryDiv);
            });
        } else {
            const modifyAlbumDialog = document.querySelector('.side-panel.modify-album-dialog');
            const modifyAlbumCancel = modifyAlbumDialog.querySelector('button[type="button"]');
            const modifyAlbumSubmit = modifyAlbumDialog.querySelector('button[type="submit"]');
            modifyAlbumCancel.removeAttribute('disabled');
            modifyAlbumSubmit.removeAttribute('disabled');
            summaryDiv.remove();
            gThis.closeAlbumPanel();
        }
    }

    getForm() {
        const modifyAlbumDialog = document.querySelector('.side-panel.modify-album-dialog');
        return modifyAlbumDialog.querySelector('form');
    }

    updateImageDisplay(e) {
        const input = e.target;
        const inputName = input.name;
        const preview = gThis.getForm().querySelector('.preview-' + inputName);
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

        const list = document.createElement("ol");
        preview.appendChild(list);

        for (const file of curFiles) {
            const listItem = document.createElement("li");
            const div = document.createElement("div");
            if (gThis.validFileType(file)) {
                div.textContent = `${file.name}, ${gThis.returnFileSize(file.size)}`;
                const image = document.createElement("img");
                image.src = URL.createObjectURL(file);
                image.alt = image.title = file.name;

                listItem.appendChild(div);
                listItem.appendChild(image);
            } else {
                div.innerHTML = `${file.name} <span class="error">'Not a valid file type. Update your selection'</span>`;
                listItem.appendChild(div);
            }
            list.appendChild(listItem);
        }
    }

    validFileType(file) {
        return gThis.fileTypes.includes(file.type);
    }

    returnFileSize(number) {
        if (number < 1e3) {
            return `${number} bytes`;
        } else if (number >= 1e3 && number < 1e6) {
            return `${(number / 1e3).toFixed(1)} KB`;
        } else {
            return `${(number / 1e6).toFixed(1)} MB`;
        }
    }

    getFormData(form) {
        const crudTypeInput = form.querySelector('input[name="crud-type"]');
        const crudIdInput = form.querySelector('input[name="crud-id"]');
        const nameInput = form.querySelector('input[name="name"]');
        const descriptionTextarea = form.querySelector('textarea[name="description"]');

        const formData = new FormData();
        formData.append('crud-type', crudTypeInput.value);
        formData.append('crud-id', crudIdInput.value);
        formData.append('name', nameInput.value);
        formData.append('description', descriptionTextarea.value);

        return formData;
    }

    getFormFiles(form) {
        const imageFilesInput = form.querySelector('input[name*="image-files"]');

        if (imageFilesInput.files.length === 0) {
            return [];
        }

        const formDataArr = [];
        Array.from(imageFilesInput.files).forEach(function (file, index) {
            const formData = new FormData();
            formData.append('additional-image-' + index, file);
            formDataArr.push(formData);
        });

        return formDataArr;
    }

    openAlbumPanel(dialog) {
        const form = dialog.querySelector('form');
        const nameInput = form.querySelector('input[name="name"]');

        dialog.classList.add('open');
        nameInput.focus();
        nameInput.select();
    }

    closeAlbumPanel() {
        const modifyDialogDialog = document.querySelector('.side-panel.modify-album-dialog');
        modifyDialogDialog.classList.remove('open');
    }
}
