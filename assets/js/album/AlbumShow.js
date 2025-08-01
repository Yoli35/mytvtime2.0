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
     */

    constructor() {
        gThis = this;
        this.toolTips = new ToolTips();
        this.flashMessage = new FlashMessage();
        /** @var {Globs} */
        const globs = JSON.parse(document.querySelector('div#globs').textContent);
        const svgs = document.querySelector('div#svgs');
        this.album = globs.album;
        this.texts = globs.texts;

        this.init();
    }

    init() {

        /******************************************************************************
         * Diaporama for posters, backdrops and logos                                 *
         ******************************************************************************/
        const diaporama = new Diaporama();
        const photoWrapper = document.querySelector('.album-photos');
        const photos = photoWrapper.querySelectorAll('img');
        diaporama.start(photos);

        /******************************************************************************
         * mapbox gl                                                                  *
         ******************************************************************************/
        const mapDiv = document.querySelector('.map-controller');
        if (mapDiv) {
            this.map = new Map();
        }

        /******************************************************************************
         * Filming location form                                                      *
         * When call location.js:                                                     *
         *     → new Location(data, fieldList);                                       *
         *     → data: div#globs                                                      *
         *     → fieldList: [                                                         *
         *                   "series-id", "tmdb-id", "crud-type", "crud-id","title",  *
         *                   "episode-number", "season-number",                       *
         *                   "location", "description",                               *
         *                   "latitude", "longitude",                                 *
         *                  ]                                                         *
         ******************************************************************************/
        const seriesMap = document.querySelector('#map');
        const addLocationButton = document.querySelector('.add-location-button');
        const addLocationDialog = document.querySelector('.side-panel.add-location-dialog');
        const addLocationForm = addLocationDialog.querySelector('form');
        const inputGoogleMapsUrl = addLocationForm.querySelector('input[name="google-map-url"]');
        const inputLatitude = addLocationForm.querySelector('input[name="latitude"]');
        const inputLongitude = addLocationForm.querySelector('input[name="longitude"]');
        const addLocationCancel = addLocationForm.querySelector('button[type="button"]');
        const addLocationSubmit = addLocationForm.querySelector('button[type="submit"]');
        const imageInputs = addLocationForm.querySelectorAll('input[type="url"]');
        const submitRow = addLocationForm.querySelector('.form-row.submit-row');
        const scrollDownToSubmitDiv = addLocationDialog.querySelector('.scroll-down-to-submit');
        const scrollDownToSubmitButton = scrollDownToSubmitDiv.querySelector('button');
        console.log({imageInputs});

        // Lorsque le panneau devient trop haut la div "submit-row" disparait.
        // Si la div "submit-row" est hors du cadre, la div "scroll-down-to-submit" apparaît.
        // Si la div "submit-row" est visible, la div "scroll-down-to-submit" disparaît.
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
            addLocationDialog.querySelector('.frame').scrollTo(0, submitRow.offsetTop);
        });

        if (seriesMap) {
            const mapViewValue = JSON.parse(seriesMap.getAttribute('data-symfony--ux-leaflet-map--map-view-value'));
            console.log({mapViewValue});
        }

        /*addLocationButton.addEventListener('click', function () {
            gThis.openLocationPanel('create', {'title': seriesName}, translations['Add']);
        });
        inputGoogleMapsUrl.addEventListener('paste', function (e) {
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
        });
        addLocationCancel.addEventListener('click', function () {
            gThis.closeLocationPanel();
        });
        addLocationSubmit.addEventListener('click', function (event) {
            event.preventDefault();

            const inputs = addLocationForm.querySelectorAll('input[required]');
            const crudTypeInput = addLocationForm.querySelector('input[name="crud-type"]');
            let emptyInput = false;
            if (crudTypeInput.value === 'create') {
                inputs.forEach(function (input) {
                    // la première image ("image-url") est requise, mais peut être remplacée par un fichier (image-file)
                    // en mode création
                    if (input.name === 'image-url') {
                        if (!input.value && !input.closest('.form-row').querySelector('input[name="image-file"]').value) {
                            input.nextElementSibling.textContent = translations['This field is required'];
                            emptyInput = true;
                        } else {
                            input.nextElementSibling.textContent = '';
                        }
                    } else {
                        if (input.required && !input.value) {
                            input.nextElementSibling.textContent = translations['This field is required'];
                            emptyInput = true;
                        } else {
                            input.nextElementSibling.textContent = '';
                        }
                    }
                });
            }
            if (!emptyInput) {
                const formData = gThis.getFormData(addLocationForm, gThis.fieldList);
                fetch('/' + lang + '/series/location/add/' + seriesId,
                    {
                        method: 'POST',
                        body: formData
                    }
                ).then(async function (response) {
                    const data = await response.json();
                    console.log({data});
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        gThis.flashMessage.add('error', data.message);
                    }
                    gThis.closeLocationPanel();
                });
            }
        });

        imageInputs.forEach(function (imageInput) {
            // Les champs de type "url" peuvent être modifiés pour afficher une image
            imageInput.addEventListener('input', function () {
                let validValue = false;
                const path = this.value;
                const img = this.closest('.form-field').querySelector('img');
                // is it a valid url?
                const isUrl = path.match(/https?:\/\/.+\.(jpg|jpeg|png|gif|webp)/);
                if (isUrl) {
                    img.src = path;
                    validValue = true;
                }
                if (this.value.includes('~/')) { // for dev test
                    const filename = path.split('/').pop();
                    // is a valid filename?
                    const isFilename = filename.match(/.+\.jpg|jpeg|png|webp/);
                    if (isFilename) {
                        img.src = this.value.replace('~/', '/images/map/');
                        validValue = true;
                    }
                }
                if (!validValue) {
                    img.src = '';
                }
            });
            // Les champs de type "url" peuvent recevoir un fichier de type image par glisser-déposer
            imageInput.addEventListener('drop', function (e) {
                e.preventDefault();
                const file = e.dataTransfer.files[0];
                const img = this.closest('.form-field').querySelector('img');
                img.src = URL.createObjectURL(file);
                this.value = img.src;
                console.log({file});
                console.log(img.src)
                const blobPreviewDiv = this.closest('.form-field').querySelector('.blob-preview');
                const blobPreview = blobPreviewDiv.querySelector('img');
                previewFile(file, blobPreview);
            });
        });*/

        // https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file
        const imageFile = addLocationForm.querySelector('input[name="image-file"]');
        imageFile.addEventListener("change", updateImageDisplay);
        const imageFiles = addLocationForm.querySelector('input[name="image-files"]');
        imageFiles.addEventListener("change", updateImageDisplay);

        function updateImageDisplay(e) {
            const input = e.target;
            const inputName = input.name;
            const preview = addLocationForm.querySelector('.preview-' + inputName);
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
                if (validFileType(file)) {
                    div.textContent = `${file.name}, ${returnFileSize(file.size)}`;
                    const image = document.createElement("img");
                    image.src = URL.createObjectURL(file);
                    image.alt = image.title = file.name;

                    listItem.appendChild(div);
                    listItem.appendChild(image);
                } else {
                    div.innerHTML = `${file.name}<span class="error">${translations['Not a valid file type. Update your selection']}.</span>`;
                    listItem.appendChild(div);
                }

                list.appendChild(listItem);
            }

        }

        // https://developer.mozilla.org/en-US/docs/Web/Media/Formats/Image_types
        const fileTypes = [
            /* "image/apng",*/
            /* "image/bmp",*/
            /* "image/gif",*/
            "image/jpeg",
            /* "image/pjpeg",*/
            "image/png",
            /* "image/svg+xml",*/
            /* "image/tiff",*/
            "image/webp",
            /* "image/x-icon",*/
            /*"image/heic",*/
        ];

        function validFileType(file) {
            return fileTypes.includes(file.type);
        }

        function returnFileSize(number) {
            if (number < 1e3) {
                return `${number} bytes`;
            } else if (number >= 1e3 && number < 1e6) {
                return `${(number / 1e3).toFixed(1)} KB`;
            } else {
                return `${(number / 1e6).toFixed(1)} MB`;
            }
        }

        function previewFile(file, preview) {
            const reader = new FileReader();

            reader.addEventListener("load", () => {
                preview.src = reader.result;
                console.log({reader});
            }, false);
            if (file) {
                reader.readAsDataURL(file);
            }
        }
    }

    getFormData(form, list) {
        // const seriesIdInput = form.querySelector('input[name="series-id"]');
        // const tmdbIdInput = form.querySelector('input[name="tmdb-id"]');
        // const crudTypeInput = form.querySelector('input[name="crud-type"]');
        // const crudIdInput = form.querySelector('input[name="crud-id"]');
        // const titleInput = form.querySelector('input[name="title"]');
        // const episodeNumberInput = form.querySelector('input[name="episode-number"]');
        // const seasonNumberInput = form.querySelector('input[name="season-number"]');
        // const locationInput = form.querySelector('input[name="location"]');
        // const descriptionInput = form.querySelector('input[name="description"]');
        const imageUrlInputs = form.querySelectorAll('input[name*="image-url"]');
        const imageFileInput = form.querySelector('input[name="image-file"]');
        const imageFilesInput = form.querySelector('input[name*="image-files"]');
        // const latitudeInput = form.querySelector('input[name="latitude"]');
        // const longitudeInput = form.querySelector('input[name="longitude"]');

        const formData = new FormData();
        list.forEach(function (field) {
            const fieldInput = form.querySelector('input[name="' + field + '"]');
            if (fieldInput) {
                formData.append(field, fieldInput.value);
            }
            const fieldSelect = form.querySelector('select[name="' + field + '"]');
            if (fieldSelect) {
                formData.append(field, fieldSelect.value);
            }
            const fieldTextarea = form.querySelector('textarea[name="' + field + '"]');
            if (fieldTextarea) {
                formData.append(field, fieldTextarea.value);
            }
        });

        imageUrlInputs.forEach(function (input) {
            formData.append(input.name, input.value);
            if (input.value.includes('blob:')) {
                const blobPreviewDiv = input.closest('.form-field').querySelector('.blob-preview');
                const blobPreview = blobPreviewDiv.querySelector('img');
                const file = blobPreview.src;
                formData.append(input.name + '-blob', file);
            }
        });
        if (imageFileInput.files.length)
            formData.append(imageFileInput.name, imageFileInput.files[0]);
        Array.from(imageFilesInput.files).forEach(function (file, index) {
            formData.append('additional-image-' + index, file);
        });

        return formData;
    }

    openLocationPanel(crud, location, buttonText) {
        const addLocationForm = document.querySelector('#add-location-form');
        const addLocationDialog = document.querySelector('.side-panel.add-location-dialog');
        const inputs = addLocationForm.querySelectorAll('input');
        const crudTypeInput = addLocationForm.querySelector('input[name="crud-type"]');
        const crudIdInput = addLocationForm.querySelector('input[name="crud-id"]');
        const titleInput = addLocationForm.querySelector('input[name="title"]');
        const episodeNumberInput = addLocationForm.querySelector('input[name="episode-number"]');
        const seasonNumberInput = addLocationForm.querySelector('input[name="season-number"]');
        const locationInput = addLocationForm.querySelector('input[name="location"]');
        const descriptionTextarea = addLocationForm.querySelector('textarea[name="description"]');
        const latitudeInput = addLocationForm.querySelector('input[name="latitude"]');
        const longitudeInput = addLocationForm.querySelector('input[name="longitude"]');
        const radiusInput = addLocationForm.querySelector('input[name="radius"]');
        const sourceNameInput = addLocationForm.querySelector('input[name="source-name"]');
        const sourceUrlInput = addLocationForm.querySelector('input[name="source-url"]');
        const locationImages = addLocationForm.querySelector(".location-images");
        const additionalImagesDiv = addLocationForm.querySelector('.additional-images');
        const submitButton = addLocationForm.querySelector('button[type="submit"]');

        inputs.forEach(function (input) {
            if (input.getAttribute('type') !== 'hidden') {
                input.value = '';
            }
        });
        titleInput.value = location.title;
        submitButton.textContent = buttonText;
        crudTypeInput.value = crud;
        if (crud === 'create') {
            crudIdInput.value = 0;
            episodeNumberInput.value = '0';
            seasonNumberInput.value = '0';
            locationImages.style.display = 'none';
        } else {
            crudIdInput.value = location.id;
            episodeNumberInput.value = location.episode_number;
            seasonNumberInput.value = location.season_number;
            locationInput.value = location.location;
            latitudeInput.value = location.latitude;
            longitudeInput.value = location.longitude;
            radiusInput.value = location.radius;
            descriptionTextarea.value = location.description;
            sourceNameInput.value = location.source_name;
            sourceUrlInput.value = location.source_url;

            locationImages.style.display = 'flex';
            const stillDiv = locationImages.querySelector('.still');
            const imageDiv = stillDiv.querySelector('.image');
            imageDiv.innerHTML = '';
            const img = document.createElement('img');
            img.src = '/images/map' + location.still_path;
            img.alt = location.title;
            imageDiv.appendChild(img);

            const wrapper = additionalImagesDiv.querySelector('.wrapper');
            wrapper.innerHTML = '';
            const additionalImagesArray = location.filmingLocationImages.filter(fl => fl.id !== location.still_id);
            additionalImagesArray.forEach(function (image) {
                const img = document.createElement('img');
                const imageDiv = document.createElement('div');
                imageDiv.classList.add('image');
                img.src = '/images/map' + image.path;
                img.alt = image.title;
                imageDiv.appendChild(img);
                wrapper.appendChild(imageDiv);
            });
        }
        addLocationDialog.classList.add('open');
        locationInput.focus();
        locationInput.select();
    }

    closeLocationPanel() {
        const addLocationDialog = document.querySelector('.side-panel.add-location-dialog');
        addLocationDialog.classList.remove('open');
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
