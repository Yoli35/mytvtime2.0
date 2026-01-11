import {RgbToLch} from "RgbToLch";

export class AverageColor {

    constructor(blockSize = 5) {
        this.blockSize = blockSize;
        this.defaultRGB = {r: 0, g: 0, b: 0, lightness: 0};
        this.defaultHSL = {h: 202, s: 18, l: 20};
        this.canvas = document.createElement("canvas");
        this.context = this.canvas.getContext && this.canvas.getContext("2d", {willReadFrequently: true});
    }

    getColor(img, square = false) {
        let imageData, width, height,
            i = -4,
            length,
            rgb = {r: 0, g: 0, b: 0, lightness: 0},
            count = 0;

        if (!this.context) {
            return this.defaultRGB;
        }

        height = this.canvas.height = img.naturalHeight || img.offsetHeight || img.height || 0;
        width = this.canvas.width = img.naturalWidth || img.offsetWidth || img.width || 0;

        if (!height || !width) {
            return this.defaultRGB;
        }

        this.context.drawImage(img, 0, 0);

        try {
            imageData = this.context.getImageData(0, 0, width, height);
        } catch (e) {
            /* security error, img on diff domain */
            return this.defaultRGB;
        }

        length = imageData.data.length;

        if (!square) {
            while ((i += this.blockSize * 4) < length) {
                ++count;
                rgb.r += imageData.data[i];
                rgb.g += imageData.data[i + 1];
                rgb.b += imageData.data[i + 2];
            }

            // ~~ used to floor values
            rgb.r = ~~(rgb.r / count);
            rgb.g = ~~(rgb.g / count);
            rgb.b = ~~(rgb.b / count);
        } else {
            while ((i += this.blockSize * 4) < length) {
                ++count;
                rgb.r += imageData.data[i] * imageData.data[i]
                rgb.g += imageData.data[i + 1] * imageData.data[i + 1];
                rgb.b += imageData.data[i + 2] * imageData.data[i + 2];
            }

            // ~~ used to floor values
            rgb.r = ~~(Math.sqrt(rgb.r / count));
            rgb.g = ~~(Math.sqrt(rgb.g / count));
            rgb.b = ~~(Math.sqrt(rgb.b / count));
        }

        rgb.lch = new RgbToLch(rgb);
        rgb.lightness = (0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b);

        return rgb;
    }

    rgbToHsl(rgb) {
        if (rgb === this.defaultRGB) {
            return this.defaultHSL;
        }
        const hsl = this.defaultHSL;
        const r = rgb.r / 255;
        const g = rgb.g / 255;
        const b = rgb.b / 255;
        const l = Math.max(r, g, b);
        const s = l - Math.min(r, g, b);
        const h = s
            ? l === r
                ? (g - b) / s
                : l === g
                    ? 2 + (b - r) / s
                    : 4 + (r - g) / s
            : 0;

        hsl.h = Math.floor(60 * h < 0 ? 60 * h + 360 : 60 * h);
        hsl.s = Math.floor(100 * (s ? (l <= 0.5 ? s / (2 * l - s) : s / (2 - (2 * l - s))) : 0));
        hsl.l = Math.floor((100 * (2 * l - s)) / 2);

        return hsl;
    }
}
