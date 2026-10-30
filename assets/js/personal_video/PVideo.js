let self = null

export class PVideo {
    constructor() {
        self = this;
        this.fileTypes = [
            "image/jpeg",
            "image/png",
            "image/webp",
        ];
        this.imageCount = 0;
        this.selectedIndex = 0;
        this.objectUrls = [];
        // Vignette finale : l'image choisie, centrée dans un cadre 16/9 de la couleur choisie
        this.thumbnailWidth = 1280;
        this.thumbnailHeight = 720;
        const globs = JSON.parse(document.querySelector("#global-data").textContent);
        this.translations = globs.texts;

        this.init();
    }

    init() {
        const toggler = document.querySelector('.thumbnail-toggler');
        if (toggler) {
            toggler.addEventListener('click', () => {
                const foldable = toggler.parentElement.querySelector('.foldable');
                if (foldable.classList.contains('folded')) {
                    foldable.style.height = foldable.scrollHeight + 'px';
                    foldable.classList.remove('folded');
                    setTimeout(() => foldable.removeAttribute('style'), 250);
                } else {
                    foldable.style.height = '0px';
                    foldable.classList.add('folded');
                    setTimeout(() => foldable.removeAttribute('style'), 250);
                }
            });
        }
        self.editForm = document.getElementById('edit-form');
        if (!self.editForm) return;
        const imageFiles = self.editForm.querySelector('input[name="thumbnail"]');
        imageFiles.value = '';
        // Retour arrière depuis la page suivante (cache du navigateur) : le sélecteur avait été désactivé à l'envoi
        window.addEventListener('pageshow', () => imageFiles.disabled = false);
        imageFiles.addEventListener("change", self.updateImageDisplay);

        // Les images peuvent aussi être ajoutées par copier-coller, n'importe où dans la page.
        // L'événement 'paste' part de l'élément qui a le focus (souvent <body>) : l'écouter sur le formulaire
        // ne fonctionne qu'après avoir cliqué dedans.
        document.addEventListener('paste', function (e) {
            const pastedFiles = Array.from(e.clipboardData?.files || []).filter(file => file.type.startsWith('image/'));
            if (!pastedFiles.length) return;
            e.preventDefault();
            self.unfold();

            const dataTransfer = new DataTransfer();
            Array.from(imageFiles.files).forEach(file => dataTransfer.items.add(file));
            pastedFiles.forEach(function (file, index) {
                // Les images collées s'appellent souvent toutes "image.png" : la vignette est retrouvée par son nom
                const extension = file.type.split('/')[1].replace('jpeg', 'jpg');
                const name = 'pasted-' + Date.now() + '-' + index + '.' + extension;
                dataTransfer.items.add(new File([file], name, {type: file.type}));
            });
            imageFiles.files = dataTransfer.files;
            self.appending = true;
            imageFiles.dispatchEvent(new Event('change'));
        });

        self.editForm.addEventListener('submit', self.submit);
    }

    unfold() {
        const foldable = self.editForm.querySelector('.foldable');
        if (foldable?.classList.contains('folded')) {
            self.editForm.querySelector('.thumbnail-toggler').click();
        }
    }

    /**
     * Si une image est sélectionnée dans la liste, la vignette est composée ici (canvas 16/9 de la couleur choisie)
     * et envoyée seule, dans le champ "thumbnail_image" : les images brutes du sélecteur ne sont pas envoyées.
     */
    async submit(e) {
        const form = self.editForm;
        const imageFiles = form.querySelector('input[name="thumbnail"]');
        const radio = form.querySelector('.thumbnail-list input[type="radio"]:checked');
        if (!radio) {
            imageFiles.disabled = true;
            return; // envoi normal : titre et description
        }
        e.preventDefault();

        const item = radio.closest('li');
        const file = imageFiles.files[parseInt(radio.dataset.index)];
        const color = item.querySelector('.thumbnail-color input[type="color"]').value;
        const blob = await self.composeThumbnail(file, color);

        const extension = blob.type.split('/')[1];
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(new File([blob], 'thumbnail.' + extension, {type: blob.type}));
        let thumbnailInput = form.querySelector('input[name="thumbnail_image"]');
        if (!thumbnailInput) {
            thumbnailInput = document.createElement('input');
            thumbnailInput.type = 'file';
            thumbnailInput.name = 'thumbnail_image';
            thumbnailInput.hidden = true;
            form.appendChild(thumbnailInput);
        }
        thumbnailInput.files = dataTransfer.files;
        imageFiles.disabled = true;

        form.submit(); // ne redéclenche pas l'événement 'submit'
    }

    /**
     * L'image est centrée et ajustée (comme "object-fit: contain") dans un cadre 16/9 rempli de la couleur choisie.
     */
    async composeThumbnail(file, color) {
        const bitmap = await createImageBitmap(file);
        const canvas = document.createElement('canvas');
        canvas.width = self.thumbnailWidth;
        canvas.height = self.thumbnailHeight;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = color;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        const scale = Math.min(canvas.width / bitmap.width, canvas.height / bitmap.height);
        const width = Math.round(bitmap.width * scale);
        const height = Math.round(bitmap.height * scale);
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(bitmap, Math.round((canvas.width - width) / 2), Math.round((canvas.height - height) / 2), width, height);
        bitmap.close();

        // Safari ne sait pas encoder en WebP : toBlob renvoie alors du PNG (converti en WebP par le serveur)
        return new Promise(resolve => canvas.toBlob(resolve, 'image/webp', .9));
    }

    updateImageDisplay(e) {
        const input = e.target;
        const inputName = input.name;
        const preview = self.editForm.querySelector('.preview-' + inputName);
        // Images collées ajoutées à la suite : la sélection et les couleurs sont conservées
        const checked = preview.querySelector('input[type="radio"]:checked');
        self.selectedIndex = self.appending && checked ? parseInt(checked.dataset.index) : 0;
        const colors = self.appending ? Array.from(preview.querySelectorAll('input[type="radio"]')).reduce((colors, radio) => {
            colors[radio.dataset.index] = radio.closest('li').querySelector('input[type="color"]').value;
            return colors;
        }, {}) : {};
        self.appending = false;
        preview.replaceChildren();
        self.objectUrls.forEach(url => URL.revokeObjectURL(url));
        self.objectUrls = [];

        const curFiles = input.files;
        if (curFiles.length === 0) {
            const div = document.createElement("div");
            div.textContent = self.translations['No files currently selected for upload'];
            preview.appendChild(div);
            return;
        }

        const list = document.createElement("ol");
        list.classList.add('thumbnail-list');
        preview.appendChild(list);

        let index = self.imageCount;
        let fileIndex = -1;
        let firstValidIndex = null;
        for (const file of curFiles) {
            fileIndex += 1;
            const listItem = document.createElement("li");
            listItem.classList.add("thumbnail-item");
            if (self.validFileType(file)) {
                const thumbnailDiv = document.createElement("div");
                thumbnailDiv.classList.add("thumbnail");
                const radio = document.createElement("input");
                radio.id = "thumbnail-" + index;
                radio.type = "radio";
                radio.name = "thumbnail-choice"; // différent de name="thumbnail" (sélecteur de fichiers)
                radio.value = file.name;
                radio.dataset.index = fileIndex.toString(); // position dans input.files
                radio.checked = fileIndex === self.selectedIndex;
                firstValidIndex ??= fileIndex;
                const label = document.createElement("label");
                label.classList.add("thumbnail-choice");
                label.setAttribute("for", "thumbnail-" + index);
                const infosDiv = document.createElement("div");
                infosDiv.classList.add("thumbnail-infos");
                const span = document.createElement("span");
                span.textContent = file.name + " (" + self.returnFileSize(file.size) + ")";

                const imgContainer = document.createElement("div");
                imgContainer.classList.add("thumbnail-image");
                const image = document.createElement("img");
                image.src = URL.createObjectURL(file);
                self.objectUrls.push(image.src);
                image.alt = image.title = file.name;
                imgContainer.appendChild(image);

                const thumbnailColorDiv = document.createElement("div");
                thumbnailColorDiv.classList.add("thumbnail-color");
                const inputColor = document.createElement("input");
                inputColor.type = "color";
                inputColor.value = colors[fileIndex] ?? "#000000";
                imgContainer.style.backgroundColor = inputColor.value;
                thumbnailColorDiv.appendChild(inputColor);

                infosDiv.appendChild(span);
                label.appendChild(infosDiv);
                label.appendChild(imgContainer);

                thumbnailDiv.appendChild(radio);
                thumbnailDiv.appendChild(label);

                listItem.appendChild(thumbnailDiv);
                listItem.appendChild(thumbnailColorDiv);
                index += 1;
            } else {
                const div = document.createElement("div");
                const error = document.createElement("span");
                error.classList.add("error");
                error.textContent = self.translations['Not a valid file type. Update your selection'] + '.';
                div.append(file.name, error);
                listItem.appendChild(div);
            }

            list.appendChild(listItem);
        }
        // L'image sélectionnée n'est plus valide (ou n'existe plus) : on sélectionne la première image valide
        if (!list.querySelector('input[type="radio"]:checked') && firstValidIndex) {
            list.querySelector(`input[data-index="${firstValidIndex}"]`).checked = true;
        }
        self.initColorInputs();
    }

    initColorInputs() {
        const colorInputs = self.editForm.querySelectorAll(".thumbnail-color input[type='color']");
        colorInputs.forEach((input) => {
            input.addEventListener("input", (event) => {
                const imgContainer = event.target.closest("li").querySelector('.thumbnail-image');
                imgContainer.style.backgroundColor = event.target.value;
            });
        });
    }

    validFileType(file) {
        return self.fileTypes.includes(file.type);
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
}