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
     * @typedef SrcsetPaths
     * @type {Object}
     * @property {string} lowRes
     * @property {string} mediumRes
     * @property {string} highRes
     * @property {string} original
     */

    /**
     * @typedef Globs
     * @type {Object}
     * @property {Album} album
     * @property {Array.<string>} imagePaths
     * @property {Array.<string>} texts
     * @property {Array.<string>} settings
     * @property {SrcsetPaths} srcsetPaths
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
        this.imagePaths = globs.imagePaths;
        console.log(gThis.imagePaths);
        this.texts = globs.texts;
        this.srcsetPaths = globs.srcsetPaths;
        this.settings = globs.settings;
        this.svgs = {date: null, update: null, create: null};
        this.svgs.date = document.querySelector('#svgs #svg-date svg');
        this.svgs.update = document.querySelector('#svgs #svg-update svg');
        this.svgs.create = document.querySelector('#svgs #svg-create svg');
        console.log(this.svgs);
        this.interval = null;
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
        if (photos && photos.length) {
            this.diaporama.start(photos);
        }

        /******************************************************************************
         * mapbox gl                                                                  *
         ******************************************************************************/
        const mapDiv = document.querySelector('.map-controller');
        if (mapDiv) {
            this.map = new Map({cooperativeGesturesOption: false});
        }

        /******************************************************************************
         * Album form                                                                 *
         ******************************************************************************/
        this.initAlbumForm();

        /******************************************************************************
         * Photo form                                                                 *
         ******************************************************************************/
        this.initPhotoForm();

        /******************************************************************************
         * Background animation                                                       *
         ******************************************************************************/
        this.initAnimation();

        /******************************************************************************
         * Layout buttons                                                             *
         ******************************************************************************/
        this.initLayoutButtons();
    }

    initLayoutButtons() {
        const albumPhotosDiv = document.querySelector('.album-photos');
        const layoutButtons = document.querySelectorAll('.layout-buttons button');

        layoutButtons.forEach(button => {
            button.addEventListener('click', () => {
                layoutButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
            });
        });
        document.querySelector('.layout-grid').addEventListener('click', () => {
            albumPhotosDiv.classList.remove('list');
            albumPhotosDiv.classList.add('grid');
            gThis.initAnimation();
            gThis.saveLayout(gThis.album.id, 'grid');
        });
        document.querySelector('.layout-list').addEventListener('click', () => {
            albumPhotosDiv.classList.remove('grid');
            albumPhotosDiv.classList.add('list');
            gThis.stopAnimation();
            gThis.saveLayout(gThis.album.id, 'list');
        });
    }

    saveLayout(id, layout) {
        const lang = document.querySelector('html').getAttribute('lang');
        const settings = {
            layout: layout,
            photosPerPage: gThis.settings.photosPerPage,
        };
        fetch('/' + lang + '/album/settings/' + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': gThis.settings.token
            },
            body: JSON.stringify(settings)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Settings updated:', data);
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
    }

    initAlbumForm() {
        const addPhotos = document.querySelector('#add-photos');
        const addPhotosButton = document.querySelector('.add-photos-button');
        const modifyAlbumDialog = document.querySelector('.side-panel.modify-album-dialog');
        const modifyAlbumForm = modifyAlbumDialog?.querySelector('form');
        const nameInput = document.querySelector('input[name="name"]');
        const modifyAlbumCancel = modifyAlbumDialog?.querySelector('button[type="button"]');
        const modifyAlbumSubmit = modifyAlbumDialog?.querySelector('button[type="submit"]');
        const submitRow = modifyAlbumForm?.querySelector('.form-row.submit-row');
        const scrollDownToSubmitDiv = modifyAlbumDialog?.querySelector('.scroll-down-to-submit');
        const scrollDownToSubmitButton = scrollDownToSubmitDiv?.querySelector('button');

        if (modifyAlbumForm) {
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    /*console.log(entry)*/
                    if (entry.isIntersecting) {
                        scrollDownToSubmitDiv.style.display = 'none';
                    } else {
                        scrollDownToSubmitDiv.style.display = 'flex';
                    }
                });
            });
            observer.observe(submitRow);
            scrollDownToSubmitButton.addEventListener('click', function () {
                modifyAlbumDialog.querySelector('.frame').scrollTo(0, submitRow.offsetTop);
            });
        }

        if (addPhotos) {
            addPhotos.addEventListener('click', function () {
                gThis.openAlbumPanel(modifyAlbumDialog);
            });
            addPhotosButton.addEventListener('click', function () {
                gThis.openAlbumPanel(modifyAlbumDialog);
            });
        }

        if (modifyAlbumForm) {
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

                const formData = gThis.getAlbumFormData(modifyAlbumForm);
                const formFiles = gThis.getAlbumFormFiles(modifyAlbumForm);
                gThis.fileCount = formFiles.length;

                summaryDiv.innerText = 'Saving album infos…'
                fetch('/' + gThis.lang + '/album/modify/' + gThis.album.id,
                    {
                        method: 'POST',
                        body: formData
                    }
                ).then(async function (response) {
                    const data = await response.json();
                    /*console.log({data});*/
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
    }

    initPhotoForm() {
        const editPhotoButtons = document.querySelectorAll('.edit-photo-button');
        const editPhotoDialog = document.querySelector('.side-panel.edit-photo-dialog');
        const editPhotoForm = editPhotoDialog.querySelector('form');
        const photoThumbnail = editPhotoDialog.querySelector('img');
        const albumIdInput = document.querySelector('input[name="album-id"]');
        const photoIdInput = document.querySelector('input[name="photo-id"]');
        const captionInput = document.querySelector('input[name="caption"]');
        const dateInput = document.querySelector('input[name="date"]');
        const coordsInput = editPhotoForm.querySelector('input[name="paste-coords-here"]');
        const latitudeInput = editPhotoForm.querySelector('input[name="latitude"]');
        const longitudeInput = editPhotoForm.querySelector('input[name="longitude"]');
        const editPhotoCancel = editPhotoForm.querySelector('button[type="button"]');
        const editPhotoSubmit = editPhotoForm.querySelector('button[type="submit"]');

        editPhotoButtons.forEach((btn) => {
            const id = parseInt(btn.getAttribute('data-id'));
            const photo = gThis.album.photos.find(photo => photo.id === id);
            btn.addEventListener('click', function () {
                photoThumbnail.src = "/albums/576p" + photo['image_path'];
                albumIdInput.value = photo['album_id'];
                photoIdInput.value = id;
                captionInput.value = photo['caption'];
                dateInput.value = photo['date_string'];
                coordsInput.value = '';
                latitudeInput.value = photo['latitude'];
                longitudeInput.value = photo['longitude'];
                coordsInput.addEventListener('paste', function (e) {
                    const url = e.clipboardData.getData('text');
                    const isGoogleMapsUrl = url.match(/https:\/\/www.google.com\/maps\//);
                    let urlParts;
                    if (isGoogleMapsUrl) {
                        urlParts = url.split('@')[1].split(',');
                    } else { // 48.8566,2.3522
                        urlParts = url.split(',');
                    }
                    latitudeInput.value = parseFloat(urlParts[0].trim());
                    longitudeInput.value = parseFloat(urlParts[1].trim());
                });
                gThis.openPhotoPanel(editPhotoDialog);
            });
        });

        editPhotoCancel.addEventListener('click', function () {
            gThis.closePhotoPanel();
        });
        editPhotoSubmit.addEventListener('click', function (event) {
            event.preventDefault();

            const requiredFields = editPhotoForm.querySelectorAll('input[required]');
            let itsOk = true;
            requiredFields.forEach((field) => {
                const errorSpan = field.parentElement.querySelector('span');
                if (field.value === '') {
                    errorSpan.innerText = 'This field is required';
                    itsOk = false;
                } else {
                    errorSpan.innerText = '';
                }
            });
            if (!itsOk) return;

            editPhotoCancel.setAttribute('disabled', '');
            editPhotoSubmit.setAttribute('disabled', '');

            const formData = gThis.getPhotoFormData(editPhotoForm);

            fetch('/' + gThis.lang + '/album/photo/edit',
                {
                    method: 'POST',
                    body: formData
                }
            ).then(async function (response) {
                const data = await response.json();
                if (response.ok) {
                    /*console.log({data});*/
                    const photo = data['photo'];
                    const id = parseInt(photo['photo-id']);
                    editPhotoCancel.removeAttribute('disabled');
                    editPhotoSubmit.removeAttribute('disabled');
                    const albumPhotoDiv = document.querySelector(".album-photo[data-id='" + id + "']");
                    const nameDiv = albumPhotoDiv.querySelector('.name');
                    nameDiv.innerText = photo.caption;
                    const dateDiv = albumPhotoDiv.querySelector('.date');
                    const dateSpan = dateDiv.parentElement.querySelector('span');
                    dateSpan.innerText = photo['date_string'];
                    gThis.map.addPhotoMarker(photo);
                    gThis.closePhotoPanel();
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
                /*  console.log({data});*/
                if (response.ok) {
                    const results = data['results'];
                    data['messages'].forEach(msg => {
                        gThis.flashMessage.add('success', msg);
                    });
                    if (results.length) {
                        const albumPhotosDiv = document.querySelector('.album-photos');
                        results.forEach(result => {
                            gThis.map.addPhotoMarker(result);
                            const albumPhotoDiv = document.createElement('div');
                            albumPhotoDiv.classList.add('album-photo');
                            albumPhotoDiv.setAttribute('data-id', result.id);
                            const img = document.createElement('img');
                            const imagePath = result['image_path'];
                            img.setAttribute('data-title', result['date_string']);
                            img.src = gThis.srcsetPaths['highRes'] + imagePath;
                            img.alt = imagePath;
                            img.loading = "lazy";
                            img.srcset = gThis.srcsetPaths['lowRes'] + imagePath + ' 576w,'
                                + gThis.srcsetPaths['mediumRes'] + imagePath + ' 720w,'
                                + gThis.srcsetPaths['highRes'] + imagePath + ' 1080w,'
                                + gThis.srcsetPaths['original'] + imagePath + ' 1600w';
                            albumPhotoDiv.appendChild(img);
                            gThis.diaporama.start(albumPhotoDiv.querySelectorAll('img'));
                            const albumPhotoInfos = document.createElement('div');
                            albumPhotoInfos.classList.add('album-photo-infos');
                            const nameDiv = document.createElement('div');
                            nameDiv.classList.add('name');
                            albumPhotoInfos.appendChild(nameDiv);

                            const datesDiv = document.createElement('div');
                            datesDiv.classList.add('dates');
                            const dateDiv = document.createElement('div');
                            dateDiv.classList.add('date');
                            dateDiv.innerText = result['date_string'];
                            datesDiv.appendChild(dateDiv);
                            const createdAtDiv = document.createElement('div');
                            createdAtDiv.classList.add('created-at');
                            createdAtDiv.innerText = result['created_at_string'];
                            datesDiv.appendChild(createdAtDiv);
                            const updatedAtDiv = document.createElement('div');
                            updatedAtDiv.classList.add('dates')
                            updatedAtDiv.innerText = result['updated_at_string'];
                            datesDiv.appendChild(updatedAtDiv);
                            albumPhotoInfos.appendChild(datesDiv);

                            albumPhotoDiv.appendChild(albumPhotoInfos);
                            const firstAlbumPhotoDiv = albumPhotosDiv.querySelector('.album-photo');
                            if (firstAlbumPhotoDiv) {
                                albumPhotosDiv.insertBefore(albumPhotoDiv, firstAlbumPhotoDiv);
                            } else {
                                albumPhotosDiv.appendChild(albumPhotoDiv);
                            }
                            // TODO: modifier le span de addPhotoDiv en fonction du nombre de photo
                            const allPhotoDivs = albumPhotosDiv.querySelectorAll(".album-photo");
                            allPhotoDivs.forEach((photoDiv, index) => {
                                const modulo = index % 27;
                                if (modulo === 0 || modulo === 10 || modulo === 20) {
                                    photoDiv.classList.add('grid-span-2');
                                } else {
                                    photoDiv.classList.remove('grid-span-2');
                                }
                            });
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
                div.textContent = file.name + ' ' + gThis.returnFileSize(file.size);
                const image = document.createElement("img");
                image.src = URL.createObjectURL(file);
                image.alt = image.title = file.name;

                listItem.appendChild(div);
                listItem.appendChild(image);
            } else {
                div.innerHTML = file.name + ' <span class="error">Not a valid file type. Update your selection</span>';
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
            return number + " bytes";
        } else if (number >= 1e3 && number < 1e6) {
            return (number / 1e3).toFixed(1) + " KB";
        } else {
            return (number / 1e6).toFixed(1) + " MB";
        }
    }

    getPhotoFormData(form) {
        const albumIdInput = form.querySelector('input[name="album-id"]');
        const photoIdInput = form.querySelector('input[name="photo-id"]');
        const captionInput = form.querySelector('input[name="caption"]');
        const dateInput = form.querySelector('input[name="date"]');
        const latitudeInput = form.querySelector('input[name="latitude"]');
        const longitudeInput = form.querySelector('input[name="longitude"]');

        const formData = new FormData();
        formData.append('album-id', albumIdInput.value);
        formData.append('photo-id', photoIdInput.value);
        formData.append('caption', captionInput.value);
        formData.append('date', dateInput.value);
        formData.append('latitude', latitudeInput.value);
        formData.append('longitude', longitudeInput.value);

        return formData;
    }

    getAlbumFormData(form) {
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

    getAlbumFormFiles(form) {
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

    openPhotoPanel(dialog) {
        const form = dialog.querySelector('form');
        const captionInput = form.querySelector('input[name="caption"]');

        dialog.classList.add('open');
        captionInput.focus();
        captionInput.select();
    }

    closePhotoPanel() {
        const dialog = document.querySelector('.side-panel.edit-photo-dialog');
        dialog.classList.remove('open');
    }

    initAnimation() {
        if (!gThis.imagePaths.length) {
            return;
        }
        const albumPhotosDiv = document.querySelector('.album-photos');
        gThis.pathArr = gThis.imagePaths.slice();
        if (albumPhotosDiv.classList.contains('list')) {
            return;
        }
        const imgElement1 = document.querySelector(".background-1").querySelector('img');
        const imgElement2 = document.querySelector(".background-2").querySelector('img');
        gThis.effect({img1: imgElement1, img2: imgElement2, path: gThis.srcsetPaths.original});
        gThis.interval = setInterval(gThis.effect, 4000, {img1: imgElement1, img2: imgElement2, path: gThis.srcsetPaths.original});
    }

    stopAnimation() {
        if (gThis.interval) {
            const albumPhotosDiv = document.querySelector('.album-photos');
            clearInterval(gThis.interval);
            albumPhotosDiv.classList.remove('play');
            albumPhotosDiv.removeAttribute('style');
            gThis.interval = null;
        }
    }

    effect(arg) {
        const imgElement1 = arg.img1;
        const imgElement2 = arg.img2;
        const path = arg.path

        const arrLength = gThis.pathArr.length;
        const imageIndex = Math.floor(Math.random() * (arrLength - 1));
        const imageFilename = gThis.pathArr[imageIndex];
        gThis.pathArr.splice(imageIndex, 1);
        if (gThis.pathArr.length === 0) {
            gThis.pathArr = gThis.imagePaths.slice();
        }
        imgElement2.src = path + imageFilename;
        setTimeout(() => {
            imgElement2.classList.add('displayed');
            setTimeout(() => {
                imgElement2.classList.add('visible');
                setTimeout(() => {
                    imgElement1.src = imgElement2.src;
                    imgElement2.classList.remove('displayed');
                    imgElement2.classList.remove('visible');
                }, 1000);
            }, 0);
        }, 2950);
    }
}
