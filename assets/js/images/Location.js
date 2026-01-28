import {Map} from "Map";

let gThis = null;

export class Location {

    constructor(type, data, fieldList, mapDiv) {
        gThis = this;
        this.mapDiv = mapDiv;
        this.map = null;
        this.lang = document.documentElement.lang;
        this.filmingLocations = [];
        this.flashMessage = null;
        this.type = type;// 'poi'|'loc'
        this.translations = data.translations || {};
        this.emptyLocation = data.emptyLocation || {};
        this.imagePath = data.imagePath || '';
        this.seriesId = data.seriesId || null;
        this.seriesName = data.seriesName || '';
        this.url = '/' + gThis.lang + (this.seriesId ? '/series/location/add/' + this.seriesId : '/admin/point-of-interest/add');
        this.fieldList = fieldList || [];

        this.openLocationPanel = this.openLocationPanel.bind(this);

        this.init();
    }

    init() {
        /******************************************************************************
         * mapbox gl                                                                  *
         ******************************************************************************/
        if (this.mapDiv) this.map = new Map({cooperativeGesturesOption: false});

        /******************************************************************************
         * Filming location / point of interest form                                  *
         ******************************************************************************/
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
            addLocationDialog.querySelector('.frame').scrollTo(0, submitRow.offsetTop);
        });

        if (this.mapDiv) {
            const mapViewValue = JSON.parse(this.mapDiv.getAttribute('data-symfony--ux-leaflet-map--map-view-value'));
            console.log({mapViewValue});

            const locationsDiv = document.querySelector('.locations');
            const imageDivs = locationsDiv.querySelectorAll('.series-location-image');
            let imageSrcLists = [];
            let currentImages = [];
            imageDivs.forEach(function (imageDiv, imageDivIndex) {
                if (!imageDiv.classList.contains('help-text')) {
                    const listDiv = imageDiv.querySelector('.list');
                    if (listDiv) {
                        const imageList = Array.from(listDiv.querySelectorAll('img'));
                        if (imageList.length > 1) {
                            imageSrcLists[imageDivIndex] = imageList.map(function (image) {
                                return {src: image.src};
                            });
                            const imageImg = imageDiv.querySelector('img');
                            const leftArrow = imageDiv.querySelector('.arrow.left');
                            const rightArrow = imageDiv.querySelector('.arrow.right');
                            currentImages[imageDivIndex] = 0;

                            leftArrow.addEventListener('click', function () {
                                const lastIndex = imageSrcLists[imageDivIndex].length - 1;
                                let i = currentImages[imageDivIndex];
                                i = i === 0 ? lastIndex : (i - 1);
                                currentImages[imageDivIndex] = i;
                                imageImg.src = imageSrcLists[imageDivIndex][i].src;
                            });
                            rightArrow.addEventListener('click', function () {
                                const lastIndex = imageSrcLists[imageDivIndex].length - 1;
                                let i = currentImages[imageDivIndex];
                                i = i === lastIndex ? 0 : (i + 1);
                                currentImages[imageDivIndex] = i;
                                imageImg.src = imageSrcLists[imageDivIndex][i].src;
                            });
                        }
                    }
                    const editButton = imageDiv.querySelector('.edit');
                    editButton.addEventListener('click', function () {
                        const locationId = this.getAttribute('data-loc-id');
                        const location = gThis.filmingLocations.find(location => location.id === parseInt(locationId));
                        gThis.openLocationPanel('update', location, gThis.translations['Update']);
                    });
                }
            });
        }

        addLocationButton.addEventListener('click', function () {
            gThis.openLocationPanel('create', gThis.emptyLocation, gThis.translations['Add']);
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
                            input.nextElementSibling.textContent = gThis.translations['This field is required'];
                            emptyInput = true;
                        } else {
                            input.nextElementSibling.textContent = '';
                        }
                    } else {
                        if (!input.value) {
                            input.nextElementSibling.textContent = gThis.translations['This field is required'];
                            emptyInput = true;
                        } else {
                            input.nextElementSibling.textContent = '';
                        }
                    }
                });
            }
            if (!emptyInput) {
                const formData = gThis.getFormData(addLocationForm, gThis.fieldList);
                fetch(gThis.url,
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
        });

        // https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file
        const imageFile = addLocationForm.querySelector('input[name="image-file"]');
        imageFile.addEventListener("change", updateImageDisplay);
        const imageFiles = addLocationForm.querySelector('input[name="image-files"]');
        imageFiles.addEventListener("change", updateImageDisplay);

        function updateImageDisplay(e) {
            const input = e.target;
            const inputName = input.name;
            const preview = addLocationForm.querySelector('.preview-' + inputName);
            const existingList = preview.querySelector('ol');
            existingList?.remove();

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
                    div.innerHTML = `${file.name}<span class="error">${gThis.translations['Not a valid file type. Update your selection']}.</span>`;
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

        const imageUrlInputs = form.querySelectorAll('input[name*="image-url"]');
        const imageFileInput = form.querySelector('input[name="image-file"]');
        const imageFilesInput = form.querySelector('input[name*="image-files"]');

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
        const firstInput = addLocationForm.querySelector('input[required]');
        const titleInput = addLocationForm.querySelector('input[name="title"]');
        const crudIdInput = addLocationForm.querySelector('input[name="crud-id"]');
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
            // const name = input.getAttribute('name');
            // input.value = location[name] || '';
            if (input.getAttribute('type') !== 'hidden') {
                input.value = '';
            }
        });
        titleInput.value = location.title;
        submitButton.textContent = buttonText;
        crudTypeInput.value = crud;

        if (crud === 'create') {
            if (this.type === 'loc') {
                crudIdInput.value = 0;
                episodeNumberInput.value = '0';
                seasonNumberInput.value = '0';
            }
            locationImages.style.display = 'none';
        } else {
            if (this.type === 'loc') {
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
            }
            locationImages.style.display = 'flex';
            const stillDiv = locationImages.querySelector('.still');
            const imageDiv = stillDiv.querySelector('.image');
            imageDiv.innerHTML = '';
            const img = document.createElement('img');
            img.src = this.imagePath + location.still_path;
            img.alt = "Location image"
            imageDiv.appendChild(img);

            const wrapper = additionalImagesDiv.querySelector('.wrapper');
            wrapper.innerHTML = '';
            const additionalImagesArray = location.filmingLocationImages.filter(fl => fl.id !== location.still_id);
            additionalImagesArray.forEach(function (image) {
                const img = document.createElement('img');
                const imageDiv = document.createElement('div');
                imageDiv.classList.add('image');
                img.src = this.imagePath + image.path;
                img.alt = image.title;
                imageDiv.appendChild(img);
                wrapper.appendChild(imageDiv);
            });
        }
        addLocationDialog.classList.add('open');
        firstInput.focus();
        firstInput.select();
    }

    closeLocationPanel() {
        const addLocationDialog = document.querySelector('.side-panel.add-location-dialog');
        addLocationDialog.classList.remove('open');
    }
}